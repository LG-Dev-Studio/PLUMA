<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

/**
 * Nivel Dos B.3: "nivel A no es infalibilidad, es solo la lista de
 * confianza editable del Capítulo 4.3" — listas de dominios/nombres de
 * fuente configurables por el cliente (`pluma_fuentes_nivel_a`,
 * `pluma_fuentes_nivel_b`); toda fuente no listada es Nivel C por defecto
 * (nunca sustento por sí sola, solo pista).
 */
final class ClasificadorNivelFuente {

	public const OPCION_FUENTES_NIVEL_A = 'pluma_fuentes_nivel_a';
	public const OPCION_FUENTES_NIVEL_B = 'pluma_fuentes_nivel_b';

	public function nivelDe( string $fuente ): NivelFuente {
		$fuenteNormalizada = mb_strtolower( trim( $fuente ) );

		if ( in_array( $fuenteNormalizada, $this->listaConfigurada( self::OPCION_FUENTES_NIVEL_A ), true ) ) {
			return NivelFuente::A;
		}

		if ( in_array( $fuenteNormalizada, $this->listaConfigurada( self::OPCION_FUENTES_NIVEL_B ), true ) ) {
			return NivelFuente::B;
		}

		return NivelFuente::C;
	}

	/**
	 * @return list<string>
	 */
	private function listaConfigurada( string $opcion ): array {
		$valor = get_option( $opcion, array() );

		if ( ! is_array( $valor ) ) {
			return array();
		}

		return array_values(
			array_map(
				static fn ( $f ): string => mb_strtolower( trim( (string) $f ) ),
				array_filter( $valor, static fn ( $f ): bool => is_string( $f ) || is_numeric( $f ) )
			)
		);
	}
}
