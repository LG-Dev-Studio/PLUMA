<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Sensores;

use Brain\Monkey\Functions;
use Pluma\Sensores\PuntuacionOportunidad;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Sensores\PuntuacionOportunidad
 */
final class PuntuacionOportunidadTest extends CasoDePruebaUnitario {

	public function test_afinidad_bajo_el_umbral_de_fabrica_hace_la_tendencia_no_elegible(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$puntuacion = PuntuacionOportunidad::calcular( 100.0, 14.9 );

		self::assertFalse( $puntuacion->elegible );
		self::assertSame( 0.0, $puntuacion->total );
	}

	public function test_afinidad_en_el_umbral_de_fabrica_es_elegible(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$puntuacion = PuntuacionOportunidad::calcular( 100.0, 15.0 );

		self::assertTrue( $puntuacion->elegible );
	}

	/**
	 * Nivel Dos C.1: velocidad alta y afinidad cero NO puede alcanzar un
	 * total alto solo porque los demás factores compensan — la puerta
	 * bloquea el total entero a 0, no lo diluye.
	 */
	public function test_afinidad_cero_bloquea_el_total_aunque_la_velocidad_sea_maxima(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$puntuacion = PuntuacionOportunidad::calcular( 100.0, 0.0 );

		self::assertFalse( $puntuacion->elegible );
		self::assertSame( 0.0, $puntuacion->total );
	}

	/**
	 * Techo honesto: 0.40×velocidad + 0.15×afinidad, nunca 100, mientras
	 * hueco competitivo (0.25) y vida útil (0.20) sigan sin construir
	 * (`PLUMA-E1-1`).
	 */
	public function test_velocidad_y_afinidad_maximas_dan_el_techo_honesto_de_55(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$puntuacion = PuntuacionOportunidad::calcular( 100.0, 100.0 );

		self::assertTrue( $puntuacion->elegible );
		self::assertSame( 100.0, $puntuacion->velocidad );
		self::assertSame( 100.0, $puntuacion->afinidad );
		self::assertEqualsWithDelta( 55.0, $puntuacion->total, 0.01 );
	}

	public function test_acota_valores_fuera_de_rango_0_100(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$puntuacion = PuntuacionOportunidad::calcular( 150.0, -20.0 );

		self::assertSame( 100.0, $puntuacion->velocidad );
		self::assertSame( 0.0, $puntuacion->afinidad );
	}

	public function test_el_umbral_de_afinidad_es_configurable_por_opcion(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				if ( PuntuacionOportunidad::OPCION_UMBRAL_AFINIDAD_MINIMA === $opcion ) {
					return 50.0;
				}

				return $defecto;
			}
		);

		$puntuacion = PuntuacionOportunidad::calcular( 100.0, 40.0 );

		self::assertFalse( $puntuacion->elegible );
	}
}
