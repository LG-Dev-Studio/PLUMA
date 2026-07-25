<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

/**
 * Declaración de identidad editorial sintética para la página de autor de
 * cada periodista (Nivel Tres N.3, Art. 50 del Reglamento (UE) 2024/1689):
 * "el nombre es una identidad editorial sintética y no una persona física" —
 * claridad regulatoria por encima de la elegancia de marca del Libro Cap. 5.2.
 * Texto fijo (no configurable): a diferencia de `AvisoTransparenciaIa`
 * (formato breve/extendido elegible por el cliente), esta declaración no
 * tiene opción de formato — existe siempre y dice lo mismo.
 */
final class DeclaracionIdentidadSintetica {

	public function comoHtml( Periodista $periodista ): string {
		$texto = sprintf(
			/* translators: %s: nombre del periodista sintético. */
			__( '%s es una identidad editorial sintética generada por inteligencia artificial, no una persona física. Su voz y criterio editorial se calibran y se supervisan bajo dirección editorial humana.', 'pluma-engine' ),
			$periodista->nombre
		);

		return sprintf( '<p class="pluma-identidad-sintetica"><strong>%s</strong></p>', esc_html( $texto ) );
	}
}
