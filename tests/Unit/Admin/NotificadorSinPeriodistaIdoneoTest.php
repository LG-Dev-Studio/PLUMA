<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Mockery;
use Pluma\Admin\NotificadorSinPeriodistaIdoneo;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * Nivel Dos C.3: notificación por correo cuando ningún periodista del
 * banco supera el umbral de dominio mínimo.
 *
 * @covers \Pluma\Admin\NotificadorSinPeriodistaIdoneo
 */
final class NotificadorSinPeriodistaIdoneoTest extends CasoDePruebaUnitario {

	public function test_notifica_al_correo_del_administrador_con_el_motivo(): void {
		Functions\when( 'get_option' )->justReturn( 'editor@example.com' );
		Functions\when( '__' )->returnArg( 1 );

		Functions\expect( 'wp_mail' )
			->once()
			->with(
				'editor@example.com',
				Mockery::on( static fn ( string $asunto ): bool => str_contains( $asunto, '#42' ) ),
				Mockery::on( static fn ( string $cuerpo ): bool => str_contains( $cuerpo, 'umbral de dominio' ) )
			)
			->andReturn( true );

		( new NotificadorSinPeriodistaIdoneo() )->notificar( 42, EstadoPieza::EnRedaccion, 'ningún periodista supera el umbral de dominio' );

		$this->expectNotToPerformAssertions();
	}

	public function test_no_notifica_si_no_hay_correo_de_administrador_configurado(): void {
		Functions\when( 'get_option' )->justReturn( '' );

		Functions\expect( 'wp_mail' )->never();

		( new NotificadorSinPeriodistaIdoneo() )->notificar( 1, EstadoPieza::EnRedaccion, 'x' );

		$this->expectNotToPerformAssertions();
	}
}
