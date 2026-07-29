<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use DateTimeImmutable;

final readonly class DerivadoSocial {

	public function __construct(
		public int $id,
		public int $piezaId,
		public string $extractoSocial,
		public string $titularDiscover,
		public EstadoDerivadoSocial $estado,
		public DateTimeImmutable $creadoEn,
	) {
	}
}
