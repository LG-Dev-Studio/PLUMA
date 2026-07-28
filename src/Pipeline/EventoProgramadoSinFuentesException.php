<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

use RuntimeException;

/**
 * Nivel Cuatro V.2: preparar cobertura exige que el editor ya haya reunido
 * al menos una fuente real — nunca se inventa ni se busca automáticamente
 * un expediente vacío (`PLUMA-E9-2`).
 */
final class EventoProgramadoSinFuentesException extends RuntimeException {

	public function __construct( int $eventoId ) {
		parent::__construct( "El evento programado {$eventoId} necesita al menos un artículo relacionado para preparar su cobertura." );
	}
}
