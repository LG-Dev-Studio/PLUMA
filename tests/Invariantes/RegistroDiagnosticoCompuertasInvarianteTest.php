<?php

declare(strict_types=1);

namespace Pluma\Tests\Invariantes;

use Brain\Monkey\Functions;
use Mockery;
use Pluma\Compuertas\ClasificadorGravedadTendencia;
use Pluma\Compuertas\CompuertaCalidad;
use Pluma\Compuertas\CompuertaOriginalidad;
use Pluma\Compuertas\CompuertaRiesgo;
use Pluma\Compuertas\EstadoModoRespeto;
use Pluma\Compuertas\EvaluadorCompuertas;
use Pluma\Compuertas\GestorDegradacion;
use Pluma\Compuertas\GestorModoRespeto;
use Pluma\Compuertas\ModoOperacion;
use Pluma\Compuertas\VerificadorLegibilidad;
use Pluma\Datos\CandadoGlobalInterface;
use Pluma\Datos\RepositorioAuditoriaInterface;
use Pluma\Datos\RepositorioBitacoraInterface;
use Pluma\Datos\RepositorioBorradoresInterface;
use Pluma\Datos\RepositorioColaPublicacionInterface;
use Pluma\Datos\RepositorioExperimentosTitularInterface;
use Pluma\Datos\RepositorioLlamadasModeloInterface;
use Pluma\Datos\RepositorioMemoriaEditorialInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Datos\RepositorioModoRespetoInterface;
use Pluma\Datos\RepositorioTendenciasInterface;
use Pluma\Datos\RepositorioVocabularioInterface;
use Pluma\Investigacion\DetectorHuecos;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\InvestigadorInterface;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Investigacion\DetectorContradiccionesNli;
use Pluma\Investigacion\OrdenadorHechosPorRelevancia;
use Pluma\Investigacion\ResolutorDisputas;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\GestorSalaRevision;
use Pluma\Pipeline\LectorConfiguracionCadencia;
use Pluma\Pipeline\Orquestador;
use Pluma\Pipeline\Pieza;
use Pluma\Pipeline\ProgramadorCadencia;
use Pluma\Pipeline\Transicionador;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\ProveedorCerebroRemoto;
use Pluma\Proveedores\ProveedorNliCerebroRemoto;
use Pluma\Proveedores\ProveedorRerankCerebroRemoto;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Publicacion\AsignadorImagenDestacadaInterface;
use Pluma\Publicacion\CreadorBorradorInterface;
use Pluma\Publicacion\PublicadorInterface;
use Pluma\Redaccion\AgrupadorTemasSinCobertura;
use Pluma\Redaccion\AnotacionCorrector;
use Pluma\Redaccion\CreadorAutomaticoPeriodistas;
use Pluma\Redaccion\Borrador;
use Pluma\Redaccion\CandidatoTesis;
use Pluma\Redaccion\ClasificacionNoticia;
use Pluma\Redaccion\EsqueletoPieza;
use Pluma\Redaccion\FichaDecisionEditorial;
use Pluma\Redaccion\GeneradorTitularAlternativo;
use Pluma\Redaccion\NovedadNoticia;
use Pluma\Redaccion\PuntoCorrector;
use Pluma\Redaccion\RedactorInterface;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Sensores\ComparadorHistorias;
use Pluma\Datos\RepositorioHistoriasInterface;
use Pluma\Pipeline\GestorHistorias;
use Pluma\Sensores\EvaluadorLegitimidadInsumo;
use Pluma\Sensores\SensorInterface;
use Pluma\Seo\AuditorCanibalizacion;
use Pluma\Seo\DetectorPluginSeo;
use Pluma\Seo\EnlazadorInterno;
use Pluma\Seo\ExtractorPalabrasClave;
use Pluma\Seo\GeneradorMetadatosSeo;
use Pluma\Seo\GestorExperimentosTitular;
use Pluma\Seo\MotorSeo;
use Pluma\Taxonomia\AsignadorCategoria;
use Pluma\Taxonomia\ExtractorEntidades;
use Pluma\Taxonomia\GestorEtiquetas;
use Pluma\Taxonomia\ReconciliadorVocabulario;
use Pluma\Taxonomia\Taxonomo;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\AzarFijo;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;
use Pluma\Tests\Unit\Dobles\RelojFijo;
use DateTimeImmutable;

/**
 * GOVERNANCE §2.1 — "toda decisión de compuerta (puntuaciones,
 * degradaciones, retenciones y sus motivos) se escribe en el expediente de
 * la Pieza" (Libro Cap. 8.4) y en `pluma_auditoria` vía `Transicionador`.
 * "El panel muestra por qué cada pieza salió, se retuvo o se degradó. Sin
 * esto, el sistema es una caja negra imposible de calibrar y de defender."
 *
 * Si este test se pone en rojo, una pieza podría atravesar la Compuerta de
 * Riesgo/Calidad/Originalidad sin dejar rastro verificable de la decisión —
 * exactamente la caja negra que este principio prohíbe.
 *
 * @covers \Pluma\Pipeline\Orquestador
 */
