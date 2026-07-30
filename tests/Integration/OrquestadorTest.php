<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use mysqli;
use Pluma\Admin\RestOrquestador;
use Pluma\Datos\CandadoGlobal;
use Pluma\Kernel\Activador;
use Pluma\Kernel\Nucleo;
use Pluma\Kernel\RelojSistema;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Orquestador contra un WordPress real (wp-env): candado global vía
 * GET_LOCK/RELEASE_LOCK de MySQL, en dos conexiones reales de base de datos
 * (pl-pipeline §2, sub-agente ORQUESTADOR de AGENTS.md).
 *
 * @covers \Pluma\Pipeline\Orquestador
 * @covers \Pluma\Datos\CandadoGlobal
 * @covers \Pluma\Admin\RestOrquestador
 */
final class OrquestadorTest extends WP_UnitTestCase {

	private function segundaConexion(): mysqli {
		return new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
	}

	public function test_doble_ejecucion_simultanea_sale_en_silencio(): void {
		global $wpdb;

		$otraConexion = $this->segundaConexion();
		$otraConexion->query( "SELECT GET_LOCK('pluma_motor', 0)" );

		try {
			$candado = new CandadoGlobal( $wpdb );

			self::assertFalse( $candado->adquirir(), 'Una segunda conexión ya tiene el candado; esta ejecución debe salir en silencio.' );
		} finally {
			$otraConexion->query( "SELECT RELEASE_LOCK('pluma_motor')" );
			$otraConexion->close();
		}
	}

	public function test_muerte_a_mitad_de_lote_libera_el_candado_al_cerrar_la_conexion(): void {
		global $wpdb;

		$otraConexion = $this->segundaConexion();
		$otraConexion->query( "SELECT GET_LOCK('pluma_motor', 0)" );
		// Simula el proceso PHP muriendo a mitad de lote (kill -9, timeout):
		// se cierra la conexión SIN liberar el candado explícitamente.
		$otraConexion->close();

		$candado = new CandadoGlobal( $wpdb );

		self::assertTrue(
			$candado->adquirir(),
			'MySQL debe liberar el candado de sesión automáticamente cuando la conexión que lo tenía se cierra.'
		);

		$candado->liberar();
	}

	public function test_endpoint_del_cron_rechaza_peticiones_sin_token(): void {
		$nucleo = new Nucleo();
		$nucleo->contenedor()->obtener( RestOrquestador::class )->registrar();
		do_action( 'rest_api_init' );

		$servidor  = rest_get_server();
		$peticion  = new WP_REST_Request( 'GET', '/pluma/v1/motor/tick' );
		$respuesta = $servidor->dispatch( $peticion );

		self::assertSame( 401, $respuesta->get_status() );
	}

	public function test_endpoint_del_cron_rechaza_token_invalido(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$nucleo = new Nucleo();
		$nucleo->contenedor()->obtener( RestOrquestador::class )->registrar();
		do_action( 'rest_api_init' );

		$servidor = rest_get_server();
		$peticion = new WP_REST_Request( 'GET', '/pluma/v1/motor/tick' );
		$peticion->set_header( 'X-Pluma-Token', 'token-incorrecto' );
		$respuesta = $servidor->dispatch( $peticion );

		self::assertSame( 401, $respuesta->get_status() );
	}

	public function test_endpoint_del_cron_acepta_el_token_correcto_y_ejecuta_un_tick(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		$token = get_option( Activador::OPCION_MOTOR_TOKEN );
		self::assertIsString( $token );

		$nucleo = new Nucleo();
		$nucleo->contenedor()->obtener( RestOrquestador::class )->registrar();
		do_action( 'rest_api_init' );

		$servidor = rest_get_server();
		$peticion = new WP_REST_Request( 'GET', '/pluma/v1/motor/tick' );
		$peticion->set_header( 'X-Pluma-Token', $token );
		$respuesta = $servidor->dispatch( $peticion );

		self::assertSame( 200, $respuesta->get_status() );
		self::assertTrue( $respuesta->get_data()['ejecutado'] );
	}
}
