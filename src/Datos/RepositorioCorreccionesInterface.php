<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Publicacion\Correccion;
use Pluma\Publicacion\EstadoCorreccion;

/**
 * Contrato del repositorio de correcciones reportadas por lectores (Nivel
 * Cuatro X.4).
 */
interface RepositorioCorreccionesInterface {

	public function crear( int $piezaId, string $afirmacionReportada, string $evidenciaAportada, ?string $emailReportante, ?string $nombreCredito, bool $creditoOptIn, DateTimeImmutable $ahora ): int;

	public function obtenerPorId( int $id ): ?Correccion;

	/**
	 * @return list<Correccion>
	 */
	public function obtenerPorEstado( EstadoCorreccion $estado, int $limite ): array;

	public function resolver( int $id, EstadoCorreccion $estado, ?string $notaEditor, DateTimeImmutable $ahora ): bool;

	/**
	 * Historial público (Capítulo Z: "historial público de correcciones") —
	 * solo las verificadas, más recientes primero.
	 *
	 * @return list<Correccion>
	 */
	public function obtenerVerificadasRecientes( int $limite ): array;
}
