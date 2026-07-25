<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

/**
 * Instantánea que se persiste como post meta al publicar, para que el emisor
 * de frontend (`Pluma\Seo\EmisorEsquemaFrontend`) construya el JSON-LD y el
 * marcado de transparencia de IA (Art. 50 UE, Nivel Tres N.3) **sin ninguna
 * consulta a repositorios en tiempo de render** (CLAUDE.md: peso adicional en
 * frontend ≈ 0).
 *
 * `tipoEsquema` viaja como string (el `value` de `Pluma\Seo\TipoEsquemaArticulo`)
 * para que `Pluma\Publicacion` no dependa del enum de `Pluma\Seo`.
 */
final readonly class SnapshotPublicacion {

	public function __construct(
		public int $piezaId,
		public bool $generadoIa,
		public string $modoPublicacion,
		public string $tipoEsquema,
		public string $autorNombre,
	) {
	}
}
