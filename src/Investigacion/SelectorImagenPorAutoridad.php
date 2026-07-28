<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

use Pluma\Proveedores\ExtractorImagenFuenteInterface;

/**
 * Imagen destacada por autoridad de fuente (decisión del propietario,
 * `ADR 0006`): ordena las fuentes del expediente por `NivelFuente`
 * (`pesoBase()` descendente, A antes que B antes que C) e intenta extraer
 * la imagen destacada de cada una, en ese orden, hasta encontrar una —
 * nunca prueba dos veces el mismo host.
 */
final class SelectorImagenPorAutoridad {

	public function __construct(
		private readonly ClasificadorNivelFuente $clasificador,
		private readonly ExtractorImagenFuenteInterface $extractor,
	) {
	}

	public function seleccionar( Expediente $expediente ): ?ImagenFuenteSeleccionada {
		$hechosOrdenados = $expediente->hechos;

		usort(
			$hechosOrdenados,
			fn ( HechoFuente $a, HechoFuente $b ): int => $this->nivelDe( $b )->pesoBase() <=> $this->nivelDe( $a )->pesoBase()
		);

		$hostsProbados = array();

		foreach ( $hechosOrdenados as $hecho ) {
			$host = $this->hostDe( $hecho->url );

			if ( null === $host || isset( $hostsProbados[ $host ] ) ) {
				continue;
			}

			$hostsProbados[ $host ] = true;

			$urlImagen = $this->extractor->extraerImagenDestacada( $hecho->url );

			if ( null !== $urlImagen ) {
				return new ImagenFuenteSeleccionada( $urlImagen, $hecho->url, $host, $this->nivelDe( $hecho ) );
			}
		}

		return null;
	}

	private function nivelDe( HechoFuente $hecho ): NivelFuente {
		$host = $this->hostDe( $hecho->url );

		return $this->clasificador->nivelDe( $host ?? $hecho->url );
	}

	private function hostDe( string $url ): ?string {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		return is_string( $host ) ? $host : null;
	}
}
