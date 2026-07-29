<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Admin\RestCorrecciones;
use Pluma\Datos\RepositorioCorrecciones;
use Pluma\Datos\RepositorioPiezas;
use Pluma\Datos\RepositorioTendencias;
use Pluma\Kernel\Activador;
use Pluma\Kernel\Nucleo;
use Pluma\Kernel\RelojSistema;
use Pluma\Publicacion\EstadoCorreccion;
use Pluma\Sensores\PuntuacionOportunidad;
use Pluma\Sensores\TendenciaDetectada;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Nivel Cuatro X.4 — reporte público de error + verificación humana, contra
 * WordPress real.
 *
 * @covers \Pluma\Admin\RestCorrecciones
 * @covers \Pluma\Publicacion\GestorCorrecciones
 */
final class RestCorreccionesTest extends WP_UnitTestCase {

	private function registrarRutas(): void {
		$nucleo = new Nucleo();
		$nucleo->contenedor()->obtener( RestCorrecciones::class )->registrar();
		do_action( 'rest_api_init' );
	}

	private function crearPieza(): int {
		global $wpdb;
		$reloj       = new RelojSistema();
		$tendenciaId = ( new RepositorioTendencias( $wpdb ) )->guardar(
			new TendenciaDetectada( 'tendencia corrección ' . uniqid(), PuntuacionOportunidad::calcular( 80, 60 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);

		return ( new RepositorioPiezas( $wpdb ) )->crear( $tendenciaId, $reloj->ahora() );
	}

	public function test_reportar_crea_una_correccion_pendiente(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		$this->registrarRutas();

		$piezaId  = $this->crearPieza();
		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/correcciones' );
		$peticion->set_body_params(
			array(
				'piezaId'             => $piezaId,
				'afirmacionReportada' => 'la cifra citada es incorrecta',
				'evidenciaAportada'   => 'fuente oficial con la cifra real',
			)
		);

		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 201, $respuesta->get_status() );

		global $wpdb;
		$correccion = ( new RepositorioCorrecciones( $wpdb ) )->obtenerPorId( $respuesta->get_data()['id'] );
		self::assertNotNull( $correccion );
		self::assertSame( EstadoCorreccion::Pendiente, $correccion->estado );
	}

	public function test_reportar_sin_datos_completos_devuelve_400(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/correcciones' );
		$peticion->set_body_params( array( 'piezaId' => 1 ) );

		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 400, $respuesta->get_status() );
	}

	public function test_pendientes_exige_la_capacidad_de_aprobar_piezas(): void {
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/correcciones/pendientes' ) );

		self::assertContains( $respuesta->get_status(), array( 401, 403 ) );
	}

	public function test_verificar_marca_verificada_y_escribe_el_banner(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->registrarRutas();

		$postId = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		global $wpdb;
		$reloj      = new RelojSistema();
		$piezaId    = $this->crearPieza();
		$repoPiezas = new RepositorioPiezas( $wpdb );
		$repoPiezas->actualizarPostId( $piezaId, $postId, $reloj->ahora() );

		$correccionId = ( new RepositorioCorrecciones( $wpdb ) )->crear( $piezaId, 'afirmación', 'evidencia', null, 'Lector Uno', true, $reloj->ahora() );

		$peticion  = new WP_REST_Request( 'POST', "/pluma/v1/correcciones/{$correccionId}/verificar" );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 200, $respuesta->get_status() );
		self::assertNotEmpty( get_post_meta( $postId, \Pluma\Publicacion\GestorCorrecciones::META_CORREGIDA_EN, true ) );
		self::assertSame( 'Lector Uno', get_post_meta( $postId, \Pluma\Publicacion\GestorCorrecciones::META_CREDITO_LECTOR, true ) );
	}

	public function test_verificar_una_correccion_inexistente_devuelve_404(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'POST', '/pluma/v1/correcciones/999999/verificar' ) );

		self::assertSame( 404, $respuesta->get_status() );
	}
}
