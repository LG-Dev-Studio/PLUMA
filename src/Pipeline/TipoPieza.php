<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

/**
 * Nivel Cuatro U.4 — "la actualización como ciudadana de primera": el Libro
 * trataba la actualización como "segunda pieza" (dos golpes, Cap. 3.4). Con
 * la entidad Historia, se formaliza como un campo propio de la Pieza —
 * `Original` es el valor por defecto (toda pieza que no es explícitamente
 * una actualización/corrección/cierre de una historia existente).
 */
enum TipoPieza: string {

	case Original      = 'original';
	case Actualizacion = 'actualizacion';
	case Correccion    = 'correccion';
	case Cierre        = 'cierre';
}
