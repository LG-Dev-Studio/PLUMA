<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use DateTimeImmutable;

final readonly class Correccion {

	public function __construct(
		public int $id,
		public int $piezaId,
		public string $afirmacionReportada,
		public string $evidenciaAportada,
		public ?string $emailReportante,
		public ?string $nombreCredito,
		public bool $creditoOptIn,
		public EstadoCorreccion $estado,
		public ?string $notaEditor,
		public DateTimeImmutable $creadoEn,
		public ?DateTimeImmutable $resueltoEn,
	) {
	}
}
