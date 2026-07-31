<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Pipeline;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Mockery;
use Pluma\Compuertas\ModoOperacion;
use Pluma\Compuertas\ResultadoEvaluacion;
use Pluma\Compuertas\DiagnosticoCalidad;
use Pluma\Compuertas\DiagnosticoOriginalidad;
use Pluma\Compuertas\DiagnosticoRiesgo;
use Pluma\Datos\RepositorioAuditoriaInterface;
use Pluma\Datos\RepositorioColaPublicacionInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Pipeline\AccionNoDisponibleException;
use Pluma\Pipeline\EstadoColaPublicacion;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\GestorSalaRevision;
use Pluma\Pipeline\PeriodistaNoEncontradoException;
use Pluma\Pipeline\Pieza;
use Pluma\Pipeline\PiezaNoEncontradaException;
use Pluma\Pipeline\RanuraPublicacion;
use Pluma\Pipeline\Transicionador;
use Pluma\Pipeline\TransicionInvalidaException;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\Especialidad;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Sala de Revisión (Libro Cap. 10.2): "la bandeja de lo que espera decisión
 * humana" — piezas RETENIDAS y la cola de veto de Copiloto.
 *
 * @covers \Pluma\Pipeline\GestorSalaRevision
 */
final class GestorSalaRevisionTest extends CasoDePruebaUnitario {

	private function pieza( int $id, EstadoPieza $estado, ?ResultadoEvaluacion $resultado = null ): Pieza {
		$reloj = new RelojFijo();

		return new Pieza( $id, 100, $estado, null, null, $reloj->ahora(), $reloj->ahora(), null, null, null, $resultado );
	}

	private function piezaSinPeriodistaIdoneo( int $id, string $tema ): Pieza {
		$reloj = new RelojFijo();

		return new Pieza( $id, 100, EstadoPieza::SinPeriodistaIdoneo, null, null, $reloj->ahora(), $reloj->ahora(), temaSinCubrir: $tema );
	}

	private function periodistaPropuesto( string $vertical = 'deportes' ): Periodista {
		$reloj    = new RelojFijo();
		$diales   = new Diales( 60, 40, 20, 60, 50, 50, 60, 50 );
		$reglas   = new ReglasConducta( 'línea', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 1, 30, $diales, $reglas, $matriz, $reloj->ahora() );

		return new Periodista(
			30,
			'Periodista Propuesto',
			null,
			'biografía',
			RolPeriodista::Cronista,
			array( new Especialidad( $vertical, 4 ) ),
			EstadoPeriodista::Propuesto,
			$conducta,
			$reloj->ahora(),
			$reloj->ahora()
		);
	}

	private function resultado( ModoOperacion $modo ): ResultadoEvaluacion {
		return new ResultadoEvaluacion(
			false,
			true,
			array( 'riesgo de difamación' ),
			$modo,
			new DiagnosticoCalidad( 80, 70, true, true, array() ),
			new DiagnosticoRiesgo( false, false, false, false, true, 'x', false, null ),
			new DiagnosticoOriginalidad( false, false, 0.8, 0.4 )
		);
	}

	public function test_obtener_retenidas_delega_al_repositorio(): void {
		$pieza = $this->pieza( 1, EstadoPieza::Retenida );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorEstado' )->with( EstadoPieza::Retenida, Mockery::any() )->andReturn( array( $pieza ) );

		$gestor = new GestorSalaRevision(
			$piezas,
			Mockery::mock( RepositorioColaPublicacionInterface::class ),
			Mockery::mock( RepositorioPeriodistasInterface::class ),
			new Transicionador( $piezas, Mockery::mock( RepositorioAuditoriaInterface::class ), new RelojFijo() )
		);

		self::assertSame( array( $pieza ), $gestor->obtenerRetenidas() );
	}

