<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Extracción de características para el clasificador NLI pure-PHP (`ADR 0024`).
 * Determinista, sin dependencias — usada TANTO por el entrenamiento offline
 * (`tools/entrenamiento-nli/entrenar.php`) COMO por `ProveedorNliEntrenado` en
 * producción: si esta lógica se desincroniza entre ambos, el modelo entrenado
 * deja de significar lo que el proveedor cree que significa. Es la única
 * fuente de verdad del vector de entrada.
 *
 * Vector de salida: `2 * count(vocabulario)` frecuencias de término (premisa,
 * luego hipótesis) + 4 escalares (similitud de coseno, solapamiento de
 * Jaccard, diferencia de negación, razón de longitud) — mismo espíritu que
 * la línea base léxica de Bowman et al. 2015 (SNLI): vectores de bolsa de
 * palabras de premisa e hipótesis, más señales de su relación.
 */
final class CaracteristicasNli {

	/** @var list<string> */
	private const PALABRAS_NEGACION = array( 'no', 'nunca', 'jamás', 'jamas', 'ningún', 'ningun', 'ninguna', 'ninguno', 'nadie', 'nada', 'tampoco', 'sin' );

	/**
	 * @return list<string>
	 */
	public function tokenizar( string $texto ): array {
		$normalizado   = mb_strtolower( $texto, 'UTF-8' );
		$sinPuntuacion = (string) preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $normalizado );
		$tokens        = preg_split( '/\s+/u', trim( $sinPuntuacion ) );

		if ( false === $tokens ) {
			return array();
		}

		return array_values( array_filter( $tokens, static fn ( string $t ): bool => '' !== $t ) );
	}

	/**
	 * @param array<string, int> $vocabulario palabra => índice (0-based, contiguo)
	 *
	 * @return list<float>
	 */
	public function vector( string $premisa, string $hipotesis, array $vocabulario ): array {
		$tokensPremisa   = $this->tokenizar( $premisa );
		$tokensHipotesis = $this->tokenizar( $hipotesis );

		$tfPremisa   = $this->tf( $tokensPremisa, $vocabulario );
		$tfHipotesis = $this->tf( $tokensHipotesis, $vocabulario );

		$escalares = array(
			$this->similitudCoseno( $tfPremisa, $tfHipotesis ),
			$this->solapamientoJaccard( $tokensPremisa, $tokensHipotesis, $vocabulario ),
			$this->diferenciaNegacion( $tokensPremisa, $tokensHipotesis ),
			$this->razonLongitud( $tokensPremisa, $tokensHipotesis ),
		);

		return array_merge( $tfPremisa, $tfHipotesis, $escalares );
	}

	/**
	 * @param list<string>        $tokens
	 * @param array<string, int>  $vocabulario
	 *
	 * @return list<float>
	 */
	private function tf( array $tokens, array $vocabulario ): array {
		$vector = array_fill( 0, count( $vocabulario ), 0.0 );
		$total  = count( $tokens );

		if ( 0 === $total ) {
			return $vector;
		}

		foreach ( $tokens as $token ) {
			if ( isset( $vocabulario[ $token ] ) ) {
				++$vector[ $vocabulario[ $token ] ];
			}
		}

		foreach ( $vector as $indice => $frecuencia ) {
			$vector[ $indice ] = $frecuencia / $total;
		}

		return $vector;
	}

	/**
	 * @param list<float> $vectorA
	 * @param list<float> $vectorB
	 */
	private function similitudCoseno( array $vectorA, array $vectorB ): float {
		$productoPunto = 0.0;
		$normaA        = 0.0;
		$normaB        = 0.0;

		foreach ( $vectorA as $indice => $valorA ) {
			$valorB         = $vectorB[ $indice ];
			$productoPunto += $valorA * $valorB;
			$normaA        += $valorA * $valorA;
			$normaB        += $valorB * $valorB;
		}

		if ( 0.0 === $normaA || 0.0 === $normaB ) {
			return 0.0;
		}

		return $productoPunto / ( sqrt( $normaA ) * sqrt( $normaB ) );
	}

	/**
	 * @param list<string>        $tokensPremisa
	 * @param list<string>        $tokensHipotesis
	 * @param array<string, int>  $vocabulario
	 */
	private function solapamientoJaccard( array $tokensPremisa, array $tokensHipotesis, array $vocabulario ): float {
		$conjuntoA = array_intersect_key( array_flip( $tokensPremisa ), $vocabulario );
		$conjuntoB = array_intersect_key( array_flip( $tokensHipotesis ), $vocabulario );

		$union = array_unique( array_merge( array_keys( $conjuntoA ), array_keys( $conjuntoB ) ) );

		if ( array() === $union ) {
			return 0.0;
		}

		$interseccion = array_intersect_key( $conjuntoA, $conjuntoB );

		return count( $interseccion ) / count( $union );
	}

	/**
	 * @param list<string> $tokensPremisa
	 * @param list<string> $tokensHipotesis
	 */
	private function diferenciaNegacion( array $tokensPremisa, array $tokensHipotesis ): float {
		$negacionesPremisa   = count( array_intersect( $tokensPremisa, self::PALABRAS_NEGACION ) );
		$negacionesHipotesis = count( array_intersect( $tokensHipotesis, self::PALABRAS_NEGACION ) );

		return (float) ( $negacionesHipotesis - $negacionesPremisa );
	}

	/**
	 * @param list<string> $tokensPremisa
	 * @param list<string> $tokensHipotesis
	 */
	private function razonLongitud( array $tokensPremisa, array $tokensHipotesis ): float {
		$largoPremisa = count( $tokensPremisa );

		if ( 0 === $largoPremisa ) {
			return 0.0;
		}

		return count( $tokensHipotesis ) / $largoPremisa;
	}
}
