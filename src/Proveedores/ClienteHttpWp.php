<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Adaptador PSR-18 sobre `wp_remote_request()` — CLAUDE.md § Ley de
 * Arquitectura: "`wp_remote_*`/HTTP SOLO en `Pluma\Proveedores`". Las
 * librerías de terceros que exigen un cliente PSR-18 (aquí,
 * `minishlink/web-push` para notificaciones push, Nivel Cuatro W.3) no
 * traen consigo un segundo canal de HTTP saliente: siguen pasando por el
 * único punto de contacto con la red que esta capa ya es.
 */
final class ClienteHttpWp implements ClientInterface {

	public function sendRequest( RequestInterface $request ): ResponseInterface {
		$cabeceras = array();

		foreach ( $request->getHeaders() as $nombre => $valores ) {
			$cabeceras[ $nombre ] = implode( ', ', $valores );
		}

		$respuesta = wp_remote_request(
			(string) $request->getUri(),
			array(
				'method'      => $request->getMethod(),
				'headers'     => $cabeceras,
				'body'        => (string) $request->getBody(),
				'timeout'     => 15,
				'redirection' => 3,
			)
		);

		if ( is_wp_error( $respuesta ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno (PSR-18), nunca se imprime como HTML.
			throw new ExcepcionRedHttpWp( $request, $respuesta->get_error_message() );
		}

		$codigo             = (int) wp_remote_retrieve_response_code( $respuesta );
		$cuerpo             = wp_remote_retrieve_body( $respuesta );
		$cabecerasRespuesta = (array) wp_remote_retrieve_headers( $respuesta );

		return new Response( $codigo, $cabecerasRespuesta, $cuerpo );
	}
}
