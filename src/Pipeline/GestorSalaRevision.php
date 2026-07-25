<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

use Pluma\Compuertas\ModoOperacion;
use Pluma\Datos\RepositorioColaPublicacionInterface;
use Pluma\Datos\RepositorioPiezasInterface;

/**
 * Sala de Revisión (Libro Cap. 10.2): "la bandeja de lo que espera decisión
 * humana" — piezas RETENIDAS y, en modo Copiloto, la cola de veto con
 * cuenta regresiva. Tres acciones: aprobar / devolver con nota / descartar.
 */
final class GestorSalaRevision {

	private const LIMITE_DEFECTO = 50;

	public function __construct(
		private readonly RepositorioPiezasInterface $piezas,
		private readonly RepositorioColaPublicacionInterface $colaPublicacion,
		private readonly Transicionador $transicionador,
	) {
	}

	/**
	 * @return list<Pieza>
	 */
	public function obtenerRetenidas( int $limite = self::LIMITE_DEFECTO ): array {
		return $this->piezas->obtenerPorEstado( EstadoPieza::Retenida, $limite );
	}

	/**
	 * Piezas PROGRAMADAS cuyo modo efectivo es Copiloto: siguen esperando el
	 * fin de la ventana de veto antes de publicarse solas (Cap. 2.4).
	 *
	 * @return list<EntradaColaDeVeto>
	 */
	public function obtenerColaDeVeto( int $ventanaVetoHoras, int $limite = self::LIMITE_DEFECTO ): array {
		$entradas = array();

		foreach ( $this->piezas->obtenerPorEstado( EstadoPieza::Programada, $limite ) as $pieza ) {
			if ( ModoOperacion::Copiloto !== ( $pieza->resultadoCompuertas->modoEfectivo ?? null ) ) {
				continue;
			}

			$ranura = $this->colaPublicacion->obtenerProgramadaPorPieza( $pieza->id );

			if ( null === $ranura ) {
				continue;
			}

			$entradas[] = new EntradaColaDeVeto( $pieza, $ranura, $ranura->horaProgramada->modify( "+{$ventanaVetoHoras} hours" ) );
		}

		return $entradas;
	}

	/**
	 * Anulación humana informada de una retención (Cap. 8.2: "RETENIDA para
	 * humano" — el humano es la autoridad final de este caso, no un atajo
	 * automático alrededor de las Compuertas).
	 *
	 * `$origen` identifica la pantalla que disparó la acción en la
	 * auditoría (Sala de Revisión o Mesa Editorial, Cap. 10.2: "forzar
	 * aprobación" ahí es literalmente este mismo botón, limitado a
	 * RETENIDA — el grafo del Transicionador ya rechaza cualquier otro
	 * origen con `TransicionInvalidaException`).
	 *
	 * @throws PiezaNoEncontradaException
	 * @throws TransicionInvalidaException
	 */
	public function aprobar( int $piezaId, string $origen = 'la Sala de Revisión' ): void {
		$this->transicionador->transitar( $piezaId, EstadoPieza::Aprobada, "Aprobada manualmente desde {$origen}.", 'editor' );
	}

	/**
	 * Reingresa a OPTIMIZADA (no a EN_REVISION, un estado transitorio que
	 * nadie vuelve a sondear por sí solo) para que la pieza pase de nuevo
	 * por Compuertas de verdad en el próximo tick del Orquestador.
	 *
	 * @throws PiezaNoEncontradaException
	 * @throws TransicionInvalidaException
	 */
	public function devolver( int $piezaId, string $nota ): void {
		$motivo = '' !== trim( $nota )
			? sprintf( 'Devuelta a revisión desde la Sala de Revisión: %s', $nota )
			: 'Devuelta a revisión desde la Sala de Revisión.';

		$this->transicionador->transitar( $piezaId, EstadoPieza::Optimizada, $motivo, 'editor' );
	}

	/**
	 * Descarta una pieza RETENIDA o, si todavía está en la cola de veto
	 * (PROGRAMADA), la veta antes de que se publique sola: expira también
	 * su ranura en `pluma_cola_publicacion`.
	 *
	 * @throws PiezaNoEncontradaException
	 * @throws TransicionInvalidaException
	 */
	/**
	 * "Aprobar ahora" (Etapa 6, porción 4c; Nivel Tres N.3 (c)): aprobación
	 * humana activa sobre una pieza en la cola de veto de Copiloto, antes de
	 * que expire la ventana. Marca la ranura para que el Orquestador la
	 * publique en su próximo tick sin esperar el resto de la ventana, y sin
	 * marcado de IA en el frontend (Art. 50 UE: la excepción aplica cuando
	 * hubo aprobación humana activa antes de publicar).
	 *
	 * @throws PiezaNoEncontradaException
	 * @throws AccionNoDisponibleException si la pieza no está en la cola de veto de Copiloto
	 */
	public function aprobarAhora( int $piezaId ): void {
		$pieza = $this->piezas->obtenerPorId( $piezaId );

		if ( null === $pieza ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new PiezaNoEncontradaException( $piezaId );
		}

		if ( EstadoPieza::Programada !== $pieza->estado || ModoOperacion::Copiloto !== ( $pieza->resultadoCompuertas->modoEfectivo ?? null ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new AccionNoDisponibleException( "La pieza {$piezaId} no está en la cola de veto de Copiloto." );
		}

		$ranura = $this->colaPublicacion->obtenerProgramadaPorPieza( $piezaId );

		if ( null === $ranura ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new AccionNoDisponibleException( "La pieza {$piezaId} no tiene una ranura programada activa." );
		}

		$this->colaPublicacion->marcarAprobacionActiva( $ranura->id );
	}

	public function descartar( int $piezaId, string $origen = 'la Sala de Revisión' ): void {
		$pieza = $this->piezas->obtenerPorId( $piezaId );

		if ( null === $pieza ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new PiezaNoEncontradaException( $piezaId );
		}

		$estabaProgramada = EstadoPieza::Programada === $pieza->estado;

		$this->transicionador->transitar( $piezaId, EstadoPieza::Descartada, "Descartada manualmente desde {$origen}.", 'editor' );

		if ( $estabaProgramada ) {
			$ranura = $this->colaPublicacion->obtenerProgramadaPorPieza( $piezaId );

			if ( null !== $ranura ) {
				$this->colaPublicacion->marcarExpirada( $ranura->id );
			}
		}
	}
}
