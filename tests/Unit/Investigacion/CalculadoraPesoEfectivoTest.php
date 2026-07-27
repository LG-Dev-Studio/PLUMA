<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Investigacion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Investigacion\CalculadoraPesoEfectivo;
use Pluma\Investigacion\ClasificadorNivelFuente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Investigacion\CalculadoraPesoEfectivo
 */
final class CalculadoraPesoEfectivoTest extends CasoDePruebaUnitario {

	private function mockearHost( string $urlEsperada, string $host ): void {
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url ) use ( $urlEsperada, $host ) {
				return $url === $urlEsperada ? $host : null;
			}
		);
	}

	public function test_hecho_de_nivel_a_sin_fuente_configurada_da_peso_base_de_nivel_c(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		$this->mockearHost( 'https://desconocido.example/nota', 'desconocido.example' );

		$hecho = new HechoFuente( 'un extracto largo suficiente para generar varios ngramas de prueba real', 'https://desconocido.example/nota', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido );

		$peso = ( new CalculadoraPesoEfectivo( new ClasificadorNivelFuente() ) )->calcular( $hecho, array( $hecho ) );

		self::assertEqualsWithDelta( 0.15, $peso, 0.0001 );
	}

	public function test_hecho_de_nivel_a_configurado_da_peso_base_completo(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				if ( ClasificadorNivelFuente::OPCION_FUENTES_NIVEL_A === $opcion ) {
					return array( 'reuters.com' );
				}

				return $defecto;
			}
		);
		$this->mockearHost( 'https://reuters.com/nota', 'reuters.com' );

		$hecho = new HechoFuente( 'un extracto largo suficiente para generar varios ngramas de prueba real', 'https://reuters.com/nota', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido );

		$peso = ( new CalculadoraPesoEfectivo( new ClasificadorNivelFuente() ) )->calcular( $hecho, array( $hecho ) );

		self::assertEqualsWithDelta( 1.0, $peso, 0.0001 );
	}

	/**
	 * Nivel Dos B.3, factor_independencia: dos hechos con el mismo texto
	 * extenso (>= 8 palabras compartidas) de fuentes distintas se tratan
	 * como no independientes — cadena de citación probable.
	 */
	public function test_dos_hechos_con_texto_compartido_de_fuentes_distintas_reducen_el_factor_independencia(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				if ( ClasificadorNivelFuente::OPCION_FUENTES_NIVEL_A === $opcion ) {
					return array( 'medio-a.example', 'medio-b.example' );
				}

				return $defecto;
			}
		);
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url ) {
				return match ( $url ) {
					'https://medio-a.example/nota' => 'medio-a.example',
					'https://medio-b.example/nota' => 'medio-b.example',
					default => null,
				};
			}
		);

		$textoCompartido = 'la agencia oficial reportó un incremento significativo en la producción industrial este trimestre';
		$hechoA          = new HechoFuente( $textoCompartido, 'https://medio-a.example/nota', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido );
		$hechoB          = new HechoFuente( $textoCompartido, 'https://medio-b.example/nota', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido );

		$peso = ( new CalculadoraPesoEfectivo( new ClasificadorNivelFuente() ) )->calcular( $hechoA, array( $hechoA, $hechoB ) );

		// nivel_fuente_base (1.0, nivel A) × decaimiento (1.0) × factor_independencia (0.5, texto compartido).
		self::assertEqualsWithDelta( 0.5, $peso, 0.0001 );
	}

	public function test_hechos_independientes_sin_texto_compartido_mantienen_el_factor_completo(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				if ( ClasificadorNivelFuente::OPCION_FUENTES_NIVEL_A === $opcion ) {
					return array( 'medio-a.example', 'medio-b.example' );
				}

				return $defecto;
			}
		);
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url ) {
				return match ( $url ) {
					'https://medio-a.example/nota' => 'medio-a.example',
					'https://medio-b.example/nota' => 'medio-b.example',
					default => null,
				};
			}
		);

		$hechoA = new HechoFuente( 'la agencia oficial reportó un incremento significativo en la producción industrial', 'https://medio-a.example/nota', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido );
		$hechoB = new HechoFuente( 'un vocero del sindicato calificó de insuficiente el aumento salarial anunciado ayer', 'https://medio-b.example/nota', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido );

		$peso = ( new CalculadoraPesoEfectivo( new ClasificadorNivelFuente() ) )->calcular( $hechoA, array( $hechoA, $hechoB ) );

		self::assertEqualsWithDelta( 1.0, $peso, 0.0001 );
	}
}
