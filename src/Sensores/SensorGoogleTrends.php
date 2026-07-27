<?php

declare(strict_types=1);

namespace Pluma\Sensores;

use Pluma\Proveedores\ProveedorTendenciasInterface;
use Pluma\Proveedores\TendenciaCruda;

/**
 * Adapta el proveedor de Google Trends al contrato del Radar: interpreta
 * (percibe), nunca hace HTTP directo (CLAUDE.md § Ley de Arquitectura).
 *
 * Velocidad: tráfico aproximado del lote normalizado 0-100 contra el máximo
 * del propio lote — proxy real de "qué tan caliente está ahora mismo"
 * mientras `pluma_tendencias` acumula historial suficiente para medir
 * aceleración día a día (docs/deuda.md: PLUMA-E1-1).
 *
 * Afinidad: solapamiento de palabras contra `pluma_verticales` (aún sin
 * pantalla propia — opción vacía por defecto). Sin verticales configurados,
 * nada se descarta por afinidad (100); con verticales, coincidencia simple
 * de subcadena (heurística v1, se refina con NLP en una etapa posterior).
 * Desde Nivel Dos C.1, esta afinidad ya actúa como puerta binaria dentro de
 * `PuntuacionOportunidad::calcular()` — sin verticales configurados, la
 * puerta nunca se cierra (afinidad siempre 100 ≥ umbral).
 */
final class SensorGoogleTrends implements SensorInterface {

	public function __construct(
		private readonly ProveedorTendenciasInterface $proveedor,
	) {
	}

	public function nombre(): string {
		return 'google_trends';
	}

	public function detectar(): array {
		$crudas = $this->proveedor->obtenerTendenciasCrudas();

		if ( array() === $crudas ) {
			return array();
		}

		$traficoMaximo = max(
			1,
			max( array_map( fn ( TendenciaCruda $c ): int => $this->traficoNumerico( $c->traficoAproximado ), $crudas ) )
		);

		$verticales = $this->verticalesConfiguradas();

		return array_map(
			fn ( TendenciaCruda $cruda ): TendenciaDetectada => new TendenciaDetectada(
				$cruda->termino,
				PuntuacionOportunidad::calcular(
					( $this->traficoNumerico( $cruda->traficoAproximado ) / $traficoMaximo ) * 100,
					$this->calcularAfinidad( $cruda->termino, $verticales )
				),
				$cruda->publicadaEn,
				$cruda->articulosRelacionados,
				$this->nombre()
			),
			$crudas
		);
	}

	private function traficoNumerico( string $texto ): int {
		$digitos = preg_replace( '/\D+/', '', $texto );

		return null !== $digitos && '' !== $digitos ? (int) $digitos : 0;
	}

	/**
	 * @return list<string>
	 */
	private function verticalesConfiguradas(): array {
		$verticales = get_option( 'pluma_verticales', array() );

		if ( ! is_array( $verticales ) ) {
			return array();
		}

		return array_values( array_filter( $verticales, 'is_string' ) );
	}

	/**
	 * @param list<string> $verticales
	 */
	private function calcularAfinidad( string $termino, array $verticales ): float {
		if ( array() === $verticales ) {
			return 100.0;
		}

		$terminoNormalizado = mb_strtolower( $termino );

		foreach ( $verticales as $vertical ) {
			if ( str_contains( $terminoNormalizado, mb_strtolower( $vertical ) ) ) {
				return 100.0;
			}
		}

		return 30.0;
	}
}
