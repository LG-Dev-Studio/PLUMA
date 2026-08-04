<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

use Pluma\Proveedores\EtiquetaNli;
use Pluma\Proveedores\NliInterface;

/**
 * NCP-3 Porción 2 (`ADR 0022`, reorientada por `ADR 0024`): capa determinista
 * (no generativa) de detección de contradicciones entre fuentes, vía NLI
 * real. El canon
 * (`docs/CEREBRO_PLUMA_v2.md` §0.1 punto 2): "la contradicción entre dos
 * extractos ES la etiqueta CONTRADICTION del mismo modelo NLI".
 *
 * Compara cada PAR de hechos del expediente — NLI por sí solo no distingue
 * `TipoContradiccion` (`Cifra`/`Atribucion`/`Ocurrencia`), así que esta capa
 * prioriza la petición generativa de {@see ResolutorDisputas}, nunca la
 * sustituye (mismo principio que {@see \Pluma\Redaccion\VerificadorContradiccionNli},
 * `ADR 0021`).
 */
final class DetectorContradiccionesNli {

	public const OPCION_UMBRAL_CONTRADICCION_FUENTES = 'pluma_umbral_contradiccion_nli_fuentes';
	private const UMBRAL_DEFECTO                     = 0.5;

	public function __construct(
		private readonly NliInterface $nli,
	) {
	}

	/**
	 * @return list<array{indiceA: int, indiceB: int}> pares de índices de `$expediente->hechos` que se contradicen
	 */
	public function paresQueContradicen( Expediente $expediente ): array {
		$hechos = $expediente->hechos;
		$total  = count( $hechos );

		if ( $total < 2 ) {
			return array();
		}

		$umbral = $this->umbralContradiccion();
		$pares  = array();

		for ( $i = 0; $i < $total; $i++ ) {
			for ( $j = $i + 1; $j < $total; $j++ ) {
				$resultados = $this->nli->inferir( $hechos[ $i ]->extracto, $hechos[ $j ]->extracto );
				$principal  = $resultados[0] ?? null;

				if ( null !== $principal && EtiquetaNli::Contradiccion === $principal->etiqueta && $principal->puntuacion >= $umbral ) {
					$pares[] = array(
						'indiceA' => $i,
						'indiceB' => $j,
					);
				}
			}
		}

		return $pares;
	}

	private function umbralContradiccion(): float {
		$valor = get_option( self::OPCION_UMBRAL_CONTRADICCION_FUENTES, self::UMBRAL_DEFECTO );

		return is_numeric( $valor ) ? (float) $valor : self::UMBRAL_DEFECTO;
	}
}
