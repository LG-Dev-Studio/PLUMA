<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\Especialidad;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;

/**
 * Contrato del repositorio del banco de periodistas (pl-periodistas
 * §Contratos innegociables 1, 8): la Conducta se versiona, nunca se
 * sobrescribe; el banco completo es exportable/importable.
 */
interface RepositorioPeriodistasInterface {

	/**
	 * Crea un periodista con su primera versión de Conducta. Devuelve el id
	 * del periodista creado.
	 *
	 * @param list<Especialidad> $especialidades
	 */
	public function crear(
		string $nombre,
		?string $avatarUrl,
		string $biografia,
		RolPeriodista $rol,
		array $especialidades,
		EstadoPeriodista $estado,
		Diales $diales,
		ReglasConducta $reglas,
		MatrizTonos $matrizTonos,
		DateTimeImmutable $ahora,
		string $localeEditorial = 'es-ES'
	): int;

	public function obtenerPorId( int $id ): ?Periodista;

	/**
	 * @return list<Periodista>
	 */
	public function obtenerActivos(): array;

	/**
	 * Incluye jubilados — a diferencia de {@see obtenerActivos()}, para
	 * export/import completo del banco (pl-periodistas §8).
	 *
	 * @return list<Periodista>
	 */
	public function obtenerTodos(): array;

	/**
	 * Todas las versiones de Conducta de un periodista, de la más vieja a la
	 * más nueva — el historial completo (pl-periodistas §1), para
	 * export/import.
	 *
	 * @return list<ConductaVersion>
	 */
	public function obtenerHistorialVersiones( int $periodistaId ): array;

	/**
	 * Registra una nueva versión de Conducta y la deja como la actual del
	 * periodista (pl-periodistas §1: cada modificación crea versión fechada,
	 * jamás se sobrescribe una existente). Devuelve el id de la nueva versión.
	 */
	public function nuevaVersionConducta(
		int $periodistaId,
		Diales $diales,
		ReglasConducta $reglas,
		MatrizTonos $matrizTonos,
		bool $respuestasHabilitadas,
		DateTimeImmutable $ahora
	): int;

	public function obtenerVersionConducta( int $versionId ): ?ConductaVersion;

	public function jubilar( int $periodistaId, DateTimeImmutable $ahora ): bool;
}
