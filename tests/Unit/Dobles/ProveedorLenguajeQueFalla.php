<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Dobles;

use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Proveedores\RespuestaLenguaje;

/**
 * Doble de `LenguajeInterface` que siempre lanza la excepción dada — para
 * tests del escenario de fallback (`RedactorConFallbackMecanico`) sin
 * necesidad de construir una cadena completa de respuestas válidas.
 */
final class ProveedorLenguajeQueFalla implements LenguajeInterface {

	public function __construct( private readonly ProveedorLenguajeException $excepcion ) {
	}

	public function completar( PeticionLenguaje $peticion ): RespuestaLenguaje {
		throw $this->excepcion;
	}

	public function familiaDe( string $modelo ): string {
		return explode( '/', $modelo, 2 )[0];
	}
}
