<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Proveedores;

use Pluma\Proveedores\CaracteristicasNli;
use Pluma\Proveedores\EtiquetaNli;
use Pluma\Proveedores\ProveedorNliEntrenado;
use Pluma\Proveedores\ResultadoNli;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * `ADR 0024`: carga el artefacto REAL entrenado (`recursos/modelos/nli-es.rbx`
 * + `nli-es-vocab.json`, ~100 KB) — nunca un doble. El modelo es pequeño y
 * determinista, así que probarlo de verdad es más barato y más honesto que
 * mockearlo.
 *
 * @covers \Pluma\Proveedores\ProveedorNliEntrenado
 */
final class ProveedorNliEntrenadoTest extends CasoDePruebaUnitario {

	private function proveedor(): ProveedorNliEntrenado {
		return new ProveedorNliEntrenado( new CaracteristicasNli() );
	}

	public function test_devuelve_las_tres_etiquetas_con_puntuaciones_que_suman_uno(): void {
		$resultados = $this->proveedor()->inferir( 'El alcalde renunció el lunes.', 'El alcalde no renunció.' );

		self::assertCount( 3, $resultados );

		$etiquetas = array_map( static fn ( ResultadoNli $r ): EtiquetaNli => $r->etiqueta, $resultados );
		self::assertContains( EtiquetaNli::Entailment, $etiquetas );
		self::assertContains( EtiquetaNli::Neutral, $etiquetas );
		self::assertContains( EtiquetaNli::Contradiccion, $etiquetas );

		$suma = array_sum( array_map( static fn ( ResultadoNli $r ): float => $r->puntuacion, $resultados ) );
		self::assertEqualsWithDelta( 1.0, $suma, 0.01 );
	}

	public function test_resultados_vienen_ordenados_descendente_por_puntuacion(): void {
		$resultados = $this->proveedor()->inferir( 'El equipo ganó el partido.', 'El equipo perdió el partido.' );
		$total      = count( $resultados );

		for ( $i = 1; $i < $total; $i++ ) {
			self::assertGreaterThanOrEqual( $resultados[ $i ]->puntuacion, $resultados[ $i - 1 ]->puntuacion );
		}
	}

	public function test_es_deterministico_para_la_misma_entrada(): void {
		$primera = $this->proveedor()->inferir( 'París es la capital de Francia.', 'París es una ciudad europea.' );
		$segunda = $this->proveedor()->inferir( 'París es la capital de Francia.', 'París es una ciudad europea.' );

		self::assertEquals( $primera, $segunda );
	}

	public function test_una_negacion_directa_produce_una_puntuacion_de_contradiccion_real(): void {
		// No se afirma que sea la etiqueta ganadora (el modelo real, medido en
		// `ADR 0024`, tiene ~50% de exactitud) — solo que la señal de negación
		// mueve la puntuación de contradicción a un valor no trivial.
		$resultados = $this->proveedor()->inferir( 'El alcalde renunció.', 'El alcalde no renunció.' );

		$contradiccion = current( array_filter( $resultados, static fn ( ResultadoNli $r ): bool => EtiquetaNli::Contradiccion === $r->etiqueta ) );

		self::assertNotFalse( $contradiccion );
		self::assertGreaterThan( 0.2, $contradiccion->puntuacion );
	}
}
