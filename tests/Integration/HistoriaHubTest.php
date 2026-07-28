<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\RepositorioHistorias;
use Pluma\Datos\RepositorioPeriodistas;
use Pluma\Datos\RepositorioPiezas;
use Pluma\Kernel\Activador;
use Pluma\Kernel\RelojSistema;
use Pluma\Pipeline\EstadoHistoria;
use Pluma\Pipeline\GestorHistorias;
use Pluma\Pipeline\TipoPieza;
use Pluma\Seo\HistoriaHub;
use WP_UnitTestCase;

/**
 * Nivel Cuatro U.2 — el hub de historia como superficie pública: segunda
 * página virtual del plugin, mismo mecanismo que `PaginaAutorPeriodista`.
 * Contra WordPress real (wp-env) — rewrite rule, resolución por id y 404
 * genuino incluidos.
 *
 * @covers \Pluma\Seo\HistoriaHub
 */
final class HistoriaHubTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$wp_rewrite->flush_rules();
	}

	private function gestorHistorias(): GestorHistorias {
		global $wpdb;

		return new GestorHistorias( new RepositorioHistorias( $wpdb ), new RepositorioPiezas( $wpdb ), new RelojSistema() );
	}

	private function hub(): HistoriaHub {
		global $wpdb;

		return new HistoriaHub( $this->gestorHistorias(), new RepositorioPeriodistas( $wpdb ) );
	}

	public function test_activador_deja_la_regla_de_reescritura_operativa_tras_el_proximo_init(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$this->hub()->registrar();
		do_action( 'init' );

		global $wp_rewrite;
		$reglas = $wp_rewrite->wp_rewrite_rules();
		self::assertIsArray( $reglas );
		self::assertArrayHasKey( '^historia/([0-9]+)/?$', $reglas );
	}

	public function test_historia_con_dos_piezas_publicadas_resuelve_con_cronologia_completa(): void {
		global $wpdb;
		$reloj      = new RelojSistema();
		$repoPiezas = new RepositorioPiezas( $wpdb );
		$gestor     = $this->gestorHistorias();

		$historiaId = ( new RepositorioHistorias( $wpdb ) )->crear( 'Saga con cobertura', $reloj->ahora() );

		$postOriginalId = self::factory()->post->create(
			array(
				'post_title'  => 'Primera cobertura',
				'post_status' => 'publish',
			)
		);
		$postNuevoId    = self::factory()->post->create(
			array(
				'post_title'  => 'La actualización',
				'post_status' => 'publish',
			)
		);

		$piezaOriginalId = $repoPiezas->crear( 1, $reloj->ahora() );
		$repoPiezas->actualizarPostId( $piezaOriginalId, $postOriginalId, $reloj->ahora() );
		$repoPiezas->vincularHistoria( $piezaOriginalId, $historiaId, TipoPieza::Original, $reloj->ahora() );

		$piezaNuevaId = $repoPiezas->crearComoActualizacion( 1, $piezaOriginalId, $reloj->ahora() );
		$repoPiezas->actualizarPostId( $piezaNuevaId, $postNuevoId, $reloj->ahora() );
		$repoPiezas->vincularHistoria( $piezaNuevaId, $historiaId, TipoPieza::Actualizacion, $reloj->ahora() );

		( new RepositorioHistorias( $wpdb ) )->actualizarEstado( $historiaId, EstadoHistoria::EnSeguimiento, $reloj->ahora() );

		$hub = $this->hub();
		$hub->registrar();
		do_action( 'init' );

		$this->go_to( HistoriaHub::urlDe( $historiaId ) );
		do_action( 'template_redirect' );

		self::assertFalse( is_404() );

		$datos = HistoriaHub::datosParaPlantilla();

		self::assertNotNull( $datos );
		self::assertSame( 'Saga con cobertura', $datos['historia']->titulo );
		self::assertCount( 2, $datos['cronologia'] );
		self::assertSame( 'Primera cobertura', $datos['cronologia'][0]['titulo'] );
		self::assertSame( 'original', $datos['cronologia'][0]['tipo'] );
		self::assertSame( 'La actualización', $datos['cronologia'][1]['titulo'] );
		self::assertSame( 'actualizacion', $datos['cronologia'][1]['tipo'] );
	}

	public function test_historia_con_una_sola_pieza_da_404_real(): void {
		global $wpdb;
		$reloj      = new RelojSistema();
		$repoPiezas = new RepositorioPiezas( $wpdb );

		$historiaId = ( new RepositorioHistorias( $wpdb ) )->crear( 'Saga incipiente', $reloj->ahora() );

		$piezaId = $repoPiezas->crear( 2, $reloj->ahora() );
		$repoPiezas->vincularHistoria( $piezaId, $historiaId, TipoPieza::Original, $reloj->ahora() );

		$hub = $this->hub();
		$hub->registrar();
		do_action( 'init' );

		$this->go_to( HistoriaHub::urlDe( $historiaId ) );
		do_action( 'template_redirect' );

		self::assertTrue( is_404() );
		self::assertNull( HistoriaHub::datosParaPlantilla() );
	}

	public function test_id_de_historia_inexistente_da_404_real(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$hub = $this->hub();
		$hub->registrar();
		do_action( 'init' );

		$this->go_to( HistoriaHub::urlDe( 999999 ) );
		do_action( 'template_redirect' );

		self::assertTrue( is_404() );
	}
}
