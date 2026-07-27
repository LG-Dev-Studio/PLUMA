<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Redaccion\SegmentadorUnidadesFactuales;
use Pluma\Redaccion\VerificadorTrazabilidadDeterminista;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\EmbeddingsFalso;

/**
 * @covers \Pluma\Redaccion\VerificadorTrazabilidadDeterminista
 */
final class VerificadorTrazabilidadDeterministaTest extends CasoDePruebaUnitario {

	private function expediente(): Expediente {
		return new Expediente(
			'una tendencia',
			array( new HechoFuente( 'hecho respaldado por una fuente', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ) )
		);
	}

	/**
	 * Embeddings donde cada texto se mapea a un vector alineado con su
	 * palabra clave — "respaldado" y "inventado" son ortogonales entre sí,
	 * de forma que la unidad con "inventado" nunca alcanza el umbral contra
	 * un expediente que solo tiene "respaldado".
	 */
	private function embeddingsPorPalabraClave(): EmbeddingsFalso {
		return new EmbeddingsFalso(
			static function ( string $texto ): array {
				if ( str_contains( $texto, 'respaldado' ) ) {
					return array( 1.0, 0.0 );
				}

				return array( 0.0, 1.0 );
			}
		);
	}

	public function test_unidad_con_alta_similitud_no_se_marca_sin_respaldo(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$verificador = new VerificadorTrazabilidadDeterminista( $this->embeddingsPorPalabraClave(), new SegmentadorUnidadesFactuales() );

		$sinRespaldo = $verificador->unidadesSinRespaldoAparente( $this->expediente(), 'Esto es un hecho respaldado por una fuente clara.' );

		self::assertSame( array(), $sinRespaldo );
	}

	public function test_unidad_con_baja_similitud_se_marca_sin_respaldo(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$verificador = new VerificadorTrazabilidadDeterminista( $this->embeddingsPorPalabraClave(), new SegmentadorUnidadesFactuales() );

		$sinRespaldo = $verificador->unidadesSinRespaldoAparente( $this->expediente(), 'Este dato fue completamente inventado sin fuente alguna.' );

		self::assertCount( 1, $sinRespaldo );
		self::assertStringContainsString( 'inventado', $sinRespaldo[0] );
	}

	public function test_expediente_sin_hechos_no_marca_ninguna_unidad(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$verificador = new VerificadorTrazabilidadDeterminista( $this->embeddingsPorPalabraClave(), new SegmentadorUnidadesFactuales() );
		$expediente  = new Expediente( 'una tendencia', array() );

		self::assertSame( array(), $verificador->unidadesSinRespaldoAparente( $expediente, 'Cualquier texto.' ) );
	}

	public function test_umbral_configurado_por_opcion_se_respeta(): void {
		// Umbral imposible de alcanzar (por encima de 1.0, el máximo de una similitud
		// coseno) — incluso una unidad con similitud perfecta (1.0) queda marcada,
		// lo que confirma que el umbral se lee de `get_option()` y no del valor
		// de fábrica (0.75, que la dejaría pasar).
		Functions\when( 'get_option' )->justReturn( 1.5 );

		$verificador = new VerificadorTrazabilidadDeterminista( $this->embeddingsPorPalabraClave(), new SegmentadorUnidadesFactuales() );

		self::assertCount( 1, $verificador->unidadesSinRespaldoAparente( $this->expediente(), 'Esto es un hecho respaldado por una fuente clara.' ) );
	}

	public function test_umbral_configurado_en_cero_nunca_marca_nada(): void {
		Functions\when( 'get_option' )->justReturn( 0.0 );

		$verificador = new VerificadorTrazabilidadDeterminista( $this->embeddingsPorPalabraClave(), new SegmentadorUnidadesFactuales() );

		self::assertSame( array(), $verificador->unidadesSinRespaldoAparente( $this->expediente(), 'Este dato fue completamente inventado sin fuente alguna.' ) );
	}
}
