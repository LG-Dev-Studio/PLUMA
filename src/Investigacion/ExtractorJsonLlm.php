<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

/**
 * Extrae un objeto JSON del contenido devuelto por el proveedor de lenguaje
 * (mismo tratamiento tolerante que `Pluma\Redaccion\ExtractorJsonLlm`,
 * deliberadamente no compartido entre capas — evita que `Investigacion`
 * dependa de utilidades de `Redaccion`).
 */
final class ExtractorJsonLlm {

	/**
	 * @return array<string, mixed>
	 * @throws InvestigacionException si no se encuentra un objeto JSON válido.
	 */
	public static function extraer( string $contenido ): array {
		$candidato = trim( $contenido );

		if ( str_starts_with( $candidato, '```' ) ) {
			$sinCercaInicial = preg_replace( '/^```[a-zA-Z]*\s*/', '', $candidato );
			$candidato       = preg_replace( '/```\s*$/', '', (string) $sinCercaInicial );
			$candidato       = trim( (string) $candidato );
		}

		$inicio = strpos( $candidato, '{' );
		$fin    = strrpos( $candidato, '}' );

		if ( false === $inicio || false === $fin || $fin < $inicio ) {
			throw new InvestigacionException( 'El proveedor de lenguaje no devolvió un objeto JSON reconocible.' );
		}

		$json  = substr( $candidato, $inicio, $fin - $inicio + 1 );
		$datos = json_decode( $json, true );

		if ( ! is_array( $datos ) ) {
			throw new InvestigacionException( 'El proveedor de lenguaje devolvió JSON con formato inesperado.' );
		}

		/** @var array<string, mixed> $datos */
		return $datos;
	}
}
