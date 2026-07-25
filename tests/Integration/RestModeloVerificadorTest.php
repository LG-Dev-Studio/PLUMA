<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Admin\RestModeloVerificador;
use Pluma\Kernel\Activador;
use Pluma\Kernel\RelojSistema;
use Pluma\Proveedores\EnrutadorModelos;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Modelo verificador (Nivel Tres J.1-J.2): contrato de independencia
 * epistémica. Contra WordPress real.
 *
 * @covers \Pluma\Admin\RestModeloVerificador
 */
final class RestModeloVerificadorTest extends WP_UnitTestCase {

	private function registrarRutas(): void {
		( new RestModeloVerificador() )->registrar();
		do_action( 'rest_api_init' );
	}

	public function test_rechaza_a_quien_no_tiene_la_capacidad(): void {
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/modelo-verificador' ) );

		self::assertContains( $respuesta->get_status(), array( 401, 403 ) );
	}

	public function test_obtener_devuelve_el_modelo_premium_sin_configuracion_propia(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$datos = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/modelo-verificador' ) )->get_data();

		self::assertSame( ( new EnrutadorModelos() )->modeloPara( \Pluma\Proveedores\PropositoLenguaje::Redactar ), $datos['modeloVerificador'] );
		self::assertFalse( $datos['obligatoriedadDeFabrica'] );
	}

	public function test_actualizar_persiste_el_modelo(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/modelo-verificador' );
		$peticion->set_param( 'modeloVerificador', 'openai/gpt-5' );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 200, $respuesta->get_status() );
		self::assertSame( 'openai/gpt-5', get_option( EnrutadorModelos::OPCION_MODELO_VERIFICADOR ) );
	}

	public function test_actualizar_con_valor_vacio_devuelve_400(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/modelo-verificador' );
		$peticion->set_param( 'modeloVerificador', '   ' );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 400, $respuesta->get_status() );
	}
}
