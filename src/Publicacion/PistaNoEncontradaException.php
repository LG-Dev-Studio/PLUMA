<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use RuntimeException;

final class PistaNoEncontradaException extends RuntimeException {

	public function __construct( int $id ) {
		parent::__construct( "Pista {$id} no encontrada." );
	}
}