final class RegistroDiagnosticoCompuertasInvarianteTest extends CasoDePruebaUnitario {

	private function ficha(): FichaDecisionEditorial {
		return new FichaDecisionEditorial(
			5,
			1,
			new ClasificacionNoticia( 'economia', 30, 'x', NovedadNoticia::Primicia, 50, TipoNoticia::DatoEconomico ),
			array( new CandidatoTesis( 'la tesis elegida', 80.0, 80.0, 80.0, 80.0 ) ),
			0,
			Tono::Analitico,
			Tono::Persuasivo,
			new EsqueletoPieza( 'gancho', 'hechos', array( 'm1' ), 'contra', 'remate' ),
			new DateTimeImmutable( '2026-07-22T12:00:00+00:00' )
		);
	}

	public function test_toda_evaluacion_de_compuertas_persiste_su_diagnostico_antes_de_transicionar(): void {
		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( 'get_posts' )->justReturn( array() );
		Functions\when( 'get_post_field' )->justReturn( '' );
		Functions\when( 'do_action' )->justReturn( null );

		$expediente      = new Expediente(
			'x',
			array( new HechoFuente( 'un hecho', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ) )
		);
		$ficha           = $this->ficha();
		$reloj           = new RelojFijo();
		$piezaOptimizada = new Pieza( 30, 100, EstadoPieza::Optimizada, $expediente, null, $reloj->ahora(), $reloj->ahora(), 5, null, $ficha );

		// Riesgo de difamación: la pieza DEBE retenerse. La invariante bajo
		// prueba es que, aun así, el diagnóstico se persiste — retener no es
		// excusa para dejar de auditar.
		$proveedorRiesgo = new ProveedorLenguajeFalso(
			'{"implicaMenores": false, "implicaSalud": false, "implicaViolencia": false, "riesgoDifamacion": true, "detalleDifamacion": "acusación sin doble fuente", "hechosDisputadosSinSenalar": false, "temaRegulado": null, "afirmacionNegativaSobrePersonaIdentificable": false, "posturaSenaladoAusente": false}'
		);

		$piezaEnRevision = new Pieza( 30, 100, EstadoPieza::EnRevision, $expediente, null, $reloj->ahora(), $reloj->ahora(), 5, null, $ficha );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorEstado' )->with( EstadoPieza::Optimizada, Mockery::any() )->andReturn( array( $piezaOptimizada ) );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		// Dos transiciones reales: Optimizada→EnRevision, y (riesgo de
		// difamación) EnRevision→Retenida — cada una lee el estado ACTUAL.
		$piezas->expects( 'obtenerPorId' )->with( 30 )->twice()->andReturn( $piezaOptimizada, $piezaEnRevision );
		$piezas->expects( 'actualizarEstado' )->with( 30, EstadoPieza::Optimizada, EstadoPieza::EnRevision, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarEstado' )->with( 30, EstadoPieza::EnRevision, EstadoPieza::Retenida, Mockery::any() )->andReturn( true );

		// La invariante misma: el diagnóstico se persiste sin importar el resultado.
		$piezas->expects( 'actualizarResultadoCompuertas' )->once();

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		// GOVERNANCE §2.1: cada transición queda auditada con su motivo —
		// incluida la retención, que es precisamente la decisión que más
		// necesita quedar registrada.
		$auditoria->expects( 'registrar' )->with( 30, EstadoPieza::Optimizada, EstadoPieza::EnRevision, Mockery::any(), Mockery::any(), Mockery::any(), null )->once();
		$auditoria->expects( 'registrar' )->with( 30, EstadoPieza::EnRevision, EstadoPieza::Retenida, Mockery::any(), Mockery::any(), Mockery::any(), null )->once();

		$borrador = new Borrador(
			1,
			30,
			1,
			'El banco central subió la tasa de interés al nueve por ciento este martes.',
			array(
				new AnotacionCorrector( PuntoCorrector::Hechos, true, 'ok' ),
				new AnotacionCorrector( PuntoCorrector::ProporcionInterpretativa, true, 'ok' ),
				new AnotacionCorrector( PuntoCorrector::Voz, true, 'ok' ),
			),
			true,
			new DateTimeImmutable( '2026-07-22T12:00:00+00:00' )
		);

		$borradores = Mockery::mock( RepositorioBorradoresInterface::class );
		$borradores->allows( 'obtenerUltimo' )->andReturn( $borrador );

		$candado = Mockery::mock( CandadoGlobalInterface::class );
		$candado->allows( 'adquirir' )->andReturn( true );
		$candado->allows( 'liberar' );

		$bitacora = Mockery::mock( RepositorioBitacoraInterface::class );
		$bitacora->allows( 'iniciarEjecucion' )->andReturn( 1 );
		$bitacora->allows( 'finalizarEjecucion' );

		$sensor = Mockery::mock( SensorInterface::class );
		$sensor->allows( 'detectar' )->andReturn( array() );

		$colaPublicacion = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$colaPublicacion->allows( 'obtenerVencidas' )->andReturn( array() );
		$colaPublicacion->allows( 'obtenerEntre' )->andReturn( array() );

		$orquestador = new Orquestador(
			$candado,
			$bitacora,
			$piezas,
			Mockery::mock( RepositorioTendenciasInterface::class ),
			$borradores,
			$colaPublicacion,
			new Transicionador( $piezas, $auditoria, new RelojFijo() ),
			$sensor,
			Mockery::mock( InvestigadorInterface::class ),
			Mockery::mock( RedactorInterface::class ),
			new MotorSeo(
				new ExtractorPalabrasClave(),
				new GeneradorMetadatosSeo( new ProveedorLenguajeFalso( '{"tituloSeo": "t", "metaDescripcion": "d"}' ) ),
				new DetectorPluginSeo(),
				new EnlazadorInterno( Mockery::mock( RepositorioMemoriaEditorialInterface::class )->allows( 'obtenerPosturasPorTema' )->andReturn( array() )->getMock(), Mockery::mock( RepositorioPiezasInterface::class ) ),
				new AuditorCanibalizacion( Mockery::mock( RepositorioPiezasInterface::class )->allows( 'existePiezaPublicadaConKeyword' )->andReturn( false )->getMock() )
			),
			new Taxonomo(
				new AsignadorCategoria( new ReconciliadorVocabulario(), Mockery::mock( RepositorioVocabularioInterface::class )->allows( 'obtenerPorTipo' )->andReturn( array() )->getMock() ),
				new GestorEtiquetas( new ExtractorEntidades(), new ReconciliadorVocabulario(), Mockery::mock( RepositorioVocabularioInterface::class )->allows( 'obtenerPorTipo' )->andReturn( array() )->getMock(), new RelojFijo() )
			),
			new EvaluadorCompuertas(
				new CompuertaCalidad( new VerificadorLegibilidad() ),
				new CompuertaRiesgo( $proveedorRiesgo ),
				new CompuertaOriginalidad(),
				new GestorDegradacion()
			),
			new LectorConfiguracionCadencia(),
			new ProgramadorCadencia( new AzarFijo( 0 ) ),
			Mockery::mock( CreadorBorradorInterface::class ),
			Mockery::mock( PublicadorInterface::class ),
			new ComparadorHistorias( Mockery::mock( LenguajeInterface::class ), new PresupuestoLenguaje( new RelojFijo() ) ),
			Mockery::mock( RepositorioPeriodistasInterface::class )->allows( 'obtenerPropuestos' )->andReturn( array() )->getMock(),
			new RelojFijo(),
			new ResolutorDisputas(
				Mockery::mock( LenguajeInterface::class ),
				new DetectorContradiccionesNli( new ProveedorNliCerebroRemoto( new ProveedorCerebroRemoto() ) )
			),
			new DetectorHuecos( Mockery::mock( LenguajeInterface::class ) ),
			new OrdenadorHechosPorRelevancia( new ProveedorRerankCerebroRemoto( new ProveedorCerebroRemoto() ) ),
			new ClasificadorGravedadTendencia( Mockery::mock( LenguajeInterface::class ) ),
			new GestorModoRespeto(
				Mockery::mock( RepositorioModoRespetoInterface::class )->allows( 'estadoActual' )->andReturn( EstadoModoRespeto::inactivo() )->getMock(),
				Mockery::mock( RepositorioTendenciasInterface::class ),
				$colaPublicacion,
				new ProgramadorCadencia( new AzarFijo( 0 ) ),
				new LectorConfiguracionCadencia()
			),
			Mockery::mock( AsignadorImagenDestacadaInterface::class )->allows( 'asignar' )->getMock(),
			new EvaluadorLegitimidadInsumo(),
			new GestorHistorias(
				Mockery::mock( RepositorioHistoriasInterface::class )->allows( 'obtenerAbiertasSinActividadDesde' )->andReturn( array() )->getMock(),
				$piezas,
				new RelojFijo()
			),
			new GestorExperimentosTitular(
				Mockery::mock( RepositorioExperimentosTitularInterface::class )->allows( 'obtenerListosParaConsolidar' )->andReturn( array() )->getMock(),
				$piezas,
				Mockery::mock( RepositorioPeriodistasInterface::class ),
				new GeneradorTitularAlternativo( Mockery::mock( LenguajeInterface::class ) ),
				new RelojFijo()
			),
			new CreadorAutomaticoPeriodistas(
				$piezas,
				Mockery::mock( RepositorioPeriodistasInterface::class ),
				new AgrupadorTemasSinCobertura( Mockery::mock( LenguajeInterface::class ), new PresupuestoLenguaje( new RelojFijo() ) ),
				new RelojFijo()
			),
			new GestorSalaRevision(
				$piezas,
				$colaPublicacion,
				Mockery::mock( RepositorioPeriodistasInterface::class )->allows( 'obtenerPropuestos' )->andReturn( array() )->getMock(),
				new Transicionador( $piezas, $auditoria, new RelojFijo() )
			),
			Mockery::mock( RepositorioLlamadasModeloInterface::class )->allows( 'purgarAnterioresA' )->andReturn( 0 )->getMock()
		);

		$orquestador->ejecutarTick();

		$this->expectNotToPerformAssertions();
	}
}
