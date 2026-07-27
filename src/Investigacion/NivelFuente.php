<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

/**
 * Nivel Dos B.3: jerarquía de confianza de una fuente (Libro Cap. 4.3),
 * "solo la lista de confianza editable" — nunca infalibilidad. Nivel C
 * nunca es sustento por sí solo, solo pista.
 */
enum NivelFuente: string {

	case A = 'a';
	case B = 'b';
	case C = 'c';

	/**
	 * `nivel_fuente_base` de B.3: A=1.0, B=0.6, C=0.15.
	 */
	public function pesoBase(): float {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- falso positivo: $this en un método de enum (PHP 8.1) es válido; el sniff aún no reconoce enums.
		return match ( $this ) {
			self::A => 1.0,
			self::B => 0.6,
			self::C => 0.15,
		};
	}
}