	public function test_cola_de_veto_solo_incluye_piezas_programadas_en_modo_copiloto(): void {
		$piezaCopiloto = $this->pieza( 2, EstadoPieza::Programada, $this->resultado( ModoOperacion::Copiloto ) );
		$piezaAutonoma = $this->pieza( 3, EstadoPieza::Programada, $this->resultado( ModoOperacion::Autonomo ) );

		$ranura = new RanuraPublicacion( 1, 2, 'economia', 5, new DateTimeImmutable( '2026-07-22T09:00:00+00:00' ), EstadoColaPublicacion::Programada, false, new DateTimeImmutable( '2026-07-22T08:00:00+00:00' ) );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorEstado' )->with( EstadoPieza::Programada, Mockery::any() )->andReturn( array( $piezaCopiloto, $piezaAutonoma ) );

		$cola = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$cola->expects( 'obtenerProgramadaPorPieza' )->with( 2 )->andReturn( $ranura );

		$gestor = new GestorSalaRevision(
			$piezas,
			$cola,
			Mockery::mock( RepositorioPeriodistasInterface::class ),
			new Transicionador( $piezas, Mockery::mock( RepositorioAuditoriaInterface::class ), new RelojFijo() )
		);

		$entradas = $gestor->obtenerColaDeVeto( 2 );

		self::assertCount( 1, $entradas );
		self::assertSame( 2, $entradas[0]->pieza->id );
		self::assertSame( '2026-07-22T11:00:00+00:00', $entradas[0]->horaLimiteVeto->format( DATE_ATOM ) );
	}

	public function test_aprobar_transiciona_de_retenida_a_aprobada(): void {
		Functions\when( 'do_action' )->justReturn( null );

		$pieza = $this->pieza( 4, EstadoPieza::Retenida );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorId' )->with( 4 )->andReturn( $pieza );
		$piezas->expects( 'actualizarEstado' )->with( 4, EstadoPieza::Retenida, EstadoPieza::Aprobada, Mockery::any() )->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		$gestor = new GestorSalaRevision( $piezas, Mockery::mock( RepositorioColaPublicacionInterface::class ), Mockery::mock( RepositorioPeriodistasInterface::class ), new Transicionador( $piezas, $auditoria, new RelojFijo() ) );

		$gestor->aprobar( 4 );

		$this->expectNotToPerformAssertions();
	}

	public function test_aprobar_desde_la_mesa_editorial_deja_el_origen_en_el_motivo_de_auditoria(): void {
		Functions\when( 'do_action' )->justReturn( null );

		$pieza = $this->pieza( 41, EstadoPieza::Retenida );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorId' )->with( 41 )->andReturn( $pieza );
		$piezas->expects( 'actualizarEstado' )->with( 41, EstadoPieza::Retenida, EstadoPieza::Aprobada, Mockery::any() )->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->expects( 'registrar' )
			->with( 41, EstadoPieza::Retenida, EstadoPieza::Aprobada, Mockery::any(), Mockery::on( static fn ( string $motivo ): bool => str_contains( $motivo, 'la Mesa Editorial' ) ), Mockery::any(), null );

		$gestor = new GestorSalaRevision( $piezas, Mockery::mock( RepositorioColaPublicacionInterface::class ), Mockery::mock( RepositorioPeriodistasInterface::class ), new Transicionador( $piezas, $auditoria, new RelojFijo() ) );

		$gestor->aprobar( 41, 'la Mesa Editorial' );

		$this->expectNotToPerformAssertions();
	}

	public function test_aprobar_una_pieza_no_retenida_lanza_transicion_invalida_sin_importar_el_origen(): void {
		$pieza = $this->pieza( 42, EstadoPieza::Detectada );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorId' )->with( 42 )->andReturn( $pieza );
		$piezas->expects( 'actualizarEstado' )->never();

		$gestor = new GestorSalaRevision( $piezas, Mockery::mock( RepositorioColaPublicacionInterface::class ), Mockery::mock( RepositorioPeriodistasInterface::class ), new Transicionador( $piezas, Mockery::mock( RepositorioAuditoriaInterface::class ), new RelojFijo() ) );

		$this->expectException( TransicionInvalidaException::class );

		$gestor->aprobar( 42, 'la Mesa Editorial' );
	}

