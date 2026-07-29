<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Redaccion\GeneradorBoletin;

/**
 * Nivel Cuatro W.1 — el boletín como producto del periodista: "se compone
 * automáticamente desde las piezas del periodista con un párrafo de
 * apertura redactado en su voz". La composición es automática; el disparo
 * de envío es manual del editor (mismo patrón que "Cubrir ahora" en la
 * Sala de Tendencias) — no se inventa una cadencia automática que el texto
 * fuente no especifica.
 */
final class GestorBoletines {

	private const MAXIMO_PIEZAS_BOLETIN = 5;

	public function __construct(
		private readonly RepositorioPeriodistasInterface $periodistas,
		private readonly RepositorioPiezasInterface $piezas,
		private readonly GeneradorBoletin $generador,
		private readonly NotificadorSuscripciones $notificador,
	) {
	}

	/**
	 * @return array{piezas: int, email: int, push: int}
	 *
	 * @throws PeriodistaNoEncontradoParaBoletinException
	 */
	public function enviar( int $periodistaId ): array {
		$periodista = $this->periodistas->obtenerPorId( $periodistaId );

		if ( null === $periodista ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno construido por la propia excepción, sin entrada de usuario.
			throw new PeriodistaNoEncontradoParaBoletinException( $periodistaId );
		}

		$piezasRecientes = $this->piezas->obtenerPublicadasRecientesPorPeriodista( $periodistaId, self::MAXIMO_PIEZAS_BOLETIN );

		if ( array() === $piezasRecientes ) {
			return array(
				'piezas' => 0,
				'email'  => 0,
				'push'   => 0,
			);
		}

		$enlaces = array();

		foreach ( $piezasRecientes as $pieza ) {
			if ( null === $pieza->postId ) {
				continue;
			}

			$post = get_post( $pieza->postId );

			if ( null === $post ) {
				continue;
			}

			$enlaces[] = array(
				'titulo' => $post->post_title,
				'url'    => (string) get_permalink( $post ),
			);
		}

		if ( array() === $enlaces ) {
			return array(
				'piezas' => 0,
				'email'  => 0,
				'push'   => 0,
			);
		}

		$apertura = $this->generador->generar( $periodista, array_column( $enlaces, 'titulo' ) );

		$cuerpo = $apertura . "\n\n" . implode(
			"\n",
			array_map( static fn ( array $enlace ): string => "- {$enlace['titulo']}: {$enlace['url']}", $enlaces )
		);

		// translators: %s es el nombre del periodista.
		$asunto = sprintf( __( 'Boletín de %s', 'pluma-engine' ), $periodista->nombre );

		$resultado = $this->notificador->notificarObjetivo( TipoSuscripcion::Periodista, $periodistaId, null, $asunto, $cuerpo, null );

		return array(
			'piezas' => count( $enlaces ),
			'email'  => $resultado['email'],
			'push'   => $resultado['push'],
		);
	}
}
