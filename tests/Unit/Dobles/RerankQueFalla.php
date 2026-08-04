<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Dobles;

use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Proveedores\RerankInterface;

/**
 * Doble de `RerankInterface` que siempre lanza la excepción dada — usado
 * solo para probar la degradación con gracia de
 * {@see \Pluma\Investigacion\OrdenadorHechosPorRelevancia}; el proveedor
 * real (`ProveedorRerankLexico`, `ADR 0024`) nunca falla en la práctica.
 */
final class RerankQueFalla implements RerankInterface {

	public function __construct( private readonly ProveedorLenguajeException $excepcion ) {
	}

	public function reordenar( string $consulta, array $textos ): array {
		throw $this->excepcion;
	}
}
