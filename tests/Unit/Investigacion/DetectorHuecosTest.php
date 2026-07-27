<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Investigacion;

use DateTimeImmutable;
use Pluma\Investigacion\DetectorHuecos;
use Pluma\Investigacion\DimensionEncuadre;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\InvestigacionException;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;

/**
 * @covers \Pluma\Investigacion\DetectorHuecos
 */
final class DetectorHuecosTest extends CasoDePruebaUnitario {

	private const RESPUESTA_SIN_HUECOS = '{"economica": {"cubierta": true, "datosDisponibles": true, "relevanciaCausal": true}, '
		. '"humana": {"cubierta": true, "datosDisponibles": true, "relevanciaCausal": true}, '
		. '"politica": {"cubierta": true, "datosDisponibles": true, "relevanciaCausal": true}, '
		. '"tecnica": {"cubierta": true, "datosDisponibles": true, "relevanciaCausal": true}, '
		. '"historica": {"cubierta": true, "datosDisponibles": true, "relevanciaCausal": true}, '
		. '"legal": {"cubierta": true, "datosDisponibles": true, "relevanciaCausal": true}}';

	private function expediente( int $cantidadHechos ): Expediente {
		$hechos = array();

		for ( $i = 0; $i < $cantidadHechos; $i++ ) {
			$hechos[] = new HechoFuente( "hecho {$i}", "https://example.com/{$i}", new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido );
		}

		return new Expediente( 'una tendencia', $hechos );
	}

	public function test_expediente_con_menos_de_dos_hechos_no_llama_al_proveedor(): void {
		$proveedor = new ProveedorLenguajeFalso( 'no debería llamarse' );

		$resultado = ( new DetectorHuecos( $proveedor ) )->detectar( $this->expediente( 1 ) );

		self::assertNull( $proveedor->ultimaPeticion );
		self::assertSame( array(), $resultado->huecosDetectados );
	}

	public function test_todas_las_dimensiones_cubiertas_no_produce_huecos(): void {
		$proveedor = new ProveedorLenguajeFalso( self::RESPUESTA_SIN_HUECOS );

		$resultado = ( new DetectorHuecos( $proveedor ) )->detectar( $this->expediente( 2 ) );

		self::assertSame( array(), $resultado->huecosDetectados );
	}

	/**
	 * Nivel Tres O.2: una dimensión ausente con datos disponibles pero SIN
	 * relevancia causal no es un hueco real — el filtro de O.2 la descarta.
	 */
	public function test_dimension_ausente_sin_relevancia_causal_no_es_hueco(): void {
		$respuesta = str_replace(
			'"legal": {"cubierta": true, "datosDisponibles": true, "relevanciaCausal": true}',
			'"legal": {"cubierta": false, "datosDisponibles": true, "relevanciaCausal": false}',
			self::RESPUESTA_SIN_HUECOS
		);
		$proveedor = new ProveedorLenguajeFalso( $respuesta );

		$resultado = ( new DetectorHuecos( $proveedor ) )->detectar( $this->expediente( 2 ) );

		self::assertSame( array(), $resultado->huecosDetectados );
	}

	public function test_dimension_ausente_con_sustento_y_relevancia_causal_es_hueco(): void {
		$respuesta = str_replace(
			'"legal": {"cubierta": true, "datosDisponibles": true, "relevanciaCausal": true}',
			'"legal": {"cubierta": false, "datosDisponibles": true, "relevanciaCausal": true}',
			self::RESPUESTA_SIN_HUECOS
		);
		$proveedor = new ProveedorLenguajeFalso( $respuesta );

		$resultado = ( new DetectorHuecos( $proveedor ) )->detectar( $this->expediente( 2 ) );

		self::assertSame( array( DimensionEncuadre::Legal ), $resultado->huecosDetectados );
	}

	public function test_dimension_ausente_sin_datos_disponibles_no_es_hueco(): void {
		$respuesta = str_replace(
			'"historica": {"cubierta": true, "datosDisponibles": true, "relevanciaCausal": true}',
			'"historica": {"cubierta": false, "datosDisponibles": false, "relevanciaCausal": true}',
			self::RESPUESTA_SIN_HUECOS
		);
		$proveedor = new ProveedorLenguajeFalso( $respuesta );

		$resultado = ( new DetectorHuecos( $proveedor ) )->detectar( $this->expediente( 2 ) );

		self::assertSame( array(), $resultado->huecosDetectados );
	}

	public function test_respuesta_truncada_lanza_excepcion(): void {
		$proveedor = new ProveedorLenguajeFalso( self::RESPUESTA_SIN_HUECOS, truncada: true );

		$this->expectException( InvestigacionException::class );

		( new DetectorHuecos( $proveedor ) )->detectar( $this->expediente( 2 ) );
	}

	public function test_respuesta_sin_una_dimension_lanza_excepcion(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"economica": {"cubierta": true, "datosDisponibles": true, "relevanciaCausal": true}}' );

		$this->expectException( InvestigacionException::class );

		( new DetectorHuecos( $proveedor ) )->detectar( $this->expediente( 2 ) );
	}
}
