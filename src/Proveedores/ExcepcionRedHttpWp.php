<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * Excepción de red PSR-18 para {@see ClienteHttpWp} — envuelve un `WP_Error`
 * de `wp_remote_request()` en el contrato que las librerías PSR-18 esperan.
 */
final class ExcepcionRedHttpWp extends RuntimeException implements NetworkExceptionInterface {

	public function __construct( private readonly RequestInterface $request, string $mensaje ) {
		parent::__construct( $mensaje );
	}

	public function getRequest(): RequestInterface {
		return $this->request;
	}
}
