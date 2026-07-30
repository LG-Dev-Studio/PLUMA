<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

use RuntimeException;

final class PeriodistaNoEncontradoException extends RuntimeException {

	public function __construct( int $periodistaId ) {
		parent::__construct( "Periodista {$periodistaId} no encontrado." );
	}
}
