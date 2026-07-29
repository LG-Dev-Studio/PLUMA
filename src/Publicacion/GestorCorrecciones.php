<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use Pluma\Datos\RepositorioCorreccionesInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Kernel\RelojInterface;

/**
 * Nivel Cuatro X.4 — la corrección con crédito: "reportar un error" público,
 * verificado por el editor antes de publicarse como corrección real. El
 * flujo de rectificación interno (Libro Cap. 13.6) no vivía en ningún lugar
 * de este código todavía — se construye aquí junto con la puerta pública,
 * en vez de asumir que ya existía.
 */
final class GestorCorrecciones {

	public const META_CORREGIDA_EN   = '_pluma_correccion_fecha';
	public const META_CREDITO_LECTOR = '_pluma_correccion_credito';

	public function __construct(
		private readonly RepositorioCorreccionesInterface $correcciones,
		private readonly RepositorioPiezasInterface $piezas,
		private readonly RelojInterface $reloj,
	) {
	}

	public function reportar( int $piezaId, string $afirmacionReportada, string $evidenciaAportada, ?string $emailReportante, ?string $nombreCredito, bool $creditoOptIn ): int {
		return $this->correcciones->crear( $piezaId, $afirmacionReportada, $evidenciaAportada, $emailReportante, $nombreCredito, $creditoOptIn, $this->reloj->ahora() );
	}

	/**
	 * @throws CorreccionNoEncontradaException
	 */
	public function verificar( int $id, ?string $notaEditor ): void {
		$correccion = $this->obtenerOLanzar( $id );
		$ahora      = $this->reloj->ahora();

		$this->correcciones->resolver( $id, EstadoCorreccion::Verificada, $notaEditor, $ahora );

		$pieza = $this->piezas->obtenerPorId( $correccion->piezaId );

		if ( null === $pieza || null === $pieza->postId ) {
			return;
		}

		// Formato MySQL (no DATE_ATOM): `Pluma\Seo\BannerCorreccion` lo formatea con `mysql2date()`, que exige esta forma exacta.
		update_post_meta( $pieza->postId, self::META_CORREGIDA_EN, $ahora->format( 'Y-m-d H:i:s' ) );

		if ( $correccion->creditoOptIn && null !== $correccion->nombreCredito && '' !== $correccion->nombreCredito ) {
			update_post_meta( $pieza->postId, self::META_CREDITO_LECTOR, $correccion->nombreCredito );
		}
	}

	/**
	 * @throws CorreccionNoEncontradaException
	 */
	public function rechazar( int $id, ?string $notaEditor ): void {
		$this->obtenerOLanzar( $id );
		$this->correcciones->resolver( $id, EstadoCorreccion::Rechazada, $notaEditor, $this->reloj->ahora() );
	}

	/**
	 * @return list<Correccion>
	 */
	public function pendientes( int $limite = 50 ): array {
		return $this->correcciones->obtenerPorEstado( EstadoCorreccion::Pendiente, $limite );
	}

	/**
	 * @return list<Correccion>
	 */
	public function historialPublico( int $limite = 50 ): array {
		return $this->correcciones->obtenerVerificadasRecientes( $limite );
	}

	/**
	 * @throws CorreccionNoEncontradaException
	 */
	private function obtenerOLanzar( int $id ): Correccion {
		$correccion = $this->correcciones->obtenerPorId( $id );

		if ( null === $correccion ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje interno construido por la propia excepción, sin entrada de usuario.
			throw new CorreccionNoEncontradaException( $id );
		}

		return $correccion;
	}
}
