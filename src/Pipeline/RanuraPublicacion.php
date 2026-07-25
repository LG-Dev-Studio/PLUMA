<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

use DateTimeImmutable;

/**
 * Una fila de `pluma_cola_publicacion` (Libro Cap. 9.2-9.3): la ranura
 * horaria asignada a una Pieza APROBADA.
 */
final readonly class RanuraPublicacion {

	public function __construct(
		public int $id,
		public int $piezaId,
		public string $vertical,
		public ?int $periodistaId,
		public DateTimeImmutable $horaProgramada,
		public EstadoColaPublicacion $estado,
		public bool $aprobacionActiva,
		public DateTimeImmutable $creadaEn,
	) {
	}
}
