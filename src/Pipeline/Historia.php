<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

use DateTimeImmutable;

/**
 * Nivel Cuatro U.1 — la entidad Historia: agrupa todas las Piezas de una
 * misma saga (detectada por la huella semántica que `ComparadorHistorias`
 * ya calcula, Libro Cap. 3.4), con ciclo de vida propio independiente del
 * de cada Pieza.
 */
final readonly class Historia {

	/**
	 * @param list<int> $piezaIds piezas asociadas, en orden cronológico de creación
	 */
	public function __construct(
		public int $id,
		public string $titulo,
		public EstadoHistoria $estado,
		public ?int $periodistaTitularId,
		public array $piezaIds,
		public DateTimeImmutable $creadaEn,
		public DateTimeImmutable $actualizadaEn,
	) {
	}
}
