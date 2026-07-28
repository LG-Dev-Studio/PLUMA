<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

/**
 * Imagen destacada elegida de entre las fuentes del expediente, priorizando
 * la de mayor autoridad (`NivelFuente`) — decisión explícita del propietario
 * (`ADR 0006`), distinta del diseño original del Libro (generación con IA o
 * banco de stock, diferido en `ADR 0005`).
 */
final readonly class ImagenFuenteSeleccionada {

	public function __construct(
		public string $urlImagen,
		public string $urlArticulo,
		public string $nombreFuente,
		public NivelFuente $nivelFuente,
	) {
	}
}
