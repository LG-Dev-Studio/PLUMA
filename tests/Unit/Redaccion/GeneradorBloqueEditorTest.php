<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Pluma\Redaccion\ClasificacionNoticia;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\DecisionEditorialException;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\GeneradorBloqueEditor;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\NovedadNoticia;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use DateTimeImmutable;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;

/**
 * Fase 7 del ciclo editorial (Libro Cap. 5.7).
 *
 * @covers \Pluma\Redaccion\GeneradorBloqueEditor
 */
final class GeneradorBloqueEditorTest extends CasoDePruebaUnitario {

	private function periodista(): Periodista {
		$diales   = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas   = new ReglasConducta( 'linea', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 1, 1, $diales, $reglas, $matriz, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

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

	private function clasificacion(): ClasificacionNoticia {
		return new ClasificacionNoticia( 'economia', 30, 'gobierno vs oposición', NovedadNoticia::Primicia, 50, TipoNoticia::DatoEconomico );
	}

	public function test_construye_el_bloque_a_partir_de_una_respuesta_valida(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"comentario": "Yo ya lo veía venir.", "pregunta": "¿A quién le crees aquí, y por qué?"}' );

		$bloque = ( new GeneradorBloqueEditor( $proveedor ) )->generar( $this->periodista(), $this->clasificacion(), 'tesis' );

		self::assertSame( 'Yo ya lo veía venir.', $bloque->comentario );
		self::assertSame( '¿A quién le crees aquí, y por qué?', $bloque->pregunta );
	}

	public function test_lanza_excepcion_si_falta_el_comentario_o_la_pregunta(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"comentario": "solo esto"}' );

		$this->expectException( DecisionEditorialException::class );

		( new GeneradorBloqueEditor( $proveedor ) )->generar( $this->periodista(), $this->clasificacion(), 'tesis' );
	}

	public function test_lanza_excepcion_si_la_respuesta_llego_truncada(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"comentario": "x", "pregunta": "y"}', truncada: true );

		$this->expectException( DecisionEditorialException::class );

		( new GeneradorBloqueEditor( $proveedor ) )->generar( $this->periodista(), $this->clasificacion(), 'tesis' );
	}
}
