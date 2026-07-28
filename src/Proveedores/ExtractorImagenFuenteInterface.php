<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Imagen destacada por autoridad de fuente (decisión del propietario,
 * `ADR 0006`): contrato de extracción de la imagen destacada de un
 * artículo fuente, mismo molde que `LenguajeInterface`/`EmbeddingsInterface`.
 */
interface ExtractorImagenFuenteInterface {

	/**
	 * Mejor esfuerzo: `null` ante cualquier fallo (red, HTTP, HTML sin
	 * etiqueta, URL de imagen insegura) — nunca lanza.
	 */
	public function extraerImagenDestacada( string $urlArticulo ): ?string;
}
