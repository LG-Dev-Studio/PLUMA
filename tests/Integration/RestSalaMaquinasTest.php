<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Admin\RestSalaMaquinas;
use Pluma\Kernel\Activador;
use Pluma\Kernel\Nucleo;
use Pluma\Kernel\RelojSistema;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Proveedores\ProveedorCerebroRemoto;
use Pluma\Proveedores\ProveedorOpenRouter;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Sala de Máquinas (Libro Cap. 10.2) contra WordPress real: capacidad
 * propia `pluma_configurar_motor`, y la llave de OpenRouter jamás se
 * devuelve en texto plano. Sin llave configurada en wp-env, la "prueba en
 * vivo" contra `https://openrouter.ai/api/v1/key` fallará por red real en
 * este entorno — no se ejerce ese camino aquí (cubierto por Unit con un
 * doble de `wp_remote_get`).
 *
 * @covers \Pluma\Admin\RestSalaMaquinas
 */
final class RestSalaMaquinasTest extends WP_UnitTestCase {

	private function registrarRutas(): void {
		$nucleo = new Nucleo();
		$nucleo->contenedor()->obtener( RestSalaMaquinas::class )->registrar();
		do_action( 'rest_api_init' );
	}

	public function test_rechaza_a_quien_no_tiene_la_capacidad(): void {
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/estado' ) );

		self::assertContains( $respuesta->get_status(), array( 401, 403 ) );
	}

	public function test_estado_sin_llave_configurada(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		delete_option( ProveedorOpenRouter::OPCION_LLAVE_CIFRADA );

		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/estado' ) );
		$datos     = $respuesta->get_data();

		self::assertSame( 200, $respuesta->get_status() );
		self::assertFalse( $datos['openRouter']['configurada'] );
		self::assertNull( $datos['openRouter']['ultimosCuatro'] );
		self::assertFalse( $datos['openRouter']['circuitoAbierto'] );
		self::assertFalse( $datos['googleTrends']['circuitoAbierto'] );
	}

	public function test_guardar_llave_la_cifra_y_nunca_la_expone_en_texto_plano(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/llave-openrouter' );
		$peticion->set_param( 'llave', 'sk-or-v1-secreta-de-prueba' );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 200, $respuesta->get_status() );

		$sobre = get_option( ProveedorOpenRouter::OPCION_LLAVE_CIFRADA );
		self::assertIsString( $sobre );
		self::assertStringStartsWith( 'pluma_v1:', $sobre );
		self::assertStringNotContainsString( 'sk-or-v1-secreta-de-prueba', $sobre );

		$respuestaEstado = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/estado' ) );
		$datosEstado     = $respuestaEstado->get_data();

