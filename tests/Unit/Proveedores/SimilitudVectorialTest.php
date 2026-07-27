<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Proveedores;

use Pluma\Proveedores\SimilitudVectorial;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Proveedores\SimilitudVectorial
 */
final class SimilitudVectorialTest extends CasoDePruebaUnitario {

	public function test_vectores_identicos_dan_similitud_uno(): void {
		self::assertEqualsWithDelta( 1.0, SimilitudVectorial::coseno( array( 1.0, 2.0, 3.0 ), array( 1.0, 2.0, 3.0 ) ), 0.0001 );
	}

	public function test_vectores_ortogonales_dan_similitud_cero(): void {
		self::assertEqualsWithDelta( 0.0, SimilitudVectorial::coseno( array( 1.0, 0.0 ), array( 0.0, 1.0 ) ), 0.0001 );
	}

	public function test_vectores_opuestos_dan_similitud_menos_uno(): void {
		self::assertEqualsWithDelta( -1.0, SimilitudVectorial::coseno( array( 1.0, 2.0 ), array( -1.0, -2.0 ) ), 0.0001 );
	}

	public function test_vector_vacio_devuelve_cero_de_forma_defensiva(): void {
		self::assertSame( 0.0, SimilitudVectorial::coseno( array(), array() ) );
	}

	public function test_longitudes_distintas_devuelve_cero_de_forma_defensiva(): void {
		self::assertSame( 0.0, SimilitudVectorial::coseno( array( 1.0, 2.0 ), array( 1.0 ) ) );
	}

	public function test_vector_de_norma_cero_devuelve_cero_de_forma_defensiva(): void {
		self::assertSame( 0.0, SimilitudVectorial::coseno( array( 0.0, 0.0 ), array( 1.0, 1.0 ) ) );
	}
}
