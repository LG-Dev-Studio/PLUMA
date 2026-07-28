<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Sensores\EstadoTendencia;
use Pluma\Sensores\TendenciaDetectada;

interface RepositorioTendenciasInterface {

	/**
	 * Deduplicación exacta por término normalizado + fuente de señal
	 * (huella semántica real queda como deuda — Libro Cap. 3.4).
	 */
	public function existePorTermino( string $termino, string $fuenteSenal ): bool;

	public function guardar( TendenciaDetectada $tendencia, DateTimeImmutable $ahora ): int;

	/**
	 * Guarda una tendencia detectada como POSIBLE_ACTUALIZACION de otra ya
	 * procesada (Libro Cap. 3.4, "dos golpes") — decisión del propietario,
	 * 2026-07-23: NO crea Pieza, el editor confirma desde la Sala de
	 * Tendencias antes de gastar investigación/redacción.
	 */
	public function guardarComoPosibleActualizacion( TendenciaDetectada $tendencia, int $tendenciaOriginalId, DateTimeImmutable $ahora ): int;

	/**
	 * Candidatas reales para la huella semántica (`ComparadorHistorias`):
	 * tendencias de los últimos `$diasVentana` días cuya última Pieza NO esté
	 * DESCARTADA ni FALLIDA — comparar contra una tendencia que se abandonó
	 * no tiene valor para detectar "dos golpes".
	 *
	 * @return list<array{id: int, termino: string, articulosRelacionados: list<array{titulo: string, url: string, fuente: string}>}>
	 */
	public function obtenerRecientesConPiezaViva( int $diasVentana, int $limite, DateTimeImmutable $ahora ): array;

	/**
	 * @return array{termino: string, articulosRelacionados: list<array{titulo: string, url: string, fuente: string}>}|null
	 */
	public function obtenerPorId( int $id ): ?array;

	/**
	 * Tendencias más recientes ordenadas por puntuación total (Portada,
	 * Libro Cap. 10.2: "tendencias calientes ahora").
	 *
	 * @return list<array{id: int, termino: string, puntuacionTotal: float, detectadaEn: string}>
	 */
	public function obtenerRecientes( int $limite ): array;

	/**
	 * Tarjetas completas para la Sala de Tendencias (Libro Cap. 10.2):
	 * puntuación desglosada, estado y quién la está cubriendo ya. Excluye
	 * las IGNORADAS — el editor las sacó de la agenda.
	 *
	 * @return list<array{id: int, termino: string, fuenteSenal: string, velocidad: float, afinidad: float, puntuacionTotal: float, estado: EstadoTendencia, articulosRelacionados: list<array{titulo: string, url: string, fuente: string}>, detectadaEn: string, tendenciaOriginalId: ?int}>
	 */
	public function obtenerParaSala( int $limite ): array;

	/**
	 * Cambia el estado de la tendencia en el radar (Sala de Tendencias:
	 * Cubrir ahora / Ignorar / Vigilar). Devuelve `false` si no existe.
	 */
	public function actualizarEstadoTendencia( int $id, EstadoTendencia $estado ): bool;

	/**
	 * `tendencia_original_id` de una tendencia (Libro Cap. 3.4, "dos
	 * golpes") — `null` si no existe la tendencia o no está vinculada.
	 */
	public function obtenerTendenciaOriginal( int $id ): ?int;

	/**
	 * Total de tendencias en `$estado` detectadas dentro de `[$desde, $hasta]`
	 * (Libro Cap. 14, Etapa 5: informes editoriales semanales).
	 */
	public function contarPorEstadoEntre( EstadoTendencia $estado, DateTimeImmutable $desde, DateTimeImmutable $hasta ): int;

	/**
	 * Persiste la clasificación de `Pluma\Compuertas\ClasificadorGravedadTendencia`
	 * (Nivel Dos F.1-F.2) — se llama una sola vez, justo cuando la tendencia
	 * entra al pipeline.
	 */
	public function actualizarGravedad( int $id, int $gravedad, string $campoTematico, ?string $campoGeografico ): void;

	/**
	 * Tendencias de gravedad máxima (`>= $umbralGravedad`) detectadas desde
	 * `$desde` — el material crudo del disparador automático del modo
	 * respeto (F.2): agrupar por `campoTematico`/`campoGeografico` y contar
	 * es responsabilidad del llamador, no de esta consulta.
	 *
	 * @return list<array{id: int, campoTematico: string, campoGeografico: ?string}>
	 */
	public function obtenerGravedadMaximaRecientes( int $umbralGravedad, DateTimeImmutable $desde ): array;
}
