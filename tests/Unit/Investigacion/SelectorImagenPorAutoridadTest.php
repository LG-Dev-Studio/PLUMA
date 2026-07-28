<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Investigacion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Investigacion\ClasificadorNivelFuente;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Investigacion\SelectorImagenPorAutoridad;
use Pluma\Proveedores\ExtractorImagenFuenteInterface;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * Imagen destacada por autoridad de fuente (Nivel Dos, decisión del
 * propietario — `ADR 0006`).
 *
 * @covers \Pluma\Investigacion\SelectorImagenPorAutoridad
 */
final class SelectorImagenPorAutoridadTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
	}

	private function hecho( string $url ): HechoFuente {
		return new HechoFuente( 'extracto', $url, new DateTimeImmutable( '2026-07-27T12:00:00+00:00' ), NivelVerificacion::Atribuido );
	}

	public function test_prueba_la_fuente_de_mayor_autoridad_primero(): void {
		Functions\when( 'get_option' )->alias(
			static fn ( string $opcion, $defecto = array() ) => match ( $opcion ) {
				ClasificadorNivelFuente::OPCION_FUENTES_NIVEL_A => array( 'nivel-a.example.com' ),
				ClasificadorNivelFuente::OPCION_FUENTES_NIVEL_B => array( 'nivel-b.example.com' ),
				default => $defecto,
			}
		);

		$expediente = new Expediente(
			'tendencia',
			array(
				$this->hecho( 'https://nivel-c.example.com/articulo' ),
				$this->hecho( 'https://nivel-a.example.com/articulo' ),
				$this->hecho( 'https://nivel-b.example.com/articulo' ),
			)
		);

		$extractor = $this->createMock( ExtractorImagenFuenteInterface::class );
		$extractor->expects( self::once() )
			->method( 'extraerImagenDestacada' )
			->with( 'https://nivel-a.example.com/articulo' )
			->willReturn( 'https://nivel-a.example.com/foto.jpg' );

		$resultado = ( new SelectorImagenPorAutoridad( new ClasificadorNivelFuente(), $extractor ) )->seleccionar( $expediente );

		self::assertNotNull( $resultado );
		self::assertSame( 'https://nivel-a.example.com/foto.jpg', $resultado->urlImagen );
		self::assertSame( NivelFuente::A, $resultado->nivelFuente );
		self::assertSame( 'nivel-a.example.com', $resultado->nombreFuente );
	}

	public function test_prueba_la_siguiente_fuente_si_la_de_mayor_autoridad_no_tiene_imagen(): void {
		Functions\when( 'get_option' )->alias(
			static fn ( string $opcion, $defecto = array() ) => match ( $opcion ) {
				ClasificadorNivelFuente::OPCION_FUENTES_NIVEL_A => array( 'nivel-a.example.com' ),
				default => $defecto,
			}
		);

		$expediente = new Expediente(
			'tendencia',
			array(
				$this->hecho( 'https://nivel-a.example.com/articulo' ),
				$this->hecho( 'https://nivel-c.example.com/articulo' ),
			)
		);

		$extractor = $this->createMock( ExtractorImagenFuenteInterface::class );
		$extractor->method( 'extraerImagenDestacada' )->willReturnMap(
			array(
				array( 'https://nivel-a.example.com/articulo', null ),
				array( 'https://nivel-c.example.com/articulo', 'https://nivel-c.example.com/foto.jpg' ),
			)
		);

		$resultado = ( new SelectorImagenPorAutoridad( new ClasificadorNivelFuente(), $extractor ) )->seleccionar( $expediente );

		self::assertNotNull( $resultado );
		self::assertSame( 'https://nivel-c.example.com/foto.jpg', $resultado->urlImagen );
		self::assertSame( NivelFuente::C, $resultado->nivelFuente );
	}

	public function test_nunca_prueba_el_mismo_host_dos_veces(): void {
		Functions\when( 'get_option' )->justReturn( array() );

		$expediente = new Expediente(
			'tendencia',
			array(
				$this->hecho( 'https://mismo-medio.example.com/articulo-1' ),
				$this->hecho( 'https://mismo-medio.example.com/articulo-2' ),
			)
		);

		$extractor = $this->createMock( ExtractorImagenFuenteInterface::class );
		$extractor->expects( self::once() )->method( 'extraerImagenDestacada' )->willReturn( null );

		self::assertNull( ( new SelectorImagenPorAutoridad( new ClasificadorNivelFuente(), $extractor ) )->seleccionar( $expediente ) );
	}

	public function test_devuelve_null_si_ninguna_fuente_tiene_imagen(): void {
		Functions\when( 'get_option' )->justReturn( array() );

		$expediente = new Expediente( 'tendencia', array( $this->hecho( 'https://sin-imagen.example.com/articulo' ) ) );

		$extractor = $this->createMock( ExtractorImagenFuenteInterface::class );
		$extractor->method( 'extraerImagenDestacada' )->willReturn( null );

		self::assertNull( ( new SelectorImagenPorAutoridad( new ClasificadorNivelFuente(), $extractor ) )->seleccionar( $expediente ) );
	}

	public function test_devuelve_null_con_expediente_sin_hechos(): void {
		Functions\when( 'get_option' )->justReturn( array() );

		$extractor = $this->createMock( ExtractorImagenFuenteInterface::class );
		$extractor->expects( self::never() )->method( 'extraerImagenDestacada' );

		self::assertNull( ( new SelectorImagenPorAutoridad( new ClasificadorNivelFuente(), $extractor ) )->seleccionar( new Expediente( 'tendencia', array() ) ) );
	}
}
