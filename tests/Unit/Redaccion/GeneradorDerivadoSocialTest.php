<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use DateTimeImmutable;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\DecisionEditorialException;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\GeneradorDerivadoSocial;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;

/**
 * Nivel Cuatro W.2 — la adaptación por canal: extracto social + titular de
 * Discover, nunca contradiciendo ni exagerando la pieza original.
 *
 * @covers \Pluma\Redaccion\GeneradorDerivadoSocial
 */
final class GeneradorDerivadoSocialTest extends CasoDePruebaUnitario {

	private function periodista(): Periodista {
		$diales   = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas   = new ReglasConducta( 'linea editorial escéptica', array( 'nunca inventar cifras' ), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 1, 1, $diales, $reglas, $matriz, true, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

		return new Periodista(
			1,
			'Valentina Ruiz',
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array(),
			EstadoPeriodista::Activo,
			$conducta,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);
	}

	public function test_devuelve_extracto_y_titular(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"extractoSocial": "Los precios subieron otra vez, y esto es lo que significa.", "titularDiscover": "La inflación vuelve a subir en julio"}' );

		$derivado = ( new GeneradorDerivadoSocial( $proveedor ) )->generar( $this->periodista(), 'La inflación vuelve a subir', 'extracto de la pieza original' );

		self::assertSame( 'Los precios subieron otra vez, y esto es lo que significa.', $derivado['extractoSocial'] );
		self::assertSame( 'La inflación vuelve a subir en julio', $derivado['titularDiscover'] );
	}

	public function test_lanza_excepcion_si_falta_el_extracto(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"titularDiscover": "Titular"}' );

		$this->expectException( DecisionEditorialException::class );

		( new GeneradorDerivadoSocial( $proveedor ) )->generar( $this->periodista(), 'Título', 'extracto' );
	}

	public function test_lanza_excepcion_si_falta_el_titular(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"extractoSocial": "Extracto"}' );

		$this->expectException( DecisionEditorialException::class );

		( new GeneradorDerivadoSocial( $proveedor ) )->generar( $this->periodista(), 'Título', 'extracto' );
	}

	public function test_lanza_excepcion_si_la_respuesta_llego_truncada(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"extractoSocial": "x", "titularDiscover": "y"}', truncada: true );

		$this->expectException( DecisionEditorialException::class );

		( new GeneradorDerivadoSocial( $proveedor ) )->generar( $this->periodista(), 'Título', 'extracto' );
	}
}
