<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Proveedores\EmbeddingsInterface;
use Pluma\Proveedores\SimilitudVectorial;

/**
 * Nivel Dos A.5, verificación 2 de 3 (deriva semántica): compara por
 * embeddings una muestra nueva generada con la Conducta actual de un
 * periodista contra su corpus de regresión de voz. Con los diales sin
 * cambiar, una similitud baja frente al corpus de referencia es una alarma —
 * la configuración no cambió, pero el resultado sí, lo que apunta a una
 * regresión en la plantilla de prompt o en el proveedor de lenguaje.
 *
 * Las otras dos verificaciones de A.5 no viven aquí: la presencia
 * estructural (verificación 1) reutiliza {@see VerificadorVoz} tal cual; la
 * discriminación a ciegas (verificación 3) es un protocolo de QA manual
 * documentado (`docs/protocolo-corpus-voz.md`) — simularla con código sería
 * inventar una verificación falsa.
 */
final class VerificadorRegresionVoz {

	public const OPCION_UMBRAL_SIMILITUD = 'pluma_umbral_similitud_regresion_voz';
	private const UMBRAL_DEFECTO         = 0.70;

	public function __construct( private readonly EmbeddingsInterface $embeddings ) {
	}

	/**
	 * @param list<string> $corpusReferencia
	 */
	public function similitudPromedioConCorpus( array $corpusReferencia, string $muestraNueva ): float {
		if ( array() === $corpusReferencia ) {
			return 1.0;
		}

		$vectorMuestra = $this->embeddings->embed( $muestraNueva );

		$similitudes = array_map(
			fn ( string $referencia ): float => SimilitudVectorial::coseno( $vectorMuestra, $this->embeddings->embed( $referencia ) ),
			$corpusReferencia
		);

		return array_sum( $similitudes ) / count( $similitudes );
	}

	/**
	 * @param list<string> $corpusReferencia
	 */
	public function derivaExcesiva( array $corpusReferencia, string $muestraNueva ): bool {
		return $this->similitudPromedioConCorpus( $corpusReferencia, $muestraNueva ) < $this->umbralSimilitud();
	}

	private function umbralSimilitud(): float {
		$valor = get_option( self::OPCION_UMBRAL_SIMILITUD, self::UMBRAL_DEFECTO );

		return is_numeric( $valor ) ? (float) $valor : self::UMBRAL_DEFECTO;
	}
}
