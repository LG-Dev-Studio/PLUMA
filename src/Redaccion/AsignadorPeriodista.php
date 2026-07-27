<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Kernel\AzarInterface;

/**
 * Paso 2 del Algoritmo de Decisión Editorial (Libro Cap. 5.5): puntúa a cada
 * periodista del banco contra la pieza y devuelve el de mayor puntuación.
 *
 * Lógica pura, sin `$wpdb` ni proveedor de lenguaje (CLAUDE.md § Ley de
 * Arquitectura): `DecisionEditorial` reúne los datos de carga e historial vía
 * los repositorios y se los pasa ya resueltos, para que este puntuador sea
 * determinista y trivial de testear.
 *
 * Nivel Dos C.3: piso de dominio — "gana el de mayor puntuación" no
 * distingue entre "gana porque es bueno" y "gana porque es el menos malo".
 * Ningún candidato bajo el umbral de dominio compite; si ninguno lo supera,
 * se lanza {@see NingunPeriodistaIdoneoException} en vez de asignar "al
 * menos malo".
 *
 * Nivel Dos C.2: cascada de desempate cuando el primero y el segundo
 * candidato están dentro de un margen configurable — balance de carga →
 * historial con la historia específica → `AzarInterface` con semilla,
 * nunca "el primero del array" (el bug de desempate más común).
 *
 * Deuda documentada (docs/deuda.md): la afinidad de línea editorial usa un
 * heurístico léxico simple (solapamiento de palabras), no comprensión
 * semántica real — suficiente para el criterio de salida de la Etapa 2, pero
 * candidato a mejora futura (embeddings o puntuación vía proveedor de lenguaje).
 */
final class AsignadorPeriodista {

	public const OPCION_UMBRAL_DOMINIO_MINIMO = 'pluma_umbral_dominio_minimo_periodista';
	public const OPCION_MARGEN_EMPATE         = 'pluma_margen_empate_asignacion';

	private const UMBRAL_DOMINIO_DEFECTO = 40.0;
	private const MARGEN_EMPATE_DEFECTO  = 5.0;

	private const PESO_DOMINIO               = 0.40;
	private const PESO_AFINIDAD              = 0.25;
	private const PESO_HISTORIAL             = 0.15;
	private const PESO_BALANCE_CARGA         = 0.20;
	private const PENALIZACION_POR_PIEZA_HOY = 25.0;

	public function __construct( private readonly AzarInterface $azar ) {
	}

