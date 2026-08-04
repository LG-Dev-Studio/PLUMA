<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Dobles;

use Pluma\Proveedores\RerankInterface;
use Pluma\Proveedores\ResultadoRerank;

/**
 * Doble de `RerankInterface` para tests Unit que necesitan un orden o una
 * forma de respuesta exactas y controladas (incluida una permutación
 * inválida a propósito) — el proveedor real (`ProveedorRerankLexico`,
 * `ADR 0024`) siempre produce una permutación válida, así que estos casos
 * de borde no son alcanzables con él.
 */
final class RerankFalso implements RerankInterface {

	/**
	 * @param (callable(string, list<string>): list<ResultadoRerank>)|null $mapeo
	 */
	public function __construct( private readonly mixed $mapeo = null ) {
	}

	public function reordenar( string $consulta, array $textos ): array {
		if ( null !== $this->mapeo ) {
			return ( $this->mapeo )( $consulta, $textos );
		}

		return array_map(
			static fn ( int $indice ): ResultadoRerank => new ResultadoRerank( $indice, 1.0 ),
			array_keys( $textos )
		);
	}
}
