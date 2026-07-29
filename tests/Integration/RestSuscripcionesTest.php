<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Admin\RestSuscripciones;
use Pluma\Datos\RepositorioSuscriptores;
use Pluma\Kernel\Activador;
use Pluma\Kernel\Nucleo;
use Pluma\Kernel\RelojSistema;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Suscripciones de precisión contra WordPress real (wp-env): doble opt-in,
 * baja de un clic, RGPD (`PLUMA-EV-2`) — Nivel Cuatro W.3.
 *
 * @covers \Pluma\Admin\RestSuscripciones
 * @covers \Pluma\Publicacion\GestorSuscripciones
 */
final class RestSuscripcionesTest extends WP_UnitTestCase {

	private function registrarRutas(): void {
		$nucleo = new Nucleo();
		$nucleo->contenedor()->obtener( RestSuscripciones::class )->registrar();
		do_action( 'rest_api_init' );
	}

	public function test_suscribirse_crea_una_fila_pendiente_de_confirmar(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/suscripciones' );
		$peticion->set_body_params(
			array(
				'email'        => 'lector@example.test',
				'tipo'         => 'periodista',
				'referenciaId' => 7,
			)
		);

		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 201, $respuesta->get_status() );

		global $wpdb;
		$suscripciones = ( new RepositorioSuscriptores( $wpdb ) )->obtenerPorEmail( 'lector@example.test' );
		self::assertCount( 1, $suscripciones );
		self::assertFalse( $suscripciones[0]->confirmado );
	}

	public function test_confirmar_y_luego_baja_de_un_clic(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		$this->registrarRutas();

		global $wpdb;
		$repo  = new RepositorioSuscriptores( $wpdb );
		$reloj = new RelojSistema();

		$token = bin2hex( random_bytes( 32 ) );
		$repo->crearEmail( \Pluma\Publicacion\TipoSuscripcion::Vertical, null, 'tecnologia', 'confirmar@example.test', $token, $reloj->ahora() );

		$respuestaConfirmar = rest_get_server()->dispatch( new WP_REST_Request( 'GET', "/pluma/v1/suscripciones/confirmar/{$token}" ) );
		self::assertSame( 200, $respuestaConfirmar->get_status() );

		$suscriptor = $repo->obtenerPorToken( $token );
		self::assertNotNull( $suscriptor );
		self::assertTrue( $suscriptor->confirmado );

		$respuestaBaja = rest_get_server()->dispatch( new WP_REST_Request( 'GET', "/pluma/v1/suscripciones/baja/{$token}" ) );
		self::assertSame( 200, $respuestaBaja->get_status() );
		self::assertNull( $repo->obtenerPorToken( $token ) );
	}

	public function test_clave_publica_devuelve_la_clave_vapid_generada_en_activacion(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/suscripciones/clave-publica' ) );

		self::assertSame( 200, $respuesta->get_status() );
		self::assertIsString( $respuesta->get_data()['clavePublica'] );
		self::assertNotSame( '', $respuesta->get_data()['clavePublica'] );
	}

	public function test_suscribirse_push_crea_una_fila_confirmada_de_inmediato(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/suscripciones/push' );
		$peticion->set_body_params(
			array(
				'tipo'     => 'alerta_urgente',
				'endpoint' => 'https://push.example.test/endpoint',
				'claves'   => array(
					'p256dh' => 'clave-p256dh',
					'auth'   => 'clave-auth',
				),
			)
		);

		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 201, $respuesta->get_status() );

		global $wpdb;
		$suscriptores = ( new RepositorioSuscriptores( $wpdb ) )->obtenerConfirmadosPorObjetivo(
			\Pluma\Publicacion\CanalSuscripcion::Push,
			\Pluma\Publicacion\TipoSuscripcion::AlertaUrgente,
			null,
			null
		);

		self::assertCount( 1, $suscriptores );
		self::assertTrue( $suscriptores[0]->confirmado );
	}

	public function test_confirmar_con_token_invalido_devuelve_404(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		$this->registrarRutas();

		$tokenInexistente = str_repeat( 'a', 64 );
		$respuesta        = rest_get_server()->dispatch( new WP_REST_Request( 'GET', "/pluma/v1/suscripciones/confirmar/{$tokenInexistente}" ) );

		self::assertSame( 404, $respuesta->get_status() );
	}

	public function test_listar_exige_la_capacidad_de_aprobar_piezas(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/suscripciones/listado' ) );

		self::assertContains( $respuesta->get_status(), array( 401, 403 ) );
	}

	public function test_exportar_y_borrar_por_email_requieren_capacidad_y_funcionan(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->registrarRutas();

		global $wpdb;
		$repo  = new RepositorioSuscriptores( $wpdb );
		$reloj = new RelojSistema();
		$email = 'rgpd-rest@example.test';
		$repo->crearEmail( \Pluma\Publicacion\TipoSuscripcion::Periodista, 1, null, $email, bin2hex( random_bytes( 32 ) ), $reloj->ahora() );

		$peticionExportar = new WP_REST_Request( 'POST', '/pluma/v1/suscripciones/exportar' );
		$peticionExportar->set_body_params( array( 'email' => $email ) );
		$respuestaExportar = rest_get_server()->dispatch( $peticionExportar );

		self::assertSame( 200, $respuestaExportar->get_status() );
		self::assertCount( 1, $respuestaExportar->get_data() );

		$peticionBorrar = new WP_REST_Request( 'POST', '/pluma/v1/suscripciones/borrar' );
		$peticionBorrar->set_body_params( array( 'email' => $email ) );
		$respuestaBorrar = rest_get_server()->dispatch( $peticionBorrar );

		self::assertSame( 200, $respuestaBorrar->get_status() );
		self::assertSame( 1, $respuestaBorrar->get_data()['borradas'] );
		self::assertCount( 0, $repo->obtenerPorEmail( $email ) );
	}
}
