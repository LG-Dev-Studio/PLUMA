<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

use RuntimeException;

/**
 * Una acción de la Sala de Revisión se pidió sobre una Pieza que no está en
 * el estado/condición que esa acción exige (p. ej. "aprobar ahora" sobre una
 * pieza que no está en la cola de veto de Copiloto).
 */
final class AccionNoDisponibleException extends RuntimeException {
}
