<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * `suscripcionExpirada` distingue "el navegador ya no quiere estas
 * notificaciones" (el llamador debe borrar la fila de `pluma_suscriptores`)
 * de un fallo transitorio de red (no borrar, puede reintentarse después).
 */
final readonly class ResultadoEnvioPush {

	public function __construct(
		public bool $exito,
		public bool $suscripcionExpirada,
	) {
	}
}
