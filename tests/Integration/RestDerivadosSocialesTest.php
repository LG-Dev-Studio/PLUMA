<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Admin\RestDerivadosSociales;
use Pluma\Datos\RepositorioDerivadosSociales;
use Pluma\Kernel\Activador;
use Pluma\Kernel\Nucleo;
use Pluma\Kernel\RelojSistema;
use Pluma\Publicacion\EstadoDerivadoSocial;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Nivel Cuatro W.2 — el editor revisa cada derivado antes de usarlo.
 *
 * @covers \Pluma\Admin\RestDerivadosSociales
 */
final class RestDerivadosSocialesTest extends WP_UnitTestCase {

	private function registrarRutas(): void {
		$nucleo = new Nucleo();
		$nucleo->contenedor()->obtener( RestDerivadosSociales::class )->registrar();
		do_action( 'rest_api_init' );
	}

	public function test_listar_devuelve_solo_pendientes(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->registrarRutas();

		global $wpdb;
		$repo  = new RepositorioDerivadosSociales( $wpdb );
		$reloj = new RelojSistema();
		$id    = $repo->crear( 50, 'Extracto de prueba', 'Titular de prueba', $reloj->ahora() );

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/derivados-sociales' ) );

		self::assertSame( 200, $respuesta->get_status() );
		$ids = array_column( $respuesta->get_data(), 'id' );
		self::assertContains( $id, $ids );
	}

	public function test_aprobar_cambia_el_estado(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->registrarRutas();

		global $wpdb;
		$repo  = new RepositorioDerivadosSociales( $wpdb );
		$reloj = new RelojSistema();
		$id    = $repo->crear( 51, 'Extracto', 'Titular', $reloj->ahora() );

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'POST', "/pluma/v1/derivados-sociales/{$id}/aprobar" ) );

		self::assertSame( 200, $respuesta->get_status() );
		self::assertSame( EstadoDerivadoSocial::Aprobado, $repo->obtenerPorId( $id )->estado );
	}

	public function test_descartar_cambia_el_estado(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->registrarRutas();

		global $wpdb;
		$repo  = new RepositorioDerivadosSociales( $wpdb );
		$reloj = new RelojSistema();
		$id    = $repo->crear( 52, 'Extracto', 'Titular', $reloj->ahora() );

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'POST', "/pluma/v1/derivados-sociales/{$id}/descartar" ) );

		self::assertSame( 200, $respuesta->get_status() );
		self::assertSame( EstadoDerivadoSocial::Descartado, $repo->obtenerPorId( $id )->estado );
	}

	public function test_aprobar_un_derivado_inexistente_devuelve_404(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'POST', '/pluma/v1/derivados-sociales/999999/aprobar' ) );

		self::assertSame( 404, $respuesta->get_status() );
	}

	public function test_rechaza_a_quien_no_tiene_la_capacidad(): void {
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/derivados-sociales' ) );

		self::assertContains( $respuesta->get_status(), array( 401, 403 ) );
	}
}
