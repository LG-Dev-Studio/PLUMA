<?php

declare(strict_types=1);

namespace Pluma\Sensores;

/**
 * Resultado de `EvaluadorLegitimidadInsumo` (Nivel Dos G.1).
 */
final readonly class DiagnosticoLegitimidadInsumo {

	public function __construct(
		public int $totalArticulos,
		public int $fuentesUnicas,
		public float $diversidadFuente,
		public bool $legitimo,
		public ?string $motivo,
	) {
	}
}
