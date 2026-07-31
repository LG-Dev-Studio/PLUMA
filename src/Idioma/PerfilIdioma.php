<?php

declare(strict_types=1);

namespace Pluma\Idioma;

/**
 * Perfil de idioma/escritura de un locale editorial, resuelto en Plano 0
 * (PHP puro, determinista). Deliberadamente no incluye segmentador,
 * tokenizador, stemmer, fórmula de legibilidad ni formatos de fecha/número:
 * esos dependen de los órganos semánticos del Plano 1 (NCP-2/NCP-3), que no
 * existen todavía — añadirlos aquí sería un campo que nadie lee.
 */
final readonly class PerfilIdioma {

	public function __construct(
		public string $locale,
		public DireccionEscritura $direccion,
		public NivelCobertura $cobertura,
		public ?string $motivoCobertura,
	) {
	}
}
