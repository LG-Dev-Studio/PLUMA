<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use DateTimeImmutable;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Idioma\PlegadorDiacriticos;
use Pluma\Kernel\RelojInterface;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\Pieza;

/**
 * Trabajo posterior a la Etapa 9: "el plugin debe crear periodistas según
 * grupos de noticias que lo necesiten, sin saturar el número de
 * periodistas" — decisión del propietario. Evalúa, en cada tick del
 * Orquestador, si el patrón real de Piezas atascadas en
 * `SIN_PERIODISTA_IDONEO` justifica proponer un periodista nuevo.
 *
 * Cinco guardas, en orden, cada una gratis en cómputo y revisada ANTES de
 * la siguiente — un fallo o un criterio laxo del proveedor de lenguaje
 * jamás debe ser la única defensa contra saturar el banco:
 *   1. Interruptor apagado por defecto (opt-in explícito del propietario).
 *   2. Cooldown desde el último intento (con o sin propuesta resultante).
 *   3. Tope de periodistas automáticos "en juego" (Propuesto o Activo).
 *   4/5. Volumen mínimo de Piezas elegibles ANTES de llamar a la IA — el
 *      control real contra "un periodista por cada noticia", no una
 *      esperanza sobre el criterio del modelo.
 *
 * El periodista propuesto nace siempre {@see EstadoPeriodista::Propuesto}:
 * la promoción a Activo (con ventana de veto) y la reanudación de las
 * Piezas contribuyentes viven en `Pluma\Pipeline\Orquestador`, no aquí —
 * esta clase solo decide y propone.
 */
final class CreadorAutomaticoPeriodistas {

	public const OPCION_ACTIVADA         = 'pluma_creacion_automatica_periodistas_activada';
	public const OPCION_MIN_PIEZAS_GRUPO = 'pluma_creacion_automatica_min_piezas_cluster';
	public const OPCION_VENTANA_DIAS     = 'pluma_creacion_automatica_ventana_dias';
	public const OPCION_COOLDOWN_HORAS   = 'pluma_creacion_automatica_cooldown_horas';
	public const OPCION_MAX_PERIODISTAS  = 'pluma_creacion_automatica_max_periodistas';
	private const OPCION_ULTIMO_INTENTO  = 'pluma_creacion_automatica_ultimo_intento';

	private const MIN_PIEZAS_GRUPO_DEFECTO  = 3;
	private const VENTANA_DIAS_DEFECTO      = 14;
	private const COOLDOWN_HORAS_DEFECTO    = 24;
	private const MAX_PERIODISTAS_DEFECTO   = 5;
	private const LIMITE_PIEZAS_LEIDAS      = 50;
	private const MAX_MUESTRAS_AL_AGRUPADOR = 20;

	public function __construct(
		private readonly RepositorioPiezasInterface $piezas,
		private readonly RepositorioPeriodistasInterface $periodistas,
		private readonly AgrupadorTemasSinCobertura $agrupador,
		private readonly RelojInterface $reloj,
	) {
	}

	/**
	 * Valores efectivos de configuración (opción real o, si no está fijada,
	 * el defecto de fábrica) — misma convención que
	 * `PresupuestoLenguaje::limiteDiarioUsd()`, para que `RestSalaMaquinas`
	 * exponga siempre el valor REAL que usará la próxima evaluación, nunca
	 * un defecto duplicado a mano en la capa REST.
	 */
	public function activada(): bool {
		return (bool) get_option( self::OPCION_ACTIVADA, false );
	}

	public function minPiezasGrupo(): int {
		return $this->enteroConfigurado( self::OPCION_MIN_PIEZAS_GRUPO, self::MIN_PIEZAS_GRUPO_DEFECTO );
	}

	public function ventanaDias(): int {
		return $this->enteroConfigurado( self::OPCION_VENTANA_DIAS, self::VENTANA_DIAS_DEFECTO );
	}

	public function cooldownHoras(): int {
		return $this->enteroConfigurado( self::OPCION_COOLDOWN_HORAS, self::COOLDOWN_HORAS_DEFECTO );
	}

	public function maxPeriodistas(): int {
		return $this->enteroConfigurado( self::OPCION_MAX_PERIODISTAS, self::MAX_PERIODISTAS_DEFECTO );
	}

