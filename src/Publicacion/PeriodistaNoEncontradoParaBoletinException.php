<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use RuntimeException;

final class PeriodistaNoEncontradoParaBoletinException extends RuntimeException {

	public function __construct( int $periodistaId ) {
		parent::__construct( "Periodista {$periodistaId} no encontrado." );
	}
}
