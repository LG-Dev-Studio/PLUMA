<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Idioma;

use Pluma\Idioma\DireccionEscritura;
use Pluma\Idioma\NivelCobertura;
use Pluma\Idioma\ResolutorPerfilIdioma;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Idioma\ResolutorPerfilIdioma
 */
final class ResolutorPerfilIdiomaTest extends CasoDePruebaUnitario {

	public function test_es_es_tiene_cobertura_parcial_y_direccion_ltr(): void {
		$perfil = ( new ResolutorPerfilIdioma() )->resolver( 'es-ES' );

		self::assertSame( 'es-ES', $perfil->locale );
		self::assertSame( DireccionEscritura::Ltr, $perfil->direccion );
		self::assertSame( NivelCobertura::Parcial, $perfil->cobertura );
		self::assertNull( $perfil->motivoCobertura );
	}

	public function test_arabe_saudi_es_no_soportado_pero_direccion_rtl(): void {
		$perfil = ( new ResolutorPerfilIdioma() )->resolver( 'ar-SA' );

		self::assertSame( DireccionEscritura::Rtl, $perfil->direccion );
		self::assertSame( NivelCobertura::NoSoportado, $perfil->cobertura );
		self::assertNotNull( $perfil->motivoCobertura );
	}

	public function test_hebreo_israel_es_no_soportado_pero_direccion_rtl(): void {
		$perfil = ( new ResolutorPerfilIdioma() )->resolver( 'he-IL' );

		self::assertSame( DireccionEscritura::Rtl, $perfil->direccion );
		self::assertSame( NivelCobertura::NoSoportado, $perfil->cobertura );
	}

	public function test_frances_es_no_soportado_y_direccion_ltr(): void {
		$perfil = ( new ResolutorPerfilIdioma() )->resolver( 'fr-FR' );

		self::assertSame( DireccionEscritura::Ltr, $perfil->direccion );
		self::assertSame( NivelCobertura::NoSoportado, $perfil->cobertura );
		self::assertNotNull( $perfil->motivoCobertura );
	}

	/**
	 * Invariante de Plano 0 (`ADR 0012`): ningún resolutor de esta porción
	 * debe producir `Completo` — exige los órganos ONNX del Plano 1, que no
	 * existen todavía.
	 */
	public function test_ningun_locale_produce_cobertura_completa(): void {
		$resolutor = new ResolutorPerfilIdioma();

		foreach ( array( 'es-ES', 'ar-SA', 'he-IL', 'fa-IR', 'ur-PK', 'fr-FR', 'en-US', 'pt-BR' ) as $locale ) {
			self::assertNotSame(
				NivelCobertura::Completo,
				$resolutor->resolver( $locale )->cobertura,
				"El locale {$locale} no debe resolver a NivelCobertura::Completo."
			);
		}
	}
}
