<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Rol RRK vía técnica léxica pura — TF-IDF + similitud de coseno, Plano 0
 * (`docs/CEREBRO_PLUMA_v2.md` §1.2 ya lista "BM25/TF-IDF sobre el archivo
 * propio" como léxico puro). Sin modelo, sin entrenamiento, sin red: siempre
 * disponible en cualquier hosting, sin configuración (`ADR 0024`).
 *
 * El IDF se calcula sobre el propio lote de `$textos` de cada llamada, no
 * sobre un corpus global — es la técnica correcta para reordenar un
 * conjunto pequeño de candidatos frente a una consulta (el caso real:
 * extractos de un expediente), no para recuperación en un archivo grande.
 */
final class ProveedorRerankLexico implements RerankInterface {

	/**
	 * @param list<string> $textos
	 *
	 * @return list<ResultadoRerank> ordenado descendente por puntuación.
	 */
	public function reordenar( string $consulta, array $textos ): array {
		if ( array() === $textos ) {
			return array();
		}

		$documentos     = array_map( array( $this, 'tokenizar' ), $textos );
		$idf            = $this->calcularIdf( $documentos );
		$vectorConsulta = $this->vectorTfIdf( $this->tokenizar( $consulta ), $idf );

		$resultados = array();

		foreach ( $documentos as $indice => $tokens ) {
			$vectorDocumento = $this->vectorTfIdf( $tokens, $idf );
			$resultados[]    = new ResultadoRerank( $indice, $this->similitudCoseno( $vectorConsulta, $vectorDocumento ) );
		}

		usort( $resultados, static fn ( ResultadoRerank $a, ResultadoRerank $b ): int => $b->puntuacion <=> $a->puntuacion );

		return array_values( $resultados );
	}

	/**
	 * @return list<string>
	 */
	private function tokenizar( string $texto ): array {
		$normalizado   = mb_strtolower( $texto, 'UTF-8' );
		$sinPuntuacion = (string) preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $normalizado );
		$tokens        = preg_split( '/\s+/u', trim( $sinPuntuacion ) );

		if ( false === $tokens ) {
			return array();
		}

		return array_values( array_filter( $tokens, static fn ( string $t ): bool => '' !== $t ) );
	}

	/**
	 * @param list<list<string>> $documentos
	 *
	 * @return array<string, float>
	 */
	private function calcularIdf( array $documentos ): array {
		$totalDocumentos      = count( $documentos );
		$frecuenciaDocumental = array();

		foreach ( $documentos as $tokens ) {
			foreach ( array_unique( $tokens ) as $token ) {
				$frecuenciaDocumental[ $token ] = ( $frecuenciaDocumental[ $token ] ?? 0 ) + 1;
			}
		}

		$idf = array();

		foreach ( $frecuenciaDocumental as $token => $frecuencia ) {
			// Suavizado (+1 en numerador y denominador): evita división por cero y log(0) sin distorsionar el orden relativo.
			$idf[ $token ] = log( ( 1 + $totalDocumentos ) / ( 1 + $frecuencia ) ) + 1.0;
		}

		return $idf;
	}

	/**
	 * @param list<string>         $tokens
	 * @param array<string, float> $idf
	 *
	 * @return array<string, float>
	 */
	private function vectorTfIdf( array $tokens, array $idf ): array {
		if ( array() === $tokens ) {
			return array();
		}

		$frecuenciaTermino = array_count_values( $tokens );
		$totalTerminos     = count( $tokens );
		$vector            = array();

		foreach ( $frecuenciaTermino as $token => $frecuencia ) {
			$tf = $frecuencia / $totalTerminos;
			// Un token de la consulta ausente del lote de documentos no tiene IDF conocido: pesa 0, no se inventa.
			$vector[ $token ] = $tf * ( $idf[ $token ] ?? 0.0 );
		}

		return $vector;
	}

	/**
	 * @param array<string, float> $vectorA
	 * @param array<string, float> $vectorB
	 */
	private function similitudCoseno( array $vectorA, array $vectorB ): float {
		if ( array() === $vectorA || array() === $vectorB ) {
			return 0.0;
		}

		$productoPunto = 0.0;

		foreach ( $vectorA as $token => $peso ) {
			if ( isset( $vectorB[ $token ] ) ) {
				$productoPunto += $peso * $vectorB[ $token ];
			}
		}

		$normaA = sqrt( array_sum( array_map( static fn ( float $p ): float => $p * $p, $vectorA ) ) );
		$normaB = sqrt( array_sum( array_map( static fn ( float $p ): float => $p * $p, $vectorB ) ) );

		if ( 0.0 === $normaA || 0.0 === $normaB ) {
			return 0.0;
		}

		return $productoPunto / ( $normaA * $normaB );
	}
}
