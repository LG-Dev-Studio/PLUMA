<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use Pluma\Investigacion\Expediente;

interface AsignadorImagenDestacadaInterface {

	/**
	 * Mejor esfuerzo, nunca bloqueante: si el modo está en `Ninguna`, o no
	 * se encuentra ninguna imagen elegible, no hace nada — la Pieza sigue
	 * su camino normal sin imagen destacada, igual que hoy.
	 */
	public function asignar( int $postId, Expediente $expediente ): void;
}
