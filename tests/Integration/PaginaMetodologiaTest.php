<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Compuertas\CompuertaRiesgo;
use Pluma\Compuertas\GestorModoRespeto;
use Pluma\Compuertas\ModoOperacion;
use Pluma\Compuertas\RegimenResponsabilidad;
use Pluma\Datos\RepositorioColaPublicacion;
use Pluma\Datos\RepositorioModoRespeto;
use Pluma\Datos\RepositorioTendencias;
use Pluma\Kernel\Activador;
use Pluma\Kernel\AzarSistema;
use Pluma\Kernel\RelojSistema;
use Pluma\Pipeline\LectorConfiguracionCadencia;
use Pluma\Pipeline\Orquestador;
use Pluma\Pipeline\ProgramadorCadencia;
use Pluma\Seo\PaginaMetodologia;
use WP_UnitTestCase;

/**
 * Nivel Cuatro Z — la página de metodología, generada desde la
 * configuración real del sistema. Contra WordPress real (wp-env) — rewrite
 * rule, resolución por query var y contenido reflejando opciones reales.
 *
 * @covers \Pluma\Seo\PaginaMetodologia
 */
final class PaginaMetodologiaTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$wp_rewrite->flush_rules();
	}

	private function gestorModoRespeto(): GestorModoRespeto {
		global $wpdb;

		return new GestorModoRespeto(
			new RepositorioModoRespeto( $wpdb ),
			new RepositorioTendencias( $wpdb ),
			new RepositorioColaPublicacion( $wpdb ),
			new ProgramadorCadencia( new AzarSistema() ),
			new LectorConfiguracionCadencia()
		);
	}

	private function pagina(): PaginaMetodologia {
		return new PaginaMetodologia( $this->gestorModoRespeto() );
	}

	public function test_activador_deja_la_regla_de_reescritura_operativa_tras_el_proximo_init(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$this->pagina()->registrar();
		do_action( 'init' );

		global $wp_rewrite;
		$reglas = $wp_rewrite->wp_rewrite_rules();
		self::assertIsArray( $reglas );
		self::assertArrayHasKey( '^metodologia/?$', $reglas );
	}

	public function test_la_pagina_resuelve_y_refleja_la_configuracion_real(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		update_option( Orquestador::OPCION_MODO_OPERACION, ModoOperacion::Copiloto->value );
		update_option( CompuertaRiesgo::OPCION_REGIMEN_RESPONSABILIDAD, RegimenResponsabilidad::Penal->value );

		$pagina = $this->pagina();
		$pagina->registrar();
		do_action( 'init' );

		$this->go_to( PaginaMetodologia::url() );
		do_action( 'template_redirect' );

		self::assertFalse( is_404() );

		$datos = PaginaMetodologia::datosParaPlantilla();

		self::assertNotNull( $datos );
		self::assertSame( ModoOperacion::Copiloto, $datos['modoOperacion'] );
		self::assertSame( RegimenResponsabilidad::Penal, $datos['regimenResponsabilidad'] );
		self::assertFalse( $datos['modoRespetoActivo'] );
	}

	public function test_sin_configuracion_previa_usa_los_valores_por_defecto(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$pagina = $this->pagina();
		$pagina->registrar();
		do_action( 'init' );

		$this->go_to( PaginaMetodologia::url() );
		do_action( 'template_redirect' );

		$datos = PaginaMetodologia::datosParaPlantilla();

		self::assertNotNull( $datos );
		self::assertSame( ModoOperacion::Piloto, $datos['modoOperacion'] );
		self::assertSame( RegimenResponsabilidad::Civil, $datos['regimenResponsabilidad'] );
	}

	public function test_una_peticion_ajena_no_activa_la_pagina(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$pagina = $this->pagina();
		$pagina->registrar();
		do_action( 'init' );

		$this->go_to( home_url( '/' ) );
		do_action( 'template_redirect' );

		self::assertNull( PaginaMetodologia::datosParaPlantilla() );
	}

	public function test_la_plantilla_renderiza_los_textos_reales(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$pagina = $this->pagina();
		$pagina->registrar();
		do_action( 'init' );

		$this->go_to( PaginaMetodologia::url() );
		do_action( 'template_redirect' );

		ob_start();
		include PLUMA_ENGINE_DIR . 'src/Seo/templates/pagina-metodologia.php';
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'Cómo trabaja esta redacción', $html );
		self::assertStringContainsString( 'Redacción sintética', $html );
		self::assertStringContainsString( 'Presencia en superficies de inteligencia artificial', $html );
		self::assertStringContainsString( home_url( '/correcciones/' ), $html );
	}
}
