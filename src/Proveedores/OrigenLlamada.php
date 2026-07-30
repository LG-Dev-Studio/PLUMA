<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Origen de una llamada al proveedor (NCP-1, `ADR 0010`): quién disparó el
 * gasto. Es la columna que vuelve MEDIBLE la restricción §5.1.4 de
 * `docs/CEREBRO_PLUMA_v2.md` ("ninguna inferencia en petición de visitante:
 * solo cron y peticiones autenticadas del panel"), hoy ya violada por la
 * clasificación de comentarios en `pre_comment_approved`.
 *
 * `Visitante` es el DEFECTO deliberado de `Pluma\Kernel\ContextoEjecucion`:
 * un camino de ejecución que nadie declaró se cuenta como el peor caso. Es
 * preferible sobre-reportar la exposición que subestimarla en silencio.
 */
enum OrigenLlamada: string {

	case Cron      = 'cron';
	case Panel     = 'panel';
	case Visitante = 'visitante';
}
