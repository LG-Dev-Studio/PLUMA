<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Compuertas;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Compuertas\ActivadorModoRespeto;
use Pluma\Compuertas\EstadoModoRespeto;
use Pluma\Compuertas\GestorModoRespeto;
use Pluma\Compuertas\ModoRespetoAunNoDesactivableException;
use Pluma\Datos\RepositorioColaPublicacionInterface;
use Pluma\Datos\RepositorioModoRespetoInterface;
use Pluma\Datos\RepositorioTendenciasInterface;
use Pluma\Kernel\AzarInterface;
use Pluma\Pipeline\EstadoColaPublicacion;
use Pluma\Pipeline\LectorConfiguracionCadencia;
use Pluma\Pipeline\ProgramadorCadencia;
use Pluma\Pipeline\RanuraPublicacion;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * Nivel Dos F.1-F.3: disparador de dos niveles (automático por coincidencia
 * de campo temático/geográfico, o manual con un clic), y desactivación
 * bloqueada mientras no se cumpla el piso de duración mínima.
 *
 * @covers \Pluma\Compuertas\GestorModoRespeto
 */
final class GestorModoRespetoTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( false );
	}

	private function ahora(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-07-27T12:00:00+00:00' );
	}

	private function colaPublicacionPermisiva(): RepositorioColaPublicacionInterface {
		$cola = $this->createMock( RepositorioColaPublicacionInterface::class );
		$cola->method( 'obtenerPausadas' )->willReturn( array() );

		return $cola;
	}

	private function programadorCadencia(): ProgramadorCadencia {
		$azar = $this->createMock( AzarInterface::class );
		$azar->method( 'entero' )->willReturn( 0 );

		return new ProgramadorCadencia( $azar );
	}

	public function test_no_reevalua_el_disparador_si_ya_esta_activo(): void {
		$repo = $this->createMock( RepositorioModoRespetoInterface::class );
		$repo->method( 'estadoActual' )->willReturn(
			new EstadoModoRespeto( true, $this->ahora(), ActivadorModoRespeto::Manual, 'ya activo', $this->ahora() )
		);
		$repo->expects( self::never() )->method( 'activar' );

		$tendencias = $this->createMock( RepositorioTendenciasInterface::class );
		$tendencias->expects( self::never() )->method( 'obtenerGravedadMaximaRecientes' );

		( new GestorModoRespeto( $repo, $tendencias, $this->colaPublicacionPermisiva(), $this->programadorCadencia(), new LectorConfiguracionCadencia() ) )->evaluarDisparadorAutomatico( $this->ahora() );
	}

	public function test_activa_automaticamente_cuando_dos_tendencias_comparten_campo_tematico(): void {
		$repo = $this->createMock( RepositorioModoRespetoInterface::class );
		$repo->method( 'estadoActual' )->willReturn( EstadoModoRespeto::inactivo() );
		$repo->expects( self::once() )->method( 'activar' )->with( ActivadorModoRespeto::Automatico, self::isType( 'string' ), self::isType( 'float' ), $this->ahora() );

		$tendencias = $this->createMock( RepositorioTendenciasInterface::class );
		$tendencias->method( 'obtenerGravedadMaximaRecientes' )->willReturn(
			array(
				array(
					'id'              => 1,
					'campoTematico'   => 'atentado',
					'campoGeografico' => null,
				),
				array(
					'id'              => 2,
					'campoTematico'   => 'atentado',
					'campoGeografico' => null,
				),
			)
		);

		( new GestorModoRespeto( $repo, $tendencias, $this->colaPublicacionPermisiva(), $this->programadorCadencia(), new LectorConfiguracionCadencia() ) )->evaluarDisparadorAutomatico( $this->ahora() );
	}

	public function test_activa_automaticamente_cuando_dos_tendencias_comparten_campo_geografico_sin_compartir_tema(): void {
		$repo = $this->createMock( RepositorioModoRespetoInterface::class );
		$repo->method( 'estadoActual' )->willReturn( EstadoModoRespeto::inactivo() );
		$repo->expects( self::once() )->method( 'activar' );

		$tendencias = $this->createMock( RepositorioTendenciasInterface::class );
		$tendencias->method( 'obtenerGravedadMaximaRecientes' )->willReturn(
			array(
				array(
					'id'              => 1,
					'campoTematico'   => 'desastre_natural',
					'campoGeografico' => 'Japón',
				),
				array(
					'id'              => 2,
					'campoTematico'   => 'accidente_industrial',
					'campoGeografico' => 'Japón',
				),
			)
		);

		( new GestorModoRespeto( $repo, $tendencias, $this->colaPublicacionPermisiva(), $this->programadorCadencia(), new LectorConfiguracionCadencia() ) )->evaluarDisparadorAutomatico( $this->ahora() );
	}

	public function test_no_activa_con_una_sola_tendencia_de_gravedad_maxima(): void {
		$repo = $this->createMock( RepositorioModoRespetoInterface::class );
		$repo->method( 'estadoActual' )->willReturn( EstadoModoRespeto::inactivo() );
		$repo->expects( self::never() )->method( 'activar' );

		$tendencias = $this->createMock( RepositorioTendenciasInterface::class );
		$tendencias->method( 'obtenerGravedadMaximaRecientes' )->willReturn(
			array(
				array(
					'id'              => 1,
					'campoTematico'   => 'atentado',
					'campoGeografico' => null,
				),
			)
		);

		( new GestorModoRespeto( $repo, $tendencias, $this->colaPublicacionPermisiva(), $this->programadorCadencia(), new LectorConfiguracionCadencia() ) )->evaluarDisparadorAutomatico( $this->ahora() );
	}

	public function test_activar_manualmente_activa_con_el_motivo_dado(): void {
		$repo = $this->createMock( RepositorioModoRespetoInterface::class );
		$repo->expects( self::exactly( 2 ) )->method( 'estadoActual' )->willReturnOnConsecutiveCalls(
			EstadoModoRespeto::inactivo(),
			new EstadoModoRespeto( true, $this->ahora(), ActivadorModoRespeto::Manual, 'motivo del editor', $this->ahora() )
		);
		$repo->expects( self::once() )->method( 'activar' )->with( ActivadorModoRespeto::Manual, 'motivo del editor', self::isType( 'float' ), $this->ahora() );

		$tendencias = $this->createMock( RepositorioTendenciasInterface::class );

		$estado = ( new GestorModoRespeto( $repo, $tendencias, $this->colaPublicacionPermisiva(), $this->programadorCadencia(), new LectorConfiguracionCadencia() ) )->activarManualmente( 'motivo del editor', $this->ahora() );

		self::assertTrue( $estado->activo );
	}

	public function test_activar_manualmente_es_idempotente_si_ya_esta_activo(): void {
		$estadoYaActivo = new EstadoModoRespeto( true, $this->ahora(), ActivadorModoRespeto::Automatico, 'ya activo', $this->ahora() );

		$repo = $this->createMock( RepositorioModoRespetoInterface::class );
		$repo->method( 'estadoActual' )->willReturn( $estadoYaActivo );
		$repo->expects( self::never() )->method( 'activar' );

		$tendencias = $this->createMock( RepositorioTendenciasInterface::class );

		( new GestorModoRespeto( $repo, $tendencias, $this->colaPublicacionPermisiva(), $this->programadorCadencia(), new LectorConfiguracionCadencia() ) )->activarManualmente( 'otro motivo', $this->ahora() );
	}

	public function test_desactivar_lanza_excepcion_si_el_piso_no_se_cumplio(): void {
		$puedeDesactivarseDesde = $this->ahora()->modify( '+2 hours' );

		$repo = $this->createMock( RepositorioModoRespetoInterface::class );
		$repo->method( 'estadoActual' )->willReturn(
			new EstadoModoRespeto( true, $this->ahora(), ActivadorModoRespeto::Manual, 'x', $puedeDesactivarseDesde )
		);
		$repo->expects( self::never() )->method( 'desactivar' );

		$tendencias = $this->createMock( RepositorioTendenciasInterface::class );

		$this->expectException( ModoRespetoAunNoDesactivableException::class );

		( new GestorModoRespeto( $repo, $tendencias, $this->colaPublicacionPermisiva(), $this->programadorCadencia(), new LectorConfiguracionCadencia() ) )->desactivar( $this->ahora() );
	}

	public function test_desactivar_funciona_una_vez_cumplido_el_piso(): void {
		$puedeDesactivarseDesde = $this->ahora()->modify( '-1 minute' );

		$repo = $this->createMock( RepositorioModoRespetoInterface::class );
		$repo->method( 'estadoActual' )->willReturn(
			new EstadoModoRespeto( true, $this->ahora(), ActivadorModoRespeto::Manual, 'x', $puedeDesactivarseDesde )
		);
		$repo->expects( self::once() )->method( 'desactivar' )->with( $this->ahora() );

		$tendencias = $this->createMock( RepositorioTendenciasInterface::class );

		( new GestorModoRespeto( $repo, $tendencias, $this->colaPublicacionPermisiva(), $this->programadorCadencia(), new LectorConfiguracionCadencia() ) )->desactivar( $this->ahora() );
	}

	public function test_desactivar_sin_estar_activo_es_un_no_op(): void {
		$repo = $this->createMock( RepositorioModoRespetoInterface::class );
		$repo->method( 'estadoActual' )->willReturn( EstadoModoRespeto::inactivo() );
		$repo->expects( self::never() )->method( 'desactivar' );

		$tendencias = $this->createMock( RepositorioTendenciasInterface::class );

		( new GestorModoRespeto( $repo, $tendencias, $this->colaPublicacionPermisiva(), $this->programadorCadencia(), new LectorConfiguracionCadencia() ) )->desactivar( $this->ahora() );
	}

	/**
	 * Nivel Dos F.3: al activarse automáticamente, pausa toda la cola —
	 * nunca la descarta.
	 */
	public function test_activar_automaticamente_pausa_la_cola_de_publicacion(): void {
		$repo = $this->createMock( RepositorioModoRespetoInterface::class );
		$repo->method( 'estadoActual' )->willReturn( EstadoModoRespeto::inactivo() );
		$repo->expects( self::once() )->method( 'activar' );

		$tendencias = $this->createMock( RepositorioTendenciasInterface::class );
		$tendencias->method( 'obtenerGravedadMaximaRecientes' )->willReturn(
			array(
				array(
					'id'              => 1,
					'campoTematico'   => 'atentado',
					'campoGeografico' => null,
				),
				array(
					'id'              => 2,
					'campoTematico'   => 'atentado',
					'campoGeografico' => null,
				),
			)
		);

		$cola = $this->createMock( RepositorioColaPublicacionInterface::class );
		$cola->expects( self::once() )->method( 'pausarProgramadas' );

		( new GestorModoRespeto( $repo, $tendencias, $cola, $this->programadorCadencia(), new LectorConfiguracionCadencia() ) )->evaluarDisparadorAutomatico( $this->ahora() );
	}

	/**
	 * Nivel Dos F.3: al activarse manualmente, también pausa la cola —
	 * ambos caminos (automático y manual) convergen en el mismo efecto.
	 */
	public function test_activar_manualmente_pausa_la_cola_de_publicacion(): void {
		$repo = $this->createMock( RepositorioModoRespetoInterface::class );
		$repo->method( 'estadoActual' )->willReturn( EstadoModoRespeto::inactivo() );

		$tendencias = $this->createMock( RepositorioTendenciasInterface::class );

		$cola = $this->createMock( RepositorioColaPublicacionInterface::class );
		$cola->expects( self::once() )->method( 'pausarProgramadas' );

		( new GestorModoRespeto( $repo, $tendencias, $cola, $this->programadorCadencia(), new LectorConfiguracionCadencia() ) )->activarManualmente( 'motivo', $this->ahora() );
	}

	/**
	 * Nivel Dos F.3: al desactivarse, reactiva completa la cola pausada,
	 * conservando la franja horaria de cada ranura y recalculando el
	 * jitter (verificado en `ProgramadorCadenciaTest`).
	 */
	public function test_desactivar_reactiva_las_ranuras_pausadas_con_nueva_hora(): void {
		$puedeDesactivarseDesde = $this->ahora()->modify( '-1 minute' );

		$repo = $this->createMock( RepositorioModoRespetoInterface::class );
		$repo->method( 'estadoActual' )->willReturn(
			new EstadoModoRespeto( true, $this->ahora(), ActivadorModoRespeto::Manual, 'x', $puedeDesactivarseDesde )
		);

		$tendencias = $this->createMock( RepositorioTendenciasInterface::class );

		$ranuraPausada = new RanuraPublicacion( 42, 7, 'economia', 3, $this->ahora(), EstadoColaPublicacion::Pausada, false, $this->ahora() );

		$cola = $this->createMock( RepositorioColaPublicacionInterface::class );
		$cola->method( 'obtenerPausadas' )->willReturn( array( $ranuraPausada ) );
		$cola->expects( self::once() )->method( 'reprogramar' )->with( 42, self::isInstanceOf( DateTimeImmutable::class ) );

		( new GestorModoRespeto( $repo, $tendencias, $cola, $this->programadorCadencia(), new LectorConfiguracionCadencia() ) )->desactivar( $this->ahora() );
	}
}
