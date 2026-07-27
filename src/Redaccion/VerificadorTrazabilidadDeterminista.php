<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Proveedores\EmbeddingsInterface;
use Pluma\Proveedores\SimilitudVectorial;

/**
 * Nivel Tres J.3: capa determinista (no generativa) de verificación de
 * trazabilidad. Para cada unidad factual del borrador ({@see
 * SegmentadorUnidadesFactuales}), compara por similitud coseno de embeddings
 * contra cada extracto del expediente; la unidad cuya similitud máxima cae
 * bajo el umbral configurable se marca `SIN_RESPALDO_APARENTE` — una señal
 * que prioriza y abarata el punto "hechos" del Corrector Interno, nunca lo
 * sustituye: los embeddings dan falsos positivos ante paráfrasis legítima,
 * así que nunca descartan una unidad por sí solos (GOVERNANCE §2.4).
 */
final class VerificadorTrazabilidadDeterminista {

	public const OPCION_UMBRAL_SIMILITUD = 'pluma_umbral_similitud_trazabilidad';
	private const UMBRAL_DEFECTO         = 0.75;

	public function __construct(
		private readonly EmbeddingsInterface $embeddings,
		private readonly SegmentadorUnidadesFactuales $segmentador,
	) {
	}

	/**
	 * @return list<string> unidades del borrador marcadas SIN_RESPALDO_APARENTE
	 */
	public function unidadesSinRespaldoAparente( Expediente $expediente, string $cuerpo ): array {
		$unidades = $this->segmentador->segmentar( $cuerpo );

		if ( array() === $unidades || array() === $expediente->hechos ) {
			return array();
		}

		$umbral           = $this->umbralSimilitud();
		$vectoresPorHecho = array_map(
			fn ( HechoFuente $hecho ): array => $this->embeddings->embed( $hecho->extracto ),
			$expediente->hechos
		);

		$sinRespaldo = array();

		foreach ( $unidades as $unidad ) {
			$vectorUnidad    = $this->embeddings->embed( $unidad );
			$similitudMaxima = 0.0;

			foreach ( $vectoresPorHecho as $vectorHecho ) {
				$similitudMaxima = max( $similitudMaxima, SimilitudVectorial::coseno( $vectorUnidad, $vectorHecho ) );
			}

			if ( $similitudMaxima < $umbral ) {
				$sinRespaldo[] = $unidad;
			}
		}

		return $sinRespaldo;
	}

	private function umbralSimilitud(): float {
		$valor = get_option( self::OPCION_UMBRAL_SIMILITUD, self::UMBRAL_DEFECTO );

		return is_numeric( $valor ) ? (float) $valor : self::UMBRAL_DEFECTO;
	}
}
