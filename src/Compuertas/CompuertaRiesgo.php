<?php

declare(strict_types=1);

namespace Pluma\Compuertas;

use Pluma\Investigacion\Expediente;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PropositoLenguaje;
use Pluma\Redaccion\ClasificacionNoticia;
use Pluma\Redaccion\TipoNoticia;

/**
 * Compuerta de Riesgo (Libro Cap. 8.2, endurecida por Nivel Tres M.1/N.1):
 * clasificadores en cascada que degradan el modo de publicación o retienen
 * para revisión humana.
 *
 * "Tragedia" se hereda de `ClasificacionNoticia` (Paso 1 del Algoritmo de
 * Decisión Editorial, Etapa 2) — el sistema no le pregunta al proveedor de
 * lenguaje algo que ya sabe. Los demás ejes (menores, salud, violencia,
 * difamación, hechos disputados, tema regulado, derecho de réplica previa)
 * exigen juicio semántico sobre el expediente y el texto final: una sola
 * llamada consolidada al proveedor de lenguaje, por eficiencia de coste
 * (mismo patrón que `CorrectorInterno` en la Etapa 2).
 *
 * M.1 (Nivel 1, mínimo viable): en vez de que "el Investigador" verifique la
 * postura del señalado con una llamada aparte, se reutiliza el mismo
 * material (hechos del expediente + texto final) ya enviado a esta misma
 * llamada — misma eficiencia de coste, sin infraestructura nueva en
 * `Pluma\Investigacion`.
 */
final class CompuertaRiesgo {

	private const MAX_TOKENS_RESPUESTA = 500;

	public const OPCION_REGIMEN_RESPONSABILIDAD = 'pluma_regimen_responsabilidad';
	private const REGIMEN_DEFECTO               = RegimenResponsabilidad::Civil;

	public function __construct( private readonly LenguajeInterface $proveedor ) {
	}

	/**
	 * @throws CompuertaException si la respuesta del proveedor no trae el formato esperado o llegó truncada.
	 * @throws \Pluma\Proveedores\ProveedorLenguajeException
	 */
	public function evaluar( Expediente $expediente, string $textoFinal, ClasificacionNoticia $clasificacion ): DiagnosticoRiesgo {
		$directrices = implode(
			"\n",
			array(
				'Eres el evaluador de riesgo editorial de un medio. Evalúa el expediente y el texto final de una pieza periodística en estos puntos.',
				'"implicaMenores": ¿el contenido involucra a menores de edad de forma central (no una mención de paso)?',
				'"implicaSalud": ¿trata una condición de salud/enfermedad de una persona identificable, o un tema de salud que exige sensibilidad (no una simple mención de política pública de salud)?',
				'"implicaViolencia": ¿describe violencia real explícita hacia personas identificables?',
				'"riesgoDifamacion": ¿la pieza afirma, como HECHO y no como opinión, algo negativo sobre una persona identificable sin que el expediente respalde esa afirmación como "verificado" con fuentes independientes?',
				'"hechosDisputadosSinSenalar": ¿el expediente marca algún hecho como "disputado" y la pieza lo presenta como consenso sin señalar la disputa?',
				'"temaRegulado": si la pieza da consejos de salud, financieros o legales que normalmente exigirían un descargo regulatorio, responde "salud", "financiero" o "legal"; si no aplica, responde null.',
				'"afirmacionNegativaSobrePersonaIdentificable": ¿la pieza afirma algo negativo (acusación, señalamiento, hecho desfavorable) sobre una persona u organización identificable, sin importar si esa afirmación ya está verificada o no?',
				'"posturaSenaladoAusente": SOLO relevante si "afirmacionNegativaSobrePersonaIdentificable" es verdadero — ¿ninguno de los hechos del expediente incluye una declaración, negación, o constancia de "contactado para comentar, no respondió" de la parte señalada? Si no hay afirmación negativa, responde false.',
				'Responde ÚNICAMENTE con un objeto JSON de esta forma exacta:',
				'{"implicaMenores": boolean, "implicaSalud": boolean, "implicaViolencia": boolean, "riesgoDifamacion": boolean, "detalleDifamacion": string, "hechosDisputadosSinSenalar": boolean, "temaRegulado": string|null, "afirmacionNegativaSobrePersonaIdentificable": boolean, "posturaSenaladoAusente": boolean}',
			)
		);

		$peticion  = new PeticionLenguaje( PropositoLenguaje::Clasificar, $directrices, $this->materialParaEvaluar( $expediente, $textoFinal ), self::MAX_TOKENS_RESPUESTA );
		$respuesta = $this->proveedor->completar( $peticion );
		VerificadorTruncamiento::asegurar( $respuesta );
		$datos = ExtractorJsonLlm::extraer( $respuesta->contenido );

		return $this->aDiagnostico( $datos, TipoNoticia::Tragedia === $clasificacion->tipoNoticia );
	}

