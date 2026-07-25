<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Admin\RestTransparencia;
use Pluma\Kernel\Activador;
use Pluma\Kernel\RelojSistema;
use Pluma\Redaccion\AvisoTransparenciaIa;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Transparencia y cumplimiento (Art. 50 UE, Nivel Tres N.3): el cliente
 * configura SOLO el formato del bloque visible; el marcado legible por
 * máquina es piso de fábrica (ADR 0002). Contra WordPress real.
 *
 * @covers \Pluma\Admin\RestTransparencia
 */
final class RestTransparenciaTest extends WP_UnitTestCase {

	private function registrarRutas(): void {
		( new RestTransparencia() )->registrar();
		do_action( 'rest_api_init' );
	}

	public function test_rechaza_a_quien_no_tiene_la_capacidad(): void {
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/transparencia' ) );

		self::assertContains( $respuesta->get_status(), array( 401, 403 ) );
	}

	public function test_obtener_devuelve_formato_actual_y_marcado_de_fabrica(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$datos = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/transparencia' ) )->get_data();

		self::assertContains( $datos['formato'], array( AvisoTransparenciaIa::FORMATO_BREVE, AvisoTransparenciaIa::FORMATO_EXTENDIDO ) );
		self::assertTrue( $datos['marcadoIaDeFabrica'] );
	}

	public function test_actualizar_persiste_el_formato_valido(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/transparencia' );
		$peticion->set_param( 'formato', AvisoTransparenciaIa::FORMATO_EXTENDIDO );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 200, $respuesta->get_status() );
		self::assertSame( AvisoTransparenciaIa::FORMATO_EXTENDIDO, get_option( AvisoTransparenciaIa::OPCION_FORMATO ) );
	}

	public function test_actualizar_con_formato_invalido_devuelve_400(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/transparencia' );
		$peticion->set_param( 'formato', 'gigante' );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 400, $respuesta->get_status() );
	}
}
