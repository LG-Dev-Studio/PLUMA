<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use Pluma\Datos\RepositorioPistasInterface;
use Pluma\Kernel\RelojInterface;

/**
 * Nivel Cuatro X.3 — el buzón de pistas: "¿Sabes algo más sobre esta
 * historia?" en cada hub de Historia. Toda pista es material de procedencia
 * NO verificada por definición — nunca entra al expediente directo, solo
 * queda a la vista del editor para que decida si investigarla por los
 * canales normales (p. ej. como fuente aportada al preparar cobertura,
 * `Pluma\Pipeline\GestorCalendarioEditorial::prepararCobertura()`).
 */
final class GestorPistas {

	public function __construct(
		private readonly RepositorioPistasInterface $pistas,
		private readonly RelojInterface $reloj,
	) {
	}

	public function reportar( int $historiaId, string $contenido, ?string $contactoEmail ): int {
		return $this->pistas->crear( $historiaId, $contenido, $contactoEmail, $this->reloj->ahora() );
	}

	/**
	 * @return list<Pista>
	 */
	public function pendientes( int $limite = 50 ): array {
		return $this->pistas->obtenerPorEstado( EstadoPista::Pendiente, $limite );
	}

	/**
	 * @throws PistaNoEncontradaException
	 */
	public function marcarRevisada( int $id ): void {
		$this->transitar( $id, EstadoPista::Revisada );
	}

	/**
	 * @throws PistaNoEncontradaException
	 */
	public function marcarDescartada( int $id ): void {
		$this->transitar( $id, EstadoPista::Descartada );
	}

	/**
	 * @throws PistaNoEncontradaException
	 */
	private function transitar( int $id, EstadoPista $estado ): void {
		if ( null === $this->pistas->obtenerPorId( $id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje interno construido por la propia excepción, sin entrada de usuario.
			throw new PistaNoEncontradaException( $id );
		}

		$this->pistas->actualizarEstado( $id, $estado );
	}
}
