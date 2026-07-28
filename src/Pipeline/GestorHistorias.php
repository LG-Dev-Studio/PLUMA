<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

use DateInterval;
use Pluma\Datos\RepositorioHistoriasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Kernel\RelojInterface;

/**
 * Nivel Cuatro U.1 — la entidad Historia: agrupa Piezas de una misma saga
 * (detectada por `Pluma\Sensores\ComparadorHistorias`, huella semántica del
 * Radar) y mantiene su ciclo de vida propio + el bloque "Lo que sabemos /
 * Lo que no sabemos".
 *
 * Se engancha en el ÚNICO punto donde hoy se enlazan dos Piezas de la misma
 * saga: `RedactorConFallbackMecanico`/`GestorSalaTendencias::cubrirComoActualizacion()`
 * → `RepositorioPiezasInterface::crearComoActualizacion()`. Nunca se
 * inventa una detección de saga nueva — reutiliza exactamente la que ya
 * existe (decisión del propietario, 2026-07-23: "dos golpes" nunca
 * automático).
 */
final class GestorHistorias {

	public const OPCION_DIAS_INACTIVIDAD = 'pluma_historia_dias_inactividad';

	private const DIAS_INACTIVIDAD_DEFECTO = 14;
	private const UMBRAL_PIEZAS_HUB        = 2;

	public function __construct(
		private readonly RepositorioHistoriasInterface $historias,
		private readonly RepositorioPiezasInterface $piezas,
		private readonly RelojInterface $reloj,
	) {
	}

	/**
	 * Vincula la Pieza original y la Pieza de actualización a la misma
	 * Historia — crea la Historia si la original todavía no pertenecía a
	 * ninguna (primera actualización de la saga). Devuelve el id de la
	 * Historia.
	 */
	public function vincularActualizacion( Pieza $piezaOriginal, int $piezaNuevaId, string $tituloSaga ): int {
		$ahora      = $this->reloj->ahora();
		$historiaId = $piezaOriginal->historiaId;

		if ( null === $historiaId ) {
			$historiaId = $this->historias->crear( $tituloSaga, $ahora );
			$this->piezas->vincularHistoria( $piezaOriginal->id, $historiaId, $piezaOriginal->tipo, $ahora );
		}

		$this->piezas->vincularHistoria( $piezaNuevaId, $historiaId, TipoPieza::Actualizacion, $ahora );

		$totalPiezas = count( $this->piezas->obtenerPorHistoria( $historiaId ) );

		$this->historias->actualizarEstado(
			$historiaId,
			$totalPiezas >= self::UMBRAL_PIEZAS_HUB ? EstadoHistoria::EnSeguimiento : EstadoHistoria::Abierta,
			$ahora
		);

		return $historiaId;
	}

	/**
	 * Historia completamente hidratada: `RepositorioHistoriasInterface` no
	 * conoce `pluma_piezas` (CLAUDE.md § Ley de Arquitectura, un repositorio
	 * por tabla) — aquí se compone con `piezaIds` real, en orden.
	 */
	public function obtener( int $historiaId ): ?Historia {
		$historia = $this->historias->obtenerPorId( $historiaId );

		if ( null === $historia ) {
			return null;
		}

		$piezaIds = array_map(
			static fn ( Pieza $p ): int => $p->id,
			$this->piezas->obtenerPorHistoria( $historiaId )
		);

		return new Historia(
			$historia->id,
			$historia->titulo,
			$historia->estado,
			$historia->periodistaTitularId,
			$piezaIds,
			$historia->creadaEn,
			$historia->actualizadaEn
		);
	}

	/**
	 * Piezas de la Historia, en orden cronológico (U.1/U.2).
	 *
	 * @return list<Pieza>
	 */
	public function piezasDe( int $historiaId ): array {
		return $this->piezas->obtenerPorHistoria( $historiaId );
	}

	/**
	 * Nivel Cuatro U.1 — "Lo que sabemos / Lo que no sabemos": agrega los
	 * hechos de los expedientes de TODAS las Piezas de la Historia.
	 * `Verificado`/`Atribuido` tienen sustento suficiente ("sabemos");
	 * `Disputado` no lo tiene todavía ("no sabemos aún"). Piezas sin
	 * expediente (no investigadas todavía) no aportan nada al bloque —
	 * respuesta honesta, no un hueco fingido.
	 *
	 * @param list<Pieza> $piezasDeLaHistoria
	 */
	public function bloqueConocimiento( array $piezasDeLaHistoria ): BloqueConocimientoHistoria {
		$sabemos   = array();
		$noSabemos = array();

		foreach ( $piezasDeLaHistoria as $pieza ) {
			if ( null === $pieza->expediente ) {
				continue;
			}

			foreach ( $pieza->expediente->hechos as $hecho ) {
				if ( NivelVerificacion::Disputado === $hecho->nivel ) {
					$noSabemos[] = $hecho->extracto;
				} else {
					$sabemos[] = $hecho->extracto;
				}
			}
		}

		return new BloqueConocimientoHistoria(
			array_values( array_unique( $sabemos ) ),
			array_values( array_unique( $noSabemos ) )
		);
	}

	/**
	 * Cierre editorial explícito (Nivel Cuatro U.1: "esta historia
	 * concluyó") — terminal, nunca decidido por el sistema.
	 */
	public function cerrar( int $historiaId ): bool {
		return $this->historias->actualizarEstado( $historiaId, EstadoHistoria::Cerrada, $this->reloj->ahora() );
	}

	/**
	 * Barrido periódico (Orquestador, cada tick): Historias `Abierta`/
	 * `EnSeguimiento` sin Pieza nueva en `pluma_historia_dias_inactividad`
	 * días (default 14) pasan a `Inactiva` — un estado descriptivo, nunca
	 * un cierre. Devuelve cuántas se marcaron.
	 */
	public function marcarInactivasVencidas(): int {
		$ahora  = $this->reloj->ahora();
		$dias   = $this->diasInactividad();
		$limite = $ahora->sub( new DateInterval( "P{$dias}D" ) );

		$vencidas = $this->historias->obtenerAbiertasSinActividadDesde( $limite );

		foreach ( $vencidas as $historiaId ) {
			$this->historias->actualizarEstado( $historiaId, EstadoHistoria::Inactiva, $ahora );
		}

		return count( $vencidas );
	}

	private function diasInactividad(): int {
		$valor = get_option( self::OPCION_DIAS_INACTIVIDAD, self::DIAS_INACTIVIDAD_DEFECTO );

		return is_numeric( $valor ) ? max( 1, (int) $valor ) : self::DIAS_INACTIVIDAD_DEFECTO;
	}
}
