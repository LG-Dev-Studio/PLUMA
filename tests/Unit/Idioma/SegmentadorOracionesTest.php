<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Idioma;

use Brain\Monkey\Functions;
use Pluma\Idioma\SegmentadorOraciones;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * Rol SEG (`ADR 0018`): cada caso corre dos veces — una contra ICU real (la
 * extensión `intl` del entorno de pruebas la tiene compilada) y otra forzando
 * `extension_loaded('intl')` a `false` vía `Brain\Monkey`, para confirmar que
 * el fallback determinista también funciona, no solo la rama que da la
 * casualidad del entorno.
 *
 * @covers \Pluma\Idioma\SegmentadorOraciones
 */
final class SegmentadorOracionesTest extends CasoDePruebaUnitario {

	private function forzarSinIcu(): void {
		Functions\when( 'extension_loaded' )->justReturn( false );
	}

	public function test_texto_vacio_no_produce_ninguna_oracion(): void {
		self::assertSame( array(), ( new SegmentadorOraciones() )->segmentar( '' ) );

		$this->forzarSinIcu();
		self::assertSame( array(), ( new SegmentadorOraciones() )->segmentar( '' ) );
	}

	public function test_una_sola_oracion_se_devuelve_intacta(): void {
		$texto = 'El mercado cerró estable hoy.';

		self::assertSame( array( $texto ), ( new SegmentadorOraciones() )->segmentar( $texto ) );

		$this->forzarSinIcu();
		self::assertSame( array( $texto ), ( new SegmentadorOraciones() )->segmentar( $texto ) );
	}

	public function test_varias_oraciones_se_separan_correctamente(): void {
		$texto = 'El mercado cerró estable hoy. La inflación bajó medio punto. ¿Qué pasará mañana?';

		$esperado = array(
			'El mercado cerró estable hoy.',
			'La inflación bajó medio punto.',
			'¿Qué pasará mañana?',
		);

		self::assertSame( $esperado, ( new SegmentadorOraciones() )->segmentar( $texto ) );

		$this->forzarSinIcu();
		self::assertSame( $esperado, ( new SegmentadorOraciones() )->segmentar( $texto ) );
	}

	public function test_abreviatura_protegida_no_parte_la_oracion(): void {
		$texto = 'El Dr. Smith llegó tarde a la reunión. La sesión empezó sin él.';

		$esperado = array(
			'El Dr. Smith llegó tarde a la reunión.',
			'La sesión empezó sin él.',
		);

		self::assertSame( $esperado, ( new SegmentadorOraciones() )->segmentar( $texto ) );

		$this->forzarSinIcu();
		self::assertSame( $esperado, ( new SegmentadorOraciones() )->segmentar( $texto ) );
	}

	public function test_numero_decimal_no_se_parte_como_fin_de_oracion(): void {
		$texto = 'La inflación cerró en 4.5 puntos este mes. El dato sorprendió a los analistas.';

		$esperado = array(
			'La inflación cerró en 4.5 puntos este mes.',
			'El dato sorprendió a los analistas.',
		);

		self::assertSame( $esperado, ( new SegmentadorOraciones() )->segmentar( $texto ) );

		$this->forzarSinIcu();
		self::assertSame( $esperado, ( new SegmentadorOraciones() )->segmentar( $texto ) );
	}

	public function test_signos_de_apertura_en_espanol_no_confunden_la_segmentacion(): void {
		$texto = '¿Cómo estás? Espero que bien. ¡Qué buena noticia!';

		$esperado = array(
			'¿Cómo estás?',
			'Espero que bien.',
			'¡Qué buena noticia!',
		);

		self::assertSame( $esperado, ( new SegmentadorOraciones() )->segmentar( $texto ) );

		$this->forzarSinIcu();
		self::assertSame( $esperado, ( new SegmentadorOraciones() )->segmentar( $texto ) );
	}
}
