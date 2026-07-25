<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

/**
 * Transparencia de autoría IA (Libro Cap. 5.2, GOVERNANCE §2.6): "el bloque
 * configurable existe siempre; la opción controla el formato, no la
 * existencia". Ninguna Pieza redactada por `RedactorSintetico` se publica
 * sin este aviso — solo cambia si es breve o extendido.
 */
final class AvisoTransparenciaIa {

	public const OPCION_FORMATO    = 'pluma_transparencia_ia_formato';
	public const FORMATO_BREVE     = 'breve';
	public const FORMATO_EXTENDIDO = 'extendido';

	public function comoHtml( string $nombrePeriodista ): string {
		$formato = get_option( self::OPCION_FORMATO, self::FORMATO_BREVE );
		$formato = is_string( $formato ) && self::FORMATO_EXTENDIDO === $formato ? self::FORMATO_EXTENDIDO : self::FORMATO_BREVE;

		$texto = self::FORMATO_EXTENDIDO === $formato
			? sprintf(
				/* translators: %s: nombre del periodista sintético firmante. */
				__( 'Esta pieza fue redactada por %s, un periodista sintético entrenado y editado bajo dirección editorial humana: la IA genera el borrador, un editor humano supervisa la publicación.', 'pluma-engine' ),
				$nombrePeriodista
			)
			: sprintf(
				/* translators: %s: nombre del periodista sintético firmante. */
				__( '%s es un periodista sintético bajo dirección editorial humana.', 'pluma-engine' ),
				$nombrePeriodista
			);

		return sprintf( '<p class="pluma-transparencia-ia"><small>%s</small></p>', esc_html( $texto ) );
	}
}
