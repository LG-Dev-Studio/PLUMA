<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Datos\RepositorioBorradoresInterface;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Kernel\RelojInterface;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PropositoLenguaje;

/**
 * Redacción en dos pasadas + autocrítica (Libro Cap. 5.6): el redactor
 * escribe según la Ficha de Decisión Editorial y el esqueleto, con la voz del
 * periodista; el Corrector Interno ataca el borrador. Máximo
 * {@see self::MAXIMO_CICLOS} ciclos de reescritura — al agotarlos sin
 * aprobación, la pieza se marca RETENIDA, jamás se publica "lo menos malo".
 *
 * Cada ciclo (aprobado o no) queda registrado en `pluma_borradores` — el
 * historial de revisión completo (Libro Cap. 11).
 */
final class RedactorSintetico {

	private const MAXIMO_CICLOS        = 2;
	private const MAX_TOKENS_REDACCION = 2400;

	public function __construct(
		private readonly LenguajeInterface $proveedor,
		private readonly CompiladorDirectrices $compiladorDirectrices,
		private readonly CorrectorInterno $corrector,
		private readonly GeneradorBloqueEditor $generadorBloqueEditor,
		private readonly AvisoTransparenciaIa $avisoTransparenciaIa,
		private readonly RepositorioBorradoresInterface $repoBorradores,
		private readonly RelojInterface $reloj,
	) {
	}

	/**
	 * @throws DecisionEditorialException si una respuesta del proveedor no trae el formato esperado o llegó truncada.
	 * @throws \Pluma\Proveedores\ProveedorLenguajeException
	 */
	public function redactar( int $piezaId, Periodista $periodista, Expediente $expediente, FichaDecisionEditorial $ficha ): ResultadoRedaccion {
		$nivelSatira = $periodista->conductaActual->matrizTonos->paraTipo( $ficha->clasificacion->tipoNoticia )->nivelSatira;
		$directrices = $this->compiladorDirectrices->compilar( $periodista, $ficha->tonoDominante, $ficha->tonoApoyo, $nivelSatira );

		$anotacionesPrevias = null;
		$borradorPrevio     = null;

		for ( $ciclo = 1; $ciclo <= self::MAXIMO_CICLOS; $ciclo++ ) {
			$borradorPrevio = $this->redactarPasada( $directrices, $expediente, $ficha, $anotacionesPrevias, $borradorPrevio );
			$anotaciones    = $this->corrector->revisar( $periodista, $expediente, $ficha, $borradorPrevio['titulo'], $borradorPrevio['cuerpo'] );
			$aprobado       = $this->corrector->aprobado( $anotaciones );

			$this->repoBorradores->crear(
				$piezaId,
				$ciclo,
				$borradorPrevio['titulo'] . "\n\n" . $borradorPrevio['cuerpo'],
				$anotaciones,
				$aprobado,
				$this->reloj->ahora()
			);

			if ( $aprobado ) {
				$bloqueEditor = $this->generadorBloqueEditor->generar( $periodista, $ficha->clasificacion, $ficha->tesisElegida()->tesis );

				return new ResultadoRedaccion(
					$borradorPrevio['titulo'],
					$this->ensamblarHtml( $borradorPrevio['cuerpo'], $bloqueEditor, $expediente, $periodista->nombre ),
					false,
					null,
					$ciclo
				);
			}

			$anotacionesPrevias = $anotaciones;
		}

		return new ResultadoRedaccion(
			'',
			'',
			true,
			sprintf( 'El Corrector Interno no aprobó la pieza tras %d ciclos de revisión.', self::MAXIMO_CICLOS ),
			self::MAXIMO_CICLOS
		);
	}

