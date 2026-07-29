<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

use Minishlink\WebPush\VAPID;
use Pluma\Kernel\Cifrado;

/**
 * Nivel Cuatro W.3 (push web): un par de claves VAPID identifica el sitio
 * ante los servicios de push del navegador (Chrome/Firefox/Edge) — se
 * generan UNA vez en activación y se reutilizan siempre; regenerarlas
 * invalidaría todas las suscripciones push existentes. La clave privada se
 * cifra en reposo con `Pluma\Kernel\Cifrado` (misma disciplina que la llave
 * de OpenRouter, GOVERNANCE §3.2) — la pública no es secreta por diseño
 * (viaja al navegador de cada suscriptor).
 */
final class ClavesVapid {

	public const OPCION_CLAVE_PUBLICA         = 'pluma_vapid_clave_publica';
	public const OPCION_CLAVE_PRIVADA_CIFRADA = 'pluma_vapid_clave_privada_cifrada';

	/**
	 * Idempotente: `add_option()` no sobrescribe un par ya generado.
	 */
	public static function generarSiNoExisten(): void {
		if ( false !== get_option( self::OPCION_CLAVE_PUBLICA, false ) ) {
			return;
		}

		$claves = VAPID::createVapidKeys();

		add_option( self::OPCION_CLAVE_PUBLICA, $claves['publicKey'], '', false );
		add_option( self::OPCION_CLAVE_PRIVADA_CIFRADA, Cifrado::cifrar( $claves['privateKey'] ), '', false );
	}

	public static function publica(): ?string {
		$valor = get_option( self::OPCION_CLAVE_PUBLICA, false );

		return is_string( $valor ) && '' !== $valor ? $valor : null;
	}

	public static function privada(): ?string {
		$sobre = get_option( self::OPCION_CLAVE_PRIVADA_CIFRADA, false );

		return is_string( $sobre ) && '' !== $sobre ? Cifrado::descifrar( $sobre ) : null;
	}
}
