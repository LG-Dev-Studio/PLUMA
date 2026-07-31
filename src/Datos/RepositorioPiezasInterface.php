<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Compuertas\ResultadoEvaluacion;
use Pluma\Investigacion\Expediente;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\Pieza;
use Pluma\Pipeline\TipoPieza;
use Pluma\Redaccion\FichaDecisionEditorial;
use Pluma\Seo\DatosSeo;
use Pluma\Taxonomia\ResultadoTaxonomia;

/**
 * Contrato del repositorio de Piezas. `Pluma\Pipeline\Transicionador` y
 * `Orquestador` dependen de esta interfaz, jamás de la implementación
 * concreta sobre `$wpdb` — así son testeables en Unit con un doble simple.
 */
interface RepositorioPiezasInterface {

	public function crear( int $tendenciaId, DateTimeImmutable $ahora ): int;

	/**
	 * Crea una Pieza como actualización enlazada de otra ya cubierta (Libro
	 * Cap. 3.4, "dos golpes") — solo se invoca cuando el editor confirma
	 * "Cubrir como actualización" desde la Sala de Tendencias sobre una
	 * tendencia POSIBLE_ACTUALIZACION (decisión del propietario, 2026-07-23:
	 * nunca automático).
	 */
	public function crearComoActualizacion( int $tendenciaId, int $piezaOriginalId, DateTimeImmutable $ahora ): int;

	public function obtenerPorId( int $id ): ?Pieza;

	/**
	 * Resuelve una Pieza a partir del post de WordPress que publicó (Libro
	 * Cap. 6.4: el bucle de Search Console reporta por URL de página, hay
	 * que mapearla de vuelta a la Pieza real que la generó). `null` si el
	 * post no es de ninguna Pieza gestionada por PLUMA.
	 */
	public function obtenerPorPostId( int $postId ): ?Pieza;

	/**
	 * La Pieza más reciente de una tendencia (Sala de Tendencias, Libro
	 * Cap. 10.2: las acciones Cubrir/Ignorar/Vigilar operan sobre la Pieza
	 * en curso de la tarjeta). `null` si nunca se creó una.
	 */
	public function obtenerUltimaPorTendencia( int $tendenciaId ): ?Pieza;

	/**
	 * "Cubrir ahora (salta la cola)" (Libro Cap. 10.2): marca la Pieza como
	 * prioritaria — el Orquestador ordena cada lote por prioridad DESC antes
	 * que por antigüedad. Devuelve `false` si la Pieza no existe.
	 */
	public function priorizar( int $id, DateTimeImmutable $ahora ): bool;

	/**
	 * @return list<Pieza>
	 */
	public function obtenerPorEstado( EstadoPieza $estado, int $limite ): array;

	/**
	 * Piezas en `$estado` dentro de una ventana de fechas explícita (Libro
	 * Cap. 14, Etapa 5: informes editoriales semanales) — a diferencia de
	 * `obtenerPorEstado()`, que acota "recientes" desde ahora hacia atrás,
	 * esta consulta permite cualquier rango `[$desde, $hasta]`.
	 *
	 * @return list<Pieza>
	 */
	public function obtenerPorEstadoEntre( EstadoPieza $estado, DateTimeImmutable $desde, DateTimeImmutable $hasta, int $limite ): array;

	/**
	 * Total exacto de Piezas en `$estado` (Portada, Libro Cap. 10.2: "piezas
	 * en cada estado del pipeline, como un tablero kanban"). Deliberadamente
	 * un `COUNT(*)` propio y no `count(obtenerPorEstado(...))`: ese método
	 * está acotado por `$limite` y subestimaría el total real.
	 */
	public function contarPorEstado( EstadoPieza $estado ): int;

	/**
	 * Actualización optimista: solo aplica si la fila sigue en
	 * `$estadoEsperado` (candado por-Pieza — pl-pipeline §2). Devuelve
	 * `false` si otra ejecución ya la movió.
	 */
	public function actualizarEstado(
		int $id,
		EstadoPieza $estadoEsperado,
		EstadoPieza $nuevoEstado,
		DateTimeImmutable $ahora
	): bool;

	public function actualizarExpediente( int $id, Expediente $expediente, DateTimeImmutable $ahora ): bool;

	public function actualizarPostId( int $id, int $postId, DateTimeImmutable $ahora ): bool;

	/**
	 * Paso 2 del Algoritmo de Decisión Editorial (Libro Cap. 5.5): registra
	 * qué periodista y qué versión de su Conducta redactó la pieza.
	 */
	public function asignarPeriodista( int $id, int $periodistaId, int $periodistaVersionId, DateTimeImmutable $ahora ): bool;

