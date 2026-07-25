<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\RepositorioPeriodistas;
use Pluma\Kernel\Activador;
use Pluma\Kernel\RelojSistema;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\DeclaracionIdentidadSintetica;
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
use Pluma\Seo\PaginaAutorPeriodista;
use WP_UnitTestCase;

/**
 * Primera página virtual del plugin (Nivel Tres N.3, Art. 50 UE): declara sin
 * ambigüedad que un periodista sintético es una identidad editorial, no una
 * persona física. Contra WordPress real (wp-env) — rewrite rule, resolución
 * de slug y 404 genuino incluidos.
 *
 * @covers \Pluma\Seo\PaginaAutorPeriodista
 */
final class PaginaAutorPeriodistaTest extends WP_UnitTestCase {

	/**
	 * `WP_Rewrite::rewrite_rules()` devuelve vacío si la estructura de enlaces
	 * permanentes está en blanco (permalinks "planos", el valor por defecto
	 * del sitio de pruebas) — ignora por completo las reglas añadidas vía
	 * `add_rewrite_rule()`. Sin una estructura real no hay manera de que la
	 * regla de la página de autor llegue a existir ni de que `go_to()` la
	 * empareje.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$wp_rewrite->flush_rules();
	}

	private function diales(): Diales {
		return new Diales( 60, 40, 20, 60, 50, 50, 60, 50 );
	}

	private function reglas(): ReglasConducta {
		return new ReglasConducta( 'Línea de prueba.', array(), array(), array(), TratamientoLector::Tu, 'Pregunta de cierre.' );
	}

	private function matriz(): MatrizTonos {
		return MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
	}

	private function crearPeriodista( string $nombre, EstadoPeriodista $estado = EstadoPeriodista::Activo ): int {
		global $wpdb;

		return ( new RepositorioPeriodistas( $wpdb ) )->crear(
			$nombre,
			null,
			'Biografía de prueba.',
			RolPeriodista::Columnista,
			array(),
			$estado,
			$this->diales(),
			$this->reglas(),
			$this->matriz(),
			( new RelojSistema() )->ahora()
		);
	}

	private function pagina(): PaginaAutorPeriodista {
		global $wpdb;

		return new PaginaAutorPeriodista( new RepositorioPeriodistas( $wpdb ), new DeclaracionIdentidadSintetica() );
	}

	public function test_activador_deja_la_regla_de_reescritura_operativa_tras_el_proximo_init(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		self::assertSame( '1', get_option( Activador::OPCION_FLUSH_REESCRITURA_PENDIENTE ) );

		$this->pagina()->registrar();
		do_action( 'init' );

		global $wp_rewrite;
		$reglas = $wp_rewrite->wp_rewrite_rules();
		self::assertIsArray( $reglas );
		self::assertArrayHasKey( '^periodista/([^/]+)/?$', $reglas );
		self::assertFalse( get_option( Activador::OPCION_FLUSH_REESCRITURA_PENDIENTE ) );
	}

	public function test_slug_de_periodista_activo_resuelve_y_expone_la_declaracion(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		$this->crearPeriodista( 'Valentina Ruiz' );

		$pagina = $this->pagina();
		$pagina->registrar();
		do_action( 'init' );

		$this->go_to( home_url( '/periodista/valentina-ruiz/' ) );
		do_action( 'template_redirect' );

		$datos = PaginaAutorPeriodista::datosParaPlantilla();

		self::assertNotNull( $datos );
		self::assertSame( 'Valentina Ruiz', $datos['periodista']->nombre );
		self::assertStringContainsString( 'Valentina Ruiz', $datos['declaracionHtml'] );
		self::assertStringContainsString( 'identidad editorial sintética', $datos['declaracionHtml'] );
		self::assertFalse( is_404() );
	}

	public function test_slug_inexistente_da_un_404_real(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$pagina = $this->pagina();
		$pagina->registrar();
		do_action( 'init' );

		$this->go_to( home_url( '/periodista/nadie-existe/' ) );
		do_action( 'template_redirect' );

		self::assertTrue( is_404() );
		self::assertNull( PaginaAutorPeriodista::datosParaPlantilla() );
	}

	public function test_periodista_jubilado_no_resuelve(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		$this->crearPeriodista( 'Marcos Iriarte', EstadoPeriodista::Jubilado );

		$pagina = $this->pagina();
		$pagina->registrar();
		do_action( 'init' );

		$this->go_to( home_url( '/periodista/marcos-iriarte/' ) );
		do_action( 'template_redirect' );

		self::assertTrue( is_404() );
	}
}
