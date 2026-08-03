<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Proveedores;

use Brain\Monkey\Functions;
use Mockery;
use Pluma\Kernel\Cifrado;
use Pluma\Proveedores\ProveedorCerebroRemoto;
use Pluma\Proveedores\ProveedorEmbeddingsCerebroRemoto;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use WP_Error;

/**
 * `ProveedorCerebroRemoto` es `final` (no mockeable) — se construye real y
 * se controla vía `get_option`, mismo patrón que `SensorCapacidadesTest`.
 * Protocolo verificado contra un servicio real Hugging Face Text
 * Embeddings Inference en `ADR 0016` — `POST {url}/embed`,
 * `{"inputs": "texto"}`, respuesta `[[float,...]]`.
 *
 * @covers \Pluma\Proveedores\ProveedorEmbeddingsCerebroRemoto
 */
final class ProveedorEmbeddingsCerebroRemotoTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'AUTH_KEY' ) ) {
			define( 'AUTH_KEY', 'clave-app-de-prueba' );
			define( 'SECURE_AUTH_KEY', 'clave-secure-de-prueba' );
		}
	}

	private function cerebroRemoto( ?string $url, ?string $token ): ProveedorCerebroRemoto {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) use ( $url, $token ) {
				return match ( $opcion ) {
					ProveedorCerebroRemoto::OPCION_URL => $url ?? false,
					ProveedorCerebroRemoto::OPCION_TOKEN_CIFRADO => null !== $token ? Cifrado::cifrar( $token ) : false,
					default => $defecto,
				};
			}
		);

		return new ProveedorCerebroRemoto();
	}

	public function test_embed_sin_cerebro_remoto_configurado_lanza_excepcion(): void {
		$this->expectException( ProveedorLenguajeException::class );

		( new ProveedorEmbeddingsCerebroRemoto( $this->cerebroRemoto( null, null ) ) )->embed( 'texto' );
	}

	public function test_embed_devuelve_el_vector_de_la_respuesta_real(): void {
		$cerebroRemoto = $this->cerebroRemoto( 'https://cerebro.example', 'token-de-prueba' );

		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				'https://cerebro.example/embed',
				Mockery::on(
					static fn ( array $args ): bool => 'Bearer token-de-prueba' === $args['headers']['Authorization']
						&& '{"inputs":"query: el gato duerme"}' === $args['body']
				)
			)
			->andReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '[[0.1,0.2,0.3]]' );

		$vector = ( new ProveedorEmbeddingsCerebroRemoto( $cerebroRemoto ) )->embed( 'query: el gato duerme' );

		self::assertSame( array( 0.1, 0.2, 0.3 ), $vector );
	}

	public function test_embed_normaliza_una_url_con_barra_final(): void {
		$cerebroRemoto = $this->cerebroRemoto( 'https://cerebro.example/', 'token' );

		Functions\expect( 'wp_remote_post' )
			->once()
			->with( 'https://cerebro.example/embed', Mockery::type( 'array' ) )
			->andReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '[[0.5]]' );

		( new ProveedorEmbeddingsCerebroRemoto( $cerebroRemoto ) )->embed( 'texto' );

		$this->expectNotToPerformAssertions();
	}

	public function test_embed_lanza_excepcion_si_la_peticion_falla(): void {
		$cerebroRemoto = $this->cerebroRemoto( 'https://cerebro.example', 'token' );

		Functions\when( 'wp_remote_post' )->justReturn( new WP_Error( 'http_request_failed', 'Timeout' ) );
		Functions\when( 'is_wp_error' )->justReturn( true );

		$this->expectException( ProveedorLenguajeException::class );

		( new ProveedorEmbeddingsCerebroRemoto( $cerebroRemoto ) )->embed( 'texto' );
	}

	public function test_embed_lanza_excepcion_si_el_codigo_no_es_200(): void {
		$cerebroRemoto = $this->cerebroRemoto( 'https://cerebro.example', 'token' );

		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 503 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 503 );

		$this->expectException( ProveedorLenguajeException::class );

		( new ProveedorEmbeddingsCerebroRemoto( $cerebroRemoto ) )->embed( 'texto' );
	}

	public function test_embed_lanza_excepcion_con_respuesta_malformada(): void {
		$cerebroRemoto = $this->cerebroRemoto( 'https://cerebro.example', 'token' );

		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"no":"es un array de vectores"}' );

		$this->expectException( ProveedorLenguajeException::class );

		( new ProveedorEmbeddingsCerebroRemoto( $cerebroRemoto ) )->embed( 'texto' );
	}
}
