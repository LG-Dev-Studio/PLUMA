<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PropositoLenguaje;

/**
 * Nivel Cuatro Y.2 — el experimento de titular: "dos variantes generadas en
 * la voz del periodista... nunca del title SEO, que queda fijo". La
 * directriz anti-clickbait se le pide al proveedor directamente en el
 * prompt (mismo criterio que `GeneradorDerivadoSocial` para W.2) — "el A/B
 * no es licencia para exagerar", literal del texto fuente.
 */
final class GeneradorTitularAlternativo {

	private const MAX_TOKENS_RESPUESTA = 100;

	public function __construct( private readonly LenguajeInterface $proveedor ) {
	}

	/**
	 * @throws DecisionEditorialException si la variante no trae texto, o llegó truncada.
	 * @throws \Pluma\Proveedores\ProveedorLenguajeException
	 */
	public function generar( Periodista $periodista, string $tituloOriginal, string $tesis ): string {
		$reglas = $periodista->conductaActual->reglas;

		$directrices = implode(
			"\n",
			array(
				"Eres {$periodista->nombre}. Ya escribiste este titular para una pieza: \"{$tituloOriginal}\".",
				'Genera una SEGUNDA variante del mismo titular editorial, en tu misma voz, que promete exactamente lo que la pieza cumple — nunca clickbait, el A/B no es licencia para exagerar.',
				"Tu línea editorial: {$reglas->lineaEditorial}.",
				'La variante debe ser claramente distinta en formulación de la original, no un sinónimo trivial.',
				'Responde ÚNICAMENTE con un objeto JSON de esta forma exacta: {"tituloAlternativo": string}',
			)
		);

		$material = sprintf( "Titular original: %s\nTesis de la pieza: %s", $tituloOriginal, $tesis );

		$peticion  = new PeticionLenguaje( PropositoLenguaje::TitularAlternativo, $directrices, $material, self::MAX_TOKENS_RESPUESTA );
		$respuesta = $this->proveedor->completar( $peticion );
		VerificadorTruncamiento::asegurar( $respuesta );
		$datos = ExtractorJsonLlm::extraer( $respuesta->contenido );

		if ( ! isset( $datos['tituloAlternativo'] ) || ! is_string( $datos['tituloAlternativo'] ) || '' === trim( $datos['tituloAlternativo'] ) ) {
			throw new DecisionEditorialException( 'El titular alternativo no trae texto.' );
		}

		return $datos['tituloAlternativo'];
	}
}
