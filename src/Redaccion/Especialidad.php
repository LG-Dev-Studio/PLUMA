<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

/**
 * Vertical que un periodista puede firmar, con nivel de dominio 1–5
 * (Libro Cap. 5.2, Capa 1 — Identidad). El Paso 2 del Algoritmo de Decisión
 * Editorial pondera el dominio del vertical con peso alto en la asignación.
 */
final readonly class Especialidad {

	/**
	 * Sentinela de "cubre todos los temas" — un periodista generalista
	 * declara una única Especialidad con este vertical en vez de una fila
	 * por tema. `Periodista::dominioDe()` lo trata como comodín: solo
	 * responde cuando ningún vertical declarado calza exactamente con el
	 * tema real de la Pieza (un match exacto siempre gana sobre el
	 * comodín, aunque el comodín tenga nivel más alto).
	 */
	public const VERTICAL_COMODIN = '*';

	public function __construct(
		public string $vertical,
		public int $nivelDominio,
	) {
	}

	/**
	 * @return array{vertical: string, nivelDominio: int}
	 */
	public function aArray(): array {
		return array(
			'vertical'     => $this->vertical,
			'nivelDominio' => $this->nivelDominio,
		);
	}

	/**
	 * @param array{vertical: string, nivelDominio: int} $datos
	 */
	public static function desdeArray( array $datos ): self {
		return new self( $datos['vertical'], $datos['nivelDominio'] );
	}
}
