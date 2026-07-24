<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Kernel;

use Brain\Monkey\Functions;
use Pluma\Kernel\DetectorConflictos;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Kernel\DetectorConflictos
 *
 * `WPSEO_VERSION`/`RANK_MATH_VERSION` son constantes globales reales — una
 * vez definidas en un proceso PHP no se pueden "undefine". Por eso este caso
 * corre en un proceso PHPUnit `@runInSeparateProcess` (aislado del resto de
 * la suite Unit) y prueba únicamente el camino "ambas activas"; el camino
 * "ninguna activa" ya lo cubre implícitamente cualquier otro test Unit de la
 * suite que nunca define esas constantes.
 */
final class DetectorConflictosTest extends CasoDePruebaUnitario {

	public function test_sin_ninguna_constante_definida_no_hay_advertencias(): void {
		self::assertSame( array(), ( new DetectorConflictos() )->detectar() );
	}

	/**
	 * @runInSeparateProcess
	 */
	public function test_yoast_y_rank_math_activos_a_la_vez_genera_una_advertencia(): void {
		Functions\when( '__' )->returnArg( 1 );

		define( 'WPSEO_VERSION', '20.0' );
		define( 'RANK_MATH_VERSION', '1.0' );

		$advertencias = ( new DetectorConflictos() )->detectar();

		self::assertCount( 1, $advertencias );
		self::assertStringContainsString( 'Yoast', $advertencias[0] );
		self::assertStringContainsString( 'Rank Math', $advertencias[0] );
	}
}
