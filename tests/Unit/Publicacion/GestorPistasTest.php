<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Publicacion;

use DateTimeImmutable;
use Mockery;
use Pluma\Datos\RepositorioPistasInterface;
use Pluma\Publicacion\EstadoPista;
use Pluma\Publicacion\GestorPistas;
use Pluma\Publicacion\Pista;
use Pluma\Publicacion\PistaNoEncontradaException;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Nivel Cuatro X.3 — el buzón de pistas.
 *
 * @covers \Pluma\Publicacion\GestorPistas
 */
final class GestorPistasTest extends CasoDePruebaUnitario {

	private function pista( int $id ): Pista {
		$reloj = new RelojFijo();

		return new Pista( $id, 7, 'contenido de la pista', null, EstadoPista::Pendiente, $reloj->ahora() );
	}

	public function test_reportar_delega_en_el_repositorio(): void {
		$pistas = Mockery::mock( RepositorioPistasInterface::class );
		$pistas->expects( 'crear' )->with( 7, 'contenido', 'lector@example.test', Mockery::any() )->andReturn( 5 );

		$gestor = new GestorPistas( $pistas, new RelojFijo() );

		self::assertSame( 5, $gestor->reportar( 7, 'contenido', 'lector@example.test' ) );
	}

	public function test_marcar_revisada_lanza_si_no_existe(): void {
		$pistas = Mockery::mock( RepositorioPistasInterface::class );
		$pistas->expects( 'obtenerPorId' )->with( 999 )->andReturn( null );

		$this->expectException( PistaNoEncontradaException::class );

		( new GestorPistas( $pistas, new RelojFijo() ) )->marcarRevisada( 999 );
	}

	public function test_marcar_revisada_actualiza_el_estado(): void {
		$pistas = Mockery::mock( RepositorioPistasInterface::class );
		$pistas->expects( 'obtenerPorId' )->with( 5 )->andReturn( $this->pista( 5 ) );
		$pistas->expects( 'actualizarEstado' )->with( 5, EstadoPista::Revisada )->andReturn( true );

		( new GestorPistas( $pistas, new RelojFijo() ) )->marcarRevisada( 5 );

		self::assertTrue( true );
	}

	public function test_marcar_descartada_actualiza_el_estado(): void {
		$pistas = Mockery::mock( RepositorioPistasInterface::class );
		$pistas->expects( 'obtenerPorId' )->with( 5 )->andReturn( $this->pista( 5 ) );
		$pistas->expects( 'actualizarEstado' )->with( 5, EstadoPista::Descartada )->andReturn( true );

		( new GestorPistas( $pistas, new RelojFijo() ) )->marcarDescartada( 5 );

		self::assertTrue( true );
	}

	public function test_pendientes_delega_en_el_repositorio(): void {
		$pistas = Mockery::mock( RepositorioPistasInterface::class );
		$pistas->expects( 'obtenerPorEstado' )->with( EstadoPista::Pendiente, 50 )->andReturn( array( $this->pista( 1 ) ) );

		self::assertCount( 1, ( new GestorPistas( $pistas, new RelojFijo() ) )->pendientes() );
	}
}
