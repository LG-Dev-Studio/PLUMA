<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PropositoLenguaje;

/**
 * Nivel Dos B.1 + B.2: Algoritmo de Resolución de Disputas. Clasifica el
 * tipo de contradicción entre pares de hechos del expediente ANTES de
 * intentar resolverla, porque cada tipo tiene una salida distinta:
 *
 * - `Cifra` y `Atribucion`: ambas versiones ya conviven en el expediente
 *   como hechos separados (`InvestigadorMecanico` nunca las fusiona) — no
 *   requieren mutación, solo quedan clasificadas para que aguas abajo se
 *   sepa que existe la disputa.
 * - `Ocurrencia`: estado `NivelVerificacion::Disputado` obligatorio.
 *
 * **Deuda conocida (bloqueador nuevo, ver `docs/deuda.md`)**: B.2 punto 2
 * exige "buscar una tercera fuente independiente antes de cerrar el
 * expediente" para contradicciones de ocurrencia — PLUMA no tiene hoy
 * ningún proveedor de búsqueda web (solo Google Trends y OpenRouter para
 * lenguaje); ese paso queda diferido hasta elegir y verificar un proveedor
 * real (decisión del propietario, mismo tratamiento que D.1 en la
 * Porción 8). Toda contradicción de ocurrencia se marca `Disputado`
 * directamente, sin el intento de resolución por tercera fuente.
 *
 * NCP-3 Porción 2 (`ADR 0022`): antes de la llamada generativa,
 * {@see \Pluma\Investigacion\DetectorContradiccionesNli} (capa determinista,
 * NLI real vía T3) señala qué pares de hechos se contradicen — prioriza y
 * abarata la clasificación, nunca la sustituye (NLI no distingue
 * `TipoContradiccion`, solo confirma que hay contradicción).
 */
final class ResolutorDisputas {

	private const MAX_TOKENS_RESPUESTA = 500;

	public function __construct(
		private readonly LenguajeInterface $proveedor,
		private readonly DetectorContradiccionesNli $detectorNli,
	) {
	}

	/**
	 * @throws InvestigacionException si la respuesta del proveedor no trae el formato esperado, o llegó truncada.
	 * @throws \Pluma\Proveedores\ProveedorLenguajeException
	 */
	public function resolver( Expediente $expediente ): Expediente {
		if ( count( $expediente->hechos ) < 2 ) {
			return $expediente;
		}

		$bloques = array(
			'Eres el Investigador: identifica contradicciones entre los hechos numerados del expediente adjunto.',
			'Clasifica CADA contradicción real que encuentres (no relaciones triviales) en exactamente uno de estos tipos:',
			'"cifra": dos números distintos para el mismo hecho concreto (ej. "300 asistentes" vs "3.000 asistentes").',
			'"atribucion": mismo hecho, distinto responsable señalado (ej. la empresa dice error técnico, el sindicato dice decisión deliberada).',
			'"ocurrencia": una fuente afirma que algo pasó, otra que afirma que no pasó — un desacuerdo sobre si el hecho ocurrió, no solo sobre su detalle.',
			'Responde ÚNICAMENTE con un objeto JSON de esta forma exacta (lista vacía si no hay ninguna contradicción real):',
			'{"contradicciones": [{"indiceA": integer, "indiceB": integer, "tipo": "cifra" | "atribucion" | "ocurrencia"}]}',
		);

		$paresContradictorios = $this->detectorNli->paresQueContradicen( $expediente );

		if ( array() !== $paresContradictorios ) {
			// NCP-3 Porción 2 (`ADR 0022`): capa determinista (no generativa, NLI real vía T3), previa a
			// esta llamada — prioriza la clasificación, nunca la sustituye (puede ser un falso positivo).
			$bloques[] = 'ALERTA DE CONTRADICCIÓN DETERMINISTA (NLI): estos pares de hechos (por índice) fueron detectados como contradictorios por un modelo de inferencia de lenguaje natural — revísalos con tu propio criterio y clasifica su tipo exacto: '
				. implode(
					' | ',
					array_map(
						static fn ( array $par ): string => "({$par['indiceA']},{$par['indiceB']})",
						$paresContradictorios
					)
				);
		}

		$directrices = implode( "\n", $bloques );

		$peticion = new PeticionLenguaje(
			PropositoLenguaje::Clasificar,
			$directrices,
			FormateadorHechos::comoTexto( $expediente->hechos ),
			self::MAX_TOKENS_RESPUESTA
		);

		$respuesta = $this->proveedor->completar( $peticion );

		if ( $respuesta->truncada ) {
			throw new InvestigacionException( 'La resolución de disputas del Investigador llegó truncada.' );
		}

		$datos = ExtractorJsonLlm::extraer( $respuesta->contenido );

		if ( ! isset( $datos['contradicciones'] ) || ! is_array( $datos['contradicciones'] ) ) {
			throw new InvestigacionException( 'La resolución de disputas no devolvió el formato esperado.' );
		}

		$hechos = $expediente->hechos;

		foreach ( $datos['contradicciones'] as $contradiccion ) {
			if ( ! is_array( $contradiccion ) || ! isset( $contradiccion['indiceA'], $contradiccion['indiceB'], $contradiccion['tipo'] ) ) {
				continue;
			}

			if ( TipoContradiccion::Ocurrencia !== TipoContradiccion::tryFrom( (string) $contradiccion['tipo'] ) ) {
				continue;
			}

			foreach ( array( $contradiccion['indiceA'], $contradiccion['indiceB'] ) as $indice ) {
				$indice = (int) $indice;

				if ( isset( $hechos[ $indice ] ) ) {
					$hechos[ $indice ] = $this->marcarDisputado( $hechos[ $indice ] );
				}
			}
		}

		return new Expediente( $expediente->tendenciaOrigen, $hechos, $expediente->huecosDetectados );
	}

	private function marcarDisputado( HechoFuente $hecho ): HechoFuente {
		return new HechoFuente(
			$hecho->extracto,
			$hecho->url,
			$hecho->fecha,
			NivelVerificacion::Disputado,
			$hecho->procedenciaDeclaracion,
			$hecho->corroboracionAudiovisual
		);
	}
}
