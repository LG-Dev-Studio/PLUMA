<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Pipeline;

use Brain\Monkey\Functions;
use Mockery;
use Pluma\Datos\RepositorioAuditoriaInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\Pieza;
use Pluma\Pipeline\PiezaNoEncontradaException;
use Pluma\Pipeline\Transicionador;
use Pluma\Pipeline\TransicionInvalidaException;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * @covers \Pluma\Pipeline\Transicionador
 */
final class TransicionadorTest extends CasoDePruebaUnitario {

	private function piezaDeEjemplo( EstadoPieza $estado ): Pieza {
		$reloj = new RelojFijo();

		return new Pieza( 1, 10, $estado, null, null, $reloj->ahora(), $reloj->ahora() );
	}

	public function test_transitar_aplica_el_cambio_registra_auditoria_y_dispara_evento(): void {
		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorId' )->with( 1 )->andReturn( $this->piezaDeEjemplo( EstadoPieza::Detectada ) );
		$piezas->expects( 'actualizarEstado' )
			->with( 1, EstadoPieza::Detectada, EstadoPieza::EnInvestigacion, Mockery::type( \DateTimeImmutable::class ) )
			->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->expects( 'registrar' )->with(
			1,
			EstadoPieza::Detectada,
			EstadoPieza::EnInvestigacion,
			'sistema',
			'inicio de investigación',
			Mockery::type( \DateTimeImmutable::class ),
			null
		);

		Functions\expect( 'do_action' )->once()->with(
			'pluma/pieza_en_investigacion',
			1,
			EstadoPieza::Detectada,
			'inicio de investigación'
		);

		$transicionador = new Transicionador( $piezas, $auditoria, new RelojFijo() );
		$resultado      = $transicionador->transitar( 1, EstadoPieza::EnInvestigacion, 'inicio de investigación' );

		self::assertNotNull( $resultado );
		self::assertSame( EstadoPieza::EnInvestigacion, $resultado->estado );
	}

	public function test_transitar_devuelve_null_si_otra_ejecucion_ya_la_movio(): void {
		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorId' )->andReturn( $this->piezaDeEjemplo( EstadoPieza::Detectada ) );
		$piezas->expects( 'actualizarEstado' )->andReturn( false );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->expects( 'registrar' )->never();

		Functions\expect( 'do_action' )->never();

		$transicionador = new Transicionador( $piezas, $auditoria, new RelojFijo() );
		$resultado      = $transicionador->transitar( 1, EstadoPieza::EnInvestigacion, 'motivo' );

		self::assertNull( $resultado );
	}

	public function test_transitar_lanza_excepcion_si_la_pieza_no_existe(): void {
		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorId' )->with( 99 )->andReturn( null );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );

		$transicionador = new Transicionador( $piezas, $auditoria, new RelojFijo() );

		$this->expectException( PiezaNoEncontradaException::class );

		$transicionador->transitar( 99, EstadoPieza::EnInvestigacion, 'motivo' );
	}

	public function test_transitar_lanza_excepcion_si_la_arista_no_existe_en_el_grafo(): void {
		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorId' )->andReturn( $this->piezaDeEjemplo( EstadoPieza::Detectada ) );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->expects( 'registrar' )->never();

		$transicionador = new Transicionador( $piezas, $auditoria, new RelojFijo() );

		$this->expectException( TransicionInvalidaException::class );

		// Detectada -> Publicada no existe en el grafo (references/estados.md).
		$transicionador->transitar( 1, EstadoPieza::Publicada, 'motivo inválido' );
	}

	public function test_estado_terminal_no_tiene_aristas_salientes(): void {
		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorId' )->andReturn( $this->piezaDeEjemplo( EstadoPieza::Publicada ) );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );

		$transicionador = new Transicionador( $piezas, $auditoria, new RelojFijo() );

		$this->expectException( TransicionInvalidaException::class );

		$transicionador->transitar( 1, EstadoPieza::Descartada, 'motivo' );
	}

	/**
	 * Nivel Dos C.3: `SIN_PERIODISTA_IDONEO` es una salida lateral nueva
	 * desde `EN_REDACCION` (el paso de asignación de periodista).
	 */
	public function test_en_redaccion_puede_transitar_a_sin_periodista_idoneo(): void {
		Functions\when( 'do_action' )->justReturn( null );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorId' )->andReturn( $this->piezaDeEjemplo( EstadoPieza::EnRedaccion ) );
		$piezas->expects( 'actualizarEstado' )->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->expects( 'registrar' );

		$transicionador = new Transicionador( $piezas, $auditoria, new RelojFijo() );

		$resultado = $transicionador->transitar( 1, EstadoPieza::SinPeriodistaIdoneo, 'ningún periodista supera el umbral de dominio' );

		self::assertNotNull( $resultado );
	}

	/**
	 * Nivel Dos C.3: reanudable a `EN_REDACCION` (ajuste del banco por el
	 * editor) o `DESCARTADA` (la tendencia caduca mientras espera).
	 */
	public function test_sin_periodista_idoneo_es_reanudable_o_descartable(): void {
		Functions\when( 'do_action' )->justReturn( null );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorId' )->twice()->andReturn( $this->piezaDeEjemplo( EstadoPieza::SinPeriodistaIdoneo ) );
		$piezas->expects( 'actualizarEstado' )->twice()->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->expects( 'registrar' )->twice();

		$transicionador = new Transicionador( $piezas, $auditoria, new RelojFijo() );

		self::assertNotNull( $transicionador->transitar( 1, EstadoPieza::EnRedaccion, 'ajuste del banco' ) );
		self::assertNotNull( $transicionador->transitar( 1, EstadoPieza::Descartada, 'tendencia caducada' ) );
	}
}
