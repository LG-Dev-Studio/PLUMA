<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Investigacion\Expediente;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PropositoLenguaje;

/**
 * Corrector Interno (Libro Cap. 5.6): agente separado con un solo trabajo,
 * atacar el borrador con la lista de verificación de 6 puntos. Los puntos 3
 * (solapamiento n-grama) y 4 (voz) son deterministas — no necesitan
 * proveedor de lenguaje. Los otros 4 se evalúan en una sola llamada
 * (`PropositoLenguaje::Corregir`) por eficiencia de coste.
 *
 * Nivel Tres J.3: antes de esa llamada, {@see VerificadorTrazabilidadDeterminista}
 * (capa no generativa, embeddings) señala qué frases del borrador no
 * encontraron respaldo aparente en el expediente, y {@see VerificadorContradiccionNli}
 * (NCP-3 Porción 1, `ADR 0021`, capa no generativa, NLI real vía T3) señala
 * qué frases contradicen directamente un extracto — ambas priorizan y
 * abaratan el punto "hechos", nunca lo sustituyen.
 *
 * **Decisión de arquitectura confirmada por el propietario (`ADR 0021`)**: a
 * partir de esta porción el cerebro remoto (T3) es OBLIGATORIO para que
 * `revisar()` funcione — si no está configurado, `VerificadorContradiccionNli`
 * lanza `ProveedorLenguajeException` y se propaga sin capturar, igual que
 * cualquier otro fallo real del proveedor de lenguaje. No es un descuido.
 *
 * "Jamás aprobar lo menos malo" (pl-periodistas §5): {@see aprobado()} exige
 * los 6 puntos, no una mayoría.
 */
final class CorrectorInterno {

	private const MAX_TOKENS_RESPUESTA = 900;

	public function __construct(
		private readonly LenguajeInterface $proveedor,
		private readonly VerificadorVoz $verificadorVoz,
		private readonly VerificadorNGramas $verificadorNGramas,
		private readonly VerificadorTrazabilidadDeterminista $verificadorTrazabilidad,
		private readonly VerificadorContradiccionNli $verificadorContradiccion,
	) {
	}

	/**
	 * @return list<AnotacionCorrector> exactamente 6, en el orden de `PuntoCorrector`
	 *
	 * @throws DecisionEditorialException si la respuesta del proveedor no trae los 4 puntos evaluables por lenguaje, o llegó truncada.
	 * @throws \Pluma\Proveedores\ProveedorLenguajeException
	 */
	public function revisar(
		Periodista $periodista,
		Expediente $expediente,
		FichaDecisionEditorial $ficha,
		string $titulo,
		string $cuerpo
	): array {
		$anotacionesLlm = $this->consultarProveedor( $periodista, $expediente, $ficha, $titulo, $cuerpo );

		return array(
			$anotacionesLlm[ PuntoCorrector::Hechos->value ],
			$anotacionesLlm[ PuntoCorrector::ProporcionInterpretativa->value ],
			$this->verificadorNGramas->verificar( $expediente, $cuerpo ),
			$this->verificadorVoz->verificar( $periodista, $cuerpo ),
			$anotacionesLlm[ PuntoCorrector::TitularHonesto->value ],
			$anotacionesLlm[ PuntoCorrector::MatrizYLineasRojas->value ],
		);
	}

