<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Un índice (posición dentro de la lista original de textos) y su
 * puntuación, tal como los devuelve el endpoint `/rerank` de TEI
 * (`ADR 0020`).
 */
final readonly class ResultadoRerank {

	public function __construct(
		public int $indice,
		public float $puntuacion,
	) {
	}
}