	public function test_devolver_transiciona_de_retenida_a_optimizada_con_la_nota_en_el_motivo(): void {
		Functions\when( 'do_action' )->justReturn( null );

		$pieza = $this->pieza( 5, EstadoPieza::Retenida );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorId' )->with( 5 )->andReturn( $pieza );
		$piezas->expects( 'actualizarEstado' )->with( 5, EstadoPieza::Retenida, EstadoPieza::Optimizada, Mockery::any() )->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->expects( 'registrar' )
			->with( 5, EstadoPieza::Retenida, EstadoPieza::Optimizada, Mockery::any(), Mockery::on( static fn ( string $motivo ): bool => str_contains( $motivo, 'falta contexto' ) ), Mockery::any(), null );

		$gestor = new GestorSalaRevision( $piezas, Mockery::mock( RepositorioColaPublicacionInterface::class ), Mockery::mock( RepositorioPeriodistasInterface::class ), new Transicionador( $piezas, $auditoria, new RelojFijo() ) );

		$gestor->devolver( 5, 'falta contexto' );

		$this->expectNotToPerformAssertions();
	}

	public function test_descartar_una_pieza_retenida_no_toca_la_cola_de_publicacion(): void {
		Functions\when( 'do_action' )->justReturn( null );

		$pieza = $this->pieza( 6, EstadoPieza::Retenida );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorId' )->with( 6 )->andReturn( $pieza );
		$piezas->expects( 'actualizarEstado' )->with( 6, EstadoPieza::Retenida, EstadoPieza::Descartada, Mockery::any() )->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		$cola = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$cola->expects( 'obtenerProgramadaPorPieza' )->never();
		$cola->expects( 'marcarExpirada' )->never();

		$gestor = new GestorSalaRevision( $piezas, $cola, Mockery::mock( RepositorioPeriodistasInterface::class ), new Transicionador( $piezas, $auditoria, new RelojFijo() ) );

		$gestor->descartar( 6 );

		$this->expectNotToPerformAssertions();
	}

	public function test_aprobar_ahora_marca_la_ranura_con_aprobacion_activa(): void {
		$pieza  = $this->pieza( 10, EstadoPieza::Programada, $this->resultado( ModoOperacion::Copiloto ) );
		$ranura = new RanuraPublicacion( 20, 10, 'economia', 5, new DateTimeImmutable( '2026-07-22T09:00:00+00:00' ), EstadoColaPublicacion::Programada, false, new DateTimeImmutable( '2026-07-22T08:00:00+00:00' ) );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorId' )->with( 10 )->andReturn( $pieza );

		$cola = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$cola->expects( 'obtenerProgramadaPorPieza' )->with( 10 )->andReturn( $ranura );
		$cola->expects( 'marcarAprobacionActiva' )->once()->with( 20 );

		$gestor = new GestorSalaRevision( $piezas, $cola, Mockery::mock( RepositorioPeriodistasInterface::class ), new Transicionador( $piezas, Mockery::mock( RepositorioAuditoriaInterface::class ), new RelojFijo() ) );

		$gestor->aprobarAhora( 10 );

		$this->expectNotToPerformAssertions();
	}

	public function test_aprobar_ahora_sobre_una_pieza_no_programada_lanza_excepcion(): void {
		$pieza = $this->pieza( 11, EstadoPieza::Retenida );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorId' )->with( 11 )->andReturn( $pieza );

		$cola = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$cola->expects( 'obtenerProgramadaPorPieza' )->never();

		$gestor = new GestorSalaRevision( $piezas, $cola, Mockery::mock( RepositorioPeriodistasInterface::class ), new Transicionador( $piezas, Mockery::mock( RepositorioAuditoriaInterface::class ), new RelojFijo() ) );

		$this->expectException( AccionNoDisponibleException::class );

		$gestor->aprobarAhora( 11 );
	}

	public function test_aprobar_ahora_sobre_una_pieza_en_modo_autonomo_lanza_excepcion(): void {
		$pieza = $this->pieza( 12, EstadoPieza::Programada, $this->resultado( ModoOperacion::Autonomo ) );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorId' )->with( 12 )->andReturn( $pieza );

		$cola = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$cola->expects( 'obtenerProgramadaPorPieza' )->never();

		$gestor = new GestorSalaRevision( $piezas, $cola, Mockery::mock( RepositorioPeriodistasInterface::class ), new Transicionador( $piezas, Mockery::mock( RepositorioAuditoriaInterface::class ), new RelojFijo() ) );

		$this->expectException( AccionNoDisponibleException::class );

		$gestor->aprobarAhora( 12 );
	}

