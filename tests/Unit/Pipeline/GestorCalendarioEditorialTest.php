<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Pipeline;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Mockery;
use Pluma\Datos\RepositorioEventosProgramadosInterface;
use Pluma\Datos\RepositorioHistoriasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Datos\RepositorioTendenciasInterface;
use Pluma\Pipeline\EstadoEventoProgramado;
use Pluma\Pipeline\EventoProgramado;
use Pluma\Pipeline\EventoProgramadoNoEncontradoException;
use Pluma\Pipeline\EventoProgramadoSinFuentesException;
use Pluma\Pipeline\GestorCalendarioEditorial;
use Pluma\Pipeline\TipoPieza;
use Pluma\Sensores\TendenciaDetectada;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Nivel Cuatro V.1 (Calendario Editorial) + V.2 (la pieza preparada).
 *
 * @covers \Pluma\Pipeline\GestorCalendarioEditorial
 */
final class GestorCalendarioEditorialTest extends CasoDePruebaUnitario {

	private function evento( int $id, EstadoEventoProgramado $estado = EstadoEventoProgramado::Previsto, ?int $historiaId = null ): EventoProgramado {
		$reloj = new RelojFijo();

		return new EventoProgramado(
			$id,
			'Elecciones generales',
			'politica',
			$reloj->ahora(),
			$estado,
			null,
			$historiaId,
			null,
			$reloj->ahora(),
			$reloj->ahora()
		);
	}

	private function construir(
		?RepositorioEventosProgramadosInterface $eventos = null,
		?RepositorioTendenciasInterface $tendencias = null,
		?RepositorioPiezasInterface $piezas = null,
		?RepositorioHistoriasInterface $historias = null
	): GestorCalendarioEditorial {
		return new GestorCalendarioEditorial(
			$eventos ?? Mockery::mock( RepositorioEventosProgramadosInterface::class ),
			$tendencias ?? Mockery::mock( RepositorioTendenciasInterface::class ),
			$piezas ?? Mockery::mock( RepositorioPiezasInterface::class ),
			$historias ?? Mockery::mock( RepositorioHistoriasInterface::class ),
			new RelojFijo()
		);
	}

	public function test_crear_delega_en_el_repositorio(): void {
		$eventos = Mockery::mock( RepositorioEventosProgramadosInterface::class );
		$eventos->expects( 'crear' )->with( 'Elecciones', 'politica', Mockery::any(), 5, null, Mockery::any() )->andReturn( 42 );

		$id = $this->construir( $eventos )->crear( 'Elecciones', 'politica', new DateTimeImmutable(), 5 );

		self::assertSame( 42, $id );
	}

	public function test_preparar_cobertura_lanza_si_el_evento_no_existe(): void {
		$eventos = Mockery::mock( RepositorioEventosProgramadosInterface::class );
		$eventos->expects( 'obtenerPorId' )->with( 999 )->andReturn( null );

		$this->expectException( EventoProgramadoNoEncontradoException::class );

		$this->construir( $eventos )->prepararCobertura(
			999,
			array(
				array(
					'titulo' => 't',
					'url'    => 'https://example.test',
					'fuente' => 'f',
				),
			)
		);
	}

	public function test_preparar_cobertura_lanza_si_no_hay_fuentes(): void {
		$eventos = Mockery::mock( RepositorioEventosProgramadosInterface::class );
		$eventos->expects( 'obtenerPorId' )->with( 5 )->andReturn( $this->evento( 5 ) );

		$this->expectException( EventoProgramadoSinFuentesException::class );

		$this->construir( $eventos )->prepararCobertura( 5, array() );
	}