	private function materialParaEvaluar( Expediente $expediente, string $textoFinal ): string {
		$lineas   = array( 'Hechos del expediente (con su estado de verificación):' );
		$lineas[] = '';

		foreach ( $expediente->hechos as $indice => $hecho ) {
			$lineas[] = sprintf( '[%d] (%s) %s', $indice + 1, $hecho->nivel->value, $hecho->extracto );
		}

		$lineas[] = '';
		$lineas[] = 'Texto final de la pieza:';
		$lineas[] = $textoFinal;

		return implode( "\n", $lineas );
	}

	/**
	 * @param array<string, mixed> $datos
	 */
	private function aDiagnostico( array $datos, bool $implicaTragedia ): DiagnosticoRiesgo {
		if (
			! isset(
				$datos['implicaMenores'],
				$datos['implicaSalud'],
				$datos['implicaViolencia'],
				$datos['riesgoDifamacion'],
				$datos['detalleDifamacion'],
				$datos['hechosDisputadosSinSenalar'],
				$datos['afirmacionNegativaSobrePersonaIdentificable'],
				$datos['posturaSenaladoAusente']
			)
			|| ! array_key_exists( 'temaRegulado', $datos )
			|| ! is_string( $datos['detalleDifamacion'] )
		) {
			throw new CompuertaException( 'La Compuerta de Riesgo no recibió el formato esperado del proveedor de lenguaje.' );
		}

		$temaRegulado = null;

		if ( null !== $datos['temaRegulado'] ) {
			if ( ! is_string( $datos['temaRegulado'] ) ) {
				throw new CompuertaException( 'La Compuerta de Riesgo recibió un temaRegulado con formato inesperado.' );
			}

			$temaRegulado = TemaRegulado::tryFrom( $datos['temaRegulado'] );

			if ( null === $temaRegulado ) {
				$mensaje = "La Compuerta de Riesgo recibió un temaRegulado desconocido: '{$datos['temaRegulado']}'.";

				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
				throw new CompuertaException( $mensaje );
			}
		}

		$afirmacionNegativa     = (bool) $datos['afirmacionNegativaSobrePersonaIdentificable'];
		$posturaSenaladoAusente = $afirmacionNegativa && (bool) $datos['posturaSenaladoAusente'];

		return new DiagnosticoRiesgo(
			$implicaTragedia,
			(bool) $datos['implicaMenores'],
			(bool) $datos['implicaSalud'],
			(bool) $datos['implicaViolencia'],
			(bool) $datos['riesgoDifamacion'],
			$datos['detalleDifamacion'],
			(bool) $datos['hechosDisputadosSinSenalar'],
			$temaRegulado,
			$afirmacionNegativa,
			$posturaSenaladoAusente,
			$afirmacionNegativa && RegimenResponsabilidad::Penal === $this->regimenConfigurado()
		);
	}

	/**
	 * Perfil de jurisdicción de fábrica (Nivel Tres N.1) — mismo patrón que
	 * `CompuertaCalidad::umbralConfigurado()`: cada Compuerta lee su propia
	 * configuración directamente.
	 */
	private function regimenConfigurado(): RegimenResponsabilidad {
		$regimen = get_option( self::OPCION_REGIMEN_RESPONSABILIDAD, self::REGIMEN_DEFECTO->value );

		return is_string( $regimen ) ? ( RegimenResponsabilidad::tryFrom( $regimen ) ?? self::REGIMEN_DEFECTO ) : self::REGIMEN_DEFECTO;
	}
}
