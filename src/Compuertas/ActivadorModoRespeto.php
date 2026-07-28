<?php

declare(strict_types=1);

namespace Pluma\Compuertas;

/**
 * Quién activó el modo respeto (Nivel Dos F.2): automático por el
 * disparador de dos niveles del propio sistema, o manual por un clic del
 * editor — ambos caminos son legítimos, ninguno es "el de respaldo" del otro.
 */
enum ActivadorModoRespeto: string {

	case Automatico = 'automatico';
	case Manual     = 'manual';
}
