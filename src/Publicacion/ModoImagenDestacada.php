<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

/**
 * Imagen destacada por autoridad de fuente (decisión del propietario,
 * `ADR 0006`). Default de fábrica `Ninguna` — nadie queda expuesto al
 * riesgo legal de usar imágenes de terceros sin activarlo explícitamente.
 */
enum ModoImagenDestacada: string {

	case Ninguna    = 'ninguna';
	case Enlazada   = 'enlazada';
	case Descargada = 'descargada';
}
