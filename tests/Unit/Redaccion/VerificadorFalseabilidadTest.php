<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use DateTimeImmutable;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Redaccion\CandidatoTesis;
use Pluma\Redaccion\DecisionEditorialException;
use Pluma\Redaccion\VerificadorFalseabilidad;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;

/**
 * @covers \Pluma\Redaccion\VerificadorFalseabilidad
 */
final class VerificadorFalseabilidadTest extends CasoDePruebaUnitario {

	private function expediente(): Expediente {
		return new Expediente(
			'una tendencia',
			array( new HechoFuente( 'un hecho verificado', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ) )
		);
	}

	private function tesis(): CandidatoTesis {
		return new CandidatoTesis( 'la reforma beneficia a las corporaciones', 80.0, 80.0, 80.0, 80.0 );
	}

	public function test_devuelve_el_caso_en_contra_y_su_fuerza(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"casoEnContra": "los datos muestran lo contrario", "fuerzaSustento": 65}' );

		$resultado = ( new VerificadorFalseabilidad( $proveedor ) )->evaluar( $this->expediente(), $this->tesis() );

		self::assertSame( 'los datos muestran lo contrario', $resultado->casoEnContra );
		self::assertSame( 65.0, $resultado->fuerzaSustento );
	}

	public function test_acota_la_fuerza_al_rango_0_100(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"casoEnContra": "x", "fuerzaSustento": 150}' );

		$resultado = ( new VerificadorFalseabilidad( $proveedor ) )->evaluar( $this->expediente(), $this->tesis() );

		self::assertSame( 100.0, $resultado->fuerzaSustento );
	}

	public function test_la_peticion_incluye_la_tesis_a_derrotar(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"casoEnContra": "x", "fuerzaSustento": 10}' );

		( new VerificadorFalseabilidad( $proveedor ) )->evaluar( $this->expediente(), $this->tesis() );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringContainsString( 'la reforma beneficia a las corporaciones', $proveedor->ultimaPeticion->directrices );
	}

	public function test_respuesta_truncada_lanza_excepcion(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"casoEnContra": "x", "fuerzaSustento": 10}', truncada: true );

		$this->expectException( DecisionEditorialException::class );

		( new VerificadorFalseabilidad( $proveedor ) )->evaluar( $this->expediente(), $this->tesis() );
	}

	public function test_respuesta_sin_el_formato_esperado_lanza_excepcion(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"otraCosa": true}' );

		$this->expectException( DecisionEditorialException::class );

		( new VerificadorFalseabilidad( $proveedor ) )->evaluar( $this->expediente(), $this->tesis() );
	}
}
