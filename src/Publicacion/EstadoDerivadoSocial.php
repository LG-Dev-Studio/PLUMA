<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

/**
 * Nivel Cuatro W.2 — el editor revisa cada derivado antes de usarlo (PLUMA
 * no publica solo a ninguna red social todavía, `PLUMA-E9-4`).
 */
enum EstadoDerivadoSocial: string {

	case Pendiente  = 'pendiente';
	case Aprobado   = 'aprobado';
	case Descartado = 'descartado';
}
