<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Idioma;

use Pluma\Idioma\PlegadorDiacriticos;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Idioma\PlegadorDiacriticos
 */
final class PlegadorDiacriticosTest extends CasoDePruebaUnitario {

	public function test_pliega_vocales_acentuadas(): void {
		self::assertSame( 'economia', PlegadorDiacriticos::plegar( 'economía' ) );
	}

	public function test_pliega_ene_con_tilde(): void {
		self::assertSame( 'nino', PlegadorDiacriticos::plegar( 'niño' ) );
	}

	public function test_pliega_dieresis_y_cedilla(): void {
		self::assertSame( 'pinguino lecao', PlegadorDiacriticos::plegar( 'pingüino leção' ) );
	}

	public function test_texto_sin_diacriticos_queda_igual(): void {
		self::assertSame( 'texto sin acentos', PlegadorDiacriticos::plegar( 'texto sin acentos' ) );
	}

	public function test_cadena_vacia_devuelve_cadena_vacia(): void {
		self::assertSame( '', PlegadorDiacriticos::plegar( '' ) );
	}

	public function test_es_idempotente(): void {
		$plegado = PlegadorDiacriticos::plegar( 'Economía Política' );

		self::assertSame( $plegado, PlegadorDiacriticos::plegar( $plegado ) );
	}
}
