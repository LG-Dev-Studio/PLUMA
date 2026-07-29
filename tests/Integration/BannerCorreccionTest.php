<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Publicacion\GestorCorrecciones;
use Pluma\Seo\BannerCorreccion;
use WP_UnitTestCase;

/**
 * Nivel Cuatro X.4 — banner de corrección, ya anticipado por CLAUDE.md §
 * Ley de Arquitectura, contra el bucle real de WordPress.
 *
 * @covers \Pluma\Seo\BannerCorreccion
 */
final class BannerCorreccionTest extends WP_UnitTestCase {

	public function test_antepone_el_banner_dentro_del_bucle_principal_cuando_la_pieza_esta_corregida(): void {
		( new BannerCorreccion() )->registrar();

		$postId = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => 'Contenido original de la pieza.',
			)
		);
		update_post_meta( $postId, GestorCorrecciones::META_CORREGIDA_EN, '2026-07-20 10:00:00' );
		update_post_meta( $postId, GestorCorrecciones::META_CREDITO_LECTOR, 'Lector Uno' );

		$this->go_to( get_permalink( $postId ) );

		self::assertTrue( have_posts() );
		the_post();
		$html = apply_filters( 'the_content', get_the_content() );

		self::assertStringContainsString( 'pluma-banner-correccion', $html );
		self::assertStringContainsString( 'Lector Uno', $html );
		self::assertStringContainsString( 'Contenido original de la pieza.', $html );
	}

	public function test_no_antepone_banner_a_una_pieza_sin_corregir(): void {
		( new BannerCorreccion() )->registrar();

		$postId = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => 'Contenido sin corregir.',
			)
		);

		$this->go_to( get_permalink( $postId ) );

		self::assertTrue( have_posts() );
		the_post();
		$html = apply_filters( 'the_content', get_the_content() );

		self::assertStringNotContainsString( 'pluma-banner-correccion', $html );
	}
}