	/**
	 * @return list<string> errores no bloqueantes, mismo contrato que los
	 *                       demás pasos opcionales del tick del Orquestador.
	 */
	public function evaluarYProponer(): array {
		if ( ! $this->activada() ) {
			return array();
		}

		$ahora = $this->reloj->ahora();

		if ( ! $this->cooldownCumplido( $ahora ) ) {
			return array();
		}

		if ( $this->periodistas->contarAutomaticosActivos() >= $this->maxPeriodistas() ) {
			return array();
		}

		$piezasElegibles = $this->piezasElegibles( $ahora );

		if ( count( $piezasElegibles ) < $this->minPiezasGrupo() ) {
			return array();
		}

		// A partir de aquí es una "evaluación real": cuenta para el cooldown
		// exista o no una propuesta al final, para acotar el coste de IA a
		// como mucho una llamada por ventana de cooldown, nunca por tick.
		update_option( self::OPCION_ULTIMO_INTENTO, $ahora->getTimestamp(), false );

		$propuesta = $this->agrupador->evaluar( $this->muestrasDeduplicadas( $piezasElegibles ) );

		if ( null === $propuesta ) {
			return array();
		}

		$conductaBase = PlantillasSiembra::analistaDeDatosSobrio();

		$periodistaId = $this->periodistas->crear(
			$propuesta->nombre,
			null,
			$propuesta->biografia,
			$propuesta->rol,
			array( new Especialidad( $propuesta->vertical, $propuesta->nivelDominio ) ),
			EstadoPeriodista::Propuesto,
			$conductaBase->diales,
			$conductaBase->reglas,
			$conductaBase->matrizTonos,
			$ahora,
			creadoAutomaticamente: true
		);

		do_action( 'pluma/periodista_propuesto_automaticamente', $periodistaId, $propuesta->vertical );

		return array();
	}

	private function cooldownCumplido( DateTimeImmutable $ahora ): bool {
		$ultimoIntento = get_option( self::OPCION_ULTIMO_INTENTO, 0 );

		if ( ! is_numeric( $ultimoIntento ) || 0 === (int) $ultimoIntento ) {
			return true;
		}

		return $ahora->getTimestamp() >= ( (int) $ultimoIntento + $this->cooldownHoras() * HOUR_IN_SECONDS );
	}

	/**
	 * @return list<Pieza>
	 */
	private function piezasElegibles( DateTimeImmutable $ahora ): array {
		$desde = $ahora->modify( "-{$this->ventanaDias()} days" );

		$todas = $this->piezas->obtenerPorEstadoEntre( EstadoPieza::SinPeriodistaIdoneo, $desde, $ahora, self::LIMITE_PIEZAS_LEIDAS );

		return array_values( array_filter( $todas, static fn ( Pieza $p ): bool => null !== $p->temaSinCubrir && '' !== trim( $p->temaSinCubrir ) ) );
	}

	/**
	 * Deduplica por tema normalizado (mismo criterio que
	 * `Periodista::dominioDe()`) — enviar 12 muestras casi idénticas al
	 * proveedor de lenguaje desperdiciaría tokens sin aportar señal nueva;
	 * un extracto real por tema distinto ya representa el grupo.
	 *
	 * @param list<Pieza> $piezas
	 *
	 * @return list<array{tema: string, extracto: string}>
	 */
	private function muestrasDeduplicadas( array $piezas ): array {
		$porTema = array();

		foreach ( $piezas as $pieza ) {
			$temaNormalizado = PlegadorDiacriticos::plegar( mb_strtolower( trim( (string) $pieza->temaSinCubrir ) ) );

			if ( isset( $porTema[ $temaNormalizado ] ) ) {
				continue;
			}

			$porTema[ $temaNormalizado ] = array(
				'tema'     => $temaNormalizado,
				'extracto' => $pieza->expediente->hechos[0]->extracto ?? '',
			);
		}

		return array_slice( array_values( $porTema ), 0, self::MAX_MUESTRAS_AL_AGRUPADOR );
	}

	private function enteroConfigurado( string $opcion, int $defecto ): int {
		$valor = get_option( $opcion, $defecto );

		return is_numeric( $valor ) && (int) $valor > 0 ? (int) $valor : $defecto;
	}
}
