<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

use RuntimeException;

/**
 * GOVERNANCE §2.8 (Nivel Tres J.2/J.4): el modo Autónomo exige que el
 * verificador resuelva a una familia de modelo distinta de la del redactor.
 * "Debe fallar de forma explícita, nunca degradar en silencio".
 */
final class IndependenciaEpistemicaException extends RuntimeException {

	public function __construct( string $familiaCompartida ) {
		parent::__construct(
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML; $familiaCompartida viene de slugs de modelo configurados por el propio administrador, no de entrada de un tercero.
			"El modo Autónomo exige un verificador de familia de modelo distinta a la del redactor; ambos resuelven a la familia '{$familiaCompartida}'."
		);
	}
}
