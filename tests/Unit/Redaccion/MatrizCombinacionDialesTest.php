<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Pluma\Redaccion\Diales;
use Pluma\Redaccion\MatrizCombinacionDiales;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Redaccion\MatrizCombinacionDiales
 */
final class MatrizCombinacionDialesTest extends CasoDePruebaUnitario {

	public function test_ninguna_combinacion_activada_devuelve_lista_vacia(): void {
		$diales = new Diales( 10, 10, 10, 50, 10, 10, 10, 50 );

		self::assertSame( array(), MatrizCombinacionDiales::directrices( $diales ) );
	}

	public function test_humor_y_agudeza_altos_activan_su_directriz(): void {
		$diales      = new Diales( 90, 90, 10, 50, 10, 10, 10, 50 );
		$directrices = MatrizCombinacionDiales::directrices( $diales );

		self::assertCount( 1, $directrices );
		self::assertStringContainsString( 'la agudeza ataca argumentos e incentivos, jamás a la persona', $directrices[0] );
	}

	public function test_vehemencia_y_empatia_altas_activan_su_directriz(): void {
		$diales      = new Diales( 10, 10, 10, 50, 90, 90, 10, 50 );
		$directrices = MatrizCombinacionDiales::directrices( $diales );

		self::assertCount( 1, $directrices );
		self::assertStringContainsString( 'primero se nombra el impacto humano', $directrices[0] );
	}

	public function test_satira_moderada_y_densidad_de_datos_alta_activan_su_directriz(): void {
		$diales      = new Diales( 10, 10, 45, 50, 10, 10, 90, 50 );
		$directrices = MatrizCombinacionDiales::directrices( $diales );

		self::assertCount( 1, $directrices );
		self::assertStringContainsString( 'la licencia satírica cubre el tono, nunca la exactitud del dato', $directrices[0] );
	}

	public function test_formalidad_baja_y_vehemencia_alta_activan_su_directriz(): void {
		$diales      = new Diales( 10, 10, 10, 10, 90, 10, 10, 50 );
		$directrices = MatrizCombinacionDiales::directrices( $diales );

		self::assertCount( 1, $directrices );
		self::assertStringContainsString( 'un tono cercano no es excusa para una afirmación sin sustento', $directrices[0] );
	}

	public function test_varias_combinaciones_activas_a_la_vez_devuelve_varias_directrices(): void {
		$diales      = new Diales( 90, 90, 45, 10, 90, 90, 90, 50 );
		$directrices = MatrizCombinacionDiales::directrices( $diales );

		self::assertCount( 4, $directrices );
	}
}
