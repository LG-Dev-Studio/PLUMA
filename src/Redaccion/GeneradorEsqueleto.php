<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Investigacion\Expediente;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PropositoLenguaje;

/**
 * Paso 4 del Algoritmo de Decisión Editorial (Libro Cap. 5.5): arquitectura
 * argumental de la pieza a partir de la tesis ganadora. Nunca calcada de una
 * fuente — gancho → hechos esenciales con atribución → 2–4 movimientos →
 * contraargumento reconocido → remate.
 */
final class GeneradorEsqueleto {

	private const MAX_TOKENS_RESPUESTA = 900;
	private const MINIMO_MOVIMIENTOS   = 2;
	private const MAXIMO_MOVIMIENTOS   = 4;

	public function __construct( private readonly LenguajeInterface $proveedor ) {
	}

	/**
	 * @throws DecisionEditorialException si la respuesta no trae el esqueleto completo o con un número de movimientos fuera de rango.
	 * @throws \Pluma\Proveedores\ProveedorLenguajeException
	 */
	public function generar(
		Expediente $expediente,
		CandidatoTesis $tesisElegida,
		Tono $tonoDominante,
		Tono $tonoApoyo,
		?string $casoEnContraARespetar = null
	): EsqueletoPieza {
		$bloques = array(
			'Construye la arquitectura argumental (esqueleto) de una pieza periodística a partir de la tesis elegida, nunca calcada de una fuente del expediente.',
			"Tono dominante: {$tonoDominante->value}. Tono de apoyo (para el remate): {$tonoApoyo->value}.",
			'Estructura obligatoria: gancho de apertura; hechos esenciales con atribución explícita a sus fuentes (25-35% del texto final); entre 2 y 4 movimientos argumentales que desarrollen la tesis con datos del expediente; un contraargumento reconocido y respondido (no ignorado); un remate en el tono de apoyo.',
		);

		if ( null !== $casoEnContraARespetar ) {
			// Nivel Tres O.1: la Prueba de Falseabilidad encontró un caso en contra
			// comparable en fuerza — el "contraargumentoReconocido" no puede ser un
			// párrafo de cortesía, debe cargar el mismo peso argumental que la tesis.
			$bloques[] = 'ADVERTENCIA — tensión de falseabilidad: el propio sistema encontró este caso en contra, comparable en fuerza a la tesis: '
				. "\"{$casoEnContraARespetar}\". El campo \"contraargumentoReconocido\" DEBE desarrollar este caso específico con el mismo peso argumental que la tesis, no como concesión menor.";
		}

		$bloques[] = 'Responde ÚNICAMENTE con un objeto JSON de esta forma exacta:';
		$bloques[] = '{"gancho": string, "hechosEsencialesConAtribucion": string, "movimientosArgumentales": [string, ...] (entre 2 y 4 elementos), "contraargumentoReconocido": string, "remate": string}';

		$directrices = implode( "\n", $bloques );

		$material = FormateadorExpediente::comoTexto( $expediente ) . "\n\nTesis elegida: " . $tesisElegida->tesis;

		$peticion  = new PeticionLenguaje( PropositoLenguaje::Angulos, $directrices, $material, self::MAX_TOKENS_RESPUESTA );
		$respuesta = $this->proveedor->completar( $peticion );
		VerificadorTruncamiento::asegurar( $respuesta );
		$datos = ExtractorJsonLlm::extraer( $respuesta->contenido );

		if (
			! isset( $datos['gancho'], $datos['hechosEsencialesConAtribucion'], $datos['movimientosArgumentales'], $datos['contraargumentoReconocido'], $datos['remate'] )
			|| ! is_string( $datos['gancho'] )
			|| ! is_string( $datos['hechosEsencialesConAtribucion'] )
			|| ! is_array( $datos['movimientosArgumentales'] )
			|| ! is_string( $datos['contraargumentoReconocido'] )
			|| ! is_string( $datos['remate'] )
		) {
			throw new DecisionEditorialException( 'El esqueleto de la pieza no trae todos los bloques esperados.' );
		}

		$movimientos = array_values( array_filter( $datos['movimientosArgumentales'], static fn ( mixed $m ): bool => is_string( $m ) ) );

		if ( count( $movimientos ) < self::MINIMO_MOVIMIENTOS || count( $movimientos ) > self::MAXIMO_MOVIMIENTOS ) {
			$mensaje = sprintf( 'El esqueleto debe tener entre %d y %d movimientos argumentales; el proveedor devolvió %d.', self::MINIMO_MOVIMIENTOS, self::MAXIMO_MOVIMIENTOS, count( $movimientos ) );

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new DecisionEditorialException( $mensaje );
		}

		return new EsqueletoPieza(
			$datos['gancho'],
			$datos['hechosEsencialesConAtribucion'],
			$movimientos,
			$datos['contraargumentoReconocido'],
			$datos['remate']
		);
	}
}
