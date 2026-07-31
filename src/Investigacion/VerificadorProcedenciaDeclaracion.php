<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

use Pluma\Idioma\PlegadorDiacriticos;

/**
 * Nivel Tres L.1: Protocolo de Verificación de Procedencia de la
 * Declaración — detecta si un hecho es una declaración textual atribuida a
 * una persona u organización identificable (comillas + verbo de
 * atribución), y si su fuente es un canal verificado u oficial
 * (configurable por el cliente, `pluma_canales_oficiales`). Heurística
 * determinista, sin proveedor de lenguaje: "¿hay comillas o un verbo de
 * atribución?" y "¿la fuente está en la lista de canales oficiales?" son
 * ambas preguntas de coincidencia, no de comprensión semántica.
 */
final class VerificadorProcedenciaDeclaracion {

	public const OPCION_CANALES_OFICIALES = 'pluma_canales_oficiales';

	/**
	 * @var list<string>
	 */
	private const VERBOS_ATRIBUCION = array(
		'afirmó',
		'afirma',
		'aseguró',
		'asegura',
		'declaró',
		'declara',
		'dijo',
		'sostuvo',
		'sostiene',
		'señaló',
		'señala',
		'manifestó',
		'manifiesta',
		'indicó',
		'indica',
		'aseveró',
		'según',
	);

	public function detectar( string $extracto, string $url ): EstadoProcedenciaDeclaracion {
		if ( ! $this->esDeclaracionAtribuida( $extracto ) ) {
			return EstadoProcedenciaDeclaracion::NoAplica;
		}

		return $this->esCanalOficial( $url )
			? EstadoProcedenciaDeclaracion::VerificadaCanalOficial
			: EstadoProcedenciaDeclaracion::NoVerificada;
	}

	private function esDeclaracionAtribuida( string $texto ): bool {
		if ( str_contains( $texto, '"' ) || str_contains( $texto, '«' ) || str_contains( $texto, '“' ) ) {
			return true;
		}

		$normalizado = mb_strtolower( $texto );

		foreach ( self::VERBOS_ATRIBUCION as $verbo ) {
			if ( str_contains( $normalizado, $verbo ) ) {
				return true;
			}
		}

		return false;
	}

	private function esCanalOficial( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! is_string( $host ) ) {
			return false;
		}

		$canales = get_option( self::OPCION_CANALES_OFICIALES, array() );

		if ( ! is_array( $canales ) ) {
			return false;
		}

		$canalesNormalizados = array_map(
			static fn ( $c ): string => PlegadorDiacriticos::plegar( mb_strtolower( trim( (string) $c ) ) ),
			array_filter( $canales, static fn ( $c ): bool => is_string( $c ) || is_numeric( $c ) )
		);

		return in_array( PlegadorDiacriticos::plegar( mb_strtolower( $host ) ), $canalesNormalizados, true );
	}
}
