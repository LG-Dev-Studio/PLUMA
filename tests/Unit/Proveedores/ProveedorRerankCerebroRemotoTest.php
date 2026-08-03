<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Proveedores;

use Brain\Monkey\Functions;
use Mockery;
use Pluma\Kernel\Cifrado;
use Pluma\Proveedores\ProveedorCerebroRemoto;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Proveedores\ProveedorRerankCerebroRemoto;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use WP_Error;

/**
 * `ProveedorCerebroRemoto` es `final` (no mockeable) — se construye real y
 * se controla vía `get_option`, mismo patrón que
 * `ProveedorEmbeddingsCerebroRemotoTest`. Protocolo verificado contra un
 * servicio real Hugging Face Text Embeddings Inference en `ADR 0020` —
 * `POST {url}/rerank`, `{"query": string, "texts": [string,...], "raw_scores": bool}`,
 * respuesta `[{"index": int, "score": float}, ...]`.
 *
 * @covers \Pluma\Proveedores\ProveedorRerankCerebroRemoto
 */
final class ProveedorRerankCerebroRemotoTest extends CasoDePruebaUnitario {

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

	public function test_reordenar_sin_cerebro_remoto_configurado_lanza_excepcion(): void {
		$this->expectException( ProveedorLenguajeException::class );

		( new ProveedorRerankCerebroRemoto( $this->cerebroRemoto( null, null ) ) )->reordenar( 'consulta', array( 'texto' ) );
	}

	public function test_reordenar_devuelve_los_resultados_de_la_respuesta_real(): void {
		$cerebroRemoto = $this->cerebroRemoto( 'https://cerebro.example', 'token-de-prueba' );

		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				'https://cerebro.example/rerank',
				Mockery::on(
					static fn ( array $args ): bool => 'Bearer token-de-prueba' === $args['headers']['Authorization']
						&& '{"query":"cual es la capital","texts":["paris","madrid"],"raw_scores":false}' === $args['body']
				)
			)
			->andReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '[{"index":0,"score":0.44},{"index":1,"score":0.02}]' );

		$resultados = ( new ProveedorRerankCerebroRemoto( $cerebroRemoto ) )->reordenar( 'cual es la capital', array( 'paris', 'madrid' ) );

		self::assertCount( 2, $resultados );
		self::assertSame( 0, $resultados[0]->indice );
		self::assertSame( 0.44, $resultados[0]->puntuacion );
	}

	public function test_reordenar_lanza_excepcion_si_la_peticion_falla(): void {
		$cerebroRemoto = $this->cerebroRemoto( 'https://cerebro.example', 'token' );

		Functions\when( 'wp_remote_post' )->justReturn( new WP_Error( 'http_request_failed', 'Timeout' ) );
		Functions\when( 'is_wp_error' )->justReturn( true );

		$this->expectException( ProveedorLenguajeException::class );

		( new ProveedorRerankCerebroRemoto( $cerebroRemoto ) )->reordenar( 'consulta', array( 'texto' ) );
	}

	public function test_reordenar_lanza_excepcion_si_el_codigo_no_es_200(): void {
		$cerebroRemoto = $this->cerebroRemoto( 'https://cerebro.example', 'token' );

		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 503 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 503 );

		$this->expectException( ProveedorLenguajeException::class );

		( new ProveedorRerankCerebroRemoto( $cerebroRemoto ) )->reordenar( 'consulta', array( 'texto' ) );
	}

	public function test_reordenar_lanza_excepcion_con_respuesta_malformada(): void {
		$cerebroRemoto = $this->cerebroRemoto( 'https://cerebro.example', 'token' );

		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"no":"es una lista de resultados"}' );

		$this->expectException( ProveedorLenguajeException::class );

		( new ProveedorRerankCerebroRemoto( $cerebroRemoto ) )->reordenar( 'consulta', array( 'texto' ) );
	}
}
