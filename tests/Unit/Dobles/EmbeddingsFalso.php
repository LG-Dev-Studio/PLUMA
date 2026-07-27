<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Dobles;

use Pluma\Proveedores\EmbeddingsInterface;

/**
 * Doble de `EmbeddingsInterface` para tests Unit: por defecto devuelve el
 * mismo vector para cualquier texto (similitud coseno = 1.0 siempre — ningún
 * test que no se ocupe de J.3 ve una alerta de trazabilidad inesperada).
 * Acepta una función de mapeo propia para los tests que sí necesitan
 * vectores distintos según el contenido.
 */
final class EmbeddingsFalso implements EmbeddingsInterface {

	/**
	 * @param (callable(string): list<float>)|null $mapeo
	 */
	public function __construct( private readonly mixed $mapeo = null ) {
	}

	public function embed( string $texto ): array {
		if ( null !== $this->mapeo ) {
			return ( $this->mapeo )( $texto );
		}

		return array( 1.0, 0.0, 0.0 );
	}
}
