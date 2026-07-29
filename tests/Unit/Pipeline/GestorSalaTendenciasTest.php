<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Pipeline;

use Brain\Monkey\Functions;
use Mockery;
use Pluma\Datos\RepositorioAuditoriaInterface;
use Pluma\Datos\RepositorioHistoriasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Datos\RepositorioTendenciasInterface;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\GestorHistorias;
use Pluma\Pipeline\GestorSalaTendencias;
use Pluma\Pipeline\Pieza;
use Pluma\Pipeline\TendenciaNoEncontradaException;
use Pluma\Pipeline\Transicionador;
use Pluma\Sensores\EstadoTendencia;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Sala de Tendencias (Libro Cap. 10.2): las tres acciones directas sobre la
 * agenda. Semántica del propietario (2026-07-23): ignorar/vigilar descartan
 * la Pieza en curso; cubrir prioriza o crea con prioridad.
 *
 * @covers \Pluma\Pipeline\GestorSalaTendencias
 */
final class GestorSalaTendenciasTest extends CasoDePruebaUnitario {

	private function pieza( int $id, EstadoPieza $estado ): Pieza {
		$reloj = new RelojFijo();

		return new Pieza( $id, 100, $estado, null, null, $reloj->ahora(), $reloj->ahora() );
	}

	/**
	 * @param RepositorioPiezasInterface&Mockery\MockInterface $piezas
	 */
	private function construir( $tendencias, $piezas, ?GestorHistorias $gestorHistorias = null ): GestorSalaTendencias {
		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		return new GestorSalaTendencias(
			$tendencias,
			$piezas,
			new Transicionador( $piezas, $auditoria, new RelojFijo() ),
			new RelojFijo(),
			$gestorHistorias ?? $this->gestorHistoriasFalso( $piezas )
		);
	}

	/**
	 * `GestorHistorias` es `final` sin interfaz (lógica de dominio pura,
	 * mismo criterio que `SelectorAngulo`/`AsignadorPeriodista`): se
	 * construye real, con sus propios repositorios como dobles.
	 *
	 * @param RepositorioPiezasInterface&Mockery\MockInterface $piezas
	 */
	private function gestorHistoriasFalso( $piezas ): GestorHistorias {
		$historias = Mockery::mock( RepositorioHistoriasInterface::class );
		$historias->allows( 'crear' )->andReturn( 900 );
		$historias->allows( 'actualizarEstado' )->andReturn( true );

		return new GestorHistorias( $historias, $piezas, new RelojFijo() );
	}

	public function test_cubrir_ahora_prioriza_la_pieza_viva_y_devuelve_la_tendencia_al_pipeline(): void {
		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'actualizarEstadoTendencia' )->with( 5, EstadoTendencia::EnPipeline )->andReturn( true );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerUltimaPorTendencia' )->with( 5 )->andReturn( $this->pieza( 40, EstadoPieza::Detectada ) );
		$piezas->expects( 'priorizar' )->with( 40, Mockery::any() )->andReturn( true );
		$piezas->expects( 'crear' )->never();

		$this->construir( $tendencias, $piezas )->cubrirAhora( 5 );

