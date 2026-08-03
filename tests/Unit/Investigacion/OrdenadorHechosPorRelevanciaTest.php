<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Investigacion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Investigacion\OrdenadorHechosPorRelevancia;
use Pluma\Kernel\Cifrado;
use Pluma\Proveedores\ProveedorCerebroRemoto;
use Pluma\Proveedores\ProveedorRerankCerebroRemoto;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use WP_Error;

/**
 * `ProveedorRerankCerebroRemoto` es `final` (no mockeable) — se construye
 * real y se controla vía `Brain\Monkey`, mismo patrón que
 * `Pluma\Tests\Unit\Redaccion\VerificadorContradiccionNliTest`.
 *
 * @covers \Pluma\Investigacion\OrdenadorHechosPorRelevancia
 */
final class OrdenadorHechosPorRelevanciaTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'AUTH_KEY' ) ) {
			define( 'AUTH_KEY', 'clave-app-de-prueba' );
			define( 'SECURE_AUTH_KEY', 'clave-secure-de-prueba' );
		}
	}

	private function ordenador(): OrdenadorHechosPorRelevancia {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				return match ( $opcion ) {
					ProveedorCerebroRemoto::OPCION_URL => 'https://cerebro.example',
					ProveedorCerebroRemoto::OPCION_TOKEN_CIFRADO => Cifrado::cifrar( 'token' ),
					default => $defecto,
				};
			}
		);

		$rerank = new ProveedorRerankCerebroRemoto( new ProveedorCerebroRemoto() );

		return new OrdenadorHechosPorRelevancia( $rerank );
	}

	private function expediente( int $cantidadHechos ): Expediente {
		$hechos = array();

		for ( $i = 0; $i < $cantidadHechos; $i++ ) {
			$hechos[] = new HechoFuente( "hecho {$i}", "https://example.com/{$i}", new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido );
		}

		return new Expediente( 'una tendencia', $hechos, array() );
	}

	public function test_expediente_con_menos_de_dos_hechos_no_llama_a_nada(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$rerank = new ProveedorRerankCerebroRemoto( new ProveedorCerebroRemoto() );

		$original = $this->expediente( 1 );

		self::assertSame( $original, ( new OrdenadorHechosPorRelevancia( $rerank ) )->ordenar( $original ) );
	}

	public function test_una_respuesta_valida_reordena_los_hechos(): void {
		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		// El hecho 2 es el más relevante, luego el 0, luego el 1.
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '[{"index":2,"score":0.9},{"index":0,"score":0.5},{"index":1,"score":0.1}]' );

		$original   = $this->expediente( 3 );
		$reordenado = $this->ordenador()->ordenar( $original );

		self::assertSame( 'una tendencia', $reordenado->tendenciaOrigen );
		self::assertSame( 'hecho 2', $reordenado->hechos[0]->extracto );
		self::assertSame( 'hecho 0', $reordenado->hechos[1]->extracto );
		self::assertSame( 'hecho 1', $reordenado->hechos[2]->extracto );
		self::assertCount( 3, $reordenado->hechos );
	}

	public function test_fallo_del_proveedor_devuelve_el_expediente_original_sin_lanzar(): void {
		Functions\when( 'wp_remote_post' )->justReturn( new WP_Error( 'http_request_failed', 'Timeout' ) );
		Functions\when( 'is_wp_error' )->justReturn( true );

		$original  = $this->expediente( 2 );
		$resultado = $this->ordenador()->ordenar( $original );

		self::assertSame( $original, $resultado );
		self::assertCount( 2, $resultado->hechos );
	}

	public function test_sin_credenciales_devuelve_el_expediente_original_sin_lanzar(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$rerank = new ProveedorRerankCerebroRemoto( new ProveedorCerebroRemoto() );

		$original  = $this->expediente( 2 );
		$resultado = ( new OrdenadorHechosPorRelevancia( $rerank ) )->ordenar( $original );

		self::assertSame( $original, $resultado );
	}

	public function test_respuesta_con_menos_resultados_que_hechos_devuelve_el_original(): void {
		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '[{"index":0,"score":0.9}]' );

		$original  = $this->expediente( 3 );
		$resultado = $this->ordenador()->ordenar( $original );

		self::assertSame( $original, $resultado );
		self::assertCount( 3, $resultado->hechos );
	}

	public function test_respuesta_con_indices_duplicados_devuelve_el_original(): void {
		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '[{"index":0,"score":0.9},{"index":0,"score":0.5}]' );

		$original  = $this->expediente( 2 );
		$resultado = $this->ordenador()->ordenar( $original );

		self::assertSame( $original, $resultado );
	}

	public function test_respuesta_con_indice_fuera_de_rango_devuelve_el_original(): void {
		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '[{"index":0,"score":0.9},{"index":5,"score":0.5}]' );

		$original  = $this->expediente( 2 );
		$resultado = $this->ordenador()->ordenar( $original );

		self::assertSame( $original, $resultado );
	}

	public function test_huecos_detectados_se_preservan_al_reordenar(): void {
		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '[{"index":1,"score":0.9},{"index":0,"score":0.1}]' );

		$original = new Expediente(
			'una tendencia',
			array(
				new HechoFuente( 'hecho 0', 'https://example.com/0', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido ),
				new HechoFuente( 'hecho 1', 'https://example.com/1', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido ),
			),
			array( \Pluma\Investigacion\DimensionEncuadre::Legal )
		);

		$reordenado = $this->ordenador()->ordenar( $original );

		self::assertSame( array( \Pluma\Investigacion\DimensionEncuadre::Legal ), $reordenado->huecosDetectados );
	}
}
