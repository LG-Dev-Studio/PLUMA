<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use DateTimeImmutable;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\DecisionEditorialException;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\GeneradorBoletin;
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
 * Nivel Cuatro W.1 — el boletín como producto del periodista: párrafo de
 * apertura en su voz.
 *
 * @covers \Pluma\Redaccion\GeneradorBoletin
 */
final class GeneradorBoletinTest extends CasoDePruebaUnitario {

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

	public function test_devuelve_el_parrafo_de_apertura(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"apertura": "Esta semana volví sobre la inflación, otra vez."}' );

		$apertura = ( new GeneradorBoletin( $proveedor ) )->generar( $this->periodista(), array( 'Los precios suben otra vez' ) );

		self::assertSame( 'Esta semana volví sobre la inflación, otra vez.', $apertura );
	}

	public function test_lanza_excepcion_si_falta_la_apertura(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"otroCampo": "x"}' );

		$this->expectException( DecisionEditorialException::class );

		( new GeneradorBoletin( $proveedor ) )->generar( $this->periodista(), array( 'Título' ) );
	}

	public function test_lanza_excepcion_si_la_apertura_esta_vacia(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"apertura": "   "}' );

		$this->expectException( DecisionEditorialException::class );

		( new GeneradorBoletin( $proveedor ) )->generar( $this->periodista(), array( 'Título' ) );
	}

	public function test_lanza_excepcion_si_la_respuesta_llego_truncada(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"apertura": "x"}', truncada: true );

		$this->expectException( DecisionEditorialException::class );

		( new GeneradorBoletin( $proveedor ) )->generar( $this->periodista(), array( 'Título' ) );
	}
}
