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
		string $localeEditorial = 'es-ES',
		bool $creadoAutomaticamente = false
	): int;

	public function obtenerPorId( int $id ): ?Periodista;

	/**
	 * Trabajo posterior a la Etapa 9 (creación automática de periodistas):
	 * cuenta los periodistas sembrados por `CreadorAutomaticoPeriodistas`
	 * que siguen "en juego" (Propuesto o Activo, nunca Jubilado) — la
	 * guarda real del tope `pluma_creacion_automatica_max_periodistas`.
	 */
	public function contarAutomaticosActivos(): int;

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
		DateTimeImmutable $ahora
	): int;

	public function obtenerVersionConducta( int $versionId ): ?ConductaVersion;

	/**
	 * Actualiza los campos de Identidad (nombre, avatarUrl, biografia, rol,
	 * especialidades) de un periodista existente, sin tocar su Conducta.
	 *
	 * A diferencia de Conducta — que se versiona explícitamente, nunca se
	 * sobrescribe (pl-periodistas §1) — la Identidad se sobrescribe
	 * directamente: no hay valor de negocio en conservar un historial de "el
	 * periodista se llamaba distinto antes" o "antes cubría otro vertical".
	 * Devuelve `false` si el id no existe.
	 *
	 * @param list<Especialidad> $especialidades
	 */
	public function actualizarIdentidad(
		int $periodistaId,
		string $nombre,
		?string $avatarUrl,
		string $biografia,
		RolPeriodista $rol,
		array $especialidades,
		DateTimeImmutable $ahora
	): bool;

	public function jubilar( int $periodistaId, DateTimeImmutable $ahora ): bool;

	/**
	 * Trabajo posterior a la Etapa 9 (creación automática de periodistas):
	 * periodistas nacidos {@see EstadoPeriodista::Propuesto} que siguen
	 * esperando la ventana de veto o una acción humana explícita.
	 *
	 * @return list<Periodista>
	 */
	public function obtenerPropuestos(): array;

	/**
	 * Promueve un periodista Propuesto a Activo — al expirar la ventana de
	 * veto (`Orquestador::procesarPeriodistasPropuestosVencidos()`) o por
	 * "Aprobar ahora" del editor. Devuelve `false` si el id no existe.
	 */
	public function activarPropuesta( int $periodistaId, DateTimeImmutable $ahora ): bool;

	/**
	 * Descarta una propuesta rechazada por el editor: borrado real, a
	 * diferencia de `jubilar()` — una propuesta nunca llegó a publicar
	 * nada, no hay historial que conservar. Devuelve `false` si el id no
	 * existe o no está en estado Propuesto.
	 */
	public function descartarPropuesta( int $periodistaId ): bool;
}
