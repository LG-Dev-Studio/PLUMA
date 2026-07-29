<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Kernel\Activador;
use Pluma\Kernel\RelojSistema;
use Pluma\Proveedores\ProveedorPushWeb;
use Pluma\Publicacion\CanalSuscripcion;
use Pluma\Publicacion\Suscriptor;
use Pluma\Publicacion\TipoSuscripcion;
use WP_UnitTestCase;

/**
 * Nivel Cuatro W.3 — envío push real de punta a punta (VAPID real +
 * cifrado del payload + `wp_remote_request()` real vía `ClienteHttpWp`)
 * contra un endpoint sintácticamente válido pero inexistente: verifica que
 * el fallo de red se maneja con normalidad (mejor esfuerzo, nunca lanza),
 * no que el envío llegue a un navegador real.
 *
 * @covers \Pluma\Proveedores\ProveedorPushWeb
 * @covers \Pluma\Proveedores\ClienteHttpWp
 */
final class ProveedorPushWebTest extends WP_UnitTestCase {

	public function test_enviar_a_un_endpoint_inexistente_falla_con_normalidad_sin_lanzar(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$reloj      = new RelojSistema();
		$suscriptor = new Suscriptor(
			1,
			CanalSuscripcion::Push,
			TipoSuscripcion::AlertaUrgente,
			null,
			null,
			null,
			'https://push.example.invalid/endpoint-que-no-existe',
			// Claves de ejemplo, no EC reales: `ProveedorPushWeb` atrapa
			// cualquier fallo (cifrado del payload o red) y devuelve
			// fracaso silencioso — exactamente el comportamiento "mejor
			// esfuerzo" que este test verifica.
			'BJ8I7pWmpBoWfjJHRAy9BZ4Y1L7fFdM8gK5rQvT3H1nO9pXY2cVbN6mZ4qA8sE0rW3tY5uI7oP9aS1dF3gH5j',
			'YWJjZGVmZ2hpams',
			'token-integracion',
			true,
			$reloj->ahora(),
			$reloj->ahora()
		);

		$resultado = ( new ProveedorPushWeb() )->enviar( $suscriptor, 'Título', 'Cuerpo', null );

		self::assertFalse( $resultado->exito );
	}
}