		$this->expectNotToPerformAssertions();
	}

	public function test_cubrir_ahora_crea_una_pieza_nueva_prioritaria_si_la_anterior_fue_descartada(): void {
		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'actualizarEstadoTendencia' )->with( 6, EstadoTendencia::EnPipeline )->andReturn( true );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerUltimaPorTendencia' )->with( 6 )->andReturn( $this->pieza( 41, EstadoPieza::Descartada ) );
		$piezas->expects( 'crear' )->with( 6, Mockery::any() )->andReturn( 42 );
		$piezas->expects( 'priorizar' )->with( 42, Mockery::any() )->andReturn( true );

		$this->construir( $tendencias, $piezas )->cubrirAhora( 6 );

		$this->expectNotToPerformAssertions();
	}

	/**
	 * Nivel Dos G.1 (Etapa 8, Porción 9): una tendencia con
	 * SOSPECHA_MANIPULACION nunca crea Pieza en `detectarTendencias()` — a
	 * diferencia de una tendencia VIGILADA (que descarta una Pieza ya
	 * existente), aquí `obtenerUltimaPorTendencia()` devuelve `null` porque
	 * la Pieza nunca llegó a existir. "Cubrir ahora" debe manejar esa rama
	 * igual de bien que la de "Descartada".
	 */
	public function test_cubrir_ahora_crea_una_pieza_nueva_prioritaria_si_nunca_hubo_pieza(): void {
		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'actualizarEstadoTendencia' )->with( 9, EstadoTendencia::EnPipeline )->andReturn( true );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerUltimaPorTendencia' )->with( 9 )->andReturn( null );
		$piezas->expects( 'crear' )->with( 9, Mockery::any() )->andReturn( 43 );
		$piezas->expects( 'priorizar' )->with( 43, Mockery::any() )->andReturn( true );

		$this->construir( $tendencias, $piezas )->cubrirAhora( 9 );

		$this->expectNotToPerformAssertions();
	}

	/**
	 * Bug real encontrado en producción ("Cubrir ahora" reportaba éxito pero
	 * no pasaba nada visible): si la Pieza en curso ya está FALLIDA, solo
	 * priorizarla no la revive — ningún tick del Orquestador vuelve a tocar
	 * una Pieza fallida por su cuenta. "Cubrir ahora" debe reanudarla a
	 * DETECTADA (arista que el propio grafo del Transicionador ya admite)
	 * antes de priorizarla, en vez de re-priorizar en silencio una Pieza
	 * muerta.
	 */
	public function test_cubrir_ahora_reanuda_una_pieza_fallida_antes_de_priorizarla(): void {
		Functions\when( 'do_action' )->justReturn( null );

		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'actualizarEstadoTendencia' )->with( 21, EstadoTendencia::EnPipeline )->andReturn( true );

		$pieza  = $this->pieza( 21, EstadoPieza::Fallida );
		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerUltimaPorTendencia' )->with( 21 )->andReturn( $pieza );
		$piezas->allows( 'obtenerPorId' )->with( 21 )->andReturn( $pieza );
		$piezas->expects( 'actualizarEstado' )->with( 21, EstadoPieza::Fallida, EstadoPieza::Detectada, Mockery::any() )->andReturn( true );
		$piezas->expects( 'priorizar' )->with( 21, Mockery::any() )->andReturn( true );
		$piezas->expects( 'crear' )->never();

		$this->construir( $tendencias, $piezas )->cubrirAhora( 21 );

		$this->expectNotToPerformAssertions();
	}

	public function test_vigilar_descarta_la_pieza_en_curso_y_marca_la_tendencia(): void {
		Functions\when( 'do_action' )->justReturn( null );

		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'actualizarEstadoTendencia' )->with( 7, EstadoTendencia::Vigilada )->andReturn( true );

		$pieza  = $this->pieza( 50, EstadoPieza::Detectada );
		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerUltimaPorTendencia' )->with( 7 )->andReturn( $pieza );
		$piezas->allows( 'obtenerPorId' )->with( 50 )->andReturn( $pieza );
		$piezas->expects( 'actualizarEstado' )->with( 50, EstadoPieza::Detectada, EstadoPieza::Descartada, Mockery::any() )->andReturn( true );

		$this->construir( $tendencias, $piezas )->vigilar( 7 );

		$this->expectNotToPerformAssertions();
	}

	public function test_ignorar_una_tendencia_cuya_pieza_ya_se_publico_no_toca_la_pieza(): void {
		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'actualizarEstadoTendencia' )->with( 8, EstadoTendencia::Ignorada )->andReturn( true );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerUltimaPorTendencia' )->with( 8 )->andReturn( $this->pieza( 60, EstadoPieza::Publicada ) );
		$piezas->expects( 'actualizarEstado' )->never();

		$this->construir( $tendencias, $piezas )->ignorar( 8 );

		$this->expectNotToPerformAssertions();
	}

	public function test_una_tendencia_inexistente_lanza_excepcion(): void {
		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->allows( 'actualizarEstadoTendencia' )->andReturn( false );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );

		$this->expectException( TendenciaNoEncontradaException::class );

		$this->construir( $tendencias, $piezas )->cubrirAhora( 999 );
	}

	public function test_cubrir_como_actualizacion_crea_la_pieza_enlazada_a_la_original(): void {
		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'obtenerTendenciaOriginal' )->with( 10 )->andReturn( 3 );
		$tendencias->expects( 'actualizarEstadoTendencia' )->with( 10, EstadoTendencia::EnPipeline )->andReturn( true );
		$tendencias->allows( 'obtenerPorId' )->with( 3 )->andReturn(
			array(
				'termino'               => 'saga de prueba',
				'articulosRelacionados' => array(),
			)
		);

		$piezaOriginal = $this->pieza( 70, EstadoPieza::Publicada );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerUltimaPorTendencia' )->with( 3 )->andReturn( $piezaOriginal );
		$piezas->expects( 'crearComoActualizacion' )->with( 10, 70, Mockery::any() )->andReturn( 71 );
		$piezas->expects( 'crear' )->never();
		$piezas->expects( 'priorizar' )->with( 71, Mockery::any() )->andReturn( true );
		// Nivel Cuatro U.1 (Etapa 9): la vinculación a Historia pasa por
		// $piezas también (vincularHistoria/obtenerPorHistoria), vía
		// GestorHistorias — no es el foco de este test, se permite tal cual.
		$piezas->allows( 'vincularHistoria' )->andReturn( true );
		$piezas->allows( 'obtenerPorHistoria' )->andReturn( array() );

		$this->construir( $tendencias, $piezas )->cubrirComoActualizacion( 10 );

		$this->expectNotToPerformAssertions();
	}

	public function test_cubrir_como_actualizacion_sin_pieza_original_viva_crea_una_pieza_normal(): void {
		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'obtenerTendenciaOriginal' )->with( 11 )->andReturn( 4 );
		$tendencias->expects( 'actualizarEstadoTendencia' )->with( 11, EstadoTendencia::EnPipeline )->andReturn( true );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerUltimaPorTendencia' )->with( 4 )->andReturn( null );
		$piezas->expects( 'crearComoActualizacion' )->never();
		$piezas->expects( 'crear' )->with( 11, Mockery::any() )->andReturn( 80 );
		$piezas->expects( 'priorizar' )->with( 80, Mockery::any() )->andReturn( true );

		$this->construir( $tendencias, $piezas )->cubrirComoActualizacion( 11 );

		$this->expectNotToPerformAssertions();
	}

	public function test_cubrir_como_actualizacion_sin_tendencia_original_lanza_excepcion(): void {
		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'obtenerTendenciaOriginal' )->with( 12 )->andReturn( null );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );

		$this->expectException( TendenciaNoEncontradaException::class );

		$this->construir( $tendencias, $piezas )->cubrirComoActualizacion( 12 );
	}
}
