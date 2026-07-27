<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

/**
 * Expediente de investigación de una Pieza (Libro Cap. 4): el redactor —
 * mecánico en esta Etapa, sintético desde la Etapa 2 — solo conoce lo que
 * está aquí (GOVERNANCE §2.4, anti-alucinación).
 *
 * Nivel Dos B.4 + Nivel Tres O.2: `huecosDetectados` son las dimensiones de
 * encuadre ausentes de la cobertura recolectada, con sustento propio en el
 * expediente Y relevancia causal verificada con los actores/hechos
 * concretos de esta tendencia — no una lista genérica de dimensiones no
 * cubiertas.
 */
final readonly class Expediente {

	/**
	 * @param list<HechoFuente> $hechos
	 * @param list<DimensionEncuadre> $huecosDetectados
	 */
	public function __construct(
		public string $tendenciaOrigen,
		public array $hechos,
		public array $huecosDetectados = array(),
	) {
	}

	/**
	 * @return array{tendenciaOrigen: string, hechos: list<array{extracto: string, url: string, fecha: string, nivel: string, procedenciaDeclaracion: string, corroboracionAudiovisual: string}>, huecosDetectados: list<string>}
	 */
	public function aArray(): array {
		return array(
			'tendenciaOrigen'  => $this->tendenciaOrigen,
			'hechos'           => array_map( static fn ( HechoFuente $h ): array => $h->aArray(), $this->hechos ),
			'huecosDetectados' => array_map( static fn ( DimensionEncuadre $d ): string => $d->value, $this->huecosDetectados ),
		);
	}

	/**
	 * @param array{tendenciaOrigen: string, hechos: list<array{extracto: string, url: string, fecha: string, nivel: string, procedenciaDeclaracion?: string, corroboracionAudiovisual?: string}>, huecosDetectados?: list<string>} $datos
	 */
	public static function desdeArray( array $datos ): self {
		return new self(
			$datos['tendenciaOrigen'],
			array_map( static fn ( array $h ): HechoFuente => HechoFuente::desdeArray( $h ), $datos['hechos'] ),
			array_map( static fn ( string $d ): DimensionEncuadre => DimensionEncuadre::from( $d ), $datos['huecosDetectados'] ?? array() ),
		);
	}
}
