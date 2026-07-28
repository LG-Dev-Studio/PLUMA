<?php

declare(strict_types=1);

namespace Pluma\Sensores;

/**
 * Nivel Dos G.1 — "El modelo de amenaza del propio Radar": antes de que una
 * tendencia entre a la cola editorial, se verifica su naturalidad de señal
 * — difusión orgánica entre fuentes diversas versus amplificación
 * coordinada desde una sola fuente republicando el mismo "trend".
 *
 * Límite de datos honesto (decisión del propietario, Etapa 8, Porción 9):
 * `TendenciaCruda`/`TendenciaDetectada` solo exponen, por artículo
 * relacionado, `titulo`/`url`/`fuente` — ningún timestamp por artículo,
 * ninguna geografía, ninguna señal de novedad de cuenta (mismo límite de
 * Sensor ya formalizado para L.2 en `PLUMA-E8-3`). Esta clase implementa
 * ÚNICAMENTE la pieza de G.1 que el dato actual permite sin inventar
 * precisión que no existe: concentración de fuente. "Novedad de las
 * cuentas de origen" y "concentración geográfica/de red" quedan fuera de
 * alcance hasta que el Sensor exponga esas señales (`docs/deuda.md`,
 * `PLUMA-EV-4`).
 */
final class EvaluadorLegitimidadInsumo {

	public const OPCION_UMBRAL_ARTICULOS_MINIMO  = 'pluma_legitimidad_articulos_minimo';
	public const OPCION_UMBRAL_DIVERSIDAD_MINIMA = 'pluma_legitimidad_diversidad_minima';

	private const ARTICULOS_MINIMO_DEFECTO  = 3;
	private const DIVERSIDAD_MINIMA_DEFECTO = 0.34;

	public function evaluar( TendenciaDetectada $tendencia ): DiagnosticoLegitimidadInsumo {
		$total         = count( $tendencia->articulosRelacionados );
		$fuentesUnicas = count( $this->fuentesUnicas( $tendencia ) );

		if ( 0 === $total || $total < $this->umbralArticulosMinimo() ) {
			// Muestra insuficiente para juzgar concentración de fuente con
			// algo de confianza — una tendencia con pocos artículos podría
			// ser cobertura legítima temprana de un evento real. G.1 exige
			// una heurística defendible, nunca un veredicto sobre datos
			// insuficientes.
			return new DiagnosticoLegitimidadInsumo( $total, $fuentesUnicas, 1.0, true, null );
		}

		$diversidad = $fuentesUnicas / $total;
		$umbral     = $this->umbralDiversidadMinima();
		$legitimo   = $diversidad >= $umbral;

		$motivo = $legitimo ? null : sprintf(
			'Concentración de fuente: %d artículos relacionados vienen de solo %d fuente(s) distinta(s) (diversidad %.2f, mínimo %.2f) — patrón compatible con amplificación coordinada en vez de difusión orgánica (Nivel Dos G.1).',
			$total,
			$fuentesUnicas,
			$diversidad,
			$umbral
		);

		return new DiagnosticoLegitimidadInsumo( $total, $fuentesUnicas, $diversidad, $legitimo, $motivo );
	}

	/**
	 * @return list<string>
	 */
	private function fuentesUnicas( TendenciaDetectada $tendencia ): array {
		return array_values(
			array_unique(
				array_map(
					static fn ( array $articulo ): string => mb_strtolower( trim( (string) $articulo['fuente'] ) ),
					$tendencia->articulosRelacionados
				)
			)
		);
	}

	private function umbralArticulosMinimo(): int {
		$valor = get_option( self::OPCION_UMBRAL_ARTICULOS_MINIMO, self::ARTICULOS_MINIMO_DEFECTO );

		return is_numeric( $valor ) ? (int) $valor : self::ARTICULOS_MINIMO_DEFECTO;
	}

	private function umbralDiversidadMinima(): float {
		$valor = get_option( self::OPCION_UMBRAL_DIVERSIDAD_MINIMA, self::DIVERSIDAD_MINIMA_DEFECTO );

		return is_numeric( $valor ) ? (float) $valor : self::DIVERSIDAD_MINIMA_DEFECTO;
	}
}
