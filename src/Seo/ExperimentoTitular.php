<?php

declare(strict_types=1);

namespace Pluma\Seo;

use DateTimeImmutable;

final readonly class ExperimentoTitular {

	public function __construct(
		public int $id,
		public int $piezaId,
		public int $postId,
		public string $tituloA,
		public string $tituloB,
		public int $impresionesA,
		public int $clicsA,
		public int $impresionesB,
		public int $clicsB,
		public ?string $tituloGanador,
		public ?DateTimeImmutable $consolidadoEn,
		public DateTimeImmutable $creadoEn,
	) {
	}
}