	/**
	 * @param list<AnotacionCorrector> $anotaciones
	 */
	public function aprobado( array $anotaciones ): bool {
		foreach ( $anotaciones as $anotacion ) {
			if ( ! $anotacion->aprobado ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return array<string, AnotacionCorrector> indexado por `PuntoCorrector::value`
	 */
	private function consultarProveedor(
		Periodista $periodista,
		Expediente $expediente,
		FichaDecisionEditorial $ficha,
		string $titulo,
		string $cuerpo
	): array {
		$reglas     = $periodista->conductaActual->reglas;
		$filaMatriz = $periodista->conductaActual->matrizTonos->paraTipo( $ficha->clasificacion->tipoNoticia );
		$bloques    = array(
			'Eres el Corrector Interno: un agente separado del redactor, con un solo trabajo, atacar el borrador. Jamás apruebes "lo menos malo".',
			'Evalúa EXACTAMENTE estos 4 puntos contra el expediente y el borrador, cada uno con {"aprobado": boolean, "detalle": string}:',
			'"hechos": ¿Cada hecho del texto existe en el expediente con el estado correcto (verificado/atribuido)? Regla de oro: el redactor no puede saber nada que el expediente no sepa. Reprueba si hay una sola afirmación sin respaldo.',
			'"proporcion_interpretativa": ¿La proporción interpretación/relato cumple el mínimo del 60% (la mayoría del texto interpreta y argumenta, no solo narra los hechos crudos)?',
			'"titular_honesto": ¿El titular promete exactamente lo que la pieza cumple (sin clickbait)?',
			sprintf(
				'"matriz_y_lineas_rojas": ¿La pieza respeta el tono esperado (dominante: %s, apoyo: %s) y las líneas rojas personales del periodista (%s)?',
				$filaMatriz->tonoDominante->value,
				$filaMatriz->tonoApoyo->value,
				array() !== $reglas->lineasRojas ? implode( '; ', $reglas->lineasRojas ) : 'ninguna configurada'
			),
			'Responde ÚNICAMENTE con un objeto JSON de esta forma exacta:',
			'{"hechos": {"aprobado": boolean, "detalle": string}, "proporcion_interpretativa": {"aprobado": boolean, "detalle": string}, "titular_honesto": {"aprobado": boolean, "detalle": string}, "matriz_y_lineas_rojas": {"aprobado": boolean, "detalle": string}}',
		);

		$unidadesSinRespaldo = $this->verificadorTrazabilidad->unidadesSinRespaldoAparente( $expediente, $cuerpo );

		if ( array() !== $unidadesSinRespaldo ) {
			// Nivel Tres J.3: capa determinista (no generativa), previa a esta llamada — prioriza
			// el punto "hechos", nunca lo sustituye (puede ser un falso positivo de paráfrasis legítima).
			$bloques[] = 'ALERTA DE TRAZABILIDAD DETERMINISTA para el punto "hechos" (verificación por embeddings, revísala con tu propio criterio semántico, puede ser un falso positivo): '
				. 'estas frases del borrador no encontraron un extracto suficientemente similar en el expediente: '
				. implode( ' | ', $unidadesSinRespaldo );
		}

		$unidadesContradictorias = $this->verificadorContradiccion->unidadesQueContradicenElExpediente( $expediente, $cuerpo );

		if ( array() !== $unidadesContradictorias ) {
			// NCP-3 Porción 1 (`ADR 0021`): capa determinista (no generativa, NLI real vía T3), previa a
			// esta llamada — prioriza el punto "hechos", nunca lo sustituye (puede ser un falso positivo).
			$bloques[] = 'ALERTA DE CONTRADICCIÓN DETERMINISTA (NLI) para el punto "hechos" (revísala con tu propio criterio semántico, puede ser un falso positivo): '
				. 'estas frases del borrador contradicen directamente un extracto del expediente según un modelo de inferencia de lenguaje natural: '
				. implode( ' | ', $unidadesContradictorias );
		}

		$directrices = implode( "\n", $bloques );

		$material = FormateadorExpediente::comoTexto( $expediente ) . "\n\nTítulo:\n{$titulo}\n\nCuerpo:\n{$cuerpo}";

		$peticion  = new PeticionLenguaje( PropositoLenguaje::Corregir, $directrices, $material, self::MAX_TOKENS_RESPUESTA );
		$respuesta = $this->proveedor->completar( $peticion );
		VerificadorTruncamiento::asegurar( $respuesta );
		$datos = ExtractorJsonLlm::extraer( $respuesta->contenido );

		$resultado = array();

		foreach ( array( 'hechos', 'proporcion_interpretativa', 'titular_honesto', 'matriz_y_lineas_rojas' ) as $punto ) {
			$bloque = $datos[ $punto ] ?? null;

			if ( ! is_array( $bloque ) || ! isset( $bloque['aprobado'], $bloque['detalle'] ) || ! is_string( $bloque['detalle'] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
				throw new DecisionEditorialException( "El Corrector Interno no devolvió el punto '{$punto}' con el formato esperado." );
			}

			$resultado[ $punto ] = new AnotacionCorrector( PuntoCorrector::from( $punto ), (bool) $bloque['aprobado'], $bloque['detalle'] );
		}

		return $resultado;
	}
}
