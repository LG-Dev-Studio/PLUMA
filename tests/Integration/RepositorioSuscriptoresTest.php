<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\RepositorioSuscriptores;
use Pluma\Kernel\RelojSistema;
use Pluma\Publicacion\CanalSuscripcion;
use Pluma\Publicacion\TipoSuscripcion;
use WP_UnitTestCase;

/**
 * Repositorio `pluma_suscriptores` contra tablas reales — Nivel Cuatro W.3
 * (Etapa 9, Porción 4).
 *
 * @covers \Pluma\Datos\RepositorioSuscriptores
 */
final class RepositorioSuscriptoresTest extends WP_UnitTestCase {

	public function test_crear_email_persiste_sin_confirmar_y_obtener_por_token_lo_recupera(): void {
		global $wpdb;
		$repo  = new RepositorioSuscriptores( $wpdb );
		$reloj = new RelojSistema();

		$token = 'token-uno-' . uniqid();
		$id    = $repo->crearEmail( TipoSuscripcion::Periodista, 7, null, 'lector@example.test', $token, $reloj->ahora() );

		self::assertGreaterThan( 0, $id );

		$suscriptor = $repo->obtenerPorToken( $token );

		self::assertNotNull( $suscriptor );
		self::assertSame( CanalSuscripcion::Email, $suscriptor->canal );
		self::assertSame( TipoSuscripcion::Periodista, $suscriptor->tipo );
		self::assertSame( 7, $suscriptor->referenciaId );
		self::assertSame( 'lector@example.test', $suscriptor->email );
		self::assertFalse( $suscriptor->confirmado );
	}

	public function test_crear_push_persiste_las_claves(): void {
		global $wpdb;
		$repo  = new RepositorioSuscriptores( $wpdb );
		$reloj = new RelojSistema();

		$token = 'token-push-' . uniqid();
		$repo->crearPush( TipoSuscripcion::AlertaUrgente, null, null, 'https://push.example/e', 'clave-p256dh', 'clave-auth', $token, $reloj->ahora() );

		$suscriptor = $repo->obtenerPorToken( $token );

		self::assertNotNull( $suscriptor );
		self::assertSame( CanalSuscripcion::Push, $suscriptor->canal );
		self::assertSame( 'https://push.example/e', $suscriptor->pushEndpoint );
		self::assertSame( 'clave-p256dh', $suscriptor->pushClaveP256dh );
		self::assertSame( 'clave-auth', $suscriptor->pushClaveAuth );
	}

	public function test_confirmar_marca_confirmado_y_fecha(): void {
		global $wpdb;
		$repo  = new RepositorioSuscriptores( $wpdb );
		$reloj = new RelojSistema();

		$token = 'token-confirmar-' . uniqid();
		$id    = $repo->crearEmail( TipoSuscripcion::Vertical, null, 'tecnologia', 'lector2@example.test', $token, $reloj->ahora() );

		self::assertTrue( $repo->confirmar( $id, $reloj->ahora() ) );

		$suscriptor = $repo->obtenerPorToken( $token );
		self::assertNotNull( $suscriptor );
		self::assertTrue( $suscriptor->confirmado );
		self::assertNotNull( $suscriptor->confirmadoEn );
	}

	public function test_eliminar_borra_la_fila(): void {
		global $wpdb;
		$repo  = new RepositorioSuscriptores( $wpdb );
		$reloj = new RelojSistema();

		$token = 'token-baja-' . uniqid();
		$id    = $repo->crearEmail( TipoSuscripcion::AlertaUrgente, null, null, 'lector3@example.test', $token, $reloj->ahora() );

		self::assertTrue( $repo->eliminar( $id ) );
		self::assertNull( $repo->obtenerPorToken( $token ) );
	}

	public function test_obtener_confirmados_por_objetivo_solo_devuelve_confirmados_del_mismo_canal_y_referencia(): void {
		global $wpdb;
		$repo  = new RepositorioSuscriptores( $wpdb );
		$reloj = new RelojSistema();

		$periodistaId = 900123;

		$confirmadoId = $repo->crearEmail( TipoSuscripcion::Periodista, $periodistaId, null, 'confirmado@example.test', 'token-a-' . uniqid(), $reloj->ahora() );
		$repo->confirmar( $confirmadoId, $reloj->ahora() );
		$repo->crearEmail( TipoSuscripcion::Periodista, $periodistaId, null, 'sin-confirmar@example.test', 'token-b-' . uniqid(), $reloj->ahora() );
		$repo->crearEmail( TipoSuscripcion::Periodista, 999999, null, 'otro-periodista@example.test', 'token-c-' . uniqid(), $reloj->ahora() );

		$resultado = $repo->obtenerConfirmadosPorObjetivo( CanalSuscripcion::Email, TipoSuscripcion::Periodista, $periodistaId, null );

		self::assertCount( 1, $resultado );
		self::assertSame( 'confirmado@example.test', $resultado[0]->email );
	}

	public function test_obtener_por_email_y_eliminar_por_email_cubren_el_ciclo_rgpd(): void {
		global $wpdb;
		$repo  = new RepositorioSuscriptores( $wpdb );
		$reloj = new RelojSistema();

		$email = 'rgpd-' . uniqid() . '@example.test';
		$repo->crearEmail( TipoSuscripcion::Periodista, 1, null, $email, 'token-rgpd-1-' . uniqid(), $reloj->ahora() );
		$repo->crearEmail( TipoSuscripcion::Vertical, null, 'deportes', $email, 'token-rgpd-2-' . uniqid(), $reloj->ahora() );

		self::assertCount( 2, $repo->obtenerPorEmail( $email ) );

		self::assertSame( 2, $repo->eliminarPorEmail( $email ) );
		self::assertCount( 0, $repo->obtenerPorEmail( $email ) );
	}
}
