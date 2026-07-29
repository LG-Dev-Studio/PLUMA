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
 * etiqueta, URL de imagen insegura) devuelve `null`, nunca lanza.
 *
 * Circuit breaker POR HOST (`PLUMA-E3-7`/`PLUMA-E8-8`), no global: a
 * diferencia de `ProveedorGoogleTrends`/`ProveedorOpenRouter`, cada llamada
 * apunta a un host distinto (el artículo de esa Pieza) — un breaker global
 * bloquearía injustamente la extracción desde una fuente sana solo porque
 * otra fuente distinta falló. Se usan `transients` (auto-expiran, sin
 * limpieza manual) con retroceso exponencial sobre el enfriamiento cuando un
 * mismo host falla repetidamente.
 */
final class ExtractorImagenFuente implements ExtractorImagenFuenteInterface {

	private const TIMEOUT_SEGUNDOS             = 8;
	private const MAX_BYTES_HTML               = 500000;
	private const UMBRAL_FALLOS                = 2;
	private const VENTANA_CONTEO_SEGUNDOS      = 3600;
	private const ENFRIAMIENTO_BASE_SEGUNDOS   = 1800;
	private const ENFRIAMIENTO_MAXIMO_SEGUNDOS = 21600;

	public function extraerImagenDestacada( string $urlArticulo ): ?string {
		if ( ! ValidadorUrl::esSegura( $urlArticulo ) ) {
			return null;
		}

		$host = (string) ( wp_parse_url( $urlArticulo, PHP_URL_HOST ) ?? '' );

		if ( '' !== $host && $this->circuitoAbiertoParaHost( $host ) ) {
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
			if ( '' !== $host ) {
				$this->registrarFallo( $host );
			}

			return null;
		}

		$html = substr( wp_remote_retrieve_body( $respuesta ), 0, self::MAX_BYTES_HTML );
		$url  = $this->extraerMetaImagen( $html );

		if ( null === $url || ! ValidadorUrl::esSegura( $url ) ) {
			return null;
		}

		if ( '' !== $host ) {
			$this->registrarExito( $host );
		}

		return $url;
	}

	private function circuitoAbiertoParaHost( string $host ): bool {
		return false !== get_transient( $this->claveAbierto( $host ) );
	}

	private function registrarFallo( string $host ): void {
		$claveFallos = $this->claveFallos( $host );
		$fallos      = ( (int) get_transient( $claveFallos ) ) + 1;
		set_transient( $claveFallos, $fallos, self::VENTANA_CONTEO_SEGUNDOS );

		if ( $fallos >= self::UMBRAL_FALLOS ) {
			$potencia     = $fallos - self::UMBRAL_FALLOS;
			$enfriamiento = min( self::ENFRIAMIENTO_BASE_SEGUNDOS * ( 2 ** $potencia ), self::ENFRIAMIENTO_MAXIMO_SEGUNDOS );

			$yaAbierto = $this->circuitoAbiertoParaHost( $host );
			set_transient( $this->claveAbierto( $host ), true, $enfriamiento );

			if ( ! $yaAbierto ) {
				// PLUMA-E3-7/E8-8: alerta una sola vez por transición cerrado→abierto.
				do_action( 'pluma/proveedor_circuito_abierto', 'extractor_imagen_fuente:' . $host );
			}
		}
	}

	private function registrarExito( string $host ): void {
		delete_transient( $this->claveFallos( $host ) );
		delete_transient( $this->claveAbierto( $host ) );
	}

	private function claveFallos( string $host ): string {
		return 'pluma_extractor_imagen_fallos_' . md5( $host );
	}

	private function claveAbierto( string $host ): string {
		return 'pluma_extractor_imagen_abierto_' . md5( $host );
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
