<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Similitud coseno entre vectores de embeddings (Nivel Dos A.5 + Nivel Tres
 * J.3). Función pura, sin dependencias — la misma fórmula sirve para medir
 * deriva semántica de voz (A.5) y trazabilidad de hechos (J.3), con
 * propósitos distintos en cada uso.
 */
final class SimilitudVectorial {

	/**
	 * @param list<float> $a
	 * @param list<float> $b
	 */
	public static function coseno( array $a, array $b ): float {
		if ( array() === $a || array() === $b || count( $a ) !== count( $b ) ) {
			return 0.0;
		}

		$productoPunto = 0.0;
		$normaA        = 0.0;
		$normaB        = 0.0;

		foreach ( $a as $indice => $valorA ) {
			$valorB         = $b[ $indice ];
			$productoPunto += $valorA * $valorB;
			$normaA        += $valorA ** 2;
			$normaB        += $valorB ** 2;
		}

		if ( 0.0 === $normaA || 0.0 === $normaB ) {
			return 0.0;
		}

		return $productoPunto / ( sqrt( $normaA ) * sqrt( $normaB ) );
	}
}
