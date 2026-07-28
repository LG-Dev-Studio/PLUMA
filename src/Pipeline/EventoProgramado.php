<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

use DateTimeImmutable;

/**
 * Nivel Cuatro V.1 — el Calendario Editorial: "la mitad del calendario
 * noticioso se conoce con semanas de anticipación". Un `EventoProgramado`
 * es la agenda de un evento previsto (fecha/ventana esperada, vertical,
 * periodista asignado por adelantado) — el punto de entrada de V.2 (la
 * pieza preparada).
 */
final readonly class EventoProgramado {

	public function __construct(
		public int $id,
		public string $titulo,
		public string $vertical,
		public DateTimeImmutable $fechaEsperada,
		public EstadoEventoProgramado $estado,
		public ?int $periodistaAsignadoId,
		public ?int $historiaId,
		public ?int $tendenciaId,
		public DateTimeImmutable $creadoEn,
		public DateTimeImmutable $actualizadoEn,
	) {
	}
}
