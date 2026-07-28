<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Sensores;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Sensores\EvaluadorLegitimidadInsumo;
use Pluma\Sensores\PuntuacionOportunidad;
use Pluma\Sensores\TendenciaDetectada;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * Nivel Dos G.1 — "el modelo de amenaza del propio Radar": concentración de
 * fuente es la única pieza de G.1 implementable hoy sin inventar datos que
 * el Sensor no expone (sin timestamps por artículo, sin geografía, sin
 * novedad de cuenta — ver docblock de la clase bajo prueba).
 *
 * @covers \Pluma\Sensores\EvaluadorLegitimidadInsumo
 */
final class EvaluadorLegitimidadInsumoTest extends CasoDePruebaUnitario {

	private function tendencia( array $articulosRelacionados ): TendenciaDetectada {
		return new TendenciaDetectada(
			'tendencia de prueba',
			PuntuacionOportunidad::calcular( 80.0, 80.0 ),
			new DateTimeImmutable( '2026-07-28T12:00:00+00:00' ),
			$articulosRelacionados,
			'google_trends'
		);
	}

	private function articulo( string $fuente ): array {
		return array(
			'titulo' => 'titulo',
			'url'    => 'https://example.com/' . $fuente,
			'fuente' => $fuente,
		);
	}

	public function test_sin_articulos_relacionados_es_muestra_insuficiente_neutral(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$diagnostico = ( new EvaluadorLegitimidadInsumo() )->evaluar( $this->tendencia( array() ) );

		self::assertTrue( $diagnostico->legitimo );
		self::assertSame( 0, $diagnostico->totalArticulos );
		self::assertSame( 1.0, $diagnostico->diversidadFuente );
		self::assertNull( $diagnostico->motivo );
	}

	public function test_bajo_el_umbral_minimo_de_articulos_es_neutral_aunque_una_sola_fuente_domine(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$tendencia = $this->tendencia( array( $this->articulo( 'Reuters' ), $this->articulo( 'Reuters' ) ) );

		$diagnostico = ( new EvaluadorLegitimidadInsumo() )->evaluar( $tendencia );

		self::assertTrue( $diagnostico->legitimo );
	}

	public function test_una_sola_fuente_republicando_con_articulos_suficientes_es_sospechosa(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$tendencia = $this->tendencia(
			array( $this->articulo( 'Reuters' ), $this->articulo( 'Reuters' ), $this->articulo( 'Reuters' ) )
		);

		$diagnostico = ( new EvaluadorLegitimidadInsumo() )->evaluar( $tendencia );

		self::assertFalse( $diagnostico->legitimo );
		self::assertSame( 3, $diagnostico->totalArticulos );
		self::assertSame( 1, $diagnostico->fuentesUnicas );
		self::assertNotNull( $diagnostico->motivo );
		self::assertStringContainsString( 'Concentración de fuente', $diagnostico->motivo );
	}

	public function test_fuentes_diversas_son_legitimas(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$tendencia = $this->tendencia(
			array( $this->articulo( 'Reuters' ), $this->articulo( 'AP' ), $this->articulo( 'BBC' ) )
		);

		$diagnostico = ( new EvaluadorLegitimidadInsumo() )->evaluar( $tendencia );

		self::assertTrue( $diagnostico->legitimo );
		self::assertSame( 3, $diagnostico->fuentesUnicas );
		self::assertEqualsWithDelta( 1.0, $diagnostico->diversidadFuente, 0.001 );
	}

	public function test_umbrales_son_configurables_por_opcion(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				return match ( $opcion ) {
					EvaluadorLegitimidadInsumo::OPCION_UMBRAL_ARTICULOS_MINIMO  => 2,
					EvaluadorLegitimidadInsumo::OPCION_UMBRAL_DIVERSIDAD_MINIMA => 0.9,
					default => $defecto,
				};
			}
		);

		// 2 artículos, 2 fuentes distintas -> diversidad 1.0, pero el umbral
		// configurado exige 0.9 (pasa); con umbral aún más alto fallaría.
		$tendencia = $this->tendencia( array( $this->articulo( 'Reuters' ), $this->articulo( 'AP' ) ) );

		$diagnostico = ( new EvaluadorLegitimidadInsumo() )->evaluar( $tendencia );

		self::assertTrue( $diagnostico->legitimo );

		// Con el mismo umbral de artículos bajado a 2, dos artículos de la
		// misma fuente ya se evalúan (antes quedaban por debajo del umbral
		// de fábrica de 3) y fallan el umbral de diversidad 0.9.
		$tendenciaConcentrada = $this->tendencia( array( $this->articulo( 'Reuters' ), $this->articulo( 'Reuters' ) ) );

		$diagnosticoConcentrado = ( new EvaluadorLegitimidadInsumo() )->evaluar( $tendenciaConcentrada );

		self::assertFalse( $diagnosticoConcentrado->legitimo );
	}
}
