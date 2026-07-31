<?php

declare(strict_types=1);

namespace Pluma\Idioma;

/**
 * Pliega diacríticos latinos a su letra base (á→a, ñ→n...) para comparaciones
 * de texto tolerantes a acentos.
 *
 * Decisión (`PLUMA-E9-21`): tabla manual `strtr()`, no `Normalizer`/`iconv`.
 * `ext-intl` no está garantizado en el hosting de un cliente, e
 * `iconv//TRANSLIT` depende de la libc del servidor — una tabla fija es
 * 100% determinista en cualquier PHP 8.2.
 */
final class PlegadorDiacriticos {

	private const TABLA = array(
		'á' => 'a',
		'à' => 'a',
		'ä' => 'a',
		'â' => 'a',
		'ã' => 'a',
		'é' => 'e',
		'è' => 'e',
		'ë' => 'e',
		'ê' => 'e',
		'í' => 'i',
		'ì' => 'i',
		'ï' => 'i',
		'î' => 'i',
		'ó' => 'o',
		'ò' => 'o',
		'ö' => 'o',
		'ô' => 'o',
		'õ' => 'o',
		'ú' => 'u',
		'ù' => 'u',
		'ü' => 'u',
		'û' => 'u',
		'ñ' => 'n',
		'ç' => 'c',
	);

	public static function plegar( string $texto ): string {
		return strtr( $texto, self::TABLA );
	}
}
