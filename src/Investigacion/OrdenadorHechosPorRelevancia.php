<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Proveedores\ProveedorRerankCerebroRemoto;
use Pluma\Proveedores\ResultadoRerank;

/**
 * NCP-3 Porción 3 (`ADR 0023`): rol RRK real, "selección de extractos del
 * expediente" (`docs/CEREBRO_PLUMA_v2.md` §1.3). Reordena los hechos del
 * expediente por relevancia a la tendencia de origen — nunca los excluye:
 * `Pluma\Redaccion\FormateadorExpediente::comoTexto()` sigue enviando TODOS
 * los hechos al redactor (GOVERNANCE §2.4), solo cambia el orden.
 *
 * A diferencia de {@see \Pluma\Redaccion\VerificadorContradiccionNli}/
 * {@see \Pluma\Investigacion\DetectorContradiccionesNli} (`ADR 0021`/`ADR 0022`,
 * donde el fallo de T3 debe propagarse por ser verificaciones de seguridad
 * editorial), esta clase **degrada con gracia**: reordenar es una
 * optimización de presentación sin ningún hecho de por medio — si el
 * reranking falla o la respuesta no es una permutación válida de los
 * índices originales, el expediente se devuelve intacto, en su orden
 * original. Decisión confirmada explícitamente por el propietario.
 */
final class OrdenadorHechosPorRelevancia {

	public function __construct(
		private readonly ProveedorRerankCerebroRemoto $rerank,
	) {
	}

	public function ordenar( Expediente $expediente ): Expediente {
		$hechos = $expediente->hechos;
		$total  = count( $hechos );

		if ( $total < 2 ) {
			return $expediente;
		}

		try {
			$resultados = $this->rerank->reordenar(
				$expediente->tendenciaOrigen,
				array_map( static fn ( HechoFuente $hecho ): string => $hecho->extracto, $hechos )
			);
		} catch ( ProveedorLenguajeException ) {
			return $expediente;
		}

		if ( ! $this->esPermutacionValida( $resultados, $total ) ) {
			return $expediente;
		}

		$hechosOrdenados = array_map(
			static fn ( ResultadoRerank $resultado ): HechoFuente => $hechos[ $resultado->indice ],
			$resultados
		);

		return new Expediente( $expediente->tendenciaOrigen, $hechosOrdenados, $expediente->huecosDetectados );
	}

	/**
	 * @param list<ResultadoRerank> $resultados
	 */
	private function esPermutacionValida( array $resultados, int $total ): bool {
		if ( count( $resultados ) !== $total ) {
			return false;
		}

		$indices = array_map( static fn ( ResultadoRerank $resultado ): int => $resultado->indice, $resultados );
		sort( $indices );

		return range( 0, $total - 1 ) === $indices;
	}
}
