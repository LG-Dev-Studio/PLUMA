<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PropositoLenguaje;

/**
 * Nivel Dos B.4 + Nivel Tres O.2: heurística de tres pasos (cuatro con la
 * corrección de O.2), no una pregunta abierta al modelo:
 *
 * 1. Matriz de encuadres cubiertos por los hechos recolectados.
 * 2. Dimensiones ausentes = candidatos de hueco.
 * 3. Filtro de sustento: el propio expediente debe tener datos para
 *    llenar el hueco.
 * 4. (O.2) Prueba de relevancia causal: esos datos deben conectar con los
 *    actores/hechos concretos de ESTA tendencia, no solo con el tema
 *    general del vertical — sin esta prueba, "el hueco" puede seguir
 *    siendo un hueco genérico disfrazado de estructurado.
 *
 * Solo las dimensiones que pasan los cuatro pasos entran a
 * `Expediente::$huecosDetectados`.
 */
final class DetectorHuecos {

	private const MAX_TOKENS_RESPUESTA = 600;

	public function __construct( private readonly LenguajeInterface $proveedor ) {
	}

	/**
	 * @throws InvestigacionException si la respuesta del proveedor no trae el formato esperado, o llegó truncada.
	 * @throws \Pluma\Proveedores\ProveedorLenguajeException
	 */
	public function detectar( Expediente $expediente ): Expediente {
		if ( count( $expediente->hechos ) < 2 ) {
			return $expediente;
		}

		$directrices = implode(
			"\n",
			array(
				sprintf( 'Eres el Investigador: analiza el expediente adjunto sobre "%s" contra estas 6 dimensiones fijas de encuadre: economica, humana, politica, tecnica, historica, legal.', $expediente->tendenciaOrigen ),
				'Para CADA una de las 6 dimensiones, responde tres preguntas honestas (no infles síes para llenar el formato):',
				'"cubierta": ¿algún hecho del expediente ya toca esta dimensión?',
				'"datosDisponibles": ¿el propio expediente tiene datos concretos (cifras, hechos) que permitirían desarrollar esta dimensión aunque hoy esté ausente? Responde false si no hay nada específico.',
				'"relevanciaCausal": ¿esos datos disponibles conectan específicamente con los actores o hechos concretos de ESTA tendencia — no solo con el tema general del vertical? Responde false ante la duda.',
				'Responde ÚNICAMENTE con un objeto JSON de esta forma exacta, con las 6 claves siempre presentes:',
				'{"economica": {"cubierta": boolean, "datosDisponibles": boolean, "relevanciaCausal": boolean}, "humana": {...}, "politica": {...}, "tecnica": {...}, "historica": {...}, "legal": {...}}',
			)
		);

		$peticion  = new PeticionLenguaje( PropositoLenguaje::Clasificar, $directrices, FormateadorHechos::comoTexto( $expediente->hechos ), self::MAX_TOKENS_RESPUESTA );
		$respuesta = $this->proveedor->completar( $peticion );

		if ( $respuesta->truncada ) {
			throw new InvestigacionException( 'La detección de huecos del Investigador llegó truncada.' );
		}

		$datos = ExtractorJsonLlm::extraer( $respuesta->contenido );

		$huecos = array();

		foreach ( DimensionEncuadre::cases() as $dimension ) {
			$bloque = $datos[ $dimension->value ] ?? null;

			if ( ! is_array( $bloque ) || ! isset( $bloque['cubierta'], $bloque['datosDisponibles'], $bloque['relevanciaCausal'] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
				throw new InvestigacionException( "La detección de huecos no devolvió la dimensión '{$dimension->value}' con el formato esperado." );
			}

			if ( ! $bloque['cubierta'] && $bloque['datosDisponibles'] && $bloque['relevanciaCausal'] ) {
				$huecos[] = $dimension;
			}
		}

		return new Expediente( $expediente->tendenciaOrigen, $expediente->hechos, $huecos );
	}
}
