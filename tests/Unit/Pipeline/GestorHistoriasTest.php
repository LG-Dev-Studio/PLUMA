<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Pipeline;

use Brain\Monkey\Functions;
use Mockery;
use Pluma\Datos\RepositorioHistoriasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Pipeline\EstadoHistoria;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\GestorHistorias;
use Pluma\Pipeline\Historia;
use Pluma\Pipeline\Pieza;
use Pluma\Pipeline\TipoPieza;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Nivel Cuatro U.1 — la entidad Historia: agrupación de Piezas de una misma
 * saga con ciclo de vida propio y el bloque "Lo que sabemos / Lo que no
 * sabemos".
 *
 * @covers \Pluma\Pipeline\GestorHistorias
 */
final class GestorHistoriasTest extends CasoDePruebaUnitario {

	private function pieza( int $id, ?int $historiaId = null, TipoPieza $tipo = TipoPieza::Original, ?Expediente $expediente = null ): Pieza {
		$reloj = new RelojFijo();

		return new Pieza(
			$id,
			100,
			EstadoPieza::Publicada,
			$expediente,
			null,
			$reloj->ahora(),
			$reloj->ahora(),
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$historiaId,
			$tipo
		);
	}

	public function test_vincular_actualizacion_crea_historia_nueva_cuando_la_original_no_tenia(): void {
		$piezaOriginal = $this->pieza( 70 );

		$historias = Mockery::mock( RepositorioHistoriasInterface::class );
		$historias->expects( 'crear' )->with( 'saga de prueba', Mockery::any() )->andReturn( 900 );
		$historias->expects( 'actualizarEstado' )->with( 900, EstadoHistoria::EnSeguimiento, Mockery::any() )->andReturn( true );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'vincularHistoria' )->with( 70, 900, TipoPieza::Original, Mockery::any() )->andReturn( true );
		$piezas->expects( 'vincularHistoria' )->with( 71, 900, TipoPieza::Actualizacion, Mockery::any() )->andReturn( true );
		$piezas->allows( 'obtenerPorHistoria' )->with( 900 )->andReturn( array( $piezaOriginal, $this->pieza( 71, 900, TipoPieza::Actualizacion ) ) );

		$gestor = new GestorHistorias( $historias, $piezas, new RelojFijo() );

		$historiaId = $gestor->vincularActualizacion( $piezaOriginal, 71, 'saga de prueba' );

