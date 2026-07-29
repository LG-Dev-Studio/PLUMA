<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

/**
 * Nivel Cuatro W.3 — la 5ª superficie de frontend público (ADR 0007): un
 * botón vía shortcode `[pluma_suscripcion]` que registra el service worker
 * de push web solo en las páginas donde el propietario del sitio decide
 * colocarlo — nunca se encola en todo el sitio (ADR 0007: "solo se sirve al
 * lector que se suscribe explícitamente").
 */
final class WidgetSuscripcionPush {

	private const SHORTCODE = 'pluma_suscripcion';
	private const HANDLE    = 'pluma-suscripcion-push';

	public function registrar(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'renderizar' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'encolarSiHaceFalta' ) );
	}

	public function encolarSiHaceFalta(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();

		if ( null === $post || ! has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			PLUMA_ENGINE_URL . 'assets/frontend/suscripcion-push.js',
			array(),
			PLUMA_ENGINE_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE,
			'plumaSuscripcionPush',
			array(
				'restUrl'       => esc_url_raw( rest_url() ),
				'swUrl'         => PLUMA_ENGINE_URL . 'assets/frontend/sw-push.js',
				'textoActivado' => __( 'Notificaciones activadas', 'pluma-engine' ),
				'textoError'    => __( 'No se pudo activar. Reintenta.', 'pluma-engine' ),
			)
		);
	}

	/**
	 * @param array<string, string>|string $atributos
	 */
	public function renderizar( $atributos ): string {
		$atributos = shortcode_atts(
			array(
				'tipo'          => TipoSuscripcion::AlertaUrgente->value,
				'referencia_id' => '',
				'vertical'      => '',
			),
			is_array( $atributos ) ? $atributos : array(),
			self::SHORTCODE
		);

		return sprintf(
			'<button type="button" class="pluma-suscripcion-push" data-tipo="%1$s" data-referencia-id="%2$s" data-vertical="%3$s">%4$s</button>',
			esc_attr( (string) $atributos['tipo'] ),
			esc_attr( (string) $atributos['referencia_id'] ),
			esc_attr( (string) $atributos['vertical'] ),
			esc_html__( 'Activar notificaciones', 'pluma-engine' )
		);
	}
}
