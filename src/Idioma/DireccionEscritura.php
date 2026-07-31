<?php

declare(strict_types=1);

namespace Pluma\Idioma;

/**
 * Dirección de escritura de un locale: de izquierda a derecha o de derecha
 * a izquierda. Determinada por el subtag primario del locale (`ResolutorPerfilIdioma`),
 * no por un catálogo exhaustivo de idiomas del mundo.
 */
enum DireccionEscritura: string {

	case Ltr = 'ltr';
	case Rtl = 'rtl';
}
