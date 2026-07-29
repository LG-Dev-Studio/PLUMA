<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use DateTimeImmutable;

final readonly class Pista {

	public function __construct(
		public int $id,
		public int $historiaId,
		public string $contenido,
		public ?string $contactoEmail,
		public EstadoPista $estado,
		public DateTimeImmutable $creadoEn,
	) {
	}
}
