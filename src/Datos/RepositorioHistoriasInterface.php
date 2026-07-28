<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Pipeline\EstadoHistoria;
use Pluma\Pipeline\Historia;

/**
 * Contrato del repositorio de Historias (Nivel Cuatro U.1). `piezaIds` de
 * la entidad `Historia` se reconstruye desde `pluma_piezas.historia_id`
 * (`RepositorioPiezasInterface::obtenerPorHistoria()`), nunca se persiste
 * duplicado en `pluma_historias`.
 */
interface RepositorioHistoriasInterface {

	public function crear( string $titulo, DateTimeImmutable $ahora ): int;

	public function obtenerPorId( int $id ): ?Historia;

	public function actualizarEstado( int $id, EstadoHistoria $estado, DateTimeImmutable $ahora ): bool;

	public function asignarPeriodistaTitular( int $id, int $periodistaId, DateTimeImmutable $ahora ): bool;

	public function tocar( int $id, DateTimeImmutable $ahora ): bool;

	/**
	 * Historias `Abierta`/`EnSeguimiento` sin actividad desde `$limite`
	 * (Nivel Cuatro U.1: transición a `Inactiva` — nunca declarada por el
	 * sistema como cerrada, solo como "sin cobertura reciente").
	 *
	 * @return list<int>
	 */
	public function obtenerAbiertasSinActividadDesde( DateTimeImmutable $limite ): array;
}
