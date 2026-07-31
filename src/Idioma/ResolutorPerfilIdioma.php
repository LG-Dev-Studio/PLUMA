<?php

declare(strict_types=1);

namespace Pluma\Idioma;

/**
 * Resuelve el `PerfilIdioma` de un locale editorial. Servicio puro, sin
 * dependencias: mismo patrón que `Pluma\Taxonomia\ExtractorEntidades`.
 *
 * Hoy solo `es-ES` tiene catálogo léxico curado (`Parcial` — los órganos de
 * Plano 1 que darían `Completo` no existen). Cualquier otro locale es
 * `NoSoportado`: negar la venta de una función es preferible a entregarla
 * degradada en silencio.
 */
final class ResolutorPerfilIdioma {

	/** @var list<string> */
	private const SUBTAGS_RTL = array( 'ar', 'he', 'iw', 'fa', 'ur' );

	/** @var list<string> */
	private const LOCALES_CON_COBERTURA_PARCIAL = array( 'es-ES' );

	public function resolver( string $locale ): PerfilIdioma {
		$subtagPrimarioParseado = strtok( $locale, '-_' );
		$subtagPrimario         = strtolower( false !== $subtagPrimarioParseado ? $subtagPrimarioParseado : $locale );
		$direccion              = in_array( $subtagPrimario, self::SUBTAGS_RTL, true )
			? DireccionEscritura::Rtl
			: DireccionEscritura::Ltr;

		if ( in_array( $locale, self::LOCALES_CON_COBERTURA_PARCIAL, true ) ) {
			return new PerfilIdioma( $locale, $direccion, NivelCobertura::Parcial, null );
		}

		return new PerfilIdioma(
			$locale,
			$direccion,
			NivelCobertura::NoSoportado,
			sprintf(
				'PLUMA no tiene catálogo léxico ni verificación de voz para el locale "%s" — solo "es-ES" está soportado hoy.',
				$locale
			)
		);
	}
}
