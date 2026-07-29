<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PropositoLenguaje;

/**
 * Nivel Cuatro W.1 — el boletín como producto del periodista: "un párrafo
 * de apertura redactado en su voz, mismo pipeline, mismas compuertas de
 * tono". Genera SOLO el párrafo de apertura — la lista de piezas recientes
 * se enhebra aparte (`Pluma\Publicacion\GestorBoletines`), esta clase nunca
 * inventa ni resume contenido de las piezas que no se le entregue.
 */
final class GeneradorBoletin {

	private const MAX_TOKENS_APERTURA = 400;

	public function __construct( private readonly LenguajeInterface $proveedor ) {
	}

	/**
	 * @param list<string> $titulosRecientes
	 *
	 * @throws DecisionEditorialException si la apertura no trae texto, o llegó truncada.
	 * @throws \Pluma\Proveedores\ProveedorLenguajeException
	 */
	public function generar( Periodista $periodista, array $titulosRecientes ): string {
		$reglas = $periodista->conductaActual->reglas;

		$directrices = implode(
			"\n",
			array(
				"Eres {$periodista->nombre}, escribiendo el párrafo de apertura del boletín semanal a tus suscriptores.",
				"Tu línea editorial: {$reglas->lineaEditorial}. Trato al lector: {$reglas->tratamientoLector->value}.",
				'El párrafo: 2-4 líneas, en primera persona, presentando el hilo común entre tus piezas recientes (o su ausencia, con honestidad) — nunca un resumen mecánico título por título, eso lo hace la plantilla del boletín, no tú.',
				'Nunca cruces tus líneas rojas: ' . implode( ', ', $reglas->lineasRojas ) . '.',
				'Responde ÚNICAMENTE con un objeto JSON de esta forma exacta: {"apertura": string}',
			)
		);

		$material = "Títulos de las piezas recientes:\n" . implode( "\n", array_map( static fn ( string $titulo ): string => '- ' . $titulo, $titulosRecientes ) );

		$peticion  = new PeticionLenguaje( PropositoLenguaje::Boletin, $directrices, $material, self::MAX_TOKENS_APERTURA );
		$respuesta = $this->proveedor->completar( $peticion );
		VerificadorTruncamiento::asegurar( $respuesta );
		$datos = ExtractorJsonLlm::extraer( $respuesta->contenido );

		if ( ! isset( $datos['apertura'] ) || ! is_string( $datos['apertura'] ) || '' === trim( $datos['apertura'] ) ) {
			throw new DecisionEditorialException( 'El párrafo de apertura del boletín no trae texto.' );
		}

		return $datos['apertura'];
	}
}
