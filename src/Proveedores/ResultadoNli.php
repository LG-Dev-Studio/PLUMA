<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Una etiqueta y su puntuación, tal como las devuelve el endpoint `/predict`
 * de TEI (`ADR 0020`) para un par premisa/hipótesis.
 */
final readonly class ResultadoNli {

	public function __construct(
		public EtiquetaNli $etiqueta,
		public float $puntuacion,
	) {
	}
}
