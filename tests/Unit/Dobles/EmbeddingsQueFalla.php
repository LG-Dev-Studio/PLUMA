<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Dobles;

use Pluma\Proveedores\EmbeddingsInterface;
use Pluma\Proveedores\ProveedorLenguajeException;

/**
 * Doble de `EmbeddingsInterface` que siempre lanza la excepción dada — mismo
 * criterio que {@see ProveedorLenguajeQueFalla}, para tests de caminos de
 * excepción sin tocar red.
 */
final class EmbeddingsQueFalla implements EmbeddingsInterface {

	public function __construct( private readonly ProveedorLenguajeException $excepcion ) {
	}

	public function embed( string $texto ): array {
		throw $this->excepcion;
	}
}
