<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use DateTimeImmutable;
use Pluma\Redaccion\ConductaVersion;
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
use Pluma\Redaccion\VerificadorVoz;
use Pluma\Redaccion\VocabularioProhibidoGlobal;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * Punto 4 del Corrector Interno (Libro Cap. 5.6): "¿Suena a la voz del
 * periodista?" — pl-periodistas §Rasgos de voz.
 *
 * @covers \Pluma\Redaccion\VerificadorVoz
 */
final class VerificadorVozTest extends CasoDePruebaUnitario {

	private function periodista( array $muletillas, array $vocabularioProhibido = array() ): Periodista {
		$diales   = new Diales( 50, 50, 50, 50, 50, 50, 50, 50 );
		$reglas   = new ReglasConducta( 'linea', array(), $muletillas, $vocabularioProhibido, TratamientoLector::Tu, '¿Y tú?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 1, 1, $diales, $reglas, $matriz, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

		return new Periodista(
			1,
			'Periodista',
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

	public function test_aprueba_un_texto_con_un_solo_rasgo_de_voz_presente(): void {
		$periodista = $this->periodista( array( 'hagamos cuentas', 'ustedes ya saben cómo termina esto' ) );

		$anotacion = ( new VerificadorVoz() )->verificar( $periodista, 'Hagamos cuentas: el resultado no cuadra con lo que dice el gobierno.' );

		self::assertTrue( $anotacion->aprobado );
	}

	public function test_reprueba_un_texto_sin_ningun_rasgo_de_voz(): void {
		$periodista = $this->periodista( array( 'hagamos cuentas' ) );

		$anotacion = ( new VerificadorVoz() )->verificar( $periodista, 'Un texto neutro que no usa ninguna de las muletillas del periodista.' );

		self::assertFalse( $anotacion->aprobado );
	}

	public function test_reprueba_si_todos_los_rasgos_de_voz_aparecen_a_la_vez(): void {
		$periodista = $this->periodista( array( 'hagamos cuentas', 'ustedes ya saben cómo termina esto' ) );

		$anotacion = ( new VerificadorVoz() )->verificar(
			$periodista,
			'Hagamos cuentas sobre esto: ustedes ya saben cómo termina esto, como siempre.'
		);

		self::assertFalse( $anotacion->aprobado );
	}

	public function test_reprueba_si_aparece_vocabulario_prohibido_propio(): void {
		$periodista = $this->periodista( array(), array( 'dar la vuelta a la tortilla' ) );

		$anotacion = ( new VerificadorVoz() )->verificar( $periodista, 'El gobierno intentó dar la vuelta a la tortilla, sin éxito.' );

		self::assertFalse( $anotacion->aprobado );
	}

	public function test_reprueba_si_aparece_una_muletilla_de_texto_ia_de_la_lista_global(): void {
		$periodista  = $this->periodista( array() );
		$muletillaIa = VocabularioProhibidoGlobal::muletillasDeTextoIa()[0];

		$anotacion = ( new VerificadorVoz() )->verificar( $periodista, "Es importante señalar que {$muletillaIa} aparece en el texto." );

		self::assertFalse( $anotacion->aprobado );
	}

	public function test_aprueba_un_texto_limpio_cuando_el_periodista_no_tiene_rasgos_definidos(): void {
		$periodista = $this->periodista( array() );

		$anotacion = ( new VerificadorVoz() )->verificar( $periodista, 'Un texto cualquiera, sin rasgos definidos que exigir.' );

		self::assertTrue( $anotacion->aprobado );
	}
}
