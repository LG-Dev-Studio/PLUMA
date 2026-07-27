<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Redaccion\EntradaMemoria;
use Pluma\Redaccion\TipoMemoria;

/**
 * Contrato del repositorio de Memoria editorial (Libro Cap. 5.4,
 * pl-periodistas §Contratos innegociables 3: "memoria antes de tesis" — el
 * registro de posturas se consulta ANTES de seleccionar ángulo).
 */
interface RepositorioMemoriaEditorialInterface {

	/**
	 * @param array<string, mixed> $contenido
	 */
	public function registrar(
		int $periodistaId,
		TipoMemoria $tipo,
		string $tema,
		array $contenido,
		?int $piezaId,
		DateTimeImmutable $ahora
	): int;

	/**
	 * Posturas previas de este periodista sobre `$tema`, más recientes
	 * primero — la consulta que el Algoritmo de Decisión Editorial hace
	 * ANTES de seleccionar ángulo (Libro Cap. 5.4).
	 *
	 * @return list<EntradaMemoria>
	 */
	public function obtenerPosturasPorTema( int $periodistaId, string $tema ): array;

	/**
	 * Posturas previas sobre `$tema` de CUALQUIER periodista (activo o
	 * jubilado), más recientes primero — la memoria colectiva del sitio
	 * (Nivel Dos E.2): un periodista nuevo, o uno que nunca cubrió este tema,
	 * necesita saber si la redacción como voz colectiva ya se pronunció,
	 * aunque quien lo hizo ya no firme. Distinto de
	 * {@see obtenerPosturasPorTema()}, que filtra por un único periodista.
	 *
	 * @return list<EntradaMemoria>
	 */
	public function obtenerPosturasColectivasPorTema( string $tema, int $limite = 20 ): array;

	/**
	 * @return list<EntradaMemoria>
	 */
	public function obtenerPorPeriodista( int $periodistaId, ?TipoMemoria $tipo = null, int $limite = 50 ): array;

	/**
	 * Toda la memoria de un periodista, sin límite — para export/import
	 * completo del banco (pl-periodistas §8).
	 *
	 * @return list<EntradaMemoria>
	 */
	public function obtenerTodoPorPeriodista( int $periodistaId ): array;

	/**
	 * ¿Este periodista ya cubrió `$tema` antes? Paso 2 del Algoritmo de
	 * Decisión Editorial (Libro Cap. 5.5): "historial con esa historia" —
	 * factor de asignación, vía `TipoMemoria::Cobertura`.
	 */
	public function existeCoberturaDelTema( int $periodistaId, string $tema ): bool;
}
