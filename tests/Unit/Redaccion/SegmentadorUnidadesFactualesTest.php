<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Pluma\Redaccion\SegmentadorUnidadesFactuales;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Redaccion\SegmentadorUnidadesFactuales
 */
final class SegmentadorUnidadesFactualesTest extends CasoDePruebaUnitario {

	public function test_divide_dos_oraciones_simples(): void {
		$unidades = ( new SegmentadorUnidadesFactuales() )->segmentar( 'El ayuntamiento aprobó la partida. Las obras empiezan en marzo.' );

		self::assertSame(
			array( 'El ayuntamiento aprobó la partida.', 'Las obras empiezan en marzo.' ),
			$unidades
		);
	}

	public function test_no_divide_por_un_numero_decimal(): void {
		$unidades = ( new SegmentadorUnidadesFactuales() )->segmentar( 'La inflación cerró en 4.2% en junio. El dato sorprendió a los analistas.' );

		self::assertCount( 2, $unidades );
		self::assertSame( 'La inflación cerró en 4.2% en junio.', $unidades[0] );
	}

	public function test_no_divide_por_una_abreviatura_comun(): void {
		$unidades = ( new SegmentadorUnidadesFactuales() )->segmentar( 'El Dr. Pérez confirmó el diagnóstico. La familia lo agradeció.' );

		self::assertCount( 2, $unidades );
		self::assertSame( 'El Dr. Pérez confirmó el diagnóstico.', $unidades[0] );
	}

	public function test_divide_a_traves_de_saltos_de_parrafo(): void {
		$unidades = ( new SegmentadorUnidadesFactuales() )->segmentar( "Primer párrafo con una idea.\n\nSegundo párrafo con otra." );

		self::assertSame(
			array( 'Primer párrafo con una idea.', 'Segundo párrafo con otra.' ),
			$unidades
		);
	}

	public function test_texto_vacio_devuelve_lista_vacia(): void {
		self::assertSame( array(), ( new SegmentadorUnidadesFactuales() )->segmentar( '' ) );
	}
}
