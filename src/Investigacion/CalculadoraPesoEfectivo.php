<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

/**
 * Nivel Dos B.3: `peso_efectivo = nivel_fuente_base × decaimiento_temporal ×
 * factor_independencia`.
 *
 * **Deuda conocida (bloqueador nuevo, ver `docs/deuda.md`)**: el
 * `decaimiento_temporal` depende de la clasificación de vida útil de la
 * tendencia (relámpago/ola/marea) que hoy no existe en ningún lugar del
 * código (`PLUMA-E1-1` — el propio Sensor todavía no la calcula). Esta
 * calculadora usa `1.0` (sin decaimiento) hasta que esa clasificación
 * exista aguas arriba; el resto de la fórmula (nivel de fuente + factor de
 * independencia) es real hoy.
 */
final class CalculadoraPesoEfectivo {

	private const TAMANO_NGRAMA                        = 8;
	private const FACTOR_INDEPENDENCIA_BAJA            = 0.5;
	private const FACTOR_INDEPENDENCIA_ALTA            = 1.0;
	private const DECAIMIENTO_TEMPORAL_SIN_IMPLEMENTAR = 1.0;

	public function __construct( private readonly ClasificadorNivelFuente $clasificadorNivelFuente ) {
	}

	/**
	 * @param list<HechoFuente> $todosLosHechosDelExpediente
	 */
	public function calcular( HechoFuente $hecho, array $todosLosHechosDelExpediente ): float {
		$nivel = $this->clasificadorNivelFuente->nivelDe( $this->hostDe( $hecho->url ) );

		return $nivel->pesoBase()
			* self::DECAIMIENTO_TEMPORAL_SIN_IMPLEMENTAR
			* $this->factorIndependencia( $hecho, $todosLosHechosDelExpediente );
	}

	private function hostDe( string $url ): string {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		return is_string( $host ) ? $host : $url;
	}

	/**
	 * Nivel Dos B.3, `factor_independencia`: dos fuentes que en realidad
	 * citan a la misma agencia original no cuentan como fuentes
	 * independientes — heurística de solapamiento n-grama entre extractos
	 * (mismo principio que `VerificadorNGramas`, pero deliberadamente no
	 * compartido entre capas: aquí compara fuentes entre sí, no un borrador
	 * contra el expediente).
	 *
	 * @param list<HechoFuente> $todosLosHechosDelExpediente
	 */
	private function factorIndependencia( HechoFuente $hecho, array $todosLosHechosDelExpediente ): float {
		$ngramasHecho = $this->ngramas( $hecho->extracto );

		if ( array() === $ngramasHecho ) {
			return self::FACTOR_INDEPENDENCIA_ALTA;
		}

		foreach ( $todosLosHechosDelExpediente as $otro ) {
			if ( $otro->url === $hecho->url ) {
				continue;
			}

			if ( array() !== array_intersect( $ngramasHecho, $this->ngramas( $otro->extracto ) ) ) {
				return self::FACTOR_INDEPENDENCIA_BAJA;
			}
		}

		return self::FACTOR_INDEPENDENCIA_ALTA;
	}

	/**
	 * @return list<string>
	 */
	private function ngramas( string $texto ): array {
		$limpio         = (string) preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', mb_strtolower( $texto ) );
		$palabrasCrudas = preg_split( '/\s+/u', trim( $limpio ) );
		$palabras       = array_values( array_filter( false !== $palabrasCrudas ? $palabrasCrudas : array(), static fn ( string $p ): bool => '' !== $p ) );

		$total = count( $palabras );

		if ( $total < self::TAMANO_NGRAMA ) {
			return array();
		}

		$ngramas = array();

		for ( $i = 0; $i <= $total - self::TAMANO_NGRAMA; $i++ ) {
			$ngramas[] = implode( ' ', array_slice( $palabras, $i, self::TAMANO_NGRAMA ) );
		}

		return $ngramas;
	}
}
