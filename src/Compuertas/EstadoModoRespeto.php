<?php

declare(strict_types=1);

namespace Pluma\Compuertas;

use DateTimeImmutable;

/**
 * Estado actual del modo respeto (Nivel Dos F.1-F.3): `$activo` es la única
 * pregunta que la mayoría del pipeline necesita responder (F.3, forzar tono
 * de Tragedia en todo el sitio). `$puedeDesactivarseDesde` es el piso de
 * duración mínima congelado en la propia activación (F.3: "impide que un
 * editor apurado desactive el modo respeto en los primeros quince minutos").
 */
final readonly class EstadoModoRespeto {

	public function __construct(
		public bool $activo,
		public ?DateTimeImmutable $activadoEn,
		public ?ActivadorModoRespeto $activadoPor,
		public ?string $motivo,
		public ?DateTimeImmutable $puedeDesactivarseDesde,
	) {
	}

	public static function inactivo(): self {
		return new self( false, null, null, null, null );
	}
}
