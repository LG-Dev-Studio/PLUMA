<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Investigacion;

use Brain\Monkey\Functions;
use Pluma\Investigacion\ClasificadorNivelFuente;
use Pluma\Investigacion\NivelFuente;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Investigacion\ClasificadorNivelFuente
 */
final class ClasificadorNivelFuenteTest extends CasoDePruebaUnitario {

	public function test_una_fuente_no_listada_es_nivel_c_por_defecto(): void {
		Functions\when( 'get_option' )->justReturn( array() );

		self::assertSame( NivelFuente::C, ( new ClasificadorNivelFuente() )->nivelDe( 'blog-desconocido.example' ) );
	}

	public function test_una_fuente_en_la_lista_nivel_a_configurada_es_nivel_a(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				if ( ClasificadorNivelFuente::OPCION_FUENTES_NIVEL_A === $opcion ) {
					return array( 'reuters.com' );
				}

				return $defecto;
			}
		);

		self::assertSame( NivelFuente::A, ( new ClasificadorNivelFuente() )->nivelDe( 'Reuters.com' ) );
	}

	public function test_una_fuente_en_la_lista_nivel_b_configurada_es_nivel_b(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				if ( ClasificadorNivelFuente::OPCION_FUENTES_NIVEL_B === $opcion ) {
					return array( 'blog-regional.example' );
				}

				return $defecto;
			}
		);

		self::assertSame( NivelFuente::B, ( new ClasificadorNivelFuente() )->nivelDe( 'blog-regional.example' ) );
	}

	public function test_nivel_a_tiene_prioridad_si_una_fuente_aparece_en_ambas_listas(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				if ( ClasificadorNivelFuente::OPCION_FUENTES_NIVEL_A === $opcion ) {
					return array( 'ambiguo.example' );
				}

				if ( ClasificadorNivelFuente::OPCION_FUENTES_NIVEL_B === $opcion ) {
					return array( 'ambiguo.example' );
				}

				return $defecto;
			}
		);

		self::assertSame( NivelFuente::A, ( new ClasificadorNivelFuente() )->nivelDe( 'ambiguo.example' ) );
	}

	/**
	 * `PLUMA-E9-21`: la comparación normaliza diacríticos, así que un nombre
	 * de fuente configurado sin tildes calza con la variante acentuada real.
	 */
	public function test_calza_una_fuente_configurada_sin_tildes_contra_la_variante_acentuada(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				if ( ClasificadorNivelFuente::OPCION_FUENTES_NIVEL_A === $opcion ) {
					return array( 'El Pais' );
				}

				return $defecto;
			}
		);

		self::assertSame( NivelFuente::A, ( new ClasificadorNivelFuente() )->nivelDe( 'El País' ) );
	}
}
