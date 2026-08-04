<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Proveedores\EtiquetaNli;
use Pluma\Proveedores\NliInterface;

/**
 * NCP-3 Porción 1 (`ADR 0021`, reorientada por `ADR 0024`): capa determinista
 * (no generativa) de detección de contradicciones, vía NLI real. Complementa a
 * {@see VerificadorTrazabilidadDeterminista} (similitud, "sin respaldo
 * aparente") con una señal categóricamente más fuerte: "esta frase del
 * borrador CONTRADICE un extracto del expediente", tal como especifica
 * `docs/CEREBRO_PLUMA_v2.md` §0.1 punto 1 ("de similitud a implicación").
 *
 * Prioriza y abarata el punto "hechos" del Corrector Interno, nunca lo
 * sustituye: los falsos positivos de NLI son posibles, así que nunca
 * descarta una unidad por sí sola (mismo principio que GOVERNANCE §2.4
 * aplica ya a la verificación por embeddings).
 */
final class VerificadorContradiccionNli {

	public const OPCION_UMBRAL_CONTRADICCION = 'pluma_umbral_contradiccion_nli';
	private const UMBRAL_DEFECTO             = 0.5;

	public function __construct(
		private readonly NliInterface $nli,
		private readonly SegmentadorUnidadesFactuales $segmentador,
	) {
	}

	/**
	 * @return list<string> unidades del borrador que contradicen algún hecho del expediente
	 */
	public function unidadesQueContradicenElExpediente( Expediente $expediente, string $cuerpo ): array {
		$unidades = $this->segmentador->segmentar( $cuerpo );

		if ( array() === $unidades || array() === $expediente->hechos ) {
			return array();
		}

		$umbral          = $this->umbralContradiccion();
		$contradicciones = array();

		foreach ( $unidades as $unidad ) {
			if ( $this->contradiceAlgunHecho( $unidad, $expediente->hechos, $umbral ) ) {
				$contradicciones[] = $unidad;
			}
		}

		return $contradicciones;
	}

	/**
	 * @param list<HechoFuente> $hechos
	 */
	private function contradiceAlgunHecho( string $unidad, array $hechos, float $umbral ): bool {
		foreach ( $hechos as $hecho ) {
			$resultados = $this->nli->inferir( $hecho->extracto, $unidad );
			$principal  = $resultados[0] ?? null;

			if ( null !== $principal && EtiquetaNli::Contradiccion === $principal->etiqueta && $principal->puntuacion >= $umbral ) {
				return true;
			}
		}

		return false;
	}

	private function umbralContradiccion(): float {
		$valor = get_option( self::OPCION_UMBRAL_CONTRADICCION, self::UMBRAL_DEFECTO );

		return is_numeric( $valor ) ? (float) $valor : self::UMBRAL_DEFECTO;
	}
}
