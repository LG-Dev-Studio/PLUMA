<?php

declare(strict_types=1);

namespace Pluma\Compuertas;

use DateTimeImmutable;
use Pluma\Datos\RepositorioModoRespetoInterface;
use Pluma\Datos\RepositorioTendenciasInterface;

/**
 * Modo respeto (Nivel Dos F.1-F.3): "el momento en que un sitio sigue
 * publicando chistes mientras el país entero está de luto es el tipo de
 * incidente que se vuelve captura de pantalla viral y cierra un medio."
 *
 * Disparador de dos niveles (F.2): automático cuando 2+ tendencias de
 * gravedad máxima aparecen en una ventana corta compartiendo campo
 * temático o geográfico — la coincidencia es lo que distingue "un evento
 * excepcional generando varias tendencias derivadas" de "varias tragedias
 * normales y distintas del ciclo de noticias"; o manual, con un clic del
 * editor, sin esperar señal del sistema.
 *
 * Asimetría deliberada (F.2): activar de más cuesta un chiste perdido,
 * desactivar de más cuesta un incidente — por eso solo el editor desactiva,
 * nunca el propio sistema, y solo tras el piso de duración mínima (F.3).
 */
final class GestorModoRespeto {

	public const OPCION_UMBRAL_GRAVEDAD_MAXIMA   = 'pluma_modo_respeto_umbral_gravedad_maxima';
	public const OPCION_VENTANA_DISPARADOR_HORAS = 'pluma_modo_respeto_ventana_disparador_horas';
	public const OPCION_DURACION_MINIMA_HORAS    = 'pluma_modo_respeto_duracion_minima_horas';

	private const UMBRAL_GRAVEDAD_MAXIMA_DEFECTO   = 90.0;
	private const VENTANA_DISPARADOR_HORAS_DEFECTO = 3.0;
	private const DURACION_MINIMA_HORAS_DEFECTO    = 6.0;

	/**
	 * Piso de fábrica no editable a la baja (F.3, misma filosofía que los
	 * umbrales de compuertas de Cap. 8.3): ninguna opción de usuario puede
	 * bajar la duración mínima de este valor.
	 */
	private const DURACION_MINIMA_HORAS_PISO = 1.0;

	private const MINIMO_TENDENCIAS_DISPARADOR = 2;

	public function __construct(
		private readonly RepositorioModoRespetoInterface $repositorio,
		private readonly RepositorioTendenciasInterface $tendencias,
	) {
	}

	public function estadoActual(): EstadoModoRespeto {
		return $this->repositorio->estadoActual();
	}

	/**
	 * Evalúa el disparador automático (F.2, nivel automático) — no hace nada
	 * si el modo respeto ya está activo (evita reabrir la ventana de
	 * duración mínima con cada tick del Orquestador).
	 */
	public function evaluarDisparadorAutomatico( DateTimeImmutable $ahora ): void {
		if ( $this->repositorio->estadoActual()->activo ) {
			return;
		}

		$desde     = $ahora->modify( '-' . $this->ventanaDisparadorHoras() . ' hours' );
		$recientes = $this->tendencias->obtenerGravedadMaximaRecientes( (int) $this->umbralGravedadMaxima(), $desde );

		$campo = $this->campoConDosOMasCoincidencias( $recientes );

		if ( null === $campo ) {
			return;
		}

		$this->repositorio->activar(
			ActivadorModoRespeto::Automatico,
			"Disparador automático: 2 o más tendencias de gravedad máxima en {$this->ventanaDisparadorHoras()}h compartiendo '{$campo}'.",
			$this->duracionMinimaHoras(),
			$ahora
		);
	}

	/**
	 * Nivel manual (F.2): un clic del editor, sin esperar señal del sistema.
	 * Idempotente — reactivar un modo ya activo no reinicia su ventana de
	 * duración mínima (evitaría que un clic repetido extienda el piso
	 * indefinidamente).
	 */
	public function activarManualmente( string $motivo, DateTimeImmutable $ahora ): EstadoModoRespeto {
		if ( ! $this->repositorio->estadoActual()->activo ) {
			$this->repositorio->activar( ActivadorModoRespeto::Manual, $motivo, $this->duracionMinimaHoras(), $ahora );
		}

		return $this->repositorio->estadoActual();
	}

	/**
	 * @throws ModoRespetoAunNoDesactivableException si el piso de duración mínima no se cumplió todavía.
	 */
	public function desactivar( DateTimeImmutable $ahora ): void {
		$estado = $this->repositorio->estadoActual();

		if ( ! $estado->activo || null === $estado->puedeDesactivarseDesde ) {
			return;
		}

		if ( $ahora < $estado->puedeDesactivarseDesde ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje construido internamente por la propia excepción, sin entrada de usuario.
			throw new ModoRespetoAunNoDesactivableException( $estado->puedeDesactivarseDesde );
		}

		$this->repositorio->desactivar( $ahora );
	}

	/**
	 * @param list<array{id: int, campoTematico: string, campoGeografico: ?string}> $recientes
	 */
	private function campoConDosOMasCoincidencias( array $recientes ): ?string {
		$porTema = array();
		$porGeo  = array();

		foreach ( $recientes as $tendencia ) {
			$porTema[ $tendencia['campoTematico'] ][ $tendencia['id'] ] = true;

			if ( null !== $tendencia['campoGeografico'] ) {
				$porGeo[ $tendencia['campoGeografico'] ][ $tendencia['id'] ] = true;
			}
		}

		foreach ( $porTema as $campo => $ids ) {
			if ( count( $ids ) >= self::MINIMO_TENDENCIAS_DISPARADOR ) {
				return $campo;
			}
		}

		foreach ( $porGeo as $campo => $ids ) {
			if ( count( $ids ) >= self::MINIMO_TENDENCIAS_DISPARADOR ) {
				return $campo;
			}
		}

		return null;
	}

	private function umbralGravedadMaxima(): float {
		$valor = get_option( self::OPCION_UMBRAL_GRAVEDAD_MAXIMA, self::UMBRAL_GRAVEDAD_MAXIMA_DEFECTO );

		return is_numeric( $valor ) ? (float) $valor : self::UMBRAL_GRAVEDAD_MAXIMA_DEFECTO;
	}

	private function ventanaDisparadorHoras(): float {
		$valor = get_option( self::OPCION_VENTANA_DISPARADOR_HORAS, self::VENTANA_DISPARADOR_HORAS_DEFECTO );

		return is_numeric( $valor ) ? (float) $valor : self::VENTANA_DISPARADOR_HORAS_DEFECTO;
	}

	private function duracionMinimaHoras(): float {
		$valor       = get_option( self::OPCION_DURACION_MINIMA_HORAS, self::DURACION_MINIMA_HORAS_DEFECTO );
		$configurada = is_numeric( $valor ) ? (float) $valor : self::DURACION_MINIMA_HORAS_DEFECTO;

		return max( self::DURACION_MINIMA_HORAS_PISO, $configurada );
	}
}
