<?php

declare(strict_types=1);

namespace Pluma\Kernel;

/**
 * Hechos crudos de infraestructura medidos por `SensorCapacidades`
 * (`docs/CEREBRO_PLUMA_v2.md` Parte 1.6/3.1). Cero derivación aquí — solo
 * lectura directa del entorno.
 *
 * Deliberadamente NO incluye ningún campo de "extensión ONNX candidata":
 * ningún runtime ONNX está integrado todavía (NCP-2), y fijar un nombre de
 * extensión hoy violaría la disciplina "investiga antes de nombrar" del
 * canon (Parte 5.2.3). Solo se reporta `ffiDisponible`, el mecanismo
 * habilitante genérico.
 */
final readonly class HechosEntorno {

	public function __construct(
		public bool $ffiDisponible,
		public int $memoriaLimiteMb,
		public int $tiempoMaximoEjecucionSegundos,
		public bool $procesoHijoDisponible,
		public bool $cerebroRemotoConfigurado,
		public bool $apiPagoConfigurada,
	) {
	}
}
