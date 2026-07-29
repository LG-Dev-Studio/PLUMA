<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Admin\RestPistas;
use Pluma\Datos\RepositorioPistas;
use Pluma\Kernel\Activador;
use Pluma\Kernel\Nucleo;
use Pluma\Kernel\RelojSistema;
use Pluma\Publicacion\EstadoPista;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Nivel Cuatro X.3 — el buzón de pistas, contra WordPress real.
 *
 * @covers \Pluma\Admin\RestPistas
 * @covers \Pluma\Publicacion\GestorPistas
 */
final class RestPistasTest extends WP_UnitTestCase {

	private function registrarRutas(): void {
		$nucleo = new Nucleo();
		$nucleo->contenedor()->obtener( RestPistas::class )->registrar();
		do_action( 'rest_api_init' );
	}

	public function test_reportar_crea_una_pista_pendiente(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/pistas' );
		$peticion->set_body_params(
			array(
				'historiaId' => 42,
				'contenido'  => 'creo que hay más detrás de esta historia',
			)
		);

		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 201, $respuesta->get_status() );

		global $wpdb;
		$pista = ( new RepositorioPistas( $wpdb ) )->obtenerPorId( $respuesta->get_data()['id'] );
		self::assertNotNull( $pista );
		self::assertSame( EstadoPista::Pendiente, $pista->estado );
	}

	public function test_reportar_sin_contenido_devuelve_400(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/pistas' );
		$peticion->set_body_params( array( 'historiaId' => 42 ) );

		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 400, $respuesta->get_status() );
	}

	public function test_pendientes_exige_la_capacidad_de_aprobar_piezas(): void {
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/pistas/pendientes' ) );

		self::assertContains( $respuesta->get_status(), array( 401, 403 ) );
	}

	public function test_revisar_marca_el_estado(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->registrarRutas();

		global $wpdb;
		$repo = new RepositorioPistas( $wpdb );
		$id   = $repo->crear( 42, 'contenido', null, ( new RelojSistema() )->ahora() );

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'POST', "/pluma/v1/pistas/{$id}/revisar" ) );

		self::assertSame( 200, $respuesta->get_status() );
		self::assertSame( EstadoPista::Revisada, $repo->obtenerPorId( $id )->estado );
	}

	public function test_revisar_una_pista_inexistente_devuelve_404(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'POST', '/pluma/v1/pistas/999999/revisar' ) );

		self::assertSame( 404, $respuesta->get_status() );
	}
}
