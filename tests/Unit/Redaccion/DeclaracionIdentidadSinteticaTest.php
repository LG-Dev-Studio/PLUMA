<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\DeclaracionIdentidadSintetica;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * Nivel Tres N.3 (Art. 50 del Reglamento (UE) 2024/1689): la página de autor
 * debe declarar sin ambigüedad que el nombre es una identidad editorial
 * sintética, no una persona física. Texto fijo, no configurable.
 *
 * @covers \Pluma\Redaccion\DeclaracionIdentidadSintetica
 */
final class DeclaracionIdentidadSinteticaTest extends CasoDePruebaUnitario {

	private function periodista( string $nombre ): Periodista {
		$diales   = new Diales( 50, 50, 50, 50, 50, 50, 50, 50 );
		$reglas   = new ReglasConducta( 'Línea editorial de prueba.', array(), array(), array(), TratamientoLector::Tu, '¿Y tú qué opinas?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 1, 1, $diales, $reglas, $matriz, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

		return new Periodista(
			1,
			$nombre,
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

	private function mockearFunciones(): void {
		Functions\when( 'esc_html' )->alias( static fn ( string $s ): string => htmlspecialchars( $s, ENT_QUOTES ) );
		Functions\when( '__' )->alias( static fn ( string $s ): string => $s );
	}

	public function test_declara_sin_ambiguedad_que_el_nombre_es_una_identidad_sintetica(): void {
		$this->mockearFunciones();

		$html = ( new DeclaracionIdentidadSintetica() )->comoHtml( $this->periodista( 'Valentina Ruiz' ) );

		self::assertStringContainsString( 'Valentina Ruiz', $html );
		self::assertStringContainsString( 'identidad editorial sintética', $html );
		self::assertStringContainsString( 'no una persona física', $html );
		self::assertStringContainsString( 'dirección editorial humana', $html );
	}

	public function test_escapa_el_nombre_del_periodista(): void {
		$this->mockearFunciones();

		$html = ( new DeclaracionIdentidadSintetica() )->comoHtml( $this->periodista( '<script>alert(1)</script>' ) );

		self::assertStringNotContainsString( '<script>', $html );
	}
}
