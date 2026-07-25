<?php

declare(strict_types=1);

namespace Pluma\Compuertas;

/**
 * Diagnóstico de la Compuerta de Calidad (Libro Cap. 8.1, Nivel Tres K.2):
 * puntuación compuesta 0–100 sobre los tres factores de PRIORIDAD
 * (proporción interpretativa, legibilidad, voz) — pero solo tras superar dos
 * PISOS eliminatorios.
 *
 * `sustentoAprobado` y `estructuraCompleta` son pisos duros, no factores de
 * puntuación (GOVERNANCE §2.4/§1.6): una pieza sin sustento o sin estructura
 * completa reprueba Calidad con `puntuacionTotal = 0`, sin importar cuánto
 * sume en los demás ejes. Diluir cualquiera de los dos en un promedio
 * permitiría que una alucinación o una pieza sin remate/Bloque del Editor
 * "se compre" con buena legibilidad — exactamente lo que la regla de oro
 * prohíbe (`docs/puntuaciones.md`).
 */
final readonly class DiagnosticoCalidad {

	/**
	 * @param list<string> $detalle
	 */
	public function __construct(
		public int $puntuacionTotal,
		public int $umbral,
		public bool $sustentoAprobado,
		public bool $estructuraCompleta,
		public array $detalle,
	) {
	}

	public function aprobada(): bool {
		return $this->sustentoAprobado && $this->estructuraCompleta && $this->puntuacionTotal >= $this->umbral;
	}

	/**
	 * @return array{puntuacionTotal: int, umbral: int, sustentoAprobado: bool, estructuraCompleta: bool, detalle: list<string>}
	 */
	public function aArray(): array {
		return array(
			'puntuacionTotal'    => $this->puntuacionTotal,
			'umbral'             => $this->umbral,
			'sustentoAprobado'   => $this->sustentoAprobado,
			'estructuraCompleta' => $this->estructuraCompleta,
			'detalle'            => $this->detalle,
		);
	}

	/**
	 * `estructuraCompleta` usa `?? true` al leer: diagnósticos persistidos
	 * antes de Nivel Tres K.2 no traen esta clave, y esas Piezas ya
	 * atravesaron sus propias Compuertas en su momento — no se reevalúan
	 * retroactivamente, solo se leen para mostrar el historial.
	 *
	 * @param array{puntuacionTotal: int, umbral: int, sustentoAprobado: bool, estructuraCompleta?: bool, detalle: list<string>} $datos
	 */
	public static function desdeArray( array $datos ): self {
		return new self(
			$datos['puntuacionTotal'],
			$datos['umbral'],
			$datos['sustentoAprobado'],
			$datos['estructuraCompleta'] ?? true,
			$datos['detalle']
		);
	}
}
