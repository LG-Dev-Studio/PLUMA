<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Imagen destacada por autoridad de fuente (decisión del propietario,
 * `ADR 0006`): descarga la página del artículo fuente y lee su etiqueta
 * `og:image` (con `twitter:image` como respaldo) — único punto del plugin
 * con HTTP saliente hacia el artículo original (CLAUDE.md § Ley de
 * Arquitectura).
 *
 * Mejor esfuerzo, nunca bloqueante: cualquier fallo (red, HTTP, HTML sin
 * etiqueta, URL de imagen insegura) devuelve `null`, nunca lanza. Sin
 * circuit breaker propio — a diferencia de `ProveedorGoogleTrends`, cada
 * llamada apunta a un host distinto (el artículo de esa Pieza), no a un
 * único endpoint compartido entre ejecuciones.
 */
final class ExtractorImagenFuente implements ExtractorImagenFuenteInterface {

	private const TIMEOUT_SEGUNDOS = 8;
	private const MAX_BYTES_HTML   = 500000;

	public function extraerImagenDestacada( string $urlArticulo ): ?string {
		if ( ! ValidadorUrl::esSegura( $urlArticulo ) ) {
			return null;
		}

		$respuesta = wp_remote_get(
			$urlArticulo,
			array(
				'timeout'    => self::TIMEOUT_SEGUNDOS,
				'user-agent' => 'PLUMA Engine/' . PLUMA_ENGINE_VERSION . ' (+https://github.com/jhonnfrank1995/PLUMA)',
			)
		);

		if ( is_wp_error( $respuesta ) || 200 !== wp_remote_retrieve_response_code( $respuesta ) ) {
			return null;
		}

		$html = substr( wp_remote_retrieve_body( $respuesta ), 0, self::MAX_BYTES_HTML );
		$url  = $this->extraerMetaImagen( $html );

		if ( null === $url || ! ValidadorUrl::esSegura( $url ) ) {
			return null;
		}

		return $url;
	}

	private function extraerMetaImagen( string $html ): ?string {
		foreach ( array( 'og:image', 'twitter:image' ) as $propiedad ) {
			$patrones = array(
				'/<meta[^>]+(?:property|name)=["\']' . preg_quote( $propiedad, '/' ) . '["\'][^>]+content=["\']([^"\']+)["\']/i',
				'/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']' . preg_quote( $propiedad, '/' ) . '["\']/i',
			);

			foreach ( $patrones as $patron ) {
				if ( 1 === preg_match( $patron, $html, $coincidencias ) ) {
					return html_entity_decode( $coincidencias[1], ENT_QUOTES );
				}
			}
		}

		return null;
	}
}
