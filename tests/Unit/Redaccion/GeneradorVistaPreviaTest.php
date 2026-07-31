<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use DateTimeImmutable;
use Mockery;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Proveedores\PropositoLenguaje;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\GeneradorVistaPrevia;
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
 * El Estudio de Conducta (Libro Cap. 10.2): "un párrafo de muestra que se
 * re-redacta al mover un dial". Nunca escribe sobre una Pieza real ni un
 * expediente; siempre un hecho neutro fijo con la conducta CANDIDATA.
 *
 * @covers \Pluma\Redaccion\GeneradorVistaPrevia
 */
final class GeneradorVistaPreviaTest extends CasoDePruebaUnitario {

	private function periodista(): Periodista {
		$diales   = new Diales( 40, 20, 10, 60, 30, 50, 60, 50 );
		$reglas   = new ReglasConducta( 'línea original', array(), array(), array(), TratamientoLector::Usted, '¿Y usted qué opina?' );
		$matriz   = MatrizTonos::desdeFilas( array() );
		$conducta = new ConductaVersion( 1, 9, $diales, $reglas, $matriz, new DateTimeImmutable( '2026-07-23T00:00:00+00:00' ) );

		return new Periodista(
			9,
			'Marcos Iriarte',
			null,
			'Economista y analista de datos.',
			RolPeriodista::Analista,
			array(),
			EstadoPeriodista::Activo,
			$conducta,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);
	}

	private function dialesCandidatos(): Diales {
		return new Diales( 90, 80, 70, 30, 90, 20, 30, 90 );
	}

	private function reglasCandidatas(): ReglasConducta {
		return new ReglasConducta( 'línea candidata, todavía sin guardar', array(), array(), array(), TratamientoLector::Tu, '¿Tú qué crees?' );
	}

	public function test_genera_el_parrafo_con_la_conducta_candidata_no_la_guardada(): void {
		$proveedor = new ProveedorLenguajeFalso( 'Un párrafo redactado con la conducta candidata.' );

		$matrizCandidata = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Humoristico, Tono::Opinion, NivelSatiraPermitida::PiezaCompleta ) )
		);

		$texto = ( new GeneradorVistaPrevia( $proveedor ) )->generar(
			$this->periodista(),
			$this->dialesCandidatos(),
			$this->reglasCandidatas(),
			$matrizCandidata
		);

		self::assertSame( 'Un párrafo redactado con la conducta candidata.', $texto );

		$peticion = $proveedor->ultimaPeticion;
		self::assertInstanceOf( PeticionLenguaje::class, $peticion );
		self::assertSame( PropositoLenguaje::VistaPrevia, $peticion->proposito );

		// Las directrices reflejan la conducta CANDIDATA (90/80/70...), no la
		// ya guardada del periodista (40/20/10...) ni su línea editorial vieja.
		self::assertStringContainsString( '90/100', $peticion->directrices );
		self::assertStringContainsString( 'línea candidata, todavía sin guardar', $peticion->directrices );
		self::assertStringNotContainsString( 'línea original', $peticion->directrices );

		// El material es siempre el hecho de muestra fijo, nunca un expediente real.
		self::assertStringContainsString( 'banco central', $peticion->material );
	}

	public function test_usa_un_tono_por_defecto_si_la_matriz_candidata_no_tiene_fila_para_el_tipo_de_muestra(): void {
		$proveedor = new ProveedorLenguajeFalso( 'párrafo' );

		// Matriz vacía de filas configuradas: solo trae la fila de sistema de Tragedia.
		$matrizSinDatoEconomico = MatrizTonos::desdeFilas( array() );

		( new GeneradorVistaPrevia( $proveedor ) )->generar(
			$this->periodista(),
			$this->dialesCandidatos(),
			$this->reglasCandidatas(),
			$matrizSinDatoEconomico
		);

		self::assertNotNull( $proveedor->ultimaPeticion );
	}

	public function test_propaga_la_excepcion_del_proveedor_cuando_el_presupuesto_esta_agotado(): void {
		$proveedor = Mockery::mock( LenguajeInterface::class );
		$proveedor->expects( 'completar' )->andThrow( new ProveedorLenguajeException( 'Presupuesto diario agotado.', presupuestoAgotado: true ) );

		$this->expectException( ProveedorLenguajeException::class );

		( new GeneradorVistaPrevia( $proveedor ) )->generar(
			$this->periodista(),
			$this->dialesCandidatos(),
			$this->reglasCandidatas(),
			MatrizTonos::desdeFilas( array() )
		);
	}
}