	public function test_aprobar_ahora_sobre_una_pieza_sin_ranura_lanza_excepcion(): void {
		$pieza = $this->pieza( 13, EstadoPieza::Programada, $this->resultado( ModoOperacion::Copiloto ) );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorId' )->with( 13 )->andReturn( $pieza );

		$cola = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$cola->expects( 'obtenerProgramadaPorPieza' )->with( 13 )->andReturn( null );

		$gestor = new GestorSalaRevision( $piezas, $cola, Mockery::mock( RepositorioPeriodistasInterface::class ), new Transicionador( $piezas, Mockery::mock( RepositorioAuditoriaInterface::class ), new RelojFijo() ) );

		$this->expectException( AccionNoDisponibleException::class );

		$gestor->aprobarAhora( 13 );
	}

	public function test_aprobar_ahora_sobre_una_pieza_inexistente_lanza_excepcion(): void {
		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorId' )->andReturn( null );

		$gestor = new GestorSalaRevision( $piezas, Mockery::mock( RepositorioColaPublicacionInterface::class ), Mockery::mock( RepositorioPeriodistasInterface::class ), new Transicionador( $piezas, Mockery::mock( RepositorioAuditoriaInterface::class ), new RelojFijo() ) );

		$this->expectException( PiezaNoEncontradaException::class );

		$gestor->aprobarAhora( 999 );
	}

	public function test_descartar_una_pieza_en_cola_de_veto_expira_su_ranura(): void {
		Functions\when( 'do_action' )->justReturn( null );

		$pieza  = $this->pieza( 7, EstadoPieza::Programada, $this->resultado( ModoOperacion::Copiloto ) );
		$ranura = new RanuraPublicacion( 9, 7, 'economia', 5, new DateTimeImmutable( '2026-07-22T09:00:00+00:00' ), EstadoColaPublicacion::Programada, false, new DateTimeImmutable( '2026-07-22T08:00:00+00:00' ) );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorId' )->with( 7 )->andReturn( $pieza );
		$piezas->expects( 'actualizarEstado' )->with( 7, EstadoPieza::Programada, EstadoPieza::Descartada, Mockery::any() )->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		$cola = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$cola->expects( 'obtenerProgramadaPorPieza' )->with( 7 )->andReturn( $ranura );
		$cola->expects( 'marcarExpirada' )->once()->with( 9 );

		$gestor = new GestorSalaRevision( $piezas, $cola, Mockery::mock( RepositorioPeriodistasInterface::class ), new Transicionador( $piezas, $auditoria, new RelojFijo() ) );

		$gestor->descartar( 7 );

		$this->expectNotToPerformAssertions();
	}

	public function test_descartar_una_pieza_inexistente_lanza_excepcion(): void {
		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorId' )->andReturn( null );

		$gestor = new GestorSalaRevision( $piezas, Mockery::mock( RepositorioColaPublicacionInterface::class ), Mockery::mock( RepositorioPeriodistasInterface::class ), new Transicionador( $piezas, Mockery::mock( RepositorioAuditoriaInterface::class ), new RelojFijo() ) );

		$this->expectException( PiezaNoEncontradaException::class );

		$gestor->descartar( 999 );
	}

