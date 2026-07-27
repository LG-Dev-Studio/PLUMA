<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Investigacion\Expediente;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PropositoLenguaje;

/**
 * Nivel Tres O.1, Fase 3.5 del Algoritmo de Decisión Editorial: entre la
 * selección de ángulo (Paso 3) y la arquitectura de la pieza (Paso 4), una
 * pasada adversarial acotada — "usando exclusivamente el expediente,
 * construye el caso más fuerte posible en contra de esta tesis exacta". No
 * es el contraargumento cortés que el Paso 4 ya contempla dentro de una
 * pieza que de todos modos defiende la tesis: es un intento genuino, con
 * instrucción adversarial explícita, de derrotarla.
 */
final class VerificadorFalseabilidad {

	public const OPCION_UMBRAL_REGRESO = 'pluma_umbral_regreso_falseabilidad';

	private const MAX_TOKENS_RESPUESTA   = 500;
	private const UMBRAL_REGRESO_DEFECTO = 75.0;

	public function __construct( private readonly LenguajeInterface $proveedor ) {
	}

	/**
	 * Nivel Tres O.1: si `ResultadoFalseabilidad::$fuerzaSustento` supera
	 * este umbral, la Pieza vuelve al Paso 3 a reevaluar entre los
	 * candidatos restantes — el caso en contra domina claramente, no basta
	 * con registrar la tensión.
	 */
	public function umbralRegreso(): float {
		$valor = get_option( self::OPCION_UMBRAL_REGRESO, self::UMBRAL_REGRESO_DEFECTO );

		return is_numeric( $valor ) ? (float) $valor : self::UMBRAL_REGRESO_DEFECTO;
	}

	/**
	 * @throws DecisionEditorialException si la respuesta no trae el formato esperado, o llegó truncada.
	 * @throws \Pluma\Proveedores\ProveedorLenguajeException
	 */
	public function evaluar( Expediente $expediente, CandidatoTesis $tesisGanadora ): ResultadoFalseabilidad {
		$directrices = implode(
			"\n",
			array(
				'Eres un fiscal, no un abogado defensor: tu único trabajo es intentar derrotar la tesis exacta indicada abajo, usando EXCLUSIVAMENTE el expediente adjunto.',
				'No escribas un contraargumento cortés que reconozca la tesis y la deje intacta — construye el caso más fuerte posible EN CONTRA, un intento genuino de derrotarla.',
				"Tesis a derrotar: {$tesisGanadora->tesis}",
				'Puntúa la fuerza de tu propio caso en contra de 0 a 100 según SUSTENTO VERIFICABLE en el expediente, no por elocuencia: 0 si el expediente no ofrece ningún dato que contradiga o debilite la tesis; 100 si el propio expediente la contradice de forma directa y verificada.',
				'Responde ÚNICAMENTE con un objeto JSON de esta forma exacta:',
				'{"casoEnContra": string, "fuerzaSustento": integer}',
			)
		);

		$peticion  = new PeticionLenguaje( PropositoLenguaje::Falsear, $directrices, FormateadorExpediente::comoTexto( $expediente ), self::MAX_TOKENS_RESPUESTA );
		$respuesta = $this->proveedor->completar( $peticion );
		VerificadorTruncamiento::asegurar( $respuesta );
		$datos = ExtractorJsonLlm::extraer( $respuesta->contenido );

		if (
			! isset( $datos['casoEnContra'], $datos['fuerzaSustento'] )
			|| ! is_string( $datos['casoEnContra'] )
			|| ! is_numeric( $datos['fuerzaSustento'] )
		) {
			throw new DecisionEditorialException( 'La Prueba de Falseabilidad no devolvió el formato esperado.' );
		}

		return new ResultadoFalseabilidad(
			$datos['casoEnContra'],
			max( 0.0, min( 100.0, (float) $datos['fuerzaSustento'] ) )
		);
	}
}
