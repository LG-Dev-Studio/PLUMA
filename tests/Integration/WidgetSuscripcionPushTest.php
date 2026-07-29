<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Publicacion\WidgetSuscripcionPush;
use WP_UnitTestCase;

/**
 * Nivel Cuatro W.3 — la 5ª superficie de frontend público (ADR 0007): el
 * shortcode solo encola sus assets en páginas que lo llevan.
 *
 * @covers \Pluma\Publicacion\WidgetSuscripcionPush
 */
final class WidgetSuscripcionPushTest extends WP_UnitTestCase {

	public function test_el_shortcode_renderiza_un_boton_con_los_atributos(): void {
		( new WidgetSuscripcionPush() )->registrar();

		$html = do_shortcode( '[pluma_suscripcion tipo="vertical" vertical="tecnologia"]' );

		self::assertStringContainsString( 'pluma-suscripcion-push', $html );
		self::assertStringContainsString( 'data-tipo="vertical"', $html );
		self::assertStringContainsString( 'data-vertical="tecnologia"', $html );
	}

	public function test_encola_los_assets_solo_en_una_pagina_con_el_shortcode(): void {
		( new WidgetSuscripcionPush() )->registrar();

		$postConShortcode = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => '[pluma_suscripcion]',
			)
		);
		$postSinShortcode = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->go_to( get_permalink( $postConShortcode ) );
		do_action( 'wp_enqueue_scripts' );
		self::assertTrue( wp_script_is( 'pluma-suscripcion-push', 'enqueued' ) );

		wp_dequeue_script( 'pluma-suscripcion-push' );

		$this->go_to( get_permalink( $postSinShortcode ) );
		do_action( 'wp_enqueue_scripts' );
		self::assertFalse( wp_script_is( 'pluma-suscripcion-push', 'enqueued' ) );
	}
}
