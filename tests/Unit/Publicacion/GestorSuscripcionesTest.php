<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Publicacion;

use Mockery;
use Pluma\Datos\RepositorioSuscriptoresInterface;
use Pluma\Publicacion\CanalSuscripcion;
use Pluma\Publicacion\GestorSuscripciones;
use Pluma\Publicacion\Suscriptor;
use Pluma\Publicacion\SuscripcionNoEncontradaException;
use Pluma\Publicacion\TipoSuscripcion;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Nivel Cuatro W.3 — suscripciones de precisión: doble opt-in, baja de un
 * clic, exportación/borrado RGPD (`PLUMA-EV-2`).
 *
 * @covers \Pluma\Publicacion\GestorSuscripciones
 */
final class GestorSuscripcionesTest extends CasoDePruebaUnitario {

	private function suscriptor( int $id, bool $confirmado, CanalSuscripcion $canal = CanalSuscripcion::Email ): Suscriptor {
		$reloj = new RelojFijo();

		return new Suscriptor(
			$id,
			$canal,
			TipoSuscripcion::Periodista,
			7,
			null,
			'lector@example.test',
			null,
			null,
			null,
			'token-de-prueba',
			$confirmado,
			$reloj->ahora(),
			null
		);
	}

	public function test_suscribir_email_crea_una_fila_no_confirmada_y_devuelve_el_token(): void {
		$repo = Mockery::mock( RepositorioSuscriptoresInterface::class );
		$repo->expects( 'crearEmail' )
			->with( TipoSuscripcion::Periodista, 7, null, 'lector@example.test', Mockery::type( 'string' ), Mockery::any() )
			->andReturn( 42 );

		$gestor    = new GestorSuscripciones( $repo, new RelojFijo() );
		$resultado = $gestor->suscribirEmail( TipoSuscripcion::Periodista, 7, null, 'lector@example.test' );

		self::assertSame( 42, $resultado['id'] );
		self::assertSame( 64, strlen( $resultado['token'] ) );
	}

	public function test_suscribir_push_se_confirma_de_inmediato(): void {
		$repo = Mockery::mock( RepositorioSuscriptoresInterface::class );
		$repo->expects( 'crearPush' )->andReturn( 43 );
		$repo->expects( 'confirmar' )->with( 43, Mockery::any() )->andReturn( true );

		$gestor = new GestorSuscripciones( $repo, new RelojFijo() );
		$id     = $gestor->suscribirPush( TipoSuscripcion::AlertaUrgente, null, null, 'https://push.example/endpoint', 'clave-p256dh', 'clave-auth' );

		self::assertSame( 43, $id );
	}

	public function test_confirmar_marca_la_suscripcion_pendiente(): void {
		$repo = Mockery::mock( RepositorioSuscriptoresInterface::class );
		$repo->expects( 'obtenerPorToken' )->with( 'token-de-prueba' )->andReturn( $this->suscriptor( 42, false ) );
		$repo->expects( 'confirmar' )->with( 42, Mockery::any() )->andReturn( true );

		( new GestorSuscripciones( $repo, new RelojFijo() ) )->confirmar( 'token-de-prueba' );

		self::assertTrue( true );
	}

	public function test_confirmar_lanza_si_el_token_no_existe(): void {
		$repo = Mockery::mock( RepositorioSuscriptoresInterface::class );
		$repo->expects( 'obtenerPorToken' )->with( 'inexistente' )->andReturn( null );

		$this->expectException( SuscripcionNoEncontradaException::class );

		( new GestorSuscripciones( $repo, new RelojFijo() ) )->confirmar( 'inexistente' );
	}

	public function test_confirmar_lanza_si_ya_estaba_confirmada(): void {
		$repo = Mockery::mock( RepositorioSuscriptoresInterface::class );
		$repo->expects( 'obtenerPorToken' )->with( 'token-de-prueba' )->andReturn( $this->suscriptor( 42, true ) );

		$this->expectException( SuscripcionNoEncontradaException::class );

		( new GestorSuscripciones( $repo, new RelojFijo() ) )->confirmar( 'token-de-prueba' );
	}

	public function test_dar_de_baja_elimina_la_fila(): void {
		$repo = Mockery::mock( RepositorioSuscriptoresInterface::class );
		$repo->expects( 'obtenerPorToken' )->with( 'token-de-prueba' )->andReturn( $this->suscriptor( 42, true ) );
		$repo->expects( 'eliminar' )->with( 42 )->andReturn( true );

		( new GestorSuscripciones( $repo, new RelojFijo() ) )->darDeBaja( 'token-de-prueba' );

		self::assertTrue( true );
	}

	public function test_dar_de_baja_lanza_si_el_token_no_existe(): void {
		$repo = Mockery::mock( RepositorioSuscriptoresInterface::class );
		$repo->expects( 'obtenerPorToken' )->with( 'inexistente' )->andReturn( null );

		$this->expectException( SuscripcionNoEncontradaException::class );

		( new GestorSuscripciones( $repo, new RelojFijo() ) )->darDeBaja( 'inexistente' );
	}

	public function test_exportar_por_email_delega_en_el_repositorio(): void {
		$repo = Mockery::mock( RepositorioSuscriptoresInterface::class );
		$repo->expects( 'obtenerPorEmail' )->with( 'lector@example.test' )->andReturn( array( $this->suscriptor( 1, true ) ) );

		$resultado = ( new GestorSuscripciones( $repo, new RelojFijo() ) )->exportarPorEmail( 'lector@example.test' );

		self::assertCount( 1, $resultado );
	}

	public function test_borrar_por_email_delega_en_el_repositorio(): void {
		$repo = Mockery::mock( RepositorioSuscriptoresInterface::class );
		$repo->expects( 'eliminarPorEmail' )->with( 'lector@example.test' )->andReturn( 2 );

		self::assertSame( 2, ( new GestorSuscripciones( $repo, new RelojFijo() ) )->borrarPorEmail( 'lector@example.test' ) );
	}
}