	public function actualizarFichaDecisionEditorial( int $id, FichaDecisionEditorial $ficha, DateTimeImmutable $ahora ): bool;

	/**
	 * Trabajo posterior a la Etapa 9 (creación automática de periodistas):
	 * registra el `$clasificacion->tema` que ningún periodista activo pudo
	 * cubrir, en el momento en que `NingunPeriodistaIdoneoException` deja la
	 * Pieza en SIN_PERIODISTA_IDONEO — señal real para
	 * `CreadorAutomaticoPeriodistas`, antes descartada dentro de la frase
	 * libre de la excepción.
	 */
	public function actualizarTemaSinCubrir( int $id, string $tema, DateTimeImmutable $ahora ): bool;

	/**
	 * Persiste el resultado de `Pluma\Compuertas\EvaluadorCompuertas` (Libro
	 * Cap. 8.4): el JSON completo del diagnóstico y, denormalizado, el modo
	 * efectivo para que el Orquestador pueda filtrar por él sin deserializar.
	 */
	public function actualizarResultadoCompuertas( int $id, ResultadoEvaluacion $resultado, DateTimeImmutable $ahora ): bool;

	/**
	 * Persiste el resultado de `Pluma\Seo\MotorSeo` (Libro Cap. 6.2-6.3): el
	 * JSON completo y, denormalizada, la keyword principal (indexada) para
	 * que `Pluma\Seo\AuditorCanibalizacion` pueda consultarla sin deserializar.
	 */
	public function actualizarDatosSeo( int $id, DatosSeo $datos, DateTimeImmutable $ahora ): bool;

	/**
	 * Auditoría de canibalización (Libro Cap. 6.3): ¿alguna OTRA pieza ya
	 * PUBLICADA usa esta misma keyword principal? `$excluirPiezaId` evita que
	 * una pieza se detecte a sí misma al re-evaluarse.
	 */
	public function existePiezaPublicadaConKeyword( string $keywordPrincipal, int $excluirPiezaId ): bool;

	/**
	 * Nivel Cuatro U.1 (Etapa 9): vincula la Pieza a una Historia (saga) con
	 * su tipo dentro de esa saga. Devuelve `false` si la Pieza no existe.
	 */
	public function vincularHistoria( int $id, int $historiaId, TipoPieza $tipo, DateTimeImmutable $ahora ): bool;

	/**
	 * Todas las Piezas de una Historia, en orden cronológico de creación
	 * (Nivel Cuatro U.1: "piezas asociadas en orden" — U.2: cronología del
	 * hub público).
	 *
	 * @return list<Pieza>
	 */
	public function obtenerPorHistoria( int $historiaId ): array;

	/**
	 * Persiste el resultado de `Pluma\Taxonomia\Taxonomo` (Libro Cap. 7): la
	 * categoría asignada y las etiquetas (nuevas o reutilizadas).
	 */
	public function actualizarResultadoTaxonomia( int $id, ResultadoTaxonomia $resultado, DateTimeImmutable $ahora ): bool;

	/**
	 * Piezas asignadas a `$periodistaId` desde `$desde` (inclusive). Paso 2
	 * del Algoritmo de Decisión Editorial (Libro Cap. 5.5): "balance de
	 * carga — nadie firma 10 piezas seguidas el mismo día".
	 */
	public function contarAsignadasDesde( int $periodistaId, DateTimeImmutable $desde ): int;

	/**
	 * Métricas vivas del Banco de Periodistas (Libro Cap. 10.2: "piezas,
	 * tráfico medio, comentarios medios, verticales donde más posiciona").
	 * "Tráfico medio" queda fuera — sin fuente real todavía (Search Console,
	 * Etapa 5) — cero invención, igual que en la Portada.
	 *
	 * @return array{piezasPublicadas: int, verticalesTop: list<string>}
	 */
	public function metricasPorPeriodista( int $periodistaId ): array;

	/**
	 * Nivel Cuatro W.1 — piezas para componer el boletín de un periodista:
	 * las últimas publicadas, con `postId` para poder resolver título/
	 * extracto reales contra el post de WordPress.
	 *
	 * @return list<Pieza>
	 */
	public function obtenerPublicadasRecientesPorPeriodista( int $periodistaId, int $limite ): array;

	/**
	 * Auditoría de canibalización agregada (Estudio SEO y Taxonomía, Libro
	 * Cap. 10.2): TODOS los grupos de Piezas ya PUBLICADAS que comparten la
	 * misma keyword principal — a diferencia de
	 * `existePiezaPublicadaConKeyword()`, que solo responde por una Pieza
	 * concreta, esto lista el panorama completo para el panel.
	 *
	 * @return list<array{keywordPrincipal: string, piezaIds: list<int>}>
	 */
	public function obtenerCanibalizacion(): array;
}
