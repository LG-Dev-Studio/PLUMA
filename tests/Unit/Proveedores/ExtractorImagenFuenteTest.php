<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Proveedores;

use Brain\Monkey\Functions;
use Pluma\Proveedores\ExtractorImagenFuente;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use WP_Error;

/**
 * Imagen destacada por autoridad de fuente (Nivel Dos, decisión del
 * propietario — `ADR 0006`).
 *
 * @covers \Pluma\Proveedores\ExtractorImagenFuente
 */
final class ExtractorImagenFuenteTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();

		// Circuit breaker por host (`PLUMA-E3-7`/`PLUMA-E8-8`): circuito
		// siempre cerrado por defecto en estos tests, que verifican solo la
		// extracción en sí, no la resiliencia (cubierta en su propio test).
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'delete_transient' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );
	}

	private function mockearUrlSegura(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		// Evita resolución DNS real (GOVERNANCE §4.4/pl-proveedor-ia §5).
		Functions\when( 'gethostbyname' )->justReturn( '93.184.216.34' );
	}

	public function test_extrae_og_image(): void {
		$this->mockearUrlSegura();
		Functions\when( 'wp_remote_get' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn(
			'<html><head><meta property="og:image" content="https://medio.example.com/foto.jpg" /></head></html>'
		);

		$url = ( new ExtractorImagenFuente() )->extraerImagenDestacada( 'https://medio.example.com/articulo' );

		self::assertSame( 'https://medio.example.com/foto.jpg', $url );
	}

	public function test_usa_twitter_image_como_respaldo_si_no_hay_og_image(): void {
		$this->mockearUrlSegura();
		Functions\when( 'wp_remote_get' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn(
			'<html><head><meta name="twitter:image" content="https://medio.example.com/twitter.jpg" /></head></html>'
		);

		$url = ( new ExtractorImagenFuente() )->extraerImagenDestacada( 'https://medio.example.com/articulo' );

		self::assertSame( 'https://medio.example.com/twitter.jpg', $url );
	}

	public function test_acepta_el_orden_inverso_de_atributos_en_la_etiqueta_meta(): void {
		$this->mockearUrlSegura();
		Functions\when( 'wp_remote_get' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn(
			'<meta content="https://medio.example.com/orden-inverso.jpg" property="og:image">'
		);

		$url = ( new ExtractorImagenFuente() )->extraerImagenDestacada( 'https://medio.example.com/articulo' );

		self::assertSame( 'https://medio.example.com/orden-inverso.jpg', $url );
	}

	public function test_devuelve_null_si_no_hay_ninguna_etiqueta_de_imagen(): void {
		$this->mockearUrlSegura();
		Functions\when( 'wp_remote_get' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '<html><head><title>Sin imagen</title></head></html>' );

		self::assertNull( ( new ExtractorImagenFuente() )->extraerImagenDestacada( 'https://medio.example.com/articulo' ) );
	}

	public function test_devuelve_null_si_la_url_del_articulo_no_pasa_la_validacion_ssrf(): void {
		Functions\when( 'wp_parse_url' )->justReturn( false );

		self::assertNull( ( new ExtractorImagenFuente() )->extraerImagenDestacada( 'http://192.168.1.1/articulo' ) );
	}

	public function test_devuelve_null_si_wp_remote_get_falla(): void {
		$this->mockearUrlSegura();
		Functions\when( 'wp_remote_get' )->justReturn( new WP_Error( 'http_request_failed', 'Timeout' ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( $valor ): bool => $valor instanceof WP_Error );

		self::assertNull( ( new ExtractorImagenFuente() )->extraerImagenDestacada( 'https://medio.example.com/articulo' ) );
	}

	public function test_devuelve_null_si_el_codigo_http_no_es_200(): void {
		$this->mockearUrlSegura();
		Functions\when( 'wp_remote_get' )->justReturn( array( 'response' => array( 'code' => 404 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 404 );

		self::assertNull( ( new ExtractorImagenFuente() )->extraerImagenDestacada( 'https://medio.example.com/articulo' ) );
	}

	/**
	 * SSRF (GOVERNANCE §3.3): una URL de imagen extraída que apunte a un
	 * rango privado nunca se devuelve, aunque la etiqueta HTML sea válida.
	 */
	public function test_devuelve_null_si_la_url_de_imagen_extraida_no_es_segura(): void {
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $componente = -1 ) {
				if ( str_contains( $url, '192.168' ) ) {
					return -1 === $componente ? array(
						'scheme' => 'https',
						'host'   => '192.168.1.1',
					) : '192.168.1.1';
				}

				return parse_url( $url, $componente ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- doble de test, no hay wp_parse_url real disponible aquí (Brain\Monkey).
			}
		);
		Functions\when( 'gethostbyname' )->alias( static fn ( string $host ) => '192.168.1.1' === $host ? $host : '93.184.216.34' );
		Functions\when( 'wp_remote_get' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn(
			'<meta property="og:image" content="http://192.168.1.1/interna.jpg" />'
		);

		self::assertNull( ( new ExtractorImagenFuente() )->extraerImagenDestacada( 'https://medio.example.com/articulo' ) );
	}

	/**
	 * Circuit breaker por host (`PLUMA-E3-7`/`PLUMA-E8-8`): un host con el
	 * circuito ya abierto ni siquiera intenta la llamada de red.
	 */
	public function test_no_intenta_la_llamada_si_el_circuito_esta_abierto_para_ese_host(): void {
		$this->mockearUrlSegura();
		Functions\when( 'get_transient' )->justReturn( true );
		Functions\expect( 'wp_remote_get' )->never();

		self::assertNull( ( new ExtractorImagenFuente() )->extraerImagenDestacada( 'https://medio.example.com/articulo' ) );
	}

	/**
	 * Al cruzar el umbral de fallos para un mismo host, el circuito se abre
	 * y se dispara la alerta una sola vez (nunca en cada fallo posterior
	 * mientras ya está abierto).
	 */
	public function test_abre_el_circuito_y_alerta_una_sola_vez_tras_el_umbral_de_fallos(): void {
		$this->mockearUrlSegura();
		Functions\when( 'wp_remote_get' )->justReturn( new WP_Error( 'http_request_failed', 'Timeout' ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( $valor ): bool => $valor instanceof WP_Error );

		$fallosPorClave  = array();
		$abiertoPorClave = array();
		Functions\when( 'get_transient' )->alias(
			static function ( string $clave ) use ( &$fallosPorClave, &$abiertoPorClave ) {
				if ( str_starts_with( $clave, 'pluma_extractor_imagen_abierto_' ) ) {
					return $abiertoPorClave[ $clave ] ?? false;
				}

				return $fallosPorClave[ $clave ] ?? false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			static function ( string $clave, $valor ) use ( &$fallosPorClave, &$abiertoPorClave ): bool {
				if ( str_starts_with( $clave, 'pluma_extractor_imagen_abierto_' ) ) {
					$abiertoPorClave[ $clave ] = $valor;
				} else {
					$fallosPorClave[ $clave ] = $valor;
				}

				return true;
			}
		);
		$alertas = array();
		Functions\when( 'do_action' )->alias(
			static function ( string $accion, ...$args ) use ( &$alertas ): void {
				if ( 'pluma/proveedor_circuito_abierto' === $accion ) {
					$alertas[] = $args;
				}
			}
		);

		$extractor = new ExtractorImagenFuente();
		self::assertNull( $extractor->extraerImagenDestacada( 'https://medio.example.com/articulo-1' ) );
		self::assertNull( $extractor->extraerImagenDestacada( 'https://medio.example.com/articulo-2' ) );

		self::assertSame( array( array( 'extractor_imagen_fuente:medio.example.com' ) ), $alertas );
	}
}
