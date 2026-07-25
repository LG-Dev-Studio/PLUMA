<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Admin\RestRiesgoLegal;
use Pluma\Compuertas\CompuertaRiesgo;
use Pluma\Compuertas\RegimenResponsabilidad;
use Pluma\Kernel\Activador;
use Pluma\Kernel\RelojSistema;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Perfil de riesgo legal (Nivel Tres N.1): el cliente declara el régimen de
 * responsabilidad de su jurisdicción real. Contra WordPress real.
 *
 * @covers \Pluma\Admin\RestRiesgoLegal
 */
final class RestRiesgoLegalTest extends WP_UnitTestCase {

	private function registrarRutas(): void {
		( new RestRiesgoLegal() )->registrar();
		do_action( 'rest_api_init' );
	}

	public function test_rechaza_a_quien_no_tiene_la_capacidad(): void {
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/riesgo-legal' ) );

		self::assertContains( $respuesta->get_status(), array( 401, 403 ) );
	}

	public function test_obtener_devuelve_civil_por_defecto(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$datos = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/riesgo-legal' ) )->get_data();

		self::assertSame( RegimenResponsabilidad::Civil->value, $datos['regimenResponsabilidad'] );
	}

	public function test_actualizar_persiste_el_regimen_valido(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/riesgo-legal' );
		$peticion->set_param( 'regimenResponsabilidad', RegimenResponsabilidad::Penal->value );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 200, $respuesta->get_status() );
		self::assertSame( RegimenResponsabilidad::Penal->value, get_option( CompuertaRiesgo::OPCION_REGIMEN_RESPONSABILIDAD ) );
	}

	public function test_actualizar_con_regimen_invalido_devuelve_400(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/riesgo-legal' );
		$peticion->set_param( 'regimenResponsabilidad', 'inventado' );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 400, $respuesta->get_status() );
	}
}
