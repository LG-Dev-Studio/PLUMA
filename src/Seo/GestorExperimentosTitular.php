<?php

declare(strict_types=1);

namespace Pluma\Seo;

use Pluma\Datos\RepositorioExperimentosTitularInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Kernel\RelojInterface;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Redaccion\DecisionEditorialException;
use Pluma\Redaccion\GeneradorTitularAlternativo;

/**
 * Nivel Cuatro Y.2 — el experimento de titular editorial: "servidas al azar
 * las primeras N horas, la ganadora por CTR interno se consolida".
 *
 * Límite honesto y documentado (`PLUMA-E9-8`): sin sesión/cookie de
 * lector, "impresión" y "clic" no están correlacionados por visitante —
 * son dos contadores independientes (impresión = el titular se mostró
 * fuera de la vista individual; clic = se vio la pieza en su vista
 * individual con esa variante activa en ese momento). Con volumen
 * suficiente y asignación aleatoria por petición, converge al CTR real por
 * variante, pero no es un A/B con trazabilidad de clic por impresión.
 */
final class GestorExperimentosTitular {

	public const OPCION_VENTANA_HORAS   = 'pluma_experimento_titular_ventana_horas';
	private const VENTANA_HORAS_DEFECTO = 24;

	/**
	 * @var array<int, string>
	 */
	private static array $varianteElegidaPorPost = array();

	public function __construct(
		private readonly RepositorioExperimentosTitularInterface $experimentos,
		private readonly RepositorioPiezasInterface $piezas,
		private readonly RepositorioPeriodistasInterface $periodistas,
		private readonly GeneradorTitularAlternativo $generador,
		private readonly RelojInterface $reloj,
	) {
	}

	public function registrar(): void {
		add_action( 'pluma/pieza_publicada', array( $this, 'procesarPublicacion' ), 10, 1 );
		add_filter( 'the_title', array( $this, 'servirYRegistrar' ), 10, 2 );
		// La caché estática solo debe vivir DENTRO de una petición (varias
		// llamadas a `the_title` para el mismo post deben coincidir en la
		// misma variante) — sin este reset en `init`, un proceso PHP de
		// larga vida (PHP-FPM persistente) filtraría la variante de una
		// petición anterior a la siguiente.
		add_action( 'init', array( self::class, 'reiniciarCachePorPeticion' ) );
	}

	public static function reiniciarCachePorPeticion(): void {
		self::$varianteElegidaPorPost = array();
	}

	public function procesarPublicacion( int $piezaId ): void {
		$pieza = $this->piezas->obtenerPorId( $piezaId );

		if ( null === $pieza || null === $pieza->postId || null === $pieza->periodistaId || null === $pieza->fichaDecisionEditorial ) {
			return;
		}

		$periodista = $this->periodistas->obtenerPorId( $pieza->periodistaId );
		$post       = get_post( $pieza->postId );

		if ( null === $periodista || null === $post || ! is_string( $post->post_title ) || '' === $post->post_title ) {
			return;
		}

		try {
			$tituloB = $this->generador->generar( $periodista, $post->post_title, $pieza->fichaDecisionEditorial->tesisElegida()->tesis );
		} catch ( ProveedorLenguajeException | DecisionEditorialException ) {
			return;
		}

		$this->experimentos->crear( $piezaId, $pieza->postId, $post->post_title, $tituloB, $this->reloj->ahora() );
	}

	public function servirYRegistrar( string $titulo, int $postId ): string {
		if ( ! isset( self::$varianteElegidaPorPost[ $postId ] ) ) {
			$experimento = $this->experimentos->obtenerPorPostId( $postId );

			if ( null === $experimento ) {
				self::$varianteElegidaPorPost[ $postId ] = '';

				return $titulo;
			}

			$variante = 1 === random_int( 0, 1 ) ? 'b' : 'a';

			self::$varianteElegidaPorPost[ $postId ] = $variante;

			if ( is_singular() && get_the_ID() === $postId ) {
				$this->experimentos->incrementarClic( $experimento->id, $variante );
			} else {
				$this->experimentos->incrementarImpresion( $experimento->id, $variante );
			}

			return 'b' === $variante ? $experimento->tituloB : $experimento->tituloA;
		}

		$variante = self::$varianteElegidaPorPost[ $postId ];

		if ( '' === $variante ) {
			return $titulo;
		}

		$experimento = $this->experimentos->obtenerPorPostId( $postId );

		return null === $experimento ? $titulo : ( 'b' === $variante ? $experimento->tituloB : $experimento->tituloA );
	}

	/**
	 * Llamado desde el Orquestador en cada tick (presupuesto de tiempo
	 * compartido, mismo criterio que el resto del motor — nunca WP-Cron).
	 */
	public function consolidarVencidos( int $limite = 20 ): int {
		$limiteCreacion = $this->reloj->ahora()->modify( '-' . $this->ventanaHoras() . ' hours' );
		$consolidados   = 0;

		foreach ( $this->experimentos->obtenerListosParaConsolidar( $limiteCreacion, $limite ) as $experimento ) {
			$ctrA = $experimento->impresionesA > 0 ? $experimento->clicsA / $experimento->impresionesA : 0.0;
			$ctrB = $experimento->impresionesB > 0 ? $experimento->clicsB / $experimento->impresionesB : 0.0;

			$ganador       = $ctrB > $ctrA ? 'b' : 'a';
			$tituloGanador = 'b' === $ganador ? $experimento->tituloB : $experimento->tituloA;

			$this->experimentos->consolidar( $experimento->id, $ganador, $this->reloj->ahora() );
			wp_update_post(
				array(
					'ID'         => $experimento->postId,
					'post_title' => $tituloGanador,
				)
			);

			++$consolidados;
		}

		return $consolidados;
	}

	private function ventanaHoras(): int {
		$valor = get_option( self::OPCION_VENTANA_HORAS, self::VENTANA_HORAS_DEFECTO );

		return is_numeric( $valor ) && (int) $valor > 0 ? (int) $valor : self::VENTANA_HORAS_DEFECTO;
	}
}
