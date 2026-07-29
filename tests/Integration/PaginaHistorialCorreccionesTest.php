<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\RepositorioCorrecciones;
use Pluma\Datos\RepositorioPiezas;
use Pluma\Kernel\Activador;
use Pluma\Kernel\RelojSistema;
use Pluma\Publicacion\EstadoCorreccion;
use Pluma\Publicacion\GestorCorrecciones;
use Pluma\Seo\PaginaHistorialCorrecciones;
use WP_UnitTestCase;

/**
 * Nivel Cuatro Z — historial público de correcciones. Contra WordPress real
 * (wp-env) — rewrite rule, resolución por query var y contenido cruzado
 * desde `pluma_correcciones` + el post real de la Pieza corregida.
 *
 * @covers \Pluma\Seo\PaginaHistorialCorrecciones
 */
final class PaginaHistorialCorreccionesTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$wp_rewrite->flush_rules();
	}

	private function gestorCorrecciones(): GestorCorrecciones {
		global $wpdb;

		return new GestorCorrecciones( new RepositorioCorrecciones( $wpdb ), new RepositorioPiezas( $wpdb ), new RelojSistema() );
	}

	private function pagina(): PaginaHistorialCorrecciones {
		global $wpdb;

		return new PaginaHistorialCorrecciones( $this->gestorCorrecciones(), new RepositorioPiezas( $wpdb ) );
	}

	public function test_activador_deja_la_regla_de_reescritura_operativa_tras_el_proximo_init(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$this->pagina()->registrar();
		do_action( 'init' );

		global $wp_rewrite;
		$reglas = $wp_rewrite->wp_rewrite_rules();
		self::assertIsArray( $reglas );
		self::assertArrayHasKey( '^correcciones/?$', $reglas );
	}

	public function test_una_correccion_verificada_con_pieza_publicada_aparece_en_el_historial(): void {
		global $wpdb;
		Activador::activar( new RelojSistema(), '0.9.0' );
		$reloj = new RelojSistema();

		$postId = self::factory()->post->create(
			array(
				'post_title'  => 'Pieza con corrección verificada',
				'post_status' => 'publish',
			)
		);

		$repoPiezas = new RepositorioPiezas( $wpdb );
		$piezaId    = $repoPiezas->crear( 1, $reloj->ahora() );
		$repoPiezas->actualizarPostId( $piezaId, $postId, $reloj->ahora() );

		$repoCorrecciones = new RepositorioCorrecciones( $wpdb );
		$correccionId     = $repoCorrecciones->crear( $piezaId, 'la cifra citada es incorrecta', 'fuente oficial con la cifra real', 'lector@example.test', 'Lector Fiel', true, $reloj->ahora() );

		$this->gestorCorrecciones()->verificar( $correccionId, 'confirmado con la fuente oficial' );

		$pagina = $this->pagina();
		$pagina->registrar();
		do_action( 'init' );

		$this->go_to( PaginaHistorialCorrecciones::url() );
		do_action( 'template_redirect' );

		self::assertFalse( is_404() );

		$entradas = PaginaHistorialCorrecciones::datosParaPlantilla();

		self::assertNotNull( $entradas );
		self::assertCount( 1, $entradas );
		self::assertSame( 'Pieza con corrección verificada', $entradas[0]['tituloPieza'] );
		self::assertSame( 'confirmado con la fuente oficial', $entradas[0]['notaEditor'] );
		self::assertSame( 'Lector Fiel', $entradas[0]['creditoLector'] );
	}

	public function test_una_correccion_pendiente_no_aparece_en_el_historial(): void {
		global $wpdb;
		Activador::activar( new RelojSistema(), '0.9.0' );
		$reloj = new RelojSistema();

		$postId = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$repoPiezas = new RepositorioPiezas( $wpdb );
		$piezaId    = $repoPiezas->crear( 2, $reloj->ahora() );
		$repoPiezas->actualizarPostId( $piezaId, $postId, $reloj->ahora() );

		( new RepositorioCorrecciones( $wpdb ) )->crear( $piezaId, 'afirmación', 'evidencia', null, null, false, $reloj->ahora() );

		$pagina = $this->pagina();
		$pagina->registrar();
		do_action( 'init' );

		$this->go_to( PaginaHistorialCorrecciones::url() );
		do_action( 'template_redirect' );

		$entradas = PaginaHistorialCorrecciones::datosParaPlantilla();

		self::assertNotNull( $entradas );
		self::assertCount( 0, $entradas );
	}

	public function test_credito_no_autorizado_no_se_muestra(): void {
		global $wpdb;
		Activador::activar( new RelojSistema(), '0.9.0' );
		$reloj = new RelojSistema();

		$postId = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$repoPiezas = new RepositorioPiezas( $wpdb );
		$piezaId    = $repoPiezas->crear( 3, $reloj->ahora() );
		$repoPiezas->actualizarPostId( $piezaId, $postId, $reloj->ahora() );

		$correccionId = ( new RepositorioCorrecciones( $wpdb ) )->crear( $piezaId, 'afirmación', 'evidencia', 'lector@example.test', 'No Mostrar', false, $reloj->ahora() );
		$this->gestorCorrecciones()->verificar( $correccionId, null );

		$pagina = $this->pagina();
		$pagina->registrar();
		do_action( 'init' );

		$this->go_to( PaginaHistorialCorrecciones::url() );
		do_action( 'template_redirect' );

		$entradas = PaginaHistorialCorrecciones::datosParaPlantilla();

		self::assertNotNull( $entradas );
		self::assertCount( 1, $entradas );
		self::assertNull( $entradas[0]['creditoLector'] );
	}

	public function test_una_peticion_ajena_no_activa_la_pagina(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$pagina = $this->pagina();
		$pagina->registrar();
		do_action( 'init' );

		$this->go_to( home_url( '/' ) );
		do_action( 'template_redirect' );

		self::assertNull( PaginaHistorialCorrecciones::datosParaPlantilla() );
	}
}
