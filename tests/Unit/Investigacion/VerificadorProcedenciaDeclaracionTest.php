<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Investigacion;

use Brain\Monkey\Functions;
use Pluma\Investigacion\EstadoProcedenciaDeclaracion;
use Pluma\Investigacion\VerificadorProcedenciaDeclaracion;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Investigacion\VerificadorProcedenciaDeclaracion
 */
final class VerificadorProcedenciaDeclaracionTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
	}

	public function test_un_hecho_sin_comillas_ni_verbo_de_atribucion_no_aplica(): void {
		Functions\when( 'get_option' )->justReturn( array() );

		$estado = ( new VerificadorProcedenciaDeclaracion() )->detectar( 'El ayuntamiento aprueba la partida de 4 millones', 'https://example.com/nota' );

		self::assertSame( EstadoProcedenciaDeclaracion::NoAplica, $estado );
	}

	public function test_una_cita_entre_comillas_de_un_canal_no_configurado_es_no_verificada(): void {
		Functions\when( 'get_option' )->justReturn( array() );

		$estado = ( new VerificadorProcedenciaDeclaracion() )->detectar( 'El ministro: "vamos a bajar los impuestos el próximo año"', 'https://blog-desconocido.example/nota' );

		self::assertSame( EstadoProcedenciaDeclaracion::NoVerificada, $estado );
	}

	public function test_un_verbo_de_atribucion_sin_comillas_tambien_cuenta_como_declaracion(): void {
		Functions\when( 'get_option' )->justReturn( array() );

		$estado = ( new VerificadorProcedenciaDeclaracion() )->detectar( 'El vocero afirmó que la empresa no tuvo responsabilidad en el incidente', 'https://blog-desconocido.example/nota' );

		self::assertSame( EstadoProcedenciaDeclaracion::NoVerificada, $estado );
	}

	public function test_una_declaracion_de_un_canal_oficial_configurado_es_verificada(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				if ( VerificadorProcedenciaDeclaracion::OPCION_CANALES_OFICIALES === $opcion ) {
					return array( 'gobierno.example' );
				}

				return $defecto;
			}
		);

		$estado = ( new VerificadorProcedenciaDeclaracion() )->detectar( 'El ministro: "vamos a bajar los impuestos el próximo año"', 'https://gobierno.example/comunicado' );

		self::assertSame( EstadoProcedenciaDeclaracion::VerificadaCanalOficial, $estado );
	}

	/**
	 * `PLUMA-E9-21`: la comparación de host normaliza diacríticos en ambos
	 * lados — un canal configurado con una variante acentuada calza contra
	 * la URL real sin importar cuál de las dos lleve el diacrítico.
	 */
	public function test_calza_un_canal_configurado_con_diacriticos_distintos_a_los_de_la_url(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				if ( VerificadorProcedenciaDeclaracion::OPCION_CANALES_OFICIALES === $opcion ) {
					return array( 'canal-oficiál.example' );
				}

				return $defecto;
			}
		);

		$estado = ( new VerificadorProcedenciaDeclaracion() )->detectar( 'El ministro: "vamos a bajar los impuestos el próximo año"', 'https://canal-oficial.example/comunicado' );

		self::assertSame( EstadoProcedenciaDeclaracion::VerificadaCanalOficial, $estado );
	}
}
