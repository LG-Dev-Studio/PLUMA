<?php

declare(strict_types=1);

namespace Pluma\Kernel;

use DateTimeImmutable;

/**
 * Deriva el `PerfilEntorno` a partir de `HechosEntorno` — servicio puro, sin
 * dependencias, mismo patrón que `Pluma\Idioma\ResolutorPerfilIdioma`.
 *
 * Implementa literalmente la matriz de decisión de
 * `docs/CEREBRO_PLUMA_v2.md` Parte 3.1: T1 (FFI) → T2 (proceso hijo) → T3
 * (cerebro remoto) → `Ninguno` (P0-lite). `$apiPagoConfigurada` es
 * ortogonal y nunca participa de esta prioridad — el Plano 2 (generativo)
 * y el Plano 1 (semántico) son conceptos distintos del canon; mezclarlos
 * repetiría exactamente el error que este resolutor existe para evitar.
 *
 * IMPORTANTE: `$transportePrioritario` es una medición PROSPECTIVA. El
 * Plano 1 (ONNX) no está construido todavía (NCP-2) — este valor nunca
 * implica que el Plano 1 esté disponible hoy, sin importar qué hechos se
 * midan. Mismo principio de honestidad que `Pluma\Idioma\NivelCobertura::Completo`
 * (modelado, documentado, nunca producido hasta que la infraestructura real
 * exista).
 */
final class ResolutorPerfilEntorno {

	public function resolver( HechosEntorno $hechos, DateTimeImmutable $medidoEn ): PerfilEntorno {
		$transporte = match ( true ) {
			$hechos->ffiDisponible => TransportePlano1::T1EnProceso,
			$hechos->procesoHijoDisponible => TransportePlano1::T2SidecarLocal,
			$hechos->cerebroRemotoConfigurado => TransportePlano1::T3CerebroRemoto,
			default => TransportePlano1::Ninguno,
		};

		return new PerfilEntorno( $hechos, $transporte, $medidoEn );
	}
}
