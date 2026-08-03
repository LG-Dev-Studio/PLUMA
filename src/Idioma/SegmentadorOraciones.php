<?php

declare(strict_types=1);

namespace Pluma\Idioma;

use IntlBreakIterator;

/**
 * Rol SEG (`docs/CEREBRO_PLUMA_v2.md` Parte 2.3, `ADR 0014` §3): segmentación
 * de oraciones, Plano 0 puro. Usa ICU (`IntlBreakIterator`, reglas Unicode
 * TR29) cuando `ext-intl` está disponible; si no, cae a un split determinista
 * en PHP puro — `ext-intl` es "opcional-con-fallback" en hosting de
 * WordPress, nunca dependencia dura (`ADR 0014` §3).
 *
 * Verificación real (`ADR 0018`): ICU por sí solo NO protege abreviaturas
 * editoriales comunes ("Dr." se detecta como fin de oración) — por eso ambas
 * rutas pasan primero por la misma protección de abreviaturas/decimales que
 * ya usa `Pluma\Redaccion\SegmentadorUnidadesFactuales::MARCADOR` (constante
 * propia aquí, deliberadamente NO compartida: acoplar `Idioma` a `Redaccion`
 * por una lista de 15 strings no está decidido todavía).
 *
 * Cubre solo escrituras con espacios (el único caso real hoy —
 * `ResolutorPerfilIdioma` solo tiene cobertura de `es-ES`). La segmentación
 * de PALABRAS para escrituras sin espacios (chino/japonés/tailandés/lao/
 * jemer/birmano) que ICU también resuelve vía diccionario queda fuera de
 * esta clase a propósito: no hay ningún locale con `NivelCobertura` real que
 * la necesite hoy — construirla sería el mismo "campo que nadie lee" que
 * `PerfilIdioma` ya evitó una vez.
 */
final class SegmentadorOraciones {

	private const MARCADOR = "\u{E000}";

	/** @var list<string> */
	private const ABREVIATURAS = array(
		'Sr.',
		'Sra.',
		'Sres.',
		'Dr.',
		'Dra.',
		'Ud.',
		'Uds.',
		'etc.',
		'p.ej.',
		'EE.UU.',
		'Ing.',
		'Lic.',
		'Av.',
		'núm.',
		'art.',
		'pág.',
	);

	/**
	 * @return list<string>
	 */
	public function segmentar( string $texto, string $locale = 'es-ES' ): array {
		$protegido = $texto;

		foreach ( self::ABREVIATURAS as $abreviatura ) {
			$protegido = str_replace( $abreviatura, str_replace( '.', self::MARCADOR, $abreviatura ), $protegido );
		}

		$protegido = (string) preg_replace( '/(\d)\.(\d)/', '$1' . self::MARCADOR . '$2', $protegido );

		$fragmentos = extension_loaded( 'intl' )
			? $this->segmentarConIcu( $protegido, $locale )
			: $this->segmentarConFallback( $protegido );

		return array_values(
			array_filter(
				array_map(
					static fn ( string $f ): string => trim( str_replace( self::MARCADOR, '.', $f ) ),
					$fragmentos
				),
				static fn ( string $f ): bool => '' !== $f
			)
		);
	}

	/**
	 * @return list<string>
	 */
	private function segmentarConIcu( string $texto, string $locale ): array {
		$bi = IntlBreakIterator::createSentenceInstance( $locale );
		$bi->setText( $texto );

		$fragmentos = array();

		foreach ( $bi->getPartsIterator() as $parte ) {
			$fragmentos[] = $parte;
		}

		return $fragmentos;
	}

	/**
	 * @return list<string>
	 */
	private function segmentarConFallback( string $texto ): array {
		$fragmentos = preg_split( '/(?<=[.!?])\s+(?=[A-ZÁÉÍÓÚÑ0-9¿¡])/u', $texto );

		return false !== $fragmentos ? $fragmentos : array( $texto );
	}
}