	public function test_preparar_cobertura_crea_historia_nueva_cuando_el_evento_no_tenia(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$eventoOriginal = $this->evento( 5, EstadoEventoProgramado::Previsto, null );

		$eventos = Mockery::mock( RepositorioEventosProgramadosInterface::class );
		$eventos->expects( 'obtenerPorId' )->with( 5 )->andReturn( $eventoOriginal );
		$eventos->expects( 'vincularHistoria' )->with( 5, 900, Mockery::any() )->andReturn( true );
		$eventos->expects( 'vincularTendencia' )->with( 5, 77, Mockery::any() )->andReturn( true );
		$eventos->expects( 'actualizarEstado' )->with( 5, EstadoEventoProgramado::Preparado, Mockery::any() )->andReturn( true );

		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'guardar' )->with(
			Mockery::on( static fn ( TendenciaDetectada $t ): bool => 'Elecciones generales' === $t->termino && 'calendario_editorial' === $t->fuenteSenal ),
			Mockery::any()
		)->andReturn( 77 );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'crear' )->with( 77, Mockery::any() )->andReturn( 200 );
		$piezas->expects( 'vincularHistoria' )->with( 200, 900, TipoPieza::Previa, Mockery::any() )->andReturn( true );

		$historias = Mockery::mock( RepositorioHistoriasInterface::class );
		$historias->expects( 'crear' )->with( 'Elecciones generales', Mockery::any() )->andReturn( 900 );

		$piezaId = $this->construir( $eventos, $tendencias, $piezas, $historias )->prepararCobertura(
			5,
			array(
				array(
					'titulo' => 'Encuestas',
					'url'    => 'https://example.test/1',
					'fuente' => 'Diario X',
				),
			)
		);

		self::assertSame( 200, $piezaId );
	}

	public function test_preparar_cobertura_reutiliza_la_historia_existente_del_evento(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$eventoConHistoria = $this->evento( 5, EstadoEventoProgramado::Previsto, 900 );

		$eventos = Mockery::mock( RepositorioEventosProgramadosInterface::class );
		$eventos->expects( 'obtenerPorId' )->with( 5 )->andReturn( $eventoConHistoria );
		$eventos->expects( 'vincularTendencia' )->andReturn( true );
		$eventos->expects( 'actualizarEstado' )->andReturn( true );
		$eventos->expects( 'vincularHistoria' )->never();

		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'guardar' )->andReturn( 77 );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'crear' )->andReturn( 200 );
		$piezas->expects( 'vincularHistoria' )->with( 200, 900, TipoPieza::Previa, Mockery::any() )->andReturn( true );

		$historias = Mockery::mock( RepositorioHistoriasInterface::class );
		$historias->expects( 'crear' )->never();

		$piezaId = $this->construir( $eventos, $tendencias, $piezas, $historias )->prepararCobertura(
			5,
			array(
				array(
					'titulo' => 'Encuestas',
					'url'    => 'https://example.test/1',
					'fuente' => 'Diario X',
				),
			)
		);

		self::assertSame( 200, $piezaId );
	}

	public function test_marcar_en_curso_lanza_si_el_evento_no_existe(): void {
		$eventos = Mockery::mock( RepositorioEventosProgramadosInterface::class );
		$eventos->expects( 'obtenerPorId' )->with( 999 )->andReturn( null );

		$this->expectException( EventoProgramadoNoEncontradoException::class );

		$this->construir( $eventos )->marcarEnCurso( 999 );
	}

	public function test_marcar_en_curso_actualiza_el_estado(): void {
		$eventos = Mockery::mock( RepositorioEventosProgramadosInterface::class );
		$eventos->expects( 'obtenerPorId' )->with( 5 )->andReturn( $this->evento( 5 ) );
		$eventos->expects( 'actualizarEstado' )->with( 5, EstadoEventoProgramado::EnCurso, Mockery::any() )->andReturn( true );

		self::assertTrue( $this->construir( $eventos )->marcarEnCurso( 5 ) );
	}

	public function test_marcar_cubierto_actualiza_el_estado(): void {
		$eventos = Mockery::mock( RepositorioEventosProgramadosInterface::class );
		$eventos->expects( 'obtenerPorId' )->with( 5 )->andReturn( $this->evento( 5 ) );
		$eventos->expects( 'actualizarEstado' )->with( 5, EstadoEventoProgramado::Cubierto, Mockery::any() )->andReturn( true );

		self::assertTrue( $this->construir( $eventos )->marcarCubierto( 5 ) );
	}

	public function test_obtener_delega_en_el_repositorio(): void {
		$eventos = Mockery::mock( RepositorioEventosProgramadosInterface::class );
		$eventos->expects( 'obtenerPorId' )->with( 5 )->andReturn( $this->evento( 5 ) );

		self::assertSame( 5, $this->construir( $eventos )->obtener( 5 )?->id );
	}

	public function test_listar_delega_en_el_repositorio(): void {
		$eventos = Mockery::mock( RepositorioEventosProgramadosInterface::class );
		$eventos->expects( 'listar' )->with( 50 )->andReturn( array( $this->evento( 1 ), $this->evento( 2 ) ) );

		self::assertCount( 2, $this->construir( $eventos )->listar() );
	}
}
