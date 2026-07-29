<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\RepositorioExperimentosTitular;
use Pluma\Kernel\Activador;
use Pluma\Kernel\Nucleo;
use Pluma\Kernel\RelojSistema;
use Pluma\Seo\GestorExperimentosTitular;
use WP_UnitTestCase;

/**
 * Nivel Cuatro Y.2 — el experimento de titular contra el bucle real de
 * WordPress: `the_title` sirve una de las dos variantes y registra la
 * señal correspondiente.
 *
 * @covers \Pluma\Seo\GestorExperimentosTitular
 * @covers \Pluma\Datos\RepositorioExperimentosTitular
 */
final class GestorExperimentosTitularTest extends WP_UnitTestCase {

	public function test_the_title_sirve_una_variante_y_registra_el_clic_en_la_vista_individual(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$nucleo = new Nucleo();
		$nucleo->contenedor()->obtener( GestorExperimentosTitular::class )->registrar();

		$postId = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Titular A',
			)
		);

		global $wpdb;
		$repo = new RepositorioExperimentosTitular( $wpdb );
		$repo->crear( 1, $postId, 'Titular A', 'Titular B', ( new RelojSistema() )->ahora() );

		$this->go_to( get_permalink( $postId ) );
		do_action( 'init' );

		self::assertTrue( have_posts() );
		the_post();
		$tituloServido = get_the_title();

		self::assertContains( $tituloServido, array( 'Titular A', 'Titular B' ) );

		$experimento = $repo->obtenerPorPostId( $postId );
		self::assertNotNull( $experimento );
		self::assertSame( 1, $experimento->clicsA + $experimento->clicsB );
		self::assertSame( 0, $experimento->impresionesA + $experimento->impresionesB );
	}

	public function test_sin_experimento_the_title_deja_pasar_el_titulo_original(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$nucleo = new Nucleo();
		$nucleo->contenedor()->obtener( GestorExperimentosTitular::class )->registrar();

		$postId = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Titular sin experimento',
			)
		);

		$this->go_to( get_permalink( $postId ) );
		do_action( 'init' );

		self::assertTrue( have_posts() );
		the_post();

		self::assertSame( 'Titular sin experimento', get_the_title() );
	}
}
