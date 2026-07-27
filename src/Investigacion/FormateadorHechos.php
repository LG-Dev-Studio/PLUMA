<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

/**
 * Formatea los hechos de un expediente como texto plano indexado, para
 * enviarlos como `material` de una `PeticionLenguaje` en las llamadas de
 * clasificación del propio Investigador (B.1/B.2, B.4/O.2) — cada hecho
 * viaja con su índice, url y estado de verificación actual.
 */
final class FormateadorHechos {

	/**
	 * @param list<HechoFuente> $hechos
	 */
	public static function comoTexto( array $hechos ): string {
		$lineas = array();

		foreach ( $hechos as $indice => $hecho ) {
			$lineas[] = sprintf( '[%d] (%s, %s) %s — %s', $indice, $hecho->nivel->value, $hecho->url, $hecho->extracto, $hecho->fecha->format( DATE_ATOM ) );
		}

		return implode( "\n", $lineas );
	}
}
