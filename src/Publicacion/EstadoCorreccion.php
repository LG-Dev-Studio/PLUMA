<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

/**
 * Nivel Cuatro X.4 — "reportar un error" nunca publica solo: el editor
 * verifica antes de que exista corrección real.
 */
enum EstadoCorreccion: string {

	case Pendiente  = 'pendiente';
	case Verificada = 'verificada';
	case Rechazada  = 'rechazada';
}
