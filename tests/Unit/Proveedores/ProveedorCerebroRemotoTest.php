<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Proveedores;

use Brain\Monkey\Functions;
use Mockery;
use Pluma\Kernel\Cifrado;
use Pluma\Proveedores\ProveedorCerebroRemoto;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use WP_Error;

/**
 * @covers \Pluma\Proveedores\ProveedorCerebroRemoto
 */
final class ProveedorCerebroRemotoTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'AUTH_KEY' ) ) {
			define( 'AUTH_KEY', 'clave-app-de-prueba' );
			define( 'SECURE_AUTH_KEY', 'clave-secure-de-prueba' );
		}

		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'gethostbyname' )->justReturn( '93.184.216.34' );
	}

	public function test_no_configurado_sin_url_ni_token(): void {
		Functions\when( 'get_option' )->justReturn( false );

		self::assertFalse( ( new ProveedorCerebroRemoto() )->configurado() );
	}

	public function test_configurado_con_url_y_token_cifrado_validos(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				return match ( $opcion ) {
					ProveedorCerebroRemoto::OPCION_URL => 'https://cerebro.example/salud',
					ProveedorCerebroRemoto::OPCION_TOKEN_CIFRADO => Cifrado::cifrar( 'token-de-prueba' ),
					default => $defecto,
				};
			}
		);

		self::assertTrue( ( new ProveedorCerebroRemoto() )->configurado() );
	}

	public function test_probar_rechaza_una_url_insegura_sin_llamar_a_la_red(): void {
		Functions\expect( 'wp_remote_get' )->never();

		self::assertFalse( ( new ProveedorCerebroRemoto() )->probar( 'http://127.0.0.1/salud', 'token' ) );
	}

	public function test_probar_devuelve_verdadero_con_respuesta_200(): void {
		Functions\expect( 'wp_remote_get' )
			->once()
			->with( 'https://cerebro.example/salud', Mockery::type( 'array' ) )
			->andReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );

		self::assertTrue( ( new ProveedorCerebroRemoto() )->probar( 'https://cerebro.example/salud', 'token' ) );
	}

	public function test_probar_devuelve_falso_si_la_peticion_falla(): void {
		Functions\when( 'wp_remote_get' )->justReturn( new WP_Error( 'http_request_failed', 'Timeout' ) );
		Functions\when( 'is_wp_error' )->justReturn( true );

		self::assertFalse( ( new ProveedorCerebroRemoto() )->probar( 'https://cerebro.example/salud', 'token' ) );
	}

	public function test_ultima_prueba_ok_lee_el_flag_cacheado_sin_red(): void {
		Functions\expect( 'wp_remote_get' )->never();
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				return ProveedorCerebroRemoto::OPCION_ULTIMA_PRUEBA_OK === $opcion ? true : $defecto;
			}
		);

		self::assertTrue( ( new ProveedorCerebroRemoto() )->ultimaPruebaOk() );
	}
}
