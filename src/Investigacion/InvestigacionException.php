<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

/**
 * Fallo del protocolo de investigación (formato inesperado del proveedor de
 * lenguaje, respuesta truncada, valor de enum desconocido).
 */
final class InvestigacionException extends \RuntimeException {
}
