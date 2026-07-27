<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

/**
 * Nivel Tres J.3: divide un borrador en unidades atómicas verificables
 * (oraciones) — capa determinista, sin llamada al proveedor de lenguaje.
 * Reglas sintácticas simples con guarda de abreviaturas comunes y números
 * decimales, para no partir "Dr." o "4.5 millones" en dos unidades.
 */
final class SegmentadorUnidadesFactuales {

	private const MARCADOR = "\u{E000}";

	/**
	 * @var list<string>
	 */
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
	public function segmentar( string $texto ): array {
		$protegido = $texto;

		foreach ( self::ABREVIATURAS as $abreviatura ) {
			$protegido = str_replace( $abreviatura, str_replace( '.', self::MARCADOR, $abreviatura ), $protegido );
		}

		$protegido = (string) preg_replace( '/(\d)\.(\d)/', '$1' . self::MARCADOR . '$2', $protegido );

		$fragmentos = preg_split( '/(?<=[.!?])\s+(?=[A-ZÁÉÍÓÚÑ0-9])/u', $protegido );

		if ( false === $fragmentos ) {
			$fragmentos = array( $protegido );
		}

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
}
