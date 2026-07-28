<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\ImagenFuenteSeleccionada;
use Pluma\Investigacion\SelectorImagenPorAutoridad;

/**
 * Imagen destacada por autoridad de fuente (decisión del propietario,
 * `ADR 0006`): desvío deliberado del principio "citar y enlazar, jamás
 * reproducir" que el resto de `Pluma\Investigacion` sigue para material de
 * texto — aquí el propietario decidió transferir la imagen del artículo
 * fuente de mayor autoridad. Dos modos, ninguno activo por fábrica:
 *
 * - `Enlazada`: la imagen se incrusta en el contenido apuntando a la URL
 *   original — nunca se descarga al servidor del cliente.
 * - `Descargada`: se sube a la biblioteca de medios de WordPress
 *   (`media_sideload_image()`) y se fija como imagen destacada nativa —
 *   una copia real en el servidor del cliente, con el riesgo legal que
 *   eso implica; el cliente la activa explícitamente asumiendo ese riesgo.
 *
 * Único punto del plugin que llama `media_sideload_image()`/
 * `set_post_thumbnail()`/`wp_update_post()` para este propósito (CLAUDE.md
 * § Ley de Arquitectura: creación/edición del post WP vive en `Publicacion`).
 */
final class AsignadorImagenDestacada implements AsignadorImagenDestacadaInterface {

	public const OPCION_MODO              = 'pluma_modo_imagen_destacada';
	public const OPCION_CREDITO_VISIBLE   = 'pluma_credito_imagen_visible';
	private const MODO_DEFECTO            = 'ninguna';
	private const CREDITO_VISIBLE_DEFECTO = true;

	public function __construct( private readonly SelectorImagenPorAutoridad $selector ) {
	}

	public function asignar( int $postId, Expediente $expediente ): void {
		$modo = $this->modoConfigurado();

		if ( ModoImagenDestacada::Ninguna === $modo ) {
			return;
		}

		$imagen = $this->selector->seleccionar( $expediente );

		if ( null === $imagen ) {
			return;
		}

		if ( ModoImagenDestacada::Enlazada === $modo ) {
			$this->insertarEnlazada( $postId, $imagen );

			return;
		}

		$this->insertarDescargada( $postId, $imagen );
	}

	private function insertarEnlazada( int $postId, ImagenFuenteSeleccionada $imagen ): void {
		$bloque = '<figure class="pluma-imagen-fuente"><img src="' . esc_url( $imagen->urlImagen ) . '" alt="" /></figure>';

		$this->prependerAlContenido( $postId, $bloque . $this->bloqueCredito( $imagen ) );
	}

	private function insertarDescargada( int $postId, ImagenFuenteSeleccionada $imagen ): void {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$adjuntoId = media_sideload_image( $imagen->urlImagen, $postId, null, 'id' );

		if ( is_wp_error( $adjuntoId ) ) {
			return;
		}

		set_post_thumbnail( $postId, (int) $adjuntoId );

		$this->prependerAlContenido( $postId, $this->bloqueCredito( $imagen ) );
	}

	private function prependerAlContenido( int $postId, string $bloque ): void {
		$post = get_post( $postId );

		if ( null === $post ) {
			return;
		}

		wp_update_post(
			array(
				'ID'           => $postId,
				'post_content' => $bloque . $post->post_content,
			)
		);
	}

	private function bloqueCredito( ImagenFuenteSeleccionada $imagen ): string {
		if ( ! $this->creditoVisibleConfigurado() ) {
			return '';
		}

		$enlace = '<a href="' . esc_url( $imagen->urlArticulo ) . '" rel="nofollow noopener" target="_blank">' . esc_html( $imagen->nombreFuente ) . '</a>';

		$texto = sprintf(
			/* translators: %s: enlace con el nombre de la fuente original de la imagen */
			__( 'Imagen vía %s', 'pluma-engine' ),
			$enlace
		);

		return '<p class="pluma-credito-imagen"><small>' . $texto . '</small></p>';
	}

	private function modoConfigurado(): ModoImagenDestacada {
		$valor = get_option( self::OPCION_MODO, self::MODO_DEFECTO );

		return is_string( $valor ) ? ( ModoImagenDestacada::tryFrom( $valor ) ?? ModoImagenDestacada::Ninguna ) : ModoImagenDestacada::Ninguna;
	}

	private function creditoVisibleConfigurado(): bool {
		$valor = get_option( self::OPCION_CREDITO_VISIBLE, self::CREDITO_VISIBLE_DEFECTO );

		return (bool) $valor;
	}
}
