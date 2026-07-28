<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Admin\RestModoRespeto;
use Pluma\Compuertas\GestorModoRespeto;
use Pluma\Datos\RepositorioModoRespeto;
use Pluma\Datos\RepositorioTendencias;
use Pluma\Kernel\Activador;
use Pluma\Kernel\RelojSistema;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Nivel Dos F.1-F.3: estado, activación manual y desactivación (con el
 * bloqueo del piso de duración mínima) contra WordPress real.
 *
 * @covers \Pluma\Admin\RestModoRespeto
 */
final class RestModoRespetoTest extends WP_UnitTestCase {

	private function registrarRutas(): RestModoRespeto {
		global $wpdb;
		$reloj       = new RelojSistema();
		$gestor      = new GestorModoRespeto( new RepositorioModoRespeto( $wpdb ), new RepositorioTendencias( $wpdb ) );
		$controlador = new RestModoRespeto( $gestor, $reloj );
		$controlador->registrar();
		do_action( 'rest_api_init' );

		return $controlador;
	}

	public function test_rechaza_a_quien_no_tiene_la_capacidad(): void {
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/modo-respeto' ) );

		self::assertContains( $respuesta->get_status(), array( 401, 403 ) );
	}

	public function test_obtener_devuelve_inactivo_por_defecto(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$datos = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/modo-respeto' ) )->get_data();

		self::assertFalse( $datos['activo'] );
	}

	public function test_activar_y_desactivar_de_punta_a_punta(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticionActivar = new WP_REST_Request( 'POST', '/pluma/v1/motor/modo-respeto/activar' );
		$peticionActivar->set_param( 'motivo', 'prueba manual' );
		$datosActivar = rest_get_server()->dispatch( $peticionActivar )->get_data();

		self::assertTrue( $datosActivar['activo'] );
		self::assertSame( 'manual', $datosActivar['activadoPor'] );
		self::assertSame( 'prueba manual', $datosActivar['motivo'] );

		$respuestaDesactivar = rest_get_server()->dispatch( new WP_REST_Request( 'POST', '/pluma/v1/motor/modo-respeto/desactivar' ) );

		// El piso de duración mínima de fábrica (>=1h) todavía no se cumplió.
		self::assertSame( 409, $respuestaDesactivar->get_status() );

		$datosEstado = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/modo-respeto' ) )->get_data();
		self::assertTrue( $datosEstado['activo'] );
	}
}
