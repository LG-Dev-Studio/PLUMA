<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

/**
 * Salida de la Fase 3.5, Prueba de Falseabilidad (Nivel Tres O.1): el caso
 * más fuerte que el propio sistema pudo construir EN CONTRA de la tesis
 * ganadora, usando exclusivamente el expediente, y su fuerza — evaluada por
 * sustento verificable, no por elocuencia — en la misma escala 0-100 que
 * `CandidatoTesis::$puntuacionSustento`, para que ambas sean comparables.
 */
final readonly class ResultadoFalseabilidad {

	public function __construct(
		public string $casoEnContra,
		public float $fuerzaSustento,
	) {
	}
}
