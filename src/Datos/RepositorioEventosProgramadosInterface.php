<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Pipeline\EstadoEventoProgramado;
use Pluma\Pipeline\EventoProgramado;

/**
 * Contrato del repositorio del Calendario Editorial (Nivel Cuatro V.1).
 */
interface RepositorioEventosProgramadosInterface {

	public function crear( string $titulo, string $vertical, DateTimeImmutable $fechaEsperada, ?int $periodistaAsignadoId, ?int $historiaId, DateTimeImmutable $ahora ): int;

	public function obtenerPorId( int $id ): ?EventoProgramado;

	/**
	 * @return list<EventoProgramado>
	 */
	public function listar( int $limite ): array;

	public function actualizarEstado( int $id, EstadoEventoProgramado $estado, DateTimeImmutable $ahora ): bool;

	public function vincularTendencia( int $id, int $tendenciaId, DateTimeImmutable $ahora ): bool;

	public function vincularHistoria( int $id, int $historiaId, DateTimeImmutable $ahora ): bool;
}
