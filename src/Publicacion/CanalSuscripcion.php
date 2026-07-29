<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

/**
 * Nivel Cuatro W.3 — "canales: email transaccional y push web (PWA)". Una
 * fila de `pluma_suscriptores` es siempre de un solo canal: un lector que
 * quiere ambos tiene dos filas, nunca una fila con los dos rellenos.
 */
enum CanalSuscripcion: string {

	case Email = 'email';
	case Push  = 'push';
}
