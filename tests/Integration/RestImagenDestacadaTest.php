<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Admin\RestImagenDestacada;
use Pluma\Kernel\Activador;
use Pluma\Kernel\RelojSistema;
use Pluma\Publicacion\AsignadorImagenDestacada;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Imagen destacada por autoridad de fuente (Nivel Dos, decisión del
 * propietario — `ADR 0006`). Contra WordPress real.
 *
 * @covers \Pluma\Admin\RestImagenDestacada
 */
final class RestImagenDestacadaTest extends WP_UnitTestCase {

	private function registrarRutas(): void {
		( new RestImagenDestacada() )->registrar();
		do_action( 'rest_api_init' );
	}

	public function test_rechaza_a_quien_no_tiene_la_capacidad(): void {
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/imagen-destacada' ) );

		self::assertContains( $respuesta->get_status(), array( 401, 403 ) );
	}

	public function test_obtener_devuelve_ninguna_por_defecto(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$datos = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/imagen-destacada' ) )->get_data();

		self::assertSame( 'ninguna', $datos['modo'] );
		self::assertTrue( $datos['creditoVisible'] );
	}

	public function test_actualizar_persiste_el_modo_y_el_credito(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/imagen-destacada' );
		$peticion->set_param( 'modo', 'enlazada' );
		$peticion->set_param( 'creditoVisible', false );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 200, $respuesta->get_status() );
		self::assertSame( 'enlazada', get_option( AsignadorImagenDestacada::OPCION_MODO ) );
		self::assertFalse( (bool) get_option( AsignadorImagenDestacada::OPCION_CREDITO_VISIBLE ) );
	}

	public function test_actualizar_con_modo_invalido_devuelve_400(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/imagen-destacada' );
		$peticion->set_param( 'modo', 'inventado' );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 400, $respuesta->get_status() );
	}
}