	/**
	 * @param list<AnotacionCorrector>|null $anotacionesPrevias
	 * @param array{titulo: string, cuerpo: string}|null $borradorPrevio
	 * @return array{titulo: string, cuerpo: string}
	 */
	private function redactarPasada(
		string $directrices,
		Expediente $expediente,
		FichaDecisionEditorial $ficha,
		?array $anotacionesPrevias,
		?array $borradorPrevio
	): array {
		$esqueleto   = $ficha->esqueleto;
		$instruccion = implode(
			"\n",
			array(
				'Redacta la pieza completa siguiendo esta arquitectura argumental (nunca calcada de una fuente):',
				'1. Gancho: ' . $esqueleto->gancho,
				'2. Hechos esenciales con atribución explícita a sus fuentes (25-35% del texto): ' . $esqueleto->hechosEsencialesConAtribucion,
				'3. Desarrollo de la tesis en estos movimientos: ' . implode( ' → ', $esqueleto->movimientosArgumentales ),
				'4. Contraargumento reconocido y respondido (no ignorado): ' . $esqueleto->contraargumentoReconocido,
				'5. Remate: ' . $esqueleto->remate,
				'Tesis a defender: ' . $ficha->tesisElegida()->tesis,
				'REGLA DE ORO CONTRA LA ALUCINACIÓN: no puedes afirmar nada que no exista en el expediente adjunto.',
				'Responde ÚNICAMENTE con un objeto JSON de esta forma exacta: {"titulo": string, "cuerpo": string} — "cuerpo" con párrafos separados por doble salto de línea.',
			)
		);

		if ( null !== $anotacionesPrevias && null !== $borradorPrevio ) {
			$fallos = array_values( array_filter( $anotacionesPrevias, static fn ( AnotacionCorrector $a ): bool => ! $a->aprobado ) );
			$fallos = self::ordenadosPorPrioridadDeReparacion( $fallos );

			$instruccion .= "\n\nEsta es una REVISIÓN: el Corrector Interno encontró estos problemas en el borrador anterior. Corrígelos en este orden, explícitamente, no los ignores:\n"
				. implode( "\n", array_map( static fn ( AnotacionCorrector $a ): string => "- {$a->detalle}", $fallos ) )
				. "\n\nBorrador anterior:\nTítulo: {$borradorPrevio['titulo']}\nCuerpo:\n{$borradorPrevio['cuerpo']}";
		}

		$peticion  = new PeticionLenguaje( PropositoLenguaje::Redactar, $directrices . "\n\n" . $instruccion, FormateadorExpediente::comoTexto( $expediente ), self::MAX_TOKENS_REDACCION );
		$respuesta = $this->proveedor->completar( $peticion );
		VerificadorTruncamiento::asegurar( $respuesta );
		$datos = ExtractorJsonLlm::extraer( $respuesta->contenido );

		if ( ! isset( $datos['titulo'], $datos['cuerpo'] ) || ! is_string( $datos['titulo'] ) || ! is_string( $datos['cuerpo'] ) ) {
			throw new DecisionEditorialException( 'La redacción no devolvió título y cuerpo.' );
		}

		return array(
			'titulo' => $datos['titulo'],
			'cuerpo' => $datos['cuerpo'],
		);
	}

	/**
	 * Nivel Dos A.6: reordena los puntos reprobados de orden de verificación
	 * (enumeración de {@see PuntoCorrector}) a orden de reparación
	 * ({@see PuntoCorrector::ordenDeReparacion()}) antes de instruir la
	 * siguiente pasada.
	 *
	 * @param list<AnotacionCorrector> $fallos
	 * @return list<AnotacionCorrector>
	 */
	private static function ordenadosPorPrioridadDeReparacion( array $fallos ): array {
		$prioridad = PuntoCorrector::ordenDeReparacion();

		usort(
			$fallos,
			static fn ( AnotacionCorrector $a, AnotacionCorrector $b ): int =>
				array_search( $a->punto, $prioridad, true ) <=> array_search( $b->punto, $prioridad, true )
		);

		return $fallos;
	}

	private function ensamblarHtml( string $cuerpo, BloqueEditor $bloqueEditor, Expediente $expediente, string $nombrePeriodista ): string {
		$parrafos = array_values(
			array_filter(
				array_map( 'trim', explode( "\n\n", $cuerpo ) ),
				static fn ( string $parrafo ): bool => '' !== $parrafo
			)
		);

		$html = implode( '', array_map( static fn ( string $parrafo ): string => '<p>' . esc_html( $parrafo ) . '</p>', $parrafos ) );

		return $html . $bloqueEditor->comoHtml() . $this->fuentesComoHtml( $expediente ) . $this->avisoTransparenciaIa->comoHtml( $nombrePeriodista );
	}

	/**
	 * GOVERNANCE §2.5: "toda fuente usada se cita y enlaza" — mecánico y
	 * garantizado, no delegado a que el proveedor de lenguaje recuerde
	 * enlazar dentro de la prosa.
	 */
	private function fuentesComoHtml( Expediente $expediente ): string {
		if ( array() === $expediente->hechos ) {
			return '';
		}

		$items = array_map(
			static function ( HechoFuente $hecho ): string {
				$host = wp_parse_url( $hecho->url, PHP_URL_HOST );
				$host = is_string( $host ) ? $host : $hecho->url;

				return sprintf( '<li><a href="%s">%s</a></li>', esc_url( $hecho->url ), esc_html( $host ) );
			},
			$expediente->hechos
		);

		return '<p><strong>' . esc_html__( 'Fuentes', 'pluma-engine' ) . '</strong></p><ul>' . implode( '', $items ) . '</ul>';
	}
}
