<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Datos\RepositorioTendenciasInterface;
use Pluma\Kernel\RelojInterface;
use Pluma\Sensores\EstadoTendencia;

/**
 * Sala de Tendencias (Libro Cap. 10.2): las tres acciones directas con las
 * que el editor interviene la agenda — Cubrir ahora / Ignorar / Vigilar.
 * Semántica fijada por decisión del propietario (2026-07-23, ver
 * `EstadoTendencia`): ignorar/vigilar descartan la Pieza en curso vía el
 * Transicionador (con auditoría, actor `editor`); cubrir prioriza la Pieza
 * activa o crea una nueva con prioridad si la anterior fue descartada.
 */
final class GestorSalaTendencias {

	public function __construct(
		private readonly RepositorioTendenciasInterface $tendencias,
		private readonly RepositorioPiezasInterface $piezas,
		private readonly Transicionador $transicionador,
		private readonly RelojInterface $reloj,
		private readonly GestorHistorias $gestorHistorias,
	) {
	}

	/**
	 * @return list<array{id: int, termino: string, fuenteSenal: string, velocidad: float, afinidad: float, puntuacionTotal: float, estado: EstadoTendencia, articulosRelacionados: list<array{titulo: string, url: string, fuente: string}>, detectadaEn: string}>
	 */
	public function obtenerTarjetas( int $limite = 30 ): array {
		return $this->tendencias->obtenerParaSala( $limite );
	}

	/**
	 * "Cubrir ahora (salta la cola)": si la Pieza en curso sigue viva, se
	 * prioriza; si fue descartada (tendencia vigilada/ignorada) se crea una
	 * nueva ya prioritaria. En ambos casos la tendencia vuelve a EN_PIPELINE.
	 *
	 * @throws TendenciaNoEncontradaException
	 */
	public function cubrirAhora( int $tendenciaId ): void {
		$this->asegurarQueExiste( $tendenciaId, EstadoTendencia::EnPipeline );

		$pieza = $this->piezas->obtenerUltimaPorTendencia( $tendenciaId );

		if ( null === $pieza || EstadoPieza::Descartada === $pieza->estado ) {
			$piezaId = $this->piezas->crear( $tendenciaId, $this->reloj->ahora() );
			$this->piezas->priorizar( $piezaId, $this->reloj->ahora() );

			return;
		}

		$this->piezas->priorizar( $pieza->id, $this->reloj->ahora() );
	}

	/**
	 * "Cubrir como actualización" (Libro Cap. 3.4, "dos golpes"): confirma
	 * que una tendencia marcada POSIBLE_ACTUALIZACION por el Radar es en
	 * efecto la evolución de la historia original, y crea la Pieza enlazada
	 * — decisión del propietario, 2026-07-23: nunca automático, siempre
	 * confirmado aquí por el editor.
	 *
	 * @throws TendenciaNoEncontradaException
	 */
	public function cubrirComoActualizacion( int $tendenciaId ): void {
		$tendenciaOriginalId = $this->tendencias->obtenerTendenciaOriginal( $tendenciaId );

		if ( null === $tendenciaOriginalId ) {
			$excepcion = new TendenciaNoEncontradaException( $tendenciaId );

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje interno construido por la propia excepción, sin entrada de usuario.
			throw $excepcion;
		}

		$this->asegurarQueExiste( $tendenciaId, EstadoTendencia::EnPipeline );

		$piezaOriginal = $this->piezas->obtenerUltimaPorTendencia( $tendenciaOriginalId );

		if ( null !== $piezaOriginal ) {
			$ahora   = $this->reloj->ahora();
			$piezaId = $this->piezas->crearComoActualizacion( $tendenciaId, $piezaOriginal->id, $ahora );

			// Nivel Cuatro U.1 (Etapa 9): "dos golpes" confirmado por el
			// editor es el único momento en que dos Piezas se saben parte de
			// la misma saga — se vinculan a una Historia aquí, nunca antes.
			$tendenciaOriginal = $this->tendencias->obtenerPorId( $tendenciaOriginalId );
			$tituloSaga        = $tendenciaOriginal['termino'] ?? '';
			$this->gestorHistorias->vincularActualizacion( $piezaOriginal, $piezaId, $tituloSaga );
		} else {
			$piezaId = $this->piezas->crear( $tendenciaId, $this->reloj->ahora() );
		}

		$this->piezas->priorizar( $piezaId, $this->reloj->ahora() );
	}

	/**
	 * @throws TendenciaNoEncontradaException
	 */
	public function ignorar( int $tendenciaId ): void {
		$this->asegurarQueExiste( $tendenciaId, EstadoTendencia::Ignorada );
		$this->descartarPiezaEnCurso( $tendenciaId, 'Tendencia ignorada desde la Sala de Tendencias.' );
	}

	/**
	 * @throws TendenciaNoEncontradaException
	 */
	public function vigilar( int $tendenciaId ): void {
		$this->asegurarQueExiste( $tendenciaId, EstadoTendencia::Vigilada );
		$this->descartarPiezaEnCurso( $tendenciaId, 'Tendencia puesta en vigilancia desde la Sala de Tendencias.' );
	}

	/**
	 * @throws TendenciaNoEncontradaException
	 */
	private function asegurarQueExiste( int $tendenciaId, EstadoTendencia $nuevoEstado ): void {
		if ( ! $this->tendencias->actualizarEstadoTendencia( $tendenciaId, $nuevoEstado ) ) {
			$excepcion = new TendenciaNoEncontradaException( $tendenciaId );

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje interno construido por la propia excepción, sin entrada de usuario.
			throw $excepcion;
		}
	}

	private function descartarPiezaEnCurso( int $tendenciaId, string $motivo ): void {
		$pieza = $this->piezas->obtenerUltimaPorTendencia( $tendenciaId );

		// Sin Pieza viva no hay nada que descartar: PUBLICADA es trabajo ya
		// entregado (no se toca) y DESCARTADA/FALLIDA ya están fuera del flujo.
		if ( null === $pieza || ! $this->esDescartable( $pieza->estado ) ) {
			return;
		}

		$this->transicionador->transitar( $pieza->id, EstadoPieza::Descartada, $motivo, 'editor' );
	}

	private function esDescartable( EstadoPieza $estado ): bool {
		return match ( $estado ) {
			EstadoPieza::Publicada, EstadoPieza::Descartada, EstadoPieza::Fallida => false,
			default => true,
		};
	}
}
