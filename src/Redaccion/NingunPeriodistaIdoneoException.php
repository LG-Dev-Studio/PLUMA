<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use RuntimeException;

/**
 * Nivel Dos C.3: ningún periodista del banco supera el umbral de dominio
 * mínimo para el vertical detectado. Deliberadamente NO extiende
 * `DecisionEditorialException` — es un resultado esperado del negocio ("el
 * banco tiene un hueco real"), no un fallo de formato/infraestructura, y no
 * debe caer en los `catch (DecisionEditorialException)` existentes.
 */
final class NingunPeriodistaIdoneoException extends RuntimeException {

	public function __construct(
		public readonly string $tema,
		public readonly float $umbralDominio,
		public readonly int $mejorDominioEncontrado,
	) {
		parent::__construct(
			sprintf(
				'Ningún periodista del banco supera el umbral de dominio (%.1f/100) para el vertical "%s". Mejor dominio disponible: %d/5.',
				$umbralDominio,
				$tema,
				$mejorDominioEncontrado
			)
		);
	}
}
