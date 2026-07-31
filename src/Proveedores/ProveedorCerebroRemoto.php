<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

use Pluma\Kernel\Cifrado;

/**
 * Credenciales y prueba de vida del cerebro remoto propio (T3,
 * `docs/CEREBRO_PLUMA_v2.md` Parte 3.1: "el servicio del cliente en su VPS
 * o el servicio gestionado del vendedor, HTTP autenticado").
 *
 * El contrato de red real de T3 no existe todavía (NCP-2, sin construir) —
 * el endpoint de prueba usado aquí es deliberadamente provisional (mismo
 * espíritu de "instrumento antes que lo instrumentado" que NCP-1 porción 1)
 * y se documentará/sustituirá cuando NCP-2 defina el protocolo real.
 *
 * Mismo patrón de ciclo de vida que `ProveedorOpenRouter` (llave cifrada,
 * `probar()` sin coste, nunca se expone el secreto en texto plano).
 */
final class ProveedorCerebroRemoto {

	public const OPCION_URL              = 'pluma_cerebro_remoto_url';
	public const OPCION_TOKEN_CIFRADO    = 'pluma_cerebro_remoto_token_cifrado';
	public const OPCION_ULTIMA_PRUEBA_OK = 'pluma_cerebro_remoto_ultima_prueba_ok';

	private const TIMEOUT_PRUEBA_SEGUNDOS = 10;

	public function configurado(): bool {
		$url    = get_option( self::OPCION_URL, false );
		$sobre  = get_option( self::OPCION_TOKEN_CIFRADO, false );
		$tienen = is_string( $url ) && '' !== $url && is_string( $sobre ) && '' !== $sobre;

		return $tienen && null !== Cifrado::descifrar( $sobre );
	}

	/**
	 * Prueba real de alcanzabilidad — nunca se llama desde una ruta
	 * automática/no bloqueante (housekeeping del Orquestador): solo desde
	 * una acción humana explícita en el panel. Valida la URL contra
	 * `ValidadorUrl::esSegura()` ANTES de cualquier llamada de red.
	 */
	public function probar( string $url, string $token ): bool {
		if ( ! ValidadorUrl::esSegura( $url ) ) {
			return false;
		}

		$respuesta = wp_remote_get(
			$url,
			array(
				'timeout' => self::TIMEOUT_PRUEBA_SEGUNDOS,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);

		if ( is_wp_error( $respuesta ) ) {
			return false;
		}

		return 200 === wp_remote_retrieve_response_code( $respuesta );
	}

	/**
	 * Lee el resultado CACHEADO de la última prueba real — nunca hace red.
	 * `cerebroRemotoConfigurado` en `HechosEntorno` significa "fue
	 * alcanzable la última vez que se guardó/probó", no "alcanzable ahora
	 * mismo": esto es lo que permite que `SensorCapacidades` se llame desde
	 * el housekeeping no bloqueante de cada tick del Orquestador sin nunca
	 * disparar una llamada de red real.
	 */
	public function ultimaPruebaOk(): bool {
		return true === get_option( self::OPCION_ULTIMA_PRUEBA_OK, false );
	}
}
