<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

use RuntimeException;

final class EventoProgramadoNoEncontradoException extends RuntimeException {

	public function __construct( int $eventoId ) {
		parent::__construct( "Evento programado {$eventoId} no encontrado." );
	}
}
