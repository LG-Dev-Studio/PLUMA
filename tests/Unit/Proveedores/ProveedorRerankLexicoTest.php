<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Proveedores;

use Pluma\Proveedores\ProveedorRerankLexico;
use Pluma\Proveedores\ResultadoRerank;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * Rol RRK vía TF-IDF + coseno (`ADR 0024`) — determinista, sin red, sin
 * dobles: es PHP puro, se prueba directo.
 *
 * @covers \Pluma\Proveedores\ProveedorRerankLexico
 */
final class ProveedorRerankLexicoTest extends CasoDePruebaUnitario {

	public function test_lista_vacia_de_textos_devuelve_lista_vacia(): void {
		self::assertSame( array(), ( new ProveedorRerankLexico() )->reordenar( 'cualquier consulta', array() ) );
	}

	public function test_el_texto_mas_relevante_queda_primero(): void {
		$resultados = ( new ProveedorRerankLexico() )->reordenar(
			'Cuál es la capital de Francia',
			array(
				'El clima en Madrid es cálido en verano.',
				'París es la capital de Francia.',
				'Francia es un país de Europa occidental.',
			)
		);

		self::assertCount( 3, $resultados );
		self::assertSame( 1, $resultados[0]->indice );
		self::assertGreaterThan( $resultados[1]->puntuacion, $resultados[0]->puntuacion );
		self::assertGreaterThanOrEqual( $resultados[2]->puntuacion, $resultados[1]->puntuacion );
	}

	public function test_devuelve_una_entrada_por_cada_texto_de_entrada(): void {
		$textos     = array( 'uno', 'dos', 'tres', 'cuatro' );
		$resultados = ( new ProveedorRerankLexico() )->reordenar( 'consulta', $textos );

		self::assertCount( 4, $resultados );

		$indices = array_map( static fn ( ResultadoRerank $r ): int => $r->indice, $resultados );
		sort( $indices );
		self::assertSame( array( 0, 1, 2, 3 ), $indices );
	}

	public function test_consulta_sin_ningun_termino_en_comun_da_puntuacion_cero(): void {
		$resultados = ( new ProveedorRerankLexico() )->reordenar(
			'xilofono marciano inexistente',
			array( 'El mercado cerró estable hoy.' )
		);

		self::assertSame( 0.0, $resultados[0]->puntuacion );
	}

	public function test_texto_identico_a_la_consulta_obtiene_la_maxima_similitud(): void {
		$consulta   = 'el banco central subió la tasa de interés';
		$resultados = ( new ProveedorRerankLexico() )->reordenar(
			$consulta,
			array( 'una noticia sin relación alguna', $consulta )
		);

		self::assertSame( 1, $resultados[0]->indice );
		self::assertEqualsWithDelta( 1.0, $resultados[0]->puntuacion, 0.0001 );
	}

	public function test_es_insensible_a_mayusculas_y_puntuacion(): void {
		$resultados = ( new ProveedorRerankLexico() )->reordenar(
			'PARÍS, capital de Francia.',
			array( 'parís capital de francia' )
		);

		self::assertEqualsWithDelta( 1.0, $resultados[0]->puntuacion, 0.0001 );
	}
}
