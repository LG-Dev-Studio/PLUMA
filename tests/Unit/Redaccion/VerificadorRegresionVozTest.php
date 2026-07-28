<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Brain\Monkey\Functions;
use Pluma\Redaccion\VerificadorRegresionVoz;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\EmbeddingsFalso;

/**
 * Nivel Dos A.5, verificación 2 de 3 (deriva semántica).
 *
 * Nivel Tres P.3 (Etapa 8, Porción 10): invocable de forma aislada vía
 * `composer test:voz`, además de correr en cada `composer test:unit`/CI —
 * cadencia mensual independiente del ciclo de releases
 * (`docs/protocolo-corpus-voz.md`).
 *
 * @covers \Pluma\Redaccion\VerificadorRegresionVoz
 * @group voz
 */
final class VerificadorRegresionVozTest extends CasoDePruebaUnitario {

	/**
	 * Embeddings donde el texto que contiene "fiel" es idéntico al corpus de
	 * referencia (similitud 1.0) y el que contiene "drift" es ortogonal
	 * (similitud 0.0) — permite simular una muestra fiel a la voz vs. una que
	 * derivó, sin llamar a un proveedor real (GOVERNANCE §4.4).
	 */
	private function embeddingsPorPalabraClave(): EmbeddingsFalso {
		return new EmbeddingsFalso(
			static function ( string $texto ): array {
				if ( str_contains( $texto, 'drift' ) ) {
					return array( 0.0, 1.0 );
				}

				return array( 1.0, 0.0 );
			}
		);
	}

	public function test_muestra_fiel_al_corpus_no_deriva_excesivamente(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$verificador = new VerificadorRegresionVoz( $this->embeddingsPorPalabraClave() );
		$corpus      = array( 'referencia uno fiel', 'referencia dos fiel' );

		self::assertFalse( $verificador->derivaExcesiva( $corpus, 'muestra nueva fiel a la voz' ) );
		self::assertEqualsWithDelta( 1.0, $verificador->similitudPromedioConCorpus( $corpus, 'muestra nueva fiel a la voz' ), 0.0001 );
	}

	public function test_muestra_que_derivo_de_la_voz_se_marca_como_deriva_excesiva(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$verificador = new VerificadorRegresionVoz( $this->embeddingsPorPalabraClave() );
		$corpus      = array( 'referencia uno fiel', 'referencia dos fiel' );

		self::assertTrue( $verificador->derivaExcesiva( $corpus, 'muestra con drift total' ) );
		self::assertEqualsWithDelta( 0.0, $verificador->similitudPromedioConCorpus( $corpus, 'muestra con drift total' ), 0.0001 );
	}

	public function test_corpus_vacio_nunca_marca_deriva(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$verificador = new VerificadorRegresionVoz( $this->embeddingsPorPalabraClave() );

		self::assertFalse( $verificador->derivaExcesiva( array(), 'cualquier muestra con drift' ) );
	}

	public function test_umbral_configurado_por_opcion_se_respeta(): void {
		// Umbral imposible de alcanzar: incluso una muestra perfectamente fiel
		// (similitud 1.0) se marca como deriva excesiva, confirmando que el
		// umbral se lee de `get_option()` y no del valor de fábrica (0.70).
		Functions\when( 'get_option' )->justReturn( 1.5 );

		$verificador = new VerificadorRegresionVoz( $this->embeddingsPorPalabraClave() );
		$corpus      = array( 'referencia fiel' );

		self::assertTrue( $verificador->derivaExcesiva( $corpus, 'muestra nueva fiel' ) );
	}
}
