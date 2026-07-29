<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use RuntimeException;

final class SuscripcionNoEncontradaException extends RuntimeException {

	public function __construct() {
		parent::__construct( 'Enlace de suscripción no válido o ya usado.' );
	}
}
