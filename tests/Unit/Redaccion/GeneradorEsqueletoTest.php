<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use DateTimeImmutable;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Redaccion\CandidatoTesis;
use Pluma\Redaccion\DecisionEditorialException;
use Pluma\Redaccion\GeneradorEsqueleto;
use Pluma\Redaccion\Tono;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;

/**
 * Paso 4 del Algoritmo de Decisión Editorial (Libro Cap. 5.5).
 *
 * @covers \Pluma\Redaccion\GeneradorEsqueleto
 */
final class GeneradorEsqueletoTest extends CasoDePruebaUnitario {

	private function expediente(): Expediente {
		return new Expediente(
			'una tendencia',
			array( new HechoFuente( 'un hecho', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ) )
		);
	}

	private function tesis(): CandidatoTesis {
		return new CandidatoTesis( 'tesis elegida', 80.0, 80.0, 80.0, 80.0 );
	}

	public function test_construye_el_esqueleto_a_partir_de_una_respuesta_valida(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"gancho": "gancho", "hechosEsencialesConAtribucion": "hechos", '
				. '"movimientosArgumentales": ["m1", "m2", "m3"], "contraargumentoReconocido": "contra", "remate": "remate"}'
		);

		$esqueleto = ( new GeneradorEsqueleto( $proveedor ) )->generar( $this->expediente(), $this->tesis(), Tono::Analitico, Tono::Persuasivo );

		self::assertSame( 'gancho', $esqueleto->gancho );
		self::assertCount( 3, $esqueleto->movimientosArgumentales );
		self::assertSame( 'remate', $esqueleto->remate );
	}

	public function test_lanza_excepcion_si_faltan_menos_de_dos_movimientos(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"gancho": "gancho", "hechosEsencialesConAtribucion": "hechos", '
				. '"movimientosArgumentales": ["m1"], "contraargumentoReconocido": "contra", "remate": "remate"}'
		);

		$this->expectException( DecisionEditorialException::class );

		( new GeneradorEsqueleto( $proveedor ) )->generar( $this->expediente(), $this->tesis(), Tono::Analitico, Tono::Persuasivo );
	}

	public function test_lanza_excepcion_si_hay_mas_de_cuatro_movimientos(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"gancho": "gancho", "hechosEsencialesConAtribucion": "hechos", '
				. '"movimientosArgumentales": ["m1", "m2", "m3", "m4", "m5"], "contraargumentoReconocido": "contra", "remate": "remate"}'
		);

		$this->expectException( DecisionEditorialException::class );

		( new GeneradorEsqueleto( $proveedor ) )->generar( $this->expediente(), $this->tesis(), Tono::Analitico, Tono::Persuasivo );
	}

	public function test_lanza_excepcion_si_falta_un_bloque(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"gancho": "gancho"}' );

		$this->expectException( DecisionEditorialException::class );

		( new GeneradorEsqueleto( $proveedor ) )->generar( $this->expediente(), $this->tesis(), Tono::Analitico, Tono::Persuasivo );
	}

	public function test_lanza_excepcion_si_la_respuesta_llego_truncada(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"gancho": "g", "hechosEsencialesConAtribucion": "h", "movimientosArgumentales": ["m1", "m2"], "contraargumentoReconocido": "c", "remate": "r"}',
			truncada: true
		);

		$this->expectException( DecisionEditorialException::class );

		( new GeneradorEsqueleto( $proveedor ) )->generar( $this->expediente(), $this->tesis(), Tono::Analitico, Tono::Persuasivo );
	}

	public function test_el_material_incluye_la_tesis_elegida(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"gancho": "g", "hechosEsencialesConAtribucion": "h", "movimientosArgumentales": ["m1", "m2"], "contraargumentoReconocido": "c", "remate": "r"}'
		);

		( new GeneradorEsqueleto( $proveedor ) )->generar( $this->expediente(), $this->tesis(), Tono::Analitico, Tono::Persuasivo );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringContainsString( 'tesis elegida', $proveedor->ultimaPeticion->material );
	}

	/**
	 * Nivel Tres O.1: cuando la Prueba de Falseabilidad encontró un caso en
	 * contra comparable, las directrices deben instruir que el
	 * "contraargumentoReconocido" lo incorpore con el mismo peso, no una
	 * concesión menor.
	 */
	public function test_incluye_el_caso_en_contra_de_falseabilidad_cuando_se_proporciona(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"gancho": "g", "hechosEsencialesConAtribucion": "h", "movimientosArgumentales": ["m1", "m2"], "contraargumentoReconocido": "c", "remate": "r"}'
		);

		( new GeneradorEsqueleto( $proveedor ) )->generar(
			$this->expediente(),
			$this->tesis(),
			Tono::Analitico,
			Tono::Persuasivo,
			'los datos muestran lo contrario'
		);

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringContainsString( 'los datos muestran lo contrario', $proveedor->ultimaPeticion->directrices );
		self::assertStringContainsString( 'mismo peso argumental', $proveedor->ultimaPeticion->directrices );
	}

	public function test_no_incluye_advertencia_de_falseabilidad_cuando_no_se_proporciona(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"gancho": "g", "hechosEsencialesConAtribucion": "h", "movimientosArgumentales": ["m1", "m2"], "contraargumentoReconocido": "c", "remate": "r"}'
		);

		( new GeneradorEsqueleto( $proveedor ) )->generar( $this->expediente(), $this->tesis(), Tono::Analitico, Tono::Persuasivo );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringNotContainsString( 'tensión de falseabilidad', $proveedor->ultimaPeticion->directrices );
	}
}
