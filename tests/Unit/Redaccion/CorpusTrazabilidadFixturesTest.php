<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * Nivel Tres J.3: el corpus de calibración de trazabilidad
 * (`tests/Fixtures/corpus-trazabilidad.php`) es copy de desarrollo congelado
 * para la herramienta de calibración de embeddings
 * (`tools/calibracion-embeddings/`), no expedientes reales de cliente — este
 * test confirma que el fixture sigue teniendo la forma honesta que declara:
 * cada caso trae un hecho, una unidad que lo respalda y una unidad sin
 * relación, las tres no vacías. No llama a ningún proveedor de embeddings
 * real — GOVERNANCE §4.4 prohíbe que un test Unit llame a una API real.
 *
 * @covers \Pluma\Redaccion\VerificadorTrazabilidadDeterminista
 */
final class CorpusTrazabilidadFixturesTest extends CasoDePruebaUnitario {

	/**
	 * @return list<array{hecho: string, unidad_respaldada: string, unidad_sin_respaldo: string}>
	 */
	private function corpus(): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions -- lectura de un fixture de archivo local del repo, no de red.
		return require __DIR__ . '/../../Fixtures/corpus-trazabilidad.php';
	}

	public function test_el_corpus_tiene_al_menos_cuatro_casos(): void {
		self::assertGreaterThanOrEqual( 4, count( $this->corpus() ), 'El corpus de calibración de trazabilidad debe tener al menos 4 casos.' );
	}

	public function test_cada_caso_trae_hecho_y_las_dos_unidades_no_vacias(): void {
		foreach ( $this->corpus() as $indice => $caso ) {
			foreach ( array( 'hecho', 'unidad_respaldada', 'unidad_sin_respaldo' ) as $clave ) {
				self::assertArrayHasKey( $clave, $caso, "El caso #{$indice} no tiene la clave «{$clave}»." );
				self::assertNotSame( '', trim( $caso[ $clave ] ), "El caso #{$indice}, clave «{$clave}», está vacío." );
			}
		}
	}

	public function test_ninguna_unidad_sin_respaldo_repite_el_hecho_literalmente(): void {
		foreach ( $this->corpus() as $indice => $caso ) {
			self::assertStringNotContainsString(
				$caso['hecho'],
				$caso['unidad_sin_respaldo'],
				"El caso #{$indice} tiene una «unidad_sin_respaldo» que repite el hecho literalmente — no calibraría nada."
			);
		}
	}
}
