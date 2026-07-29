<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Admin\RestBoletines;
use Pluma\Datos\RepositorioPeriodistas;
use Pluma\Kernel\Activador;
use Pluma\Kernel\Nucleo;
use Pluma\Kernel\RelojSistema;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Nivel Cuatro W.1 — el boletín como producto del periodista, de punta a
 * punta contra WordPress real: composición automática (proveedor de
 * lenguaje falso vía el contenedor no aplica aquí — se verifica que la
 * ruta delega correctamente y persiste/despacha), disparo manual del
 * editor.
 *
 * @covers \Pluma\Admin\RestBoletines
 * @covers \Pluma\Publicacion\GestorBoletines
 */
final class RestBoletinesTest extends WP_UnitTestCase {

	private function registrarRutas(): void {
		$nucleo = new Nucleo();
		$nucleo->contenedor()->obtener( RestBoletines::class )->registrar();
		do_action( 'rest_api_init' );
	}

	private function crearPeriodista(): int {
		global $wpdb;
		$repo  = new RepositorioPeriodistas( $wpdb );
		$reloj = new RelojSistema();

		$diales = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas = new ReglasConducta( 'linea', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);

		return $repo->crear( 'Periodista de boletín ' . uniqid(), null, 'Bio.', RolPeriodista::Columnista, array(), EstadoPeriodista::Activo, $diales, $reglas, $matriz, $reloj->ahora() );
	}

	public function test_enviar_boletin_de_un_periodista_sin_piezas_no_falla(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->registrarRutas();

		$periodistaId = $this->crearPeriodista();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'POST', "/pluma/v1/boletines/{$periodistaId}/enviar" ) );

		self::assertSame( 200, $respuesta->get_status() );
		self::assertSame( 0, $respuesta->get_data()['piezas'] );
	}

	public function test_enviar_boletin_de_un_periodista_inexistente_devuelve_404(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'POST', '/pluma/v1/boletines/999999/enviar' ) );

		self::assertSame( 404, $respuesta->get_status() );
	}

	public function test_rechaza_a_quien_no_tiene_la_capacidad(): void {
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'POST', '/pluma/v1/boletines/1/enviar' ) );

		self::assertContains( $respuesta->get_status(), array( 401, 403 ) );
	}
}