	public function test_promover_periodista_propuesto_lo_activa_y_reanuda_solo_las_piezas_que_ya_cubre(): void {
		Functions\when( 'do_action' )->justReturn( null );

		$periodista = $this->periodistaPropuesto( 'deportes' );

		$piezaCubierta   = $this->piezaSinPeriodistaIdoneo( 50, 'deportes' );
		$piezaNoCubierta = $this->piezaSinPeriodistaIdoneo( 51, 'economia' );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorEstado' )->with( EstadoPieza::SinPeriodistaIdoneo, Mockery::any() )->andReturn( array( $piezaCubierta, $piezaNoCubierta ) );
		$piezas->allows( 'obtenerPorId' )->with( 50 )->andReturn( $piezaCubierta );
		$piezas->expects( 'actualizarEstado' )->once()->with( 50, EstadoPieza::SinPeriodistaIdoneo, EstadoPieza::Investigada, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarEstado' )->with( 51, Mockery::any(), Mockery::any(), Mockery::any() )->never();

		$periodistas = Mockery::mock( RepositorioPeriodistasInterface::class );
		$periodistas->allows( 'obtenerPorId' )->with( 30 )->andReturn( $periodista );
		$periodistas->expects( 'activarPropuesta' )->once()->with( 30, Mockery::any() )->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		$gestor = new GestorSalaRevision( $piezas, Mockery::mock( RepositorioColaPublicacionInterface::class ), $periodistas, new Transicionador( $piezas, $auditoria, new RelojFijo() ) );

		$gestor->promoverPeriodistaPropuesto( 30, ( new RelojFijo() )->ahora() );

		$this->expectNotToPerformAssertions();
	}

	public function test_promover_un_periodista_inexistente_lanza_excepcion(): void {
		$periodistas = Mockery::mock( RepositorioPeriodistasInterface::class );
		$periodistas->allows( 'obtenerPorId' )->andReturn( null );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$gestor = new GestorSalaRevision( $piezas, Mockery::mock( RepositorioColaPublicacionInterface::class ), $periodistas, new Transicionador( $piezas, Mockery::mock( RepositorioAuditoriaInterface::class ), new RelojFijo() ) );

		$this->expectException( PeriodistaNoEncontradoException::class );

		$gestor->promoverPeriodistaPropuesto( 999, ( new RelojFijo() )->ahora() );
	}

	public function test_promover_un_periodista_que_no_esta_propuesto_lanza_excepcion(): void {
		$reloj      = new RelojFijo();
		$diales     = new Diales( 60, 40, 20, 60, 50, 50, 60, 50 );
		$reglas     = new ReglasConducta( 'línea', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz     = MatrizTonos::desdeFilas( array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) ) );
		$conducta   = new ConductaVersion( 1, 31, $diales, $reglas, $matriz, $reloj->ahora() );
		$periodista = new Periodista( 31, 'Ya activo', null, 'bio', RolPeriodista::Cronista, array( new Especialidad( 'deportes', 4 ) ), EstadoPeriodista::Activo, $conducta, $reloj->ahora(), $reloj->ahora() );

		$periodistas = Mockery::mock( RepositorioPeriodistasInterface::class );
		$periodistas->allows( 'obtenerPorId' )->with( 31 )->andReturn( $periodista );
		$periodistas->expects( 'activarPropuesta' )->never();

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$gestor = new GestorSalaRevision( $piezas, Mockery::mock( RepositorioColaPublicacionInterface::class ), $periodistas, new Transicionador( $piezas, Mockery::mock( RepositorioAuditoriaInterface::class ), new RelojFijo() ) );

		$this->expectException( AccionNoDisponibleException::class );

		$gestor->promoverPeriodistaPropuesto( 31, $reloj->ahora() );
	}

	public function test_descartar_periodista_propuesto_delega_al_repositorio(): void {
		$periodista = $this->periodistaPropuesto();

		$periodistas = Mockery::mock( RepositorioPeriodistasInterface::class );
		$periodistas->allows( 'obtenerPorId' )->with( 30 )->andReturn( $periodista );
		$periodistas->expects( 'descartarPropuesta' )->once()->with( 30 )->andReturn( true );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$gestor = new GestorSalaRevision( $piezas, Mockery::mock( RepositorioColaPublicacionInterface::class ), $periodistas, new Transicionador( $piezas, Mockery::mock( RepositorioAuditoriaInterface::class ), new RelojFijo() ) );

		$gestor->descartarPeriodistaPropuesto( 30 );

		$this->expectNotToPerformAssertions();
	}

	public function test_descartar_un_periodista_inexistente_lanza_excepcion(): void {
		$periodistas = Mockery::mock( RepositorioPeriodistasInterface::class );
		$periodistas->allows( 'obtenerPorId' )->andReturn( null );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$gestor = new GestorSalaRevision( $piezas, Mockery::mock( RepositorioColaPublicacionInterface::class ), $periodistas, new Transicionador( $piezas, Mockery::mock( RepositorioAuditoriaInterface::class ), new RelojFijo() ) );

		$this->expectException( PeriodistaNoEncontradoException::class );

		$gestor->descartarPeriodistaPropuesto( 999 );
	}
}
