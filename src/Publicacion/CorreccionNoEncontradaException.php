<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use RuntimeException;

final class CorreccionNoEncontradaException extends RuntimeException {

	public function __construct( int $id ) {
		parent::__construct( "Corrección {$id} no encontrada." );
	}
}
