<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use DateTimeImmutable;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\DecisionEditorialException;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\GeneradorTitularAlternativo;
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
 * Nivel Cuatro Y.2 — el experimento de titular.
 *
 * @covers \Pluma\Redaccion\GeneradorTitularAlternativo
 */
final class GeneradorTitularAlternativoTest extends CasoDePruebaUnitario {

	private function periodista(): Periodista {
		$diales   = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas   = new ReglasConducta( 'linea editorial escéptica', array( 'nunca inventar cifras' ), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 1, 1, $diales, $reglas, $matriz, true, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

		return new Periodista( 1, 'Valentina Ruiz', null, 'Bio.', RolPeriodista::Columnista, array(), EstadoPeriodista::Activo, $conducta, new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ), new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ) );
	}

	public function test_devuelve_el_titulo_alternativo(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"tituloAlternativo": "Por qué los precios no dejan de subir"}' );

		$titulo = ( new GeneradorTitularAlternativo( $proveedor ) )->generar( $this->periodista(), 'Los precios suben otra vez', 'la inflación estructural no cede' );

		self::assertSame( 'Por qué los precios no dejan de subir', $titulo );
	}

	public function test_lanza_excepcion_si_falta_el_titulo(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"otroCampo": "x"}' );

		$this->expectException( DecisionEditorialException::class );

		( new GeneradorTitularAlternativo( $proveedor ) )->generar( $this->periodista(), 'Título', 'tesis' );
	}

	public function test_lanza_excepcion_si_llego_truncado(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"tituloAlternativo": "x"}', truncada: true );

		$this->expectException( DecisionEditorialException::class );

		( new GeneradorTitularAlternativo( $proveedor ) )->generar( $this->periodista(), 'Título', 'tesis' );
	}
}