		self::assertSame( 900, $historiaId );
	}

	public function test_vincular_actualizacion_reutiliza_la_historia_existente_de_la_original(): void {
		$piezaOriginal = $this->pieza( 70, 900 );

		$historias = Mockery::mock( RepositorioHistoriasInterface::class );
		$historias->expects( 'crear' )->never();
		$historias->expects( 'actualizarEstado' )->with( 900, EstadoHistoria::EnSeguimiento, Mockery::any() )->andReturn( true );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		// La original YA pertenece a la Historia — no se vuelve a vincular.
		$piezas->expects( 'vincularHistoria' )->once()->with( 72, 900, TipoPieza::Actualizacion, Mockery::any() )->andReturn( true );
		$piezas->allows( 'obtenerPorHistoria' )->with( 900 )->andReturn( array( $piezaOriginal, $this->pieza( 72, 900, TipoPieza::Actualizacion ) ) );

		$gestor = new GestorHistorias( $historias, $piezas, new RelojFijo() );

		self::assertSame( 900, $gestor->vincularActualizacion( $piezaOriginal, 72, 'saga de prueba' ) );
	}

	public function test_bloque_conocimiento_separa_verificado_atribuido_de_disputado(): void {
		$reloj = new RelojFijo();

		$expediente = new Expediente(
			'tendencia',
			array(
				new HechoFuente( 'hecho verificado', 'https://a.example.com', $reloj->ahora(), NivelVerificacion::Verificado ),
				new HechoFuente( 'hecho atribuido', 'https://b.example.com', $reloj->ahora(), NivelVerificacion::Atribuido ),
				new HechoFuente( 'hecho disputado', 'https://c.example.com', $reloj->ahora(), NivelVerificacion::Disputado ),
			)
		);

		$piezas = array( $this->pieza( 1, null, TipoPieza::Original, $expediente ) );

		$gestor = new GestorHistorias(
			Mockery::mock( RepositorioHistoriasInterface::class ),
			Mockery::mock( RepositorioPiezasInterface::class ),
			new RelojFijo()
		);

		$bloque = $gestor->bloqueConocimiento( $piezas );

		self::assertSame( array( 'hecho verificado', 'hecho atribuido' ), $bloque->sabemos );
		self::assertSame( array( 'hecho disputado' ), $bloque->noSabemos );
	}

	public function test_bloque_conocimiento_ignora_piezas_sin_expediente(): void {
		$piezas = array( $this->pieza( 1 ) ); // expediente null

		$gestor = new GestorHistorias(
			Mockery::mock( RepositorioHistoriasInterface::class ),
			Mockery::mock( RepositorioPiezasInterface::class ),
			new RelojFijo()
		);

		$bloque = $gestor->bloqueConocimiento( $piezas );

		self::assertSame( array(), $bloque->sabemos );
		self::assertSame( array(), $bloque->noSabemos );
	}

	public function test_cerrar_marca_la_historia_como_cerrada(): void {
		$historias = Mockery::mock( RepositorioHistoriasInterface::class );
		$historias->expects( 'actualizarEstado' )->with( 5, EstadoHistoria::Cerrada, Mockery::any() )->andReturn( true );

		$gestor = new GestorHistorias( $historias, Mockery::mock( RepositorioPiezasInterface::class ), new RelojFijo() );

		self::assertTrue( $gestor->cerrar( 5 ) );
	}

	public function test_marcar_inactivas_vencidas_actualiza_cada_historia_encontrada(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$historias = Mockery::mock( RepositorioHistoriasInterface::class );
		$historias->expects( 'obtenerAbiertasSinActividadDesde' )->andReturn( array( 1, 2, 3 ) );
		$historias->expects( 'actualizarEstado' )->with( 1, EstadoHistoria::Inactiva, Mockery::any() )->andReturn( true );
		$historias->expects( 'actualizarEstado' )->with( 2, EstadoHistoria::Inactiva, Mockery::any() )->andReturn( true );
		$historias->expects( 'actualizarEstado' )->with( 3, EstadoHistoria::Inactiva, Mockery::any() )->andReturn( true );

		$gestor = new GestorHistorias( $historias, Mockery::mock( RepositorioPiezasInterface::class ), new RelojFijo() );

		self::assertSame( 3, $gestor->marcarInactivasVencidas() );
	}

	public function test_obtener_devuelve_null_si_la_historia_no_existe(): void {
		$historias = Mockery::mock( RepositorioHistoriasInterface::class );
		$historias->expects( 'obtenerPorId' )->with( 999 )->andReturn( null );

		$gestor = new GestorHistorias( $historias, Mockery::mock( RepositorioPiezasInterface::class ), new RelojFijo() );

		self::assertNull( $gestor->obtener( 999 ) );
	}

	public function test_obtener_hidrata_piezaids_desde_el_repositorio_de_piezas(): void {
		$reloj    = new RelojFijo();
		$historia = new Historia( 5, 'saga', EstadoHistoria::EnSeguimiento, null, array(), $reloj->ahora(), $reloj->ahora() );

		$historias = Mockery::mock( RepositorioHistoriasInterface::class );
		$historias->expects( 'obtenerPorId' )->with( 5 )->andReturn( $historia );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorHistoria' )->with( 5 )->andReturn( array( $this->pieza( 10, 5 ), $this->pieza( 11, 5 ) ) );

		$gestor = new GestorHistorias( $historias, $piezas, new RelojFijo() );

		$hidratada = $gestor->obtener( 5 );

		self::assertNotNull( $hidratada );
		self::assertSame( array( 10, 11 ), $hidratada->piezaIds );
	}
}
