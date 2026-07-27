<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

/**
 * Una postura previa de la memoria COLECTIVA del sitio (Nivel Dos E.2):
 * `$entrada` viene de un periodista distinto al asignado a la Pieza actual
 * — `$periodistaNombre`/`$periodistaActivo` resuelven la atribución que
 * `SelectorAngulo` necesita para redactar el reconocimiento en el registro
 * correcto: individual ("hace tres meses defendí lo contrario") si el
 * periodista sigue activo, o de sitio ("esta redacción sostuvo antes una
 * lectura distinta") si ya está jubilado.
 */
final readonly class PosturaColectiva {

	public function __construct(
		public EntradaMemoria $entrada,
		public string $periodistaNombre,
		public bool $periodistaActivo,
	) {
	}
}
