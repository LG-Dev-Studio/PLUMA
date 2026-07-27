<?php

declare(strict_types=1);

namespace Pluma\Sensores;

/**
 * Puntuación de Oportunidad (Libro Cap. 3.3, corregida por Nivel Dos C.1):
 * Velocidad 40% + Afinidad (residual) 15% + Hueco competitivo 20% + Vida
 * útil 15% — pero afinidad actúa PRIMERO como puerta binaria, no como
 * sumando: sin ella, una tendencia totalmente ajena a la línea editorial
 * (afinidad 0) todavía podía alcanzar el umbral de cobertura si el resto de
 * factores eran altos. `elegible = afinidad >= umbral_afinidad_minima`; si no
 * es elegible, `total = 0` sin excepción.
 *
 * Hueco competitivo y vida útil siguen sin ninguna fuente de datos real en
 * el código (`docs/deuda.md`: `PLUMA-E1-1`) — no se simulan con un valor
 * fijo de 0.0 pasado como si fuera medido (eso sería el placeholder que
 * CLAUDE.md prohíbe). `total` solo combina los dos factores reales
 * disponibles hoy; su techo honesto es 55/100 (0.40 + 0.15), no 100,
 * mientras esa deuda siga abierta.
 */
final readonly class PuntuacionOportunidad {

	public const OPCION_UMBRAL_AFINIDAD_MINIMA = 'pluma_umbral_afinidad_minima';

	private const PESO_VELOCIDAD          = 0.40;
	private const PESO_AFINIDAD_RESIDUAL  = 0.15;
	private const UMBRAL_AFINIDAD_DEFECTO = 15.0;

	private function __construct(
		public float $velocidad,
		public float $afinidad,
		public bool $elegible,
		public float $total,
	) {
	}

	public static function calcular( float $velocidad, float $afinidad ): self {
		$velocidad = max( 0.0, min( 100.0, $velocidad ) );
		$afinidad  = max( 0.0, min( 100.0, $afinidad ) );

		$umbral   = self::umbralAfinidadMinima();
		$elegible = $afinidad >= $umbral;
		$total    = $elegible ? round( ( $velocidad * self::PESO_VELOCIDAD ) + ( $afinidad * self::PESO_AFINIDAD_RESIDUAL ), 2 ) : 0.0;

		return new self( $velocidad, $afinidad, $elegible, $total );
	}

	private static function umbralAfinidadMinima(): float {
		$valor = get_option( self::OPCION_UMBRAL_AFINIDAD_MINIMA, self::UMBRAL_AFINIDAD_DEFECTO );

		return is_numeric( $valor ) ? (float) $valor : self::UMBRAL_AFINIDAD_DEFECTO;
	}
}
