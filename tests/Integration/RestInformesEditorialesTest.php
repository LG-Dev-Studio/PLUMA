<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use DateTimeImmutable;
use Pluma\Admin\RestInformesEditoriales;
use Pluma\Datos\RepositorioBitacora;
use Pluma\Datos\RepositorioPeriodistas;
use Pluma\Datos\RepositorioPiezas;
use Pluma\Datos\RepositorioTendencias;
use Pluma\Kernel\Activador;
use Pluma\Kernel\Nucleo;
use Pluma\Kernel\RelojSistema;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Redaccion\CandidatoTesis;
use Pluma\Redaccion\ClasificacionNoticia;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EsqueletoPieza;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\FichaDecisionEditorial;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\NovedadNoticia;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Sensores\PuntuacionOportunidad;
use Pluma\Sensores\TendenciaDetectada;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Informes editoriales semanales (Libro Cap. 14, Etapa 5) contra WordPress
 * real. Protegido con `pluma_configurar_motor` — mismo criterio que La
 * Portada: panorama agregado del motor, no una acción editorial puntual.
 *
 * @covers \Pluma\Admin\RestInformesEditoriales
 */
final class RestInformesEditorialesTest extends WP_UnitTestCase {

	private function registrarRuta(): void {
		$nucleo = new Nucleo();
		$nucleo->contenedor()->obtener( RestInformesEditoriales::class )->registrar();
		do_action( 'rest_api_init' );
	}

	private function ficha( int $periodistaId ): FichaDecisionEditorial {
		return new FichaDecisionEditorial(
			$periodistaId,
			1,
			new ClasificacionNoticia( 'economia', 30, 'x', NovedadNoticia::Primicia, 50, TipoNoticia::DatoEconomico ),
			array( new CandidatoTesis( 'la tesis elegida', 80.0, 80.0, 80.0, 80.0 ) ),
			0,
			Tono::Analitico,
			Tono::Persuasivo,
			new EsqueletoPieza( 'gancho', 'hechos', array( 'm1' ), 'contra', 'remate' ),
			new DateTimeImmutable( '2026-07-22T12:00:00+00:00' )
		);
	}

	public function test_rechaza_a_quien_no_tiene_la_capacidad(): void {
		$this->registrarRuta();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/panel/informes' ) );

		self::assertContains( $respuesta->get_status(), array( 401, 403 ) );
	}

	public function test_administrador_obtiene_el_informe_semanal_real(): void {
		Activador::activar( new RelojSistema(), '0.12.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		global $wpdb;
		$repoTendencias  = new RepositorioTendencias( $wpdb );
		$repoPiezas      = new RepositorioPiezas( $wpdb );
		$repoPeriodistas = new RepositorioPeriodistas( $wpdb );
		$repoBitacora    = new RepositorioBitacora( $wpdb );
		$reloj           = new RelojSistema();
		$ahora           = $reloj->ahora();

		$periodistaId = $repoPeriodistas->crear(
			'Valentina Ruiz',
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array(),
			EstadoPeriodista::Activo,
			new Diales( 80, 55, 40, 55, 75, 60, 60, 65 ),
			new ReglasConducta( 'linea', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' ),
			MatrizTonos::desdeFilas( array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) ) ),
			$ahora
		);

		// Una Pieza publicada esta semana, con periodista y ficha (vertical "economia").
		$tendenciaPublicadaId = $repoTendencias->guardar(
			new TendenciaDetectada( 'tendencia informe publicada ' . uniqid(), PuntuacionOportunidad::calcular( 70, 70 ), $ahora, array(), 'google_trends' ),
			$ahora
		);
		$piezaPublicadaId     = $repoPiezas->crear( $tendenciaPublicadaId, $ahora );
		$repoPiezas->asignarPeriodista( $piezaPublicadaId, $periodistaId, 1, $ahora );
		$repoPiezas->actualizarFichaDecisionEditorial( $piezaPublicadaId, $this->ficha( $periodistaId ), $ahora );
		$repoPiezas->actualizarEstado( $piezaPublicadaId, EstadoPieza::Detectada, EstadoPieza::Publicada, $ahora );

		// Una Pieza retenida esta semana.
		$tendenciaRetenidaId = $repoTendencias->guardar(
			new TendenciaDetectada( 'tendencia informe retenida ' . uniqid(), PuntuacionOportunidad::calcular( 50, 50 ), $ahora, array(), 'google_trends' ),
			$ahora
		);
		$piezaRetenidaId     = $repoPiezas->crear( $tendenciaRetenidaId, $ahora );
		$repoPiezas->actualizarEstado( $piezaRetenidaId, EstadoPieza::Detectada, EstadoPieza::Retenida, $ahora );

		// Una ejecución del motor esta semana, con un error.
		$bitacoraId = $repoBitacora->iniciarEjecucion( $ahora );
		$repoBitacora->finalizarEjecucion( $bitacoraId, $ahora, 2, array( 'fallo de proveedor' ) );

		$this->registrarRuta();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/panel/informes' ) );
		$datos     = $respuesta->get_data();

		self::assertSame( 200, $respuesta->get_status() );
		self::assertArrayHasKey( 'desde', $datos['rango'] );
		self::assertArrayHasKey( 'hasta', $datos['rango'] );

		self::assertSame( 1, $datos['piezas']['publicadas'] );

		$porPeriodista = array_column( $datos['piezas']['porPeriodista'], 'publicadas', 'periodistaId' );
		self::assertSame( 1, $porPeriodista[ $periodistaId ] );

		$porVertical = array_column( $datos['piezas']['porVertical'], 'publicadas', 'vertical' );
		self::assertSame( 1, $porVertical['economia'] );

		$idsRetenidas = array_map( static fn ( array $p ): int => $p['id'], $datos['piezas']['retenidas'] );
		self::assertContains( $piezaRetenidaId, $idsRetenidas );

		self::assertGreaterThanOrEqual( 1, $datos['tendencias']['enPipeline'] );

		self::assertGreaterThanOrEqual( 1, $datos['motor']['ejecuciones'] );
		self::assertGreaterThanOrEqual( 2, $datos['motor']['lotesProcesados'] );
		self::assertGreaterThanOrEqual( 1, $datos['motor']['ejecucionesConErrores'] );
	}
}
