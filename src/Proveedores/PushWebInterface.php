<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

use Pluma\Publicacion\Suscriptor;

/**
 * Contrato del envío de notificaciones push web (Nivel Cuatro W.3).
 */
interface PushWebInterface {

	public function enviar( Suscriptor $suscriptor, string $titulo, string $cuerpo, ?string $url ): ResultadoEnvioPush;
}
