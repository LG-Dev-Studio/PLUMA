<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Proveedores;

use Brain\Monkey\Functions;
use Pluma\Proveedores\ProveedorPushWeb;
use Pluma\Publicacion\CanalSuscripcion;
use Pluma\Publicacion\Suscriptor;
use Pluma\Publicacion\TipoSuscripcion;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Nivel Cuatro W.3 — envío push web. Sin claves VAPID generadas, el envío
 * falla en silencio (mejor esfuerzo, nunca lanza) — el camino real de envío
 * con claves reales se verifica en integración (necesita `AUTH_KEY`/
 * `SECURE_AUTH_KEY` reales para descifrar la clave privada).
 *
 * @covers \Pluma\Proveedores\ProveedorPushWeb
 */
final class ProveedorPushWebTest extends CasoDePruebaUnitario {

	private function suscriptorPush(): Suscriptor {
		$reloj = new RelojFijo();

		return new Suscriptor(
			1,
			CanalSuscripcion::Push,
			TipoSuscripcion::AlertaUrgente,
			null,
			null,
			null,
			'https://push.example/endpoint',
			'clave-p256dh',
			'clave-auth',
			'token',
			true,
			$reloj->ahora(),
			$reloj->ahora()
		);
	}

	public function test_enviar_sin_claves_vapid_configuradas_devuelve_fracaso_sin_lanzar(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$resultado = ( new ProveedorPushWeb() )->enviar( $this->suscriptorPush(), 'Título', 'Cuerpo', null );

		self::assertFalse( $resultado->exito );
		self::assertFalse( $resultado->suscripcionExpirada );
	}

	public function test_enviar_sin_datos_de_suscripcion_push_devuelve_fracaso(): void {
		// Ninguna opción real de PLUMA empieza por el sobre 'pluma_v1:' de
		// Cifrado — Cifrado::descifrar() devuelve null sin intentar
		// descifrado real, así que este valor no requiere AUTH_KEY/
		// SECURE_AUTH_KEY definidas para este test unitario.
		Functions\when( 'get_option' )->justReturn( false );

		$reloj      = new RelojFijo();
		$suscriptor = new Suscriptor( 1, CanalSuscripcion::Push, TipoSuscripcion::AlertaUrgente, null, null, null, null, null, null, 'token', true, $reloj->ahora(), $reloj->ahora() );

		$resultado = ( new ProveedorPushWeb() )->enviar( $suscriptor, 'Título', 'Cuerpo', null );

		self::assertFalse( $resultado->exito );
	}
}
