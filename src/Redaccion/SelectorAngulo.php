<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Investigacion\Expediente;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PropositoLenguaje;

/**
 * Paso 3 del Algoritmo de Decisión Editorial (Libro Cap. 5.5), endurecido por
 * Nivel Tres K.1: genera 3–5 candidatos de tesis y aplica el sustento en
 * hechos verificados como piso ELIMINATORIO (`UMBRAL_SUSTENTO_MINIMO`) antes
 * de competir — nunca se diluye en el promedio de `CandidatoTesis::puntuacionTotal()`.
 *
 * pl-periodistas §3 "memoria antes de tesis": las posturas previas del
 * periodista sobre este tema viajan como parte del material — si una tesis
 * las contradice, la directriz exige que el propio texto de la tesis lo
 * reconozca explícitamente. Silenciar la contradicción es un bug crítico
 * cubierto por `tests/Invariantes`.
 */
final class SelectorAngulo {

	private const MAX_TOKENS_RESPUESTA   = 1200;
	private const UMBRAL_SUSTENTO_MINIMO = 40.0;

	public function __construct( private readonly LenguajeInterface $proveedor ) {
	}

	/**
	 * @param list<EntradaMemoria>    $posturasPrevias    más recientes primero (ver `RepositorioMemoriaEditorialInterface::obtenerPosturasPorTema()`)
	 * @param list<PosturaColectiva>  $posturasColectivas más recientes primero, de OTROS periodistas — memoria colectiva del sitio (Nivel Dos E.2)
	 * @return list<CandidatoTesis> 3–5 candidatos que superaron el umbral de sustento
	 *
	 * @throws DecisionEditorialException si ningún candidato supera el umbral, o la respuesta no trae el formato esperado.
	 * @throws \Pluma\Proveedores\ProveedorLenguajeException
	 */
	public function generarCandidatos(
		Periodista $periodista,
		Expediente $expediente,
		ClasificacionNoticia $clasificacion,
		array $posturasPrevias,
		array $posturasColectivas = array()
	): array {
		$directrices = implode(
			"\n",
			array(
				"Eres el estratega de ángulos de {$periodista->nombre}, cuya línea editorial es: {$periodista->conductaActual->reglas->lineaEditorial}",
				'A partir del expediente, propone entre 3 y 5 candidatos de tesis (ángulos argumentales distintos entre sí).',
				'IMPORTANTE — memoria antes de tesis: si alguna postura previa listada abajo entra en contradicción con un candidato, ese candidato DEBE incluir, en su propio texto, un reconocimiento explícito de la contradicción (ej. "hace tres meses defendí lo contrario; estos datos me obligan a rectificar"). Silenciar una contradicción no está permitido.',
				'IMPORTANTE — memoria colectiva del sitio: si alguna postura colectiva listada abajo (de OTRO periodista de esta redacción) entra en contradicción con un candidato, ese candidato DEBE reconocerlo explícitamente, con la atribución exacta que se indique para cada una (individual si ese periodista sigue activo, de sitio/redacción si ya está jubilado) — nunca como si fuera la propia postura anterior del periodista actual.',
				'Para cada candidato, puntúa 0-100 en cuatro ejes: originalidad frente a la cobertura existente, compatibilidad con la línea editorial, sustento en los hechos VERIFICADOS del expediente (0 si la tesis no está respaldada por ningún hecho del expediente), y potencial de conversación.',
				'Responde ÚNICAMENTE con un objeto JSON de esta forma exacta:',
				'{"candidatos": [{"tesis": string, "puntuacionOriginalidad": integer, "puntuacionCompatibilidadLinea": integer, "puntuacionSustento": integer, "puntuacionConversacional": integer}, ...]}',
			)
		);

		$material = FormateadorExpediente::comoTexto( $expediente ) . "\n\n"
			. $this->formatearPosturasPrevias( $posturasPrevias ) . "\n\n"
			. $this->formatearPosturasColectivas( $posturasColectivas );

		$peticion  = new PeticionLenguaje( PropositoLenguaje::Angulos, $directrices, $material, self::MAX_TOKENS_RESPUESTA );
		$respuesta = $this->proveedor->completar( $peticion );
		VerificadorTruncamiento::asegurar( $respuesta );
		$datos = ExtractorJsonLlm::extraer( $respuesta->contenido );

		if ( ! isset( $datos['candidatos'] ) || ! is_array( $datos['candidatos'] ) ) {
			throw new DecisionEditorialException( 'La selección de ángulo no trajo una lista de candidatos.' );
		}

		$candidatos = array();

		foreach ( $datos['candidatos'] as $candidatoCrudo ) {
			$candidato = $this->aCandidatoValido( $candidatoCrudo );

			if ( null !== $candidato && $candidato->puntuacionSustento >= self::UMBRAL_SUSTENTO_MINIMO ) {
				$candidatos[] = $candidato;
			}
		}

		if ( array() === $candidatos ) {
			throw new DecisionEditorialException(
				'Ningún candidato de tesis superó el umbral mínimo de sustento en hechos verificados del expediente.'
			);
		}

		return $candidatos;
	}

