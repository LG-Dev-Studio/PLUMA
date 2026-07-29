<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Publicacion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Mockery;
use Pluma\Datos\RepositorioCorreccionesInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\Pieza;
use Pluma\Publicacion\Correccion;
use Pluma\Publicacion\CorreccionNoEncontradaException;
use Pluma\Publicacion\EstadoCorreccion;
use Pluma\Publicacion\GestorCorrecciones;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Nivel Cuatro X.4 — la corrección con crédito.
 *
 * @covers \Pluma\Publicacion\GestorCorrecciones
 */
final class GestorCorreccionesTest extends CasoDePruebaUnitario {

	private function correccion( int $id, bool $creditoOptIn = false, ?string $nombreCredito = null ): Correccion {
		$reloj = new RelojFijo();

		return new Correccion( $id, 42, 'afirmación', 'evidencia', null, $nombreCredito, $creditoOptIn, EstadoCorreccion::Pendiente, null, $reloj->ahora(), null );
	}

	private function pieza( ?int $postId ): Pieza {
		$reloj = new DateTimeImmutable( '2026-07-22T12:00:00+00:00' );

		return new Pieza( 42, 1, EstadoPieza::Publicada, null, $postId, $reloj, $reloj );
	}

	public function test_reportar_delega_en_el_repositorio(): void {
		$correcciones = Mockery::mock( RepositorioCorreccionesInterface::class );
		$correcciones->expects( 'crear' )->with( 42, 'afirmación', 'evidencia', 'lector@example.test', 'Lector', true, Mockery::any() )->andReturn( 5 );

		$gestor = new GestorCorrecciones( $correcciones, Mockery::mock( RepositorioPiezasInterface::class ), new RelojFijo() );

		self::assertSame( 5, $gestor->reportar( 42, 'afirmación', 'evidencia', 'lector@example.test', 'Lector', true ) );
	}

	public function test_verificar_lanza_si_la_correccion_no_existe(): void {
		$correcciones = Mockery::mock( RepositorioCorreccionesInterface::class );
		$correcciones->expects( 'obtenerPorId' )->with( 999 )->andReturn( null );

		$gestor = new GestorCorrecciones( $correcciones, Mockery::mock( RepositorioPiezasInterface::class ), new RelojFijo() );

		$this->expectException( CorreccionNoEncontradaException::class );

		$gestor->verificar( 999, null );
	}

	public function test_verificar_escribe_meta_de_fecha_y_credito_cuando_hay_opt_in(): void {
		$capturados = array();
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $postId, string $clave, $valor ) use ( &$capturados ): bool {
				$capturados[ $clave ] = $valor;

				return true;
			}
		);

		$correcciones = Mockery::mock( RepositorioCorreccionesInterface::class );
		$correcciones->expects( 'obtenerPorId' )->with( 5 )->andReturn( $this->correccion( 5, true, 'Lector Uno' ) );
		$correcciones->expects( 'resolver' )->with( 5, EstadoCorreccion::Verificada, Mockery::any(), Mockery::any() )->andReturn( true );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorId' )->with( 42 )->andReturn( $this->pieza( 100 ) );

		( new GestorCorrecciones( $correcciones, $piezas, new RelojFijo() ) )->verificar( 5, null );

		self::assertArrayHasKey( GestorCorrecciones::META_CORREGIDA_EN, $capturados );
		self::assertSame( 'Lector Uno', $capturados[ GestorCorrecciones::META_CREDITO_LECTOR ] );
	}

	public function test_verificar_no_escribe_credito_sin_opt_in(): void {
		$capturados = array();
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $postId, string $clave, $valor ) use ( &$capturados ): bool {
				$capturados[ $clave ] = $valor;

				return true;
			}
		);

		$correcciones = Mockery::mock( RepositorioCorreccionesInterface::class );
		$correcciones->expects( 'obtenerPorId' )->with( 5 )->andReturn( $this->correccion( 5, false, 'Lector Uno' ) );
		$correcciones->expects( 'resolver' )->andReturn( true );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorId' )->with( 42 )->andReturn( $this->pieza( 100 ) );

		( new GestorCorrecciones( $correcciones, $piezas, new RelojFijo() ) )->verificar( 5, null );

		self::assertArrayNotHasKey( GestorCorrecciones::META_CREDITO_LECTOR, $capturados );
	}

	public function test_verificar_sin_post_id_no_escribe_meta_ni_falla(): void {
		$correcciones = Mockery::mock( RepositorioCorreccionesInterface::class );
		$correcciones->expects( 'obtenerPorId' )->with( 5 )->andReturn( $this->correccion( 5 ) );
		$correcciones->expects( 'resolver' )->andReturn( true );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorId' )->with( 42 )->andReturn( $this->pieza( null ) );

		( new GestorCorrecciones( $correcciones, $piezas, new RelojFijo() ) )->verificar( 5, null );

		self::assertTrue( true );
	}

	public function test_rechazar_lanza_si_la_correccion_no_existe(): void {
		$correcciones = Mockery::mock( RepositorioCorreccionesInterface::class );
		$correcciones->expects( 'obtenerPorId' )->with( 999 )->andReturn( null );

		$gestor = new GestorCorrecciones( $correcciones, Mockery::mock( RepositorioPiezasInterface::class ), new RelojFijo() );

		$this->expectException( CorreccionNoEncontradaException::class );

		$gestor->rechazar( 999, null );
	}

	public function test_rechazar_marca_el_estado(): void {
		$correcciones = Mockery::mock( RepositorioCorreccionesInterface::class );
		$correcciones->expects( 'obtenerPorId' )->with( 5 )->andReturn( $this->correccion( 5 ) );
		$correcciones->expects( 'resolver' )->with( 5, EstadoCorreccion::Rechazada, 'no procede', Mockery::any() )->andReturn( true );

		( new GestorCorrecciones( $correcciones, Mockery::mock( RepositorioPiezasInterface::class ), new RelojFijo() ) )->rechazar( 5, 'no procede' );

		self::assertTrue( true );
	}
}
