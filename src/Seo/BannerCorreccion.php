<?php

declare(strict_types=1);

namespace Pluma\Seo;

use Pluma\Publicacion\GestorCorrecciones;

/**
 * Nivel Cuatro X.4 — banner de corrección, ya anticipado literalmente en
 * CLAUDE.md § Ley de Arquitectura ("(opcional) banner de corrección") entre
 * las superficies de frontend público permitidas. Se antepone al contenido
 * real solo cuando la Pieza tiene una corrección verificada
 * (`GestorCorrecciones::verificar()` escribe el post meta) — nunca en el
 * resto del sitio.
 */
final class BannerCorreccion {

	public function registrar(): void {
		add_filter( 'the_content', array( $this, 'anteponerBanner' ) );
	}

	public function anteponerBanner( string $contenido ): string {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $contenido;
		}

		$postId = get_the_ID();

		if ( false === $postId ) {
			return $contenido;
		}

		$fecha = get_post_meta( $postId, GestorCorrecciones::META_CORREGIDA_EN, true );

		if ( ! is_string( $fecha ) || '' === $fecha ) {
			return $contenido;
		}

		$credito = get_post_meta( $postId, GestorCorrecciones::META_CREDITO_LECTOR, true );

		$fechaLegible = mysql2date( get_option( 'date_format', 'j F Y' ), $fecha );

		$texto = is_string( $credito ) && '' !== $credito
			? sprintf(
				/* translators: 1: fecha de la corrección, 2: nombre del lector acreditado */
				__( 'Esta pieza fue corregida el %1$s. Gracias a %2$s por señalarlo.', 'pluma-engine' ),
				$fechaLegible,
				$credito
			)
			: sprintf(
				/* translators: %s: fecha de la corrección */
				__( 'Esta pieza fue corregida el %s.', 'pluma-engine' ),
				$fechaLegible
			);

		$banner = sprintf( '<div class="pluma-banner-correccion">%s</div>', esc_html( $texto ) );

		return $banner . $contenido;
	}
}
