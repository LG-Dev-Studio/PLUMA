<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Nyholm\Psr7\Factory\Psr17Factory;
use Pluma\Publicacion\Suscriptor;
use Throwable;

/**
 * Nivel Cuatro W.3 — envío real de notificaciones push web (Push API +
 * VAPID, RFC 8291/8292) vía `minishlink/web-push`, el HTTP saliente
 * enrutado por `ClienteHttpWp` (único punto de contacto con la red de esta
 * capa, CLAUDE.md § Ley de Arquitectura). Sin claves VAPID generadas
 * (`Pluma\Proveedores\ClavesVapid`), no hay nada que enviar — devuelve
 * fracaso silencioso, nunca lanza, igual que el resto de proveedores
 * "mejor esfuerzo, no bloqueante" de esta capa.
 */
final class ProveedorPushWeb implements PushWebInterface {

	public function enviar( Suscriptor $suscriptor, string $titulo, string $cuerpo, ?string $url ): ResultadoEnvioPush {
		$clavePublica = ClavesVapid::publica();
		$clavePrivada = ClavesVapid::privada();

		if ( null === $clavePublica || null === $clavePrivada || null === $suscriptor->pushEndpoint || null === $suscriptor->pushClaveP256dh || null === $suscriptor->pushClaveAuth ) {
			return new ResultadoEnvioPush( false, false );
		}

		$factoria = new Psr17Factory();
		$webPush  = new WebPush(
			array(
				'VAPID' => array(
					'subject'    => home_url(),
					'publicKey'  => $clavePublica,
					'privateKey' => $clavePrivada,
				),
			),
			array(),
			new ClienteHttpWp(),
			$factoria,
			$factoria
		);

		$suscripcion = new Subscription(
			$suscriptor->pushEndpoint,
			$suscriptor->pushClaveP256dh,
			$suscriptor->pushClaveAuth,
			ContentEncoding::aes128gcm
		);

		$payload = wp_json_encode(
			array(
				'titulo' => $titulo,
				'cuerpo' => $cuerpo,
				'url'    => $url,
			)
		);

		try {
			$reporte = $webPush->sendOneNotification( $suscripcion, is_string( $payload ) ? $payload : '{}' );
		} catch ( Throwable ) {
			return new ResultadoEnvioPush( false, false );
		}

		return new ResultadoEnvioPush( $reporte->isSuccess(), $reporte->isSubscriptionExpired() );
	}
}
