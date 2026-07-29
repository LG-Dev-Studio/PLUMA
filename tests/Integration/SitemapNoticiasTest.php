<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\RepositorioPiezas;
use Pluma\Kernel\Activador;
use Pluma\Kernel\RelojSistema;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Seo\SitemapNoticias;
use WP_UnitTestCase;

/**
 * Sitemap de noticias (`PLUMA-E3-1`), contra WordPress real — feed nativo
 * (`add_feed()`), ventana de 48h y referencia en `robots.txt`.
 *
 * @covers \Pluma\Seo\SitemapNoticias
 */
final class SitemapNoticiasTest extends WP_UnitTestCase {

	private function sitemap(): SitemapNoticias {
		global $wpdb;

		return new SitemapNoticias( new RepositorioPiezas( $wpdb ), new RelojSistema() );
	}

	public function test_el_feed_queda_registrado_tras_init(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$this->sitemap()->registrar();
		do_action( 'init' );

		self::assertTrue( has_action( 'do_feed_' . SitemapNoticias::NOMBRE_FEED ) > 0 );
	}

	public function test_renderiza_solo_las_piezas_publicadas_dentro_de_las_ultimas_48_horas(): void {
		global $wpdb;
		Activador::activar( new RelojSistema(), '0.9.0' );
		$reloj = new RelojSistema();
		$repo  = new RepositorioPiezas( $wpdb );

		$postReciente  = self::factory()->post->create(
			array(
				'post_title'  => 'Noticia reciente',
				'post_status' => 'publish',
			)
		);
		$piezaReciente = $repo->crear( 1, $reloj->ahora() );
		$repo->actualizarPostId( $piezaReciente, $postReciente, $reloj->ahora() );
		$repo->actualizarEstado( $piezaReciente, EstadoPieza::Detectada, EstadoPieza::Publicada, $reloj->ahora() );

		$postAntiguo  = self::factory()->post->create(
			array(
				'post_title'  => 'Noticia antigua fuera de ventana',
				'post_status' => 'publish',
			)
		);
		$fechaAntigua = $reloj->ahora()->modify( '-4 days' );
		$piezaAntigua = $repo->crear( 2, $fechaAntigua );
		$repo->actualizarPostId( $piezaAntigua, $postAntiguo, $fechaAntigua );
		$repo->actualizarEstado( $piezaAntigua, EstadoPieza::Detectada, EstadoPieza::Publicada, $fechaAntigua );

		$sitemap = $this->sitemap();

		ob_start();
		$sitemap->renderizar();
		$xml = (string) ob_get_clean();

		self::assertStringContainsString( 'Noticia reciente', $xml );
		self::assertStringContainsString( '<news:news>', $xml );
		self::assertStringNotContainsString( 'Noticia antigua fuera de ventana', $xml );
	}

	public function test_la_url_del_sitemap_aparece_en_robots_txt(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$sitemap = $this->sitemap();
		$sitemap->registrar();
		do_action( 'init' );

		$salida = apply_filters( 'robots_txt', "User-agent: *\n", true );

		self::assertStringContainsString( 'Sitemap:', $salida );
		self::assertStringContainsString( SitemapNoticias::NOMBRE_FEED, $salida );
	}
}
