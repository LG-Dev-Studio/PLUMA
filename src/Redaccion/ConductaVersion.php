<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use DateTimeImmutable;

/**
 * Versión fechada e inmutable de la Conducta de un periodista (pl-periodistas
 * §Contratos innegociables 1): toda modificación crea una versión nueva,
 * nunca se actualiza una existente. Cada Pieza registra qué versión usó.
 */
final readonly class ConductaVersion {

	public function __construct(
		public int $id,
		public int $periodistaId,
		public Diales $diales,
		public ReglasConducta $reglas,
		public MatrizTonos $matrizTonos,
		public DateTimeImmutable $creadaEn,
	) {
	}
}
