<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

/**
 * Nivel Cuatro W.3 — "el lector puede suscribirse a: un periodista (W.1),
 * una Historia (U.2), un vertical, o alertas de última hora (solo gravedad
 * alta)". `referenciaId` de `Suscriptor` apunta al `periodista_id` o
 * `historia_id` según el tipo; `Vertical` usa el campo `vertical` en su
 * lugar; `AlertaUrgente` no usa ninguno de los dos (aplica a todo el sitio).
 */
enum TipoSuscripcion: string {

	case Periodista    = 'periodista';
	case Historia      = 'historia';
	case Vertical      = 'vertical';
	case AlertaUrgente = 'alerta_urgente';
}
