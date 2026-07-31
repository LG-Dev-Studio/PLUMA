<?php

declare(strict_types=1);

namespace Pluma\Kernel;

/**
 * Transporte de ejecución del Plano 1 semántico (`docs/CEREBRO_PLUMA_v2.md`
 * Parte 3.1) que se usaría SI el Plano 1 existiera — el Plano 1 (ONNX) no
 * está construido todavía (NCP-2). Ver `ResolutorPerfilEntorno` para la
 * matriz de prioridad T1 → T2 → T3 → `Ninguno`.
 *
 * T4 (navegador/WASM, Parte 3.1: "solo tareas interactivas del panel,
 * nunca cron") queda deliberadamente fuera de este enum: su elección
 * ocurre en el navegador del editor durante trabajo interactivo, y un
 * resolutor PHP server-side (cron, REST) no tiene forma de observar "hay
 * un navegador disponible ahora" — incluirlo aquí sería un valor que
 * miente sobre lo que el servidor puede medir.
 */
enum TransportePlano1: string {

	case T1EnProceso     = 't1_en_proceso';
	case T2SidecarLocal  = 't2_sidecar_local';
	case T3CerebroRemoto = 't3_cerebro_remoto';
	case Ninguno         = 'ninguno';
}
