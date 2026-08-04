<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Dobles;

use Pluma\Proveedores\NliInterface;
use Pluma\Proveedores\ProveedorLenguajeException;

/**
 * Doble de `NliInterface` que siempre lanza la excepción dada — mismo
 * criterio que {@see EmbeddingsQueFalla}, para tests de caminos de
 * excepción sin tocar red.
 */
final class NliQueFalla implements NliInterface {

	public function __construct( private readonly ProveedorLenguajeException $excepcion ) {
	}

	public function inferir( string $premisa, string $hipotesis ): array {
		throw $this->excepcion;
	}
}
