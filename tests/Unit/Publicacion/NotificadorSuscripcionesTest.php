<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Publicacion;

use Brain\Monkey\Functions;
use Mockery;
use Pluma\Datos\RepositorioSuscriptoresInterface;
use Pluma\Proveedores\PushWebInterface;
use Pluma\Proveedores\ResultadoEnvioPush;
use Pluma\Publicacion\CanalSuscripcion;
use Pluma\Publicacion\NotificadorSuscripciones;
use Pluma\Publicacion\Suscriptor;
use Pluma\Publicacion\TipoSuscripcion;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Nivel Cuatro W.3 — despacho a suscriptores confirmados por email y push.
 *
 * @covers \Pluma\Publicacion\NotificadorSuscripciones
 */
final class NotificadorSuscripcionesTest extends CasoDePruebaUnitario {

	private function suscriptorEmail( int $id, string $email ): Suscriptor {
		$reloj = new RelojFijo();

		return new Suscriptor( $id, CanalSuscripcion::Email, TipoSuscripcion::Periodista, 7, null, $email, null, null, null, 'token-' . $id, true, $reloj->ahora(), $reloj->ahora() );
	}

	private function suscriptorPush( int $id ): Suscriptor {
		$reloj = new RelojFijo();

		return new Suscriptor( $id, CanalSuscripcion::Push, TipoSuscripcion::Periodista, 7, null, null, 'https://push.example/' . $id, 'p256dh', 'auth', 'token-' . $id, true, $reloj->ahora(), $reloj->ahora() );
	}

	public function test_notificar_objetivo_envia_correo_a_cada_suscriptor_email_confirmado(): void {
		Functions\when( 'rest_url' )->justReturn( 'https://ejemplo.test/wp-json/pluma/v1/suscripciones/baja/token' );
		Functions\when( '__' )->returnArg();
		Functions\when( 'wp_mail' )->justReturn( true );

		$repo = Mockery::mock( RepositorioSuscriptoresInterface::class );
		$repo->expects( 'obtenerConfirmadosPorObjetivo' )
			->with( CanalSuscripcion::Email, TipoSuscripcion::Periodista, 7, null )
			->andReturn( array( $this->suscriptorEmail( 1, 'a@example.test' ), $this->suscriptorEmail( 2, 'b@example.test' ) ) );
		$repo->expects( 'obtenerConfirmadosPorObjetivo' )
			->with( CanalSuscripcion::Push, TipoSuscripcion::Periodista, 7, null )
			->andReturn( array() );

		$push = Mockery::mock( PushWebInterface::class );

		$resultado = ( new NotificadorSuscripciones( $repo, $push ) )->notificarObjetivo( TipoSuscripcion::Periodista, 7, null, 'Asunto', 'Cuerpo', null );

		self::assertSame( 2, $resultado['email'] );
		self::assertSame( 0, $resultado['push'] );
	}

	public function test_notificar_objetivo_envia_push_y_borra_suscripciones_expiradas(): void {
		Functions\when( 'rest_url' )->justReturn( 'https://ejemplo.test/wp-json/' );

		$repo = Mockery::mock( RepositorioSuscriptoresInterface::class );
		$repo->expects( 'obtenerConfirmadosPorObjetivo' )
			->with( CanalSuscripcion::Email, TipoSuscripcion::AlertaUrgente, null, null )
			->andReturn( array() );
		$repo->expects( 'obtenerConfirmadosPorObjetivo' )
			->with( CanalSuscripcion::Push, TipoSuscripcion::AlertaUrgente, null, null )
			->andReturn( array( $this->suscriptorPush( 10 ), $this->suscriptorPush( 11 ) ) );
		$repo->expects( 'eliminar' )->with( 11 )->andReturn( true );

		$push = Mockery::mock( PushWebInterface::class );
		$push->expects( 'enviar' )->with( Mockery::on( static fn ( Suscriptor $s ): bool => 10 === $s->id ), 'Alerta', 'Cuerpo', null )->andReturn( new ResultadoEnvioPush( true, false ) );
		$push->expects( 'enviar' )->with( Mockery::on( static fn ( Suscriptor $s ): bool => 11 === $s->id ), 'Alerta', 'Cuerpo', null )->andReturn( new ResultadoEnvioPush( false, true ) );

		$resultado = ( new NotificadorSuscripciones( $repo, $push ) )->notificarObjetivo( TipoSuscripcion::AlertaUrgente, null, null, 'Alerta', 'Cuerpo', null );

		self::assertSame( 0, $resultado['email'] );
		self::assertSame( 1, $resultado['push'] );
	}
}
