<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Admin\RestCalendarioEditorial;
use Pluma\Datos\RepositorioEventosProgramados;
use Pluma\Datos\RepositorioHistorias;
use Pluma\Datos\RepositorioPiezas;
use Pluma\Kernel\Activador;
use Pluma\Kernel\Nucleo;
use Pluma\Kernel\RelojSistema;
use Pluma\Pipeline\EstadoEventoProgramado;
use Pluma\Pipeline\TipoPieza;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Calendario Editorial contra WordPress real (wp-env): capacidad propia
 * `pluma_aprobar_piezas`, V.1 (agenda) + V.2 (la pieza preparada) de punta
 * a punta, incluyendo el enlace real a la Historia y a la Pieza `Previa`
 * creada vía la tendencia sintética.
 *
 * @covers \Pluma\Admin\RestCalendarioEditorial
 * @covers \Pluma\Pipeline\GestorCalendarioEditorial
 */
final class RestCalendarioEditorialTest extends WP_UnitTestCase {

	private function registrarRutas(): void {
		$nucleo = new Nucleo();
		$nucleo->contenedor()->obtener( RestCalendarioEditorial::class )->registrar();
		do_action( 'rest_api_init' );
	}

	public function test_rechaza_a_quien_no_tiene_la_capacidad(): void {
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/calendario-editorial' ) );

		self::assertContains( $respuesta->get_status(), array( 401, 403 ) );
	}

	public function test_crear_lista_y_prepara_cobertura_de_punta_a_punta(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticionCrear = new WP_REST_Request( 'POST', '/pluma/v1/calendario-editorial' );
		$peticionCrear->set_body_params(
			array(
				'titulo'        => 'Elecciones generales de prueba',
				'vertical'      => 'politica',
				'fechaEsperada' => '2026-11-15T00:00:00+00:00',
			)
		);

		$respuestaCrear = rest_get_server()->dispatch( $peticionCrear );
		self::assertSame( 201, $respuestaCrear->get_status() );
		$eventoId = $respuestaCrear->get_data()['eventoId'];
		self::assertIsInt( $eventoId );

		$respuestaListar = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/calendario-editorial' ) );
		self::assertSame( 200, $respuestaListar->get_status() );
		$evento = $this->eventoDe( $respuestaListar->get_data(), $eventoId );
		self::assertNotNull( $evento );
		self::assertSame( 'previsto', $evento['estado'] );
		self::assertNull( $evento['historiaId'] );

		$peticionPreparar = new WP_REST_Request( 'POST', "/pluma/v1/calendario-editorial/{$eventoId}/preparar" );
		$peticionPreparar->set_body_params(
			array(
				'articulosRelacionados' => array(
					array(
						'titulo' => 'Encuestas previas a la elección',
						'url'    => 'https://example.test/encuestas',
						'fuente' => 'Diario de prueba',
					),
				),
			)
		);

		$respuestaPreparar = rest_get_server()->dispatch( $peticionPreparar );
		self::assertSame( 200, $respuestaPreparar->get_status() );
		$piezaId = $respuestaPreparar->get_data()['piezaId'];
		self::assertIsInt( $piezaId );

		global $wpdb;
		$evento = ( new RepositorioEventosProgramados( $wpdb ) )->obtenerPorId( $eventoId );
		self::assertNotNull( $evento );
		self::assertSame( EstadoEventoProgramado::Preparado, $evento->estado );
		self::assertNotNull( $evento->historiaId );
		self::assertNotNull( $evento->tendenciaId );

		$pieza = ( new RepositorioPiezas( $wpdb ) )->obtenerPorId( $piezaId );
		self::assertNotNull( $pieza );
		self::assertSame( TipoPieza::Previa, $pieza->tipo );
		self::assertSame( $evento->historiaId, $pieza->historiaId );

		$historia = ( new RepositorioHistorias( $wpdb ) )->obtenerPorId( $evento->historiaId );
		self::assertNotNull( $historia );
		self::assertSame( 'Elecciones generales de prueba', $historia->titulo );

		$respuestaEnCurso = rest_get_server()->dispatch( new WP_REST_Request( 'POST', "/pluma/v1/calendario-editorial/{$eventoId}/marcar-en-curso" ) );
		self::assertSame( 200, $respuestaEnCurso->get_status() );

		$respuestaCubierto = rest_get_server()->dispatch( new WP_REST_Request( 'POST', "/pluma/v1/calendario-editorial/{$eventoId}/marcar-cubierto" ) );
		self::assertSame( 200, $respuestaCubierto->get_status() );

		$evento = ( new RepositorioEventosProgramados( $wpdb ) )->obtenerPorId( $eventoId );
		self::assertNotNull( $evento );
		self::assertSame( EstadoEventoProgramado::Cubierto, $evento->estado );
	}

	public function test_preparar_sin_fuentes_devuelve_400(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticionCrear = new WP_REST_Request( 'POST', '/pluma/v1/calendario-editorial' );
		$peticionCrear->set_body_params(
			array(
				'titulo'        => 'Evento sin fuentes',
				'vertical'      => 'deportes',
				'fechaEsperada' => '2026-12-01T00:00:00+00:00',
			)
		);
		$eventoId = rest_get_server()->dispatch( $peticionCrear )->get_data()['eventoId'];

		$peticionPreparar = new WP_REST_Request( 'POST', "/pluma/v1/calendario-editorial/{$eventoId}/preparar" );
		$peticionPreparar->set_body_params( array( 'articulosRelacionados' => array() ) );

		$respuesta = rest_get_server()->dispatch( $peticionPreparar );

		self::assertSame( 400, $respuesta->get_status() );
	}

	public function test_un_evento_inexistente_devuelve_404(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/calendario-editorial/999999/marcar-en-curso' );

		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 404, $respuesta->get_status() );
	}

	/**
	 * @param list<array<string, mixed>> $eventos
	 * @return array<string, mixed>|null
	 */
	private function eventoDe( array $eventos, int $eventoId ): ?array {
		foreach ( $eventos as $evento ) {
			if ( $evento['id'] === $eventoId ) {
				return $evento;
			}
		}

		return null;
	}
}
