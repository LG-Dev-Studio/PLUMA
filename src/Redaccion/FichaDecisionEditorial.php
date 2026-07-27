<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use DateTimeImmutable;
use RuntimeException;

/**
 * Ficha de Decisión Editorial (Libro Cap. 5.5, pl-periodistas §Contratos
 * innegociables 7): periodista+versión, clasificación, candidatos de tesis
 * puntuados, tesis elegida, tonos, esqueleto. Trazabilidad pura — sin ficha
 * completa no hay paso a redacción.
 *
 * Nivel Tres O.1: `tensionFalseabilidad` registra explícitamente el caso en
 * contra cuando la Fase 3.5 lo encontró comparable en fuerza a la tesis
 * ganadora (sin llegar al umbral de retorno al Paso 3) — nunca se descarta
 * en silencio.
 */
final readonly class FichaDecisionEditorial {

	/**
	 * @param list<CandidatoTesis> $candidatosTesis
	 */
	public function __construct(
		public int $periodistaId,
		public int $periodistaVersionId,
		public ClasificacionNoticia $clasificacion,
		public array $candidatosTesis,
		public int $indiceTesisElegida,
		public Tono $tonoDominante,
		public Tono $tonoApoyo,
		public EsqueletoPieza $esqueleto,
		public DateTimeImmutable $creadaEn,
		public ?string $tensionFalseabilidad = null,
	) {
	}

	public function tesisElegida(): CandidatoTesis {
		return $this->candidatosTesis[ $this->indiceTesisElegida ]
			?? throw new RuntimeException( 'Índice de tesis elegida fuera de rango en la Ficha de Decisión Editorial.' );
	}

	/**
	 * @return array{periodistaId: int, periodistaVersionId: int, clasificacion: array{tema: string, gravedad: int, polaridad: string, novedad: string, potencialConversacional: int, tipoNoticia: string}, candidatosTesis: list<array{tesis: string, puntuacionOriginalidad: float, puntuacionCompatibilidadLinea: float, puntuacionSustento: float, puntuacionConversacional: float}>, indiceTesisElegida: int, tonoDominante: string, tonoApoyo: string, esqueleto: array{gancho: string, hechosEsencialesConAtribucion: string, movimientosArgumentales: list<string>, contraargumentoReconocido: string, remate: string}, creadaEn: string, tensionFalseabilidad: ?string}
	 */
	public function aArray(): array {
		return array(
			'periodistaId'         => $this->periodistaId,
			'periodistaVersionId'  => $this->periodistaVersionId,
			'clasificacion'        => $this->clasificacion->aArray(),
			'candidatosTesis'      => array_map( static fn ( CandidatoTesis $c ): array => $c->aArray(), $this->candidatosTesis ),
			'indiceTesisElegida'   => $this->indiceTesisElegida,
			'tonoDominante'        => $this->tonoDominante->value,
			'tonoApoyo'            => $this->tonoApoyo->value,
			'esqueleto'            => $this->esqueleto->aArray(),
			'creadaEn'             => $this->creadaEn->format( DATE_ATOM ),
			'tensionFalseabilidad' => $this->tensionFalseabilidad,
		);
	}

	/**
	 * @param array{periodistaId: int, periodistaVersionId: int, clasificacion: array{tema: string, gravedad: int, polaridad: string, novedad: string, potencialConversacional: int, tipoNoticia: string}, candidatosTesis: list<array{tesis: string, puntuacionOriginalidad: float, puntuacionCompatibilidadLinea: float, puntuacionSustento: float, puntuacionConversacional: float}>, indiceTesisElegida: int, tonoDominante: string, tonoApoyo: string, esqueleto: array{gancho: string, hechosEsencialesConAtribucion: string, movimientosArgumentales: list<string>, contraargumentoReconocido: string, remate: string}, creadaEn: string, tensionFalseabilidad?: ?string} $datos
	 */
	public static function desdeArray( array $datos ): self {
		return new self(
			$datos['periodistaId'],
			$datos['periodistaVersionId'],
			ClasificacionNoticia::desdeArray( $datos['clasificacion'] ),
			array_map( static fn ( array $c ): CandidatoTesis => CandidatoTesis::desdeArray( $c ), $datos['candidatosTesis'] ),
			$datos['indiceTesisElegida'],
			Tono::from( $datos['tonoDominante'] ),
			Tono::from( $datos['tonoApoyo'] ),
			EsqueletoPieza::desdeArray( $datos['esqueleto'] ),
			new DateTimeImmutable( $datos['creadaEn'] ),
			$datos['tensionFalseabilidad'] ?? null
		);
	}
}
