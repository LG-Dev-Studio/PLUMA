<?php

declare(strict_types=1);

namespace Pluma\Kernel;

use DateTimeImmutable;

/**
 * Perfil de Entorno (`docs/CEREBRO_PLUMA_v2.md` Parte 1.6): hechos medidos +
 * transporte del Plano 1 que se usaría si el Plano 1 existiera.
 *
 * `$hechos` se mantiene anidado, nunca aplanado en `$transportePrioritario`:
 * la distinción estructural entre "hecho medido" y "transporte derivado"
 * es lo que impide que un consumidor futuro confunda medición prospectiva
 * con capacidad activa — ver `ResolutorPerfilEntorno`.
 */
final readonly class PerfilEntorno {

	public function __construct(
		public HechosEntorno $hechos,
		public TransportePlano1 $transportePrioritario,
		public DateTimeImmutable $medidoEn,
	) {
	}
}