	/**
	 * Índice del candidato con mayor `puntuacionTotal()` (Libro Cap. 5.5:
	 * "la tesis ganadora... quedan escritos en la Ficha de Decisión Editorial").
	 *
	 * @param list<CandidatoTesis> $candidatos
	 */
	public function elegirGanadora( array $candidatos ): int {
		$mejorIndice     = 0;
		$mejorPuntuacion = -INF;

		foreach ( $candidatos as $indice => $candidato ) {
			if ( $candidato->puntuacionTotal() > $mejorPuntuacion ) {
				$mejorPuntuacion = $candidato->puntuacionTotal();
				$mejorIndice     = $indice;
			}
		}

		return $mejorIndice;
	}

	private function aCandidatoValido( mixed $candidatoCrudo ): ?CandidatoTesis {
		if (
			! is_array( $candidatoCrudo )
			|| ! isset(
				$candidatoCrudo['tesis'],
				$candidatoCrudo['puntuacionOriginalidad'],
				$candidatoCrudo['puntuacionCompatibilidadLinea'],
				$candidatoCrudo['puntuacionSustento'],
				$candidatoCrudo['puntuacionConversacional']
			)
			|| ! is_string( $candidatoCrudo['tesis'] )
			|| ! is_numeric( $candidatoCrudo['puntuacionOriginalidad'] )
			|| ! is_numeric( $candidatoCrudo['puntuacionCompatibilidadLinea'] )
			|| ! is_numeric( $candidatoCrudo['puntuacionSustento'] )
			|| ! is_numeric( $candidatoCrudo['puntuacionConversacional'] )
		) {
			return null;
		}

		return new CandidatoTesis(
			$candidatoCrudo['tesis'],
			max( 0.0, min( 100.0, (float) $candidatoCrudo['puntuacionOriginalidad'] ) ),
			max( 0.0, min( 100.0, (float) $candidatoCrudo['puntuacionCompatibilidadLinea'] ) ),
			max( 0.0, min( 100.0, (float) $candidatoCrudo['puntuacionSustento'] ) ),
			max( 0.0, min( 100.0, (float) $candidatoCrudo['puntuacionConversacional'] ) )
		);
	}

	/**
	 * @param list<EntradaMemoria> $posturasPrevias
	 */
	private function formatearPosturasPrevias( array $posturasPrevias ): string {
		if ( array() === $posturasPrevias ) {
			return 'El periodista no tiene posturas previas registradas sobre este tema.';
		}

		$lineas = array( 'Posturas previas de este periodista sobre este tema (memoria editorial):' );

		foreach ( $posturasPrevias as $entrada ) {
			$postura  = $entrada->contenido['postura'] ?? null;
			$lineas[] = '- ' . ( is_string( $postura ) ? $postura : '(postura sin texto)' );
		}

		return implode( "\n", $lineas );
	}

	/**
	 * @param list<PosturaColectiva> $posturasColectivas
	 */
	private function formatearPosturasColectivas( array $posturasColectivas ): string {
		if ( array() === $posturasColectivas ) {
			return 'Ningún otro periodista de esta redacción tiene posturas previas registradas sobre este tema.';
		}

		$lineas = array( 'Posturas previas de OTROS periodistas de esta redacción sobre este tema (memoria colectiva del sitio):' );

		foreach ( $posturasColectivas as $postura ) {
			$texto = $postura->entrada->contenido['postura'] ?? null;
			$texto = is_string( $texto ) ? $texto : '(postura sin texto)';

			$atribucion = $postura->periodistaActivo
				? "un colega de esta redacción, {$postura->periodistaNombre}, sostuvo"
				: 'esta redacción sostuvo antes (a través de un periodista que ya no forma parte del banco)';

			$lineas[] = "- {$atribucion}: {$texto}";
		}

		return implode( "\n", $lineas );
	}
}
