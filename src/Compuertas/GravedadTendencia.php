<?php

declare(strict_types=1);

namespace Pluma\Compuertas;

/**
 * Clasificación de gravedad de una tendencia recién detectada (Nivel Dos
 * F.1-F.2) — el eje de gravedad 0-100 que el disparador automático del modo
 * respeto necesita, y que el Radar nunca calculó hasta ahora (solo existía,
 * más tarde en el pipeline, como `ClasificacionNoticia::$gravedad`, ya
 * post-asignación). `campoGeografico` es nulo cuando el expediente no da
 * pie a identificar un ámbito geográfico claro — nunca se inventa uno.
 */
final readonly class GravedadTendencia {

	public function __construct(
		public int $gravedad,
		public string $campoTematico,
		public ?string $campoGeografico,
	) {
	}
}
