<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Investigacion;

use DateTimeImmutable;
use Pluma\Investigacion\DimensionEncuadre;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Investigacion\Expediente
 */
final class ExpedienteTest extends CasoDePruebaUnitario {

	public function test_huecos_detectados_por_defecto_es_lista_vacia(): void {
		$expediente = new Expediente( 'una tendencia', array() );

		self::assertSame( array(), $expediente->huecosDetectados );
	}

	public function test_aarray_y_desdearray_conservan_los_huecos_detectados(): void {
		$original = new Expediente(
			'una tendencia',
			array( new HechoFuente( 'un hecho', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido ) ),
			array( DimensionEncuadre::Legal, DimensionEncuadre::Historica )
		);

		$reconstruido = Expediente::desdeArray( $original->aArray() );

		self::assertSame( array( DimensionEncuadre::Legal, DimensionEncuadre::Historica ), $reconstruido->huecosDetectados );
	}

	/**
	 * Compatibilidad retroactiva: un expediente persistido ANTES de esta
	 * porción no tiene la clave `huecosDetectados` en su JSON.
	 */
	public function test_desdearray_sin_huecos_detectados_usa_lista_vacia(): void {
		$expediente = Expediente::desdeArray(
			array(
				'tendenciaOrigen' => 'una tendencia',
				'hechos'          => array(),
			)
		);

		self::assertSame( array(), $expediente->huecosDetectados );
	}
}
