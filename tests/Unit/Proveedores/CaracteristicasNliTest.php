<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Proveedores;

use Pluma\Proveedores\CaracteristicasNli;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * `ADR 0024`: extracción de características del clasificador NLI pure-PHP.
 * Determinista, sin dependencias — se prueba directo.
 *
 * @covers \Pluma\Proveedores\CaracteristicasNli
 */
final class CaracteristicasNliTest extends CasoDePruebaUnitario {

	public function test_tokenizar_normaliza_mayusculas_y_puntuacion(): void {
		$caracteristicas = new CaracteristicasNli();

		self::assertSame(
			array( 'el', 'alcalde', 'renunció', 'el', 'lunes' ),
			$caracteristicas->tokenizar( '¡El alcalde, renunció el lunes!' )
		);
	}

	public function test_tokenizar_texto_vacio_devuelve_lista_vacia(): void {
		self::assertSame( array(), ( new CaracteristicasNli() )->tokenizar( '' ) );
	}

	public function test_vector_tiene_el_tamano_esperado(): void {
		$caracteristicas = new CaracteristicasNli();
		$vocabulario     = array(
			'alcalde'  => 0,
			'renunció' => 1,
			'lunes'    => 2,
		);

		$vector = $caracteristicas->vector( 'El alcalde renunció el lunes.', 'El alcalde no renunció.', $vocabulario );

		// 2 * tamaño del vocabulario + 4 escalares.
		self::assertCount( 2 * count( $vocabulario ) + 4, $vector );
	}

	public function test_textos_identicos_dan_similitud_de_coseno_maxima(): void {
		$caracteristicas = new CaracteristicasNli();
		$vocabulario     = array(
			'el'       => 0,
			'alcalde'  => 1,
			'renuncio' => 2,
		);

		$vector = $caracteristicas->vector( 'El alcalde renuncio', 'El alcalde renuncio', $vocabulario );

		// El primer escalar tras los dos bloques TF es la similitud de coseno.
		$indiceCoseno = 2 * count( $vocabulario );
		self::assertEqualsWithDelta( 1.0, $vector[ $indiceCoseno ], 0.0001 );
	}

	public function test_negacion_solo_en_la_hipotesis_se_refleja_en_la_diferencia_de_negacion(): void {
		$caracteristicas = new CaracteristicasNli();
		$vocabulario     = array( 'alcalde' => 0 );

		$vector = $caracteristicas->vector( 'El alcalde renunció.', 'El alcalde no renunció.', $vocabulario );

		// Escalares: [coseno, jaccard, diferenciaNegacion, razonLongitud] tras 2*count(vocab).
		$indiceDiferenciaNegacion = 2 * count( $vocabulario ) + 2;
		self::assertSame( 1.0, $vector[ $indiceDiferenciaNegacion ] );
	}

	public function test_sin_negacion_en_ninguno_la_diferencia_es_cero(): void {
		$caracteristicas = new CaracteristicasNli();
		$vocabulario     = array( 'alcalde' => 0 );

		$vector = $caracteristicas->vector( 'El alcalde renunció.', 'El alcalde viajó.', $vocabulario );

		$indiceDiferenciaNegacion = 2 * count( $vocabulario ) + 2;
		self::assertSame( 0.0, $vector[ $indiceDiferenciaNegacion ] );
	}

	public function test_vocabulario_vacio_no_lanza_y_produce_solo_los_escalares(): void {
		$caracteristicas = new CaracteristicasNli();

		$vector = $caracteristicas->vector( 'texto cualquiera', 'otro texto', array() );

		self::assertCount( 4, $vector );
	}
}
