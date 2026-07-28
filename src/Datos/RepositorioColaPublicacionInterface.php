<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Pipeline\RanuraPublicacion;

/**
 * Contrato del repositorio de `pluma_cola_publicacion` (Libro Cap. 9.2-9.3).
 */
interface RepositorioColaPublicacionInterface {

	public function programar(
		int $piezaId,
		string $vertical,
		?int $periodistaId,
		DateTimeImmutable $horaProgramada,
		DateTimeImmutable $ahora
	): int;

	/**
	 * Ranuras programadas o publicadas cuyo `hora_programada` cae dentro de
	 * `[$inicio, $fin)` — la base para calcular cuota/topes ya comprometidos
	 * en un día (Cap. 9.3, paso 3).
	 *
	 * @return list<RanuraPublicacion>
	 */
	public function obtenerEntre( DateTimeImmutable $inicio, DateTimeImmutable $fin ): array;

	/**
	 * Ranuras `programada` cuya hora ya llegó — listas para publicarse
	 * (Cap. 9.3, paso 5).
	 *
	 * @return list<RanuraPublicacion>
	 */
	public function obtenerVencidas( DateTimeImmutable $ahora ): array;

	public function marcarPublicada( int $id ): bool;

	public function marcarExpirada( int $id ): bool;

	/**
	 * Marca que un humano usó "aprobar ahora" sobre esta ranura en la cola de
	 * veto de Copiloto (Etapa 6, porción 4c, Nivel Tres N.3 (c)): el
	 * Orquestador la publica en el próximo tick sin esperar el resto de la
	 * ventana de veto, y sin marcado de IA en el frontend (Art. 50 UE).
	 */
	public function marcarAprobacionActiva( int $id ): bool;

	/**
	 * La ranura `programada` más reciente de una Pieza (Sala de Revisión,
	 * Libro Cap. 10.2: "en modo Copiloto, la cola de veto con cuenta
	 * regresiva" — necesita la hora programada para calcular el límite de
	 * la ventana de veto).
	 */
	public function obtenerProgramadaPorPieza( int $piezaId ): ?RanuraPublicacion;

	/**
	 * Modo respeto (Nivel Dos F.3): pausa TODAS las ranuras `programada` —
	 * nunca las descarta. Devuelve cuántas se pausaron.
	 */
	public function pausarProgramadas(): int;

	/**
	 * @return list<RanuraPublicacion>
	 */
	public function obtenerPausadas(): array;

	/**
	 * Reactiva una ranura pausada con una hora nueva (jitter recalculado,
	 * Nivel Dos F.3) — vuelve a `programada`.
	 */
	public function reprogramar( int $id, DateTimeImmutable $nuevaHora ): bool;
}