	/**
	 * @param list<Periodista> $candidatos periodistas activos del banco
	 * @param array<int, int> $piezasAsignadasHoyPorPeriodista periodistaId => piezas ya asignadas hoy (balance de carga)
	 * @param array<int, bool> $tieneHistorialPorPeriodista periodistaId => ¿ya cubrió este tema antes? (historial de cobertura)
	 *
	 * @throws DecisionEditorialException si `$candidatos` está vacío.
	 * @throws NingunPeriodistaIdoneoException si ningún candidato supera el umbral de dominio mínimo.
	 */
	public function asignar(
		array $candidatos,
		ClasificacionNoticia $clasificacion,
		array $piezasAsignadasHoyPorPeriodista,
		array $tieneHistorialPorPeriodista,
		?int $periodistaIdDeHistoriaEspecifica = null
	): Periodista {
		if ( array() === $candidatos ) {
			throw new DecisionEditorialException( 'No hay periodistas activos en el banco para asignar esta pieza.' );
		}

		$umbralDominio = $this->umbralDominioMinimo();

		$elegibles = array_values(
			array_filter(
				$candidatos,
				static fn ( Periodista $p ): bool => ( $p->dominioDe( $clasificacion->tema ) / 5.0 ) * 100.0 >= $umbralDominio
			)
		);

		if ( array() === $elegibles ) {
			$mejorDominio = max( array_map( static fn ( Periodista $p ): int => $p->dominioDe( $clasificacion->tema ), $candidatos ) );

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new NingunPeriodistaIdoneoException( $clasificacion->tema, $umbralDominio, $mejorDominio );
		}

		$puntuados = array_map(
			fn ( Periodista $p ): array => array(
				$p,
				$this->puntuar(
					$p,
					$clasificacion,
					$piezasAsignadasHoyPorPeriodista[ $p->id ] ?? 0,
					$tieneHistorialPorPeriodista[ $p->id ] ?? false
				),
			),
			$elegibles
		);

		usort( $puntuados, static fn ( array $a, array $b ): int => $b[1] <=> $a[1] );

		if ( 1 === count( $puntuados ) ) {
			return $puntuados[0][0];
		}

		[$primero, $puntuacionPrimero] = $puntuados[0];
		[$segundo, $puntuacionSegundo] = $puntuados[1];

		if ( ( $puntuacionPrimero - $puntuacionSegundo ) >= $this->margenEmpate() ) {
			return $primero;
		}

		// Nivel Dos C.2, paso 1: casi-empate promueve el balance de carga.
		$piezasHoyPrimero = $piezasAsignadasHoyPorPeriodista[ $primero->id ] ?? 0;
		$piezasHoySegundo = $piezasAsignadasHoyPorPeriodista[ $segundo->id ] ?? 0;

		if ( $piezasHoyPrimero !== $piezasHoySegundo ) {
			return $piezasHoyPrimero < $piezasHoySegundo ? $primero : $segundo;
		}

		// Paso 2: historial con la historia específica (quien la empezó, la sigue).
		if ( null !== $periodistaIdDeHistoriaEspecifica ) {
			if ( $periodistaIdDeHistoriaEspecifica === $primero->id ) {
				return $primero;
			}

			if ( $periodistaIdDeHistoriaEspecifica === $segundo->id ) {
				return $segundo;
			}
		}

		// Paso 3, último recurso: azar con semilla inyectable — nunca "el primero del array".
		return 0 === $this->azar->entero( 0, 1 ) ? $primero : $segundo;
	}

	private function umbralDominioMinimo(): float {
		$valor = get_option( self::OPCION_UMBRAL_DOMINIO_MINIMO, self::UMBRAL_DOMINIO_DEFECTO );

		return is_numeric( $valor ) ? (float) $valor : self::UMBRAL_DOMINIO_DEFECTO;
	}

	private function margenEmpate(): float {
		$valor = get_option( self::OPCION_MARGEN_EMPATE, self::MARGEN_EMPATE_DEFECTO );

		return is_numeric( $valor ) ? (float) $valor : self::MARGEN_EMPATE_DEFECTO;
	}

	private function puntuar( Periodista $periodista, ClasificacionNoticia $clasificacion, int $piezasHoy, bool $tieneHistorial ): float {
		$dominio      = ( $periodista->dominioDe( $clasificacion->tema ) / 5.0 ) * 100.0;
		$afinidad     = $this->afinidadLineaEditorial( $periodista->conductaActual->reglas->lineaEditorial, $clasificacion );
		$historial    = $tieneHistorial ? 100.0 : 0.0;
		$balanceCarga = max( 0.0, 100.0 - $piezasHoy * self::PENALIZACION_POR_PIEZA_HOY );

		return self::PESO_DOMINIO * $dominio
			+ self::PESO_AFINIDAD * $afinidad
			+ self::PESO_HISTORIAL * $historial
			+ self::PESO_BALANCE_CARGA * $balanceCarga;
	}

	private function afinidadLineaEditorial( string $lineaEditorial, ClasificacionNoticia $clasificacion ): float {
		$textoNoticia = mb_strtolower( $clasificacion->tema . ' ' . $clasificacion->polaridad );

		$palabrasLinea = array_filter(
			explode( ' ', (string) preg_replace( '/[^\p{L}\s]+/u', ' ', mb_strtolower( $lineaEditorial ) ) ),
			static fn ( string $palabra ): bool => mb_strlen( $palabra ) >= 4
		);

		if ( array() === $palabrasLinea ) {
			return 0.0;
		}

		$coincidencias = 0;

		foreach ( $palabrasLinea as $palabra ) {
			if ( str_contains( $textoNoticia, $palabra ) ) {
				++$coincidencias;
			}
		}

		return ( $coincidencias / count( $palabrasLinea ) ) * 100.0;
	}
}
