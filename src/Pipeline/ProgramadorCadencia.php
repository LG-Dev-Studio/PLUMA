<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

use DateTimeImmutable;
use Pluma\Kernel\AzarInterface;

/**
 * Anatomía de una ejecución, pasos 3-4 (Libro Cap. 9.3): dada la cuota, las
 * ventanas y lo ya programado hoy, decide la próxima ranura para una pieza
 * APROBADA — o `null` si hoy ya no hay espacio (la pieza espera al próximo
 * tick, incluso si eso es mañana; "mejor esperar que publicar de más").
 *
 * Simplificación documentada: el peso de cada ventana solo desempata cuando
 * dos ventanas ofrecen la MISMA hora efectiva (rarísimo en la práctica); el
 * criterio principal es la ranura cronológicamente más próxima que respete
 * separación mínima y ventana horaria. Un reparto proporcional estricto por
 * peso a lo largo del día completo es un refinamiento futuro, no un
 * requisito del producto documentado en el Libro.
 */
final class ProgramadorCadencia {

	public function __construct( private readonly AzarInterface $azar ) {
	}

	/**
	 * @param list<RanuraPublicacion> $yaProgramadasHoy piezas ya en `pluma_cola_publicacion` (programadas o publicadas) dentro del día de `$ahora`
	 */
	public function siguienteRanura(
		ConfiguracionCadencia $config,
		array $yaProgramadasHoy,
		string $vertical,
		?int $periodistaId,
		DateTimeImmutable $ahora
	): ?DateTimeImmutable {
		if ( count( $yaProgramadasHoy ) >= $config->cuotaMaxima ) {
			return null;
		}

		if ( $this->topeAlcanzado( $config->topesPorVertical[ $vertical ] ?? null, $this->contarPorVertical( $yaProgramadasHoy, $vertical ) ) ) {
			return null;
		}

		if ( null !== $periodistaId && $this->topeAlcanzado( $config->topesPorPeriodista[ $periodistaId ] ?? null, $this->contarPorPeriodista( $yaProgramadasHoy, $periodistaId ) ) ) {
			return null;
		}

		$minimoSiguiente = $this->proximoInstanteDisponible( $config, $yaProgramadasHoy, $ahora );
		$candidatos      = array();

		foreach ( $config->ventanas as $ventana ) {
			$inicioVentanaHoy = $ahora->setTime( $ventana->horaInicio, 0 );
			$finVentanaHoy    = $ahora->setTime( $ventana->horaFin, 0 );
			$inicioEfectivo   = $minimoSiguiente > $inicioVentanaHoy ? $minimoSiguiente : $inicioVentanaHoy;

			if ( $inicioEfectivo < $finVentanaHoy ) {
				$candidatos[] = $inicioEfectivo;
			}
		}

		if ( array() === $candidatos ) {
			return null;
		}

		usort( $candidatos, static fn ( DateTimeImmutable $a, DateTimeImmutable $b ): int => $a <=> $b );
		$elegido = $candidatos[0];

		$jitter = $config->jitterMaximoMinutos > 0 ? $this->azar->entero( 0, $config->jitterMaximoMinutos ) : 0;

		return $elegido->modify( "+{$jitter} minutes" );
	}

	/**
	 * Modo respeto (Nivel Dos F.3): "la cola se reactiva completa... con el
	 * jitter de horario recalculado desde cero". Conserva la franja horaria
	 * ya asignada (evita rederivar cuota/ventanas/separación para todo el
	 * lote reactivado a la vez) y solo redibuja el jitter dentro de esa
	 * hora — publicar todo exactamente en el minuto pausado delataría
	 * automatización tan claramente como publicar en punto.
	 */
	public function rejitter( ConfiguracionCadencia $config, DateTimeImmutable $horaProgramada ): DateTimeImmutable {
		$horaBase = $horaProgramada->setTime( (int) $horaProgramada->format( 'H' ), 0 );
		$jitter   = $config->jitterMaximoMinutos > 0 ? $this->azar->entero( 0, $config->jitterMaximoMinutos ) : 0;

		return $horaBase->modify( "+{$jitter} minutes" );
	}

	private function topeAlcanzado( ?int $tope, int $usadas ): bool {
		return null !== $tope && $usadas >= $tope;
	}

	/**
	 * @param list<RanuraPublicacion> $ranuras
	 */
	private function contarPorVertical( array $ranuras, string $vertical ): int {
		return count( array_filter( $ranuras, static fn ( RanuraPublicacion $r ): bool => $r->vertical === $vertical ) );
	}

	/**
	 * @param list<RanuraPublicacion> $ranuras
	 */
	private function contarPorPeriodista( array $ranuras, int $periodistaId ): int {
		return count( array_filter( $ranuras, static fn ( RanuraPublicacion $r ): bool => $r->periodistaId === $periodistaId ) );
	}

	/**
	 * Separación mínima entre piezas (Cap. 9.2): "nada delata más a un sitio
	 * automatizado que publicar cada día exactamente a en punto".
	 *
	 * @param list<RanuraPublicacion> $yaProgramadasHoy
	 */
	private function proximoInstanteDisponible( ConfiguracionCadencia $config, array $yaProgramadasHoy, DateTimeImmutable $ahora ): DateTimeImmutable {
		$ultimaHora = null;

		foreach ( $yaProgramadasHoy as $ranura ) {
			if ( null === $ultimaHora || $ranura->horaProgramada > $ultimaHora ) {
				$ultimaHora = $ranura->horaProgramada;
			}
		}

		if ( null === $ultimaHora ) {
			return $ahora;
		}

		$conSeparacion = $ultimaHora->modify( "+{$config->separacionMinimaMinutos} minutes" );

		return $conSeparacion > $ahora ? $conSeparacion : $ahora;
	}
}