		self::assertTrue( $datosEstado['openRouter']['configurada'] );
		self::assertSame( 'ueba', $datosEstado['openRouter']['ultimosCuatro'] );
		self::assertStringNotContainsString( 'sk-or-v1-secreta-de-prueba', wp_json_encode( $datosEstado ) );
	}

	public function test_guardar_una_llave_vacia_devuelve_400(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/llave-openrouter' );
		$peticion->set_param( 'llave', '   ' );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 400, $respuesta->get_status() );
	}

	public function test_borrar_llave_la_elimina(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticionGuardar = new WP_REST_Request( 'POST', '/pluma/v1/motor/llave-openrouter' );
		$peticionGuardar->set_param( 'llave', 'sk-or-v1-para-borrar' );
		rest_get_server()->dispatch( $peticionGuardar );

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'DELETE', '/pluma/v1/motor/llave-openrouter' ) );
		self::assertSame( 200, $respuesta->get_status() );

		self::assertFalse( get_option( ProveedorOpenRouter::OPCION_LLAVE_CIFRADA ) );
	}

	public function test_actualizar_presupuesto_persiste_el_nuevo_limite(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/presupuesto' );
		$peticion->set_param( 'limiteDiarioUsd', 12.5 );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 200, $respuesta->get_status() );
		self::assertSame( 12.5, (float) get_option( PresupuestoLenguaje::OPCION_LIMITE_DIARIO ) );
	}

	public function test_actualizar_presupuesto_con_valor_negativo_devuelve_400(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/presupuesto' );
		$peticion->set_param( 'limiteDiarioUsd', -1 );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 400, $respuesta->get_status() );
	}

	public function test_bitacora_devuelve_las_ejecuciones_recientes(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'pluma_bitacora_motor',
			array(
				'iniciada_en'       => ( new RelojSistema() )->ahora()->format( 'Y-m-d H:i:s' ),
				'finalizada_en'     => ( new RelojSistema() )->ahora()->format( 'Y-m-d H:i:s' ),
				'lotes_procesados'  => 5,
				'errores'           => null,
				'candado_adquirido' => 1,
			)
		);

		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/bitacora' ) );

		self::assertSame( 200, $respuesta->get_status() );
		$lotes = array_map( static fn ( array $e ): int => $e['lotesProcesados'], $respuesta->get_data() );
		self::assertContains( 5, $lotes );
	}

	public function test_telemetria_esta_deshabilitada_por_defecto_tras_activar(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/telemetria' ) );
		$datos     = $respuesta->get_data();

		self::assertSame( 200, $respuesta->get_status() );
		self::assertFalse( $datos['habilitada'] );
		self::assertArrayHasKey( 'versionPlugin', $datos['vistaPreviaPayload'] );
		self::assertArrayNotHasKey( 'contenido', $datos['vistaPreviaPayload'] );
	}

	public function test_actualizar_telemetria_persiste_la_eleccion(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/telemetria' );
		$peticion->set_param( 'habilitada', true );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 200, $respuesta->get_status() );
		self::assertTrue( (bool) get_option( Activador::OPCION_TELEMETRIA_HABILITADA ) );

		$respuestaEstado = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/telemetria' ) );
		self::assertTrue( $respuestaEstado->get_data()['habilitada'] );
	}

	public function test_actualizar_telemetria_con_valor_no_booleano_devuelve_400(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/telemetria' );
		$peticion->set_param( 'habilitada', 'si-por-favor' );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 400, $respuesta->get_status() );
	}

	public function test_diagnostico_devuelve_entorno_conflictos_y_bitacora_sin_datos_sensibles(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$peticionLlave = new WP_REST_Request( 'POST', '/pluma/v1/motor/llave-openrouter' );
		$peticionLlave->set_param( 'llave', 'sk-or-v1-nunca-debe-salir-en-el-diagnostico' );

		$this->registrarRutas();
		rest_get_server()->dispatch( $peticionLlave );

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/diagnostico' ) );
		$datos     = $respuesta->get_data();

		self::assertSame( 200, $respuesta->get_status() );
		self::assertArrayHasKey( 'entorno', $datos );
		self::assertArrayHasKey( 'conflictos', $datos );
		self::assertArrayHasKey( 'bitacoraReciente', $datos );
		self::assertSame( PHP_VERSION, $datos['entorno']['versionPhp'] );
		self::assertStringNotContainsString( 'sk-or-v1-nunca-debe-salir-en-el-diagnostico', wp_json_encode( $datos ) );
		self::assertArrayHasKey( 'sondaCapacidades', $datos );
		self::assertArrayHasKey( 'transportePrioritario', $datos['sondaCapacidades'] );
		self::assertArrayHasKey( 'hechos', $datos['sondaCapacidades'] );
	}

	public function test_estado_incluye_cerebro_remoto_no_configurado(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		delete_option( ProveedorCerebroRemoto::OPCION_URL );
		delete_option( ProveedorCerebroRemoto::OPCION_TOKEN_CIFRADO );

		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/estado' ) );
		$datos     = $respuesta->get_data();

		self::assertSame( 200, $respuesta->get_status() );
		self::assertFalse( $datos['cerebroRemoto']['configurada'] );
		self::assertNull( $datos['cerebroRemoto']['url'] );
		self::assertFalse( $datos['cerebroRemoto']['ultimaPruebaOk'] );
	}

	/**
	 * NCP-1 · Sonda de Capacidades (`ADR 0013`): primer uso real de
	 * `ValidadorUrl::esSegura()` sobre una URL introducida por un humano —
	 * resolución DNS real del contenedor wp-env, no mockeada.
	 */
	public function test_guardar_cerebro_remoto_rechaza_url_no_https(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/cerebro-remoto' );
		$peticion->set_param( 'url', 'http://cerebro.example/salud' );
		$peticion->set_param( 'token', 'token-de-prueba' );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 400, $respuesta->get_status() );
	}

	public function test_guardar_cerebro_remoto_rechaza_url_privada(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/cerebro-remoto' );
		$peticion->set_param( 'url', 'https://127.0.0.1/salud' );
		$peticion->set_param( 'token', 'token-de-prueba' );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 400, $respuesta->get_status() );
	}

	public function test_guardar_cerebro_remoto_cifra_el_token_y_nunca_lo_expone(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		// IP pública real (Google DNS) para que ValidadorUrl::esSegura() la
		// acepte con resolución DNS real del contenedor — mismo patrón que
		// AsignadorImagenDestacadaTest::HOST.
		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/motor/cerebro-remoto' );
		$peticion->set_param( 'url', 'https://8.8.8.8/salud' );
		$peticion->set_param( 'token', 'token-secreto-de-prueba' );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 200, $respuesta->get_status() );

		$sobre = get_option( ProveedorCerebroRemoto::OPCION_TOKEN_CIFRADO );
		self::assertIsString( $sobre );
		self::assertStringStartsWith( 'pluma_v1:', $sobre );
		self::assertStringNotContainsString( 'token-secreto-de-prueba', $sobre );

		$respuestaEstado = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/estado' ) );
		$datosEstado     = $respuestaEstado->get_data();

		self::assertTrue( $datosEstado['cerebroRemoto']['configurada'] );
		self::assertSame( 'https://8.8.8.8/salud', $datosEstado['cerebroRemoto']['url'] );
		self::assertStringNotContainsString( 'token-secreto-de-prueba', wp_json_encode( $datosEstado ) );
	}

	public function test_borrar_cerebro_remoto_limpia_url_token_y_cache_de_prueba(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticionGuardar = new WP_REST_Request( 'POST', '/pluma/v1/motor/cerebro-remoto' );
		$peticionGuardar->set_param( 'url', 'https://8.8.8.8/salud' );
		$peticionGuardar->set_param( 'token', 'token-para-borrar' );
		rest_get_server()->dispatch( $peticionGuardar );

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'DELETE', '/pluma/v1/motor/cerebro-remoto' ) );
		self::assertSame( 200, $respuesta->get_status() );

		self::assertFalse( get_option( ProveedorCerebroRemoto::OPCION_URL ) );
		self::assertFalse( get_option( ProveedorCerebroRemoto::OPCION_TOKEN_CIFRADO ) );
		self::assertFalse( get_option( ProveedorCerebroRemoto::OPCION_ULTIMA_PRUEBA_OK ) );
	}

	public function test_sonda_devuelve_el_perfil_de_entorno_cacheado(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/motor/sonda' ) );
		$datos     = $respuesta->get_data();

		self::assertSame( 200, $respuesta->get_status() );
		self::assertArrayHasKey( 'transportePrioritario', $datos );
		self::assertArrayHasKey( 'medidoEn', $datos );
		self::assertArrayHasKey( 'hechos', $datos );
		self::assertArrayHasKey( 'ffiDisponible', $datos['hechos'] );
	}
}
