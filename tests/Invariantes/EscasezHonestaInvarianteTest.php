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
use Pluma\Compuertas\VerificadorLegibilidad;
use Pluma\Datos\CandadoGlobalInterface;
use Pluma\Datos\RepositorioAuditoriaInterface;
use Pluma\Datos\RepositorioBitacoraInterface;
use Pluma\Datos\RepositorioBorradoresInterface;
use Pluma\Datos\RepositorioColaPublicacionInterface;
use Pluma\Datos\RepositorioExperimentosTitularInterface;
use Pluma\Datos\RepositorioMemoriaEditorialInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Datos\RepositorioRespuestasComentariosInterface;
use Pluma\Datos\RepositorioModoRespetoInterface;
use Pluma\Datos\RepositorioTendenciasInterface;
use Pluma\Datos\RepositorioVocabularioInterface;
use Pluma\Investigacion\DetectorHuecos;
use Pluma\Investigacion\InvestigadorInterface;
use Pluma\Investigacion\ResolutorDisputas;
use Pluma\Pipeline\LectorConfiguracionCadencia;
use Pluma\Pipeline\Orquestador;
use Pluma\Pipeline\ProgramadorCadencia;
use Pluma\Pipeline\Transicionador;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Publicacion\AsignadorImagenDestacadaInterface;
use Pluma\Publicacion\CreadorBorradorInterface;
use Pluma\Publicacion\LectorComentariosInterface;
use Pluma\Publicacion\PublicadorInterface;
use Pluma\Redaccion\AnalizadorAudiencia;
use Pluma\Redaccion\GeneradorRespuestaComentario;
use Pluma\Redaccion\GeneradorTitularAlternativo;
use Pluma\Redaccion\RedactorInterface;
use Pluma\Redaccion\VerificadorComentarioSustantivo;
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

/**
 * GOVERNANCE §2.7 — "escasez honesta: si no hay piezas aprobadas
 * suficientes para la cuota, el sistema NO rebaja los umbrales para
 * rellenar. Registra el déficit, lo notifica." CLAUDE.md § Contrato del
 * Orquestador: "no existe ninguna ruta de código que publique una Pieza con
 * puntuación bajo umbral."
 *
 * Si este test se pone en rojo, el Orquestador podría estar silenciando un
 * déficit de cuota en vez de reportarlo, o (peor) tocando los umbrales de
 * las Compuertas para rellenar — exactamente lo que esta regla prohíbe.
 *
 * @covers \Pluma\Pipeline\Orquestador
 */
final class EscasezHonestaInvarianteTest extends CasoDePruebaUnitario {

	public function test_un_deficit_de_cuota_se_registra_sin_tocar_los_umbrales_de_compuertas(): void {
		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( 'get_posts' )->justReturn( array() );
		Functions\when( 'get_post_field' )->justReturn( '' );
		Functions\when( 'sanitize_title' )->alias( static fn ( string $t ): string => strtolower( $t ) );
		Functions\when( 'do_action' )->justReturn( null );

		$candado = Mockery::mock( CandadoGlobalInterface::class );
		$candado->allows( 'adquirir' )->andReturn( true );
		$candado->allows( 'liberar' );

		$bitacora = Mockery::mock( RepositorioBitacoraInterface::class );
		$bitacora->allows( 'iniciarEjecucion' )->andReturn( 1 );
		$bitacora->allows( 'finalizarEjecucion' );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );

		$sensor = Mockery::mock( SensorInterface::class );
		$sensor->allows( 'detectar' )->andReturn( array() );

		$colaPublicacion = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$colaPublicacion->allows( 'obtenerVencidas' )->andReturn( array() );
		// Cola vacía todo el día: 0 piezas comprometidas hoy, por debajo de
		// cualquier cuota mínima > 0 (piso de fábrica: 3) — dispara el déficit.
		$colaPublicacion->allows( 'obtenerEntre' )->andReturn( array() );

		// Ninguna opción de umbral de Compuertas (ni ninguna otra) debe
		// escribirse jamás desde el Orquestador, ni siquiera ante un déficit
		// de cuota detectado: "escasez honesta" prohíbe rebajar umbrales
		// para rellenar la cuota, y el Orquestador no tiene ninguna ruta de
		// código que llame `update_option`/`add_option` en absoluto.
		Functions\expect( 'update_option' )->never();
		Functions\expect( 'add_option' )->never();

		$orquestador = new Orquestador(
			$candado,
			$bitacora,
			$piezas,
			Mockery::mock( RepositorioTendenciasInterface::class ),
			Mockery::mock( RepositorioBorradoresInterface::class ),
			$colaPublicacion,
			new Transicionador( $piezas, Mockery::mock( RepositorioAuditoriaInterface::class )->allows( 'registrar' )->getMock(), new RelojFijo() ),
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
				new CompuertaRiesgo( new ProveedorLenguajeFalso( '{"implicaMenores": false, "implicaSalud": false, "implicaViolencia": false, "riesgoDifamacion": false, "detalleDifamacion": "", "hechosDisputadosSinSenalar": false, "temaRegulado": null, "afirmacionNegativaSobrePersonaIdentificable": false, "posturaSenaladoAusente": false}' ) ),
				new CompuertaOriginalidad(),
				new GestorDegradacion()
			),
			new LectorConfiguracionCadencia(),
			new ProgramadorCadencia( new AzarFijo( 0 ) ),
			Mockery::mock( CreadorBorradorInterface::class ),
			Mockery::mock( PublicadorInterface::class ),
			new ComparadorHistorias( Mockery::mock( LenguajeInterface::class ), new PresupuestoLenguaje( new RelojFijo() ) ),
			Mockery::mock( LectorComentariosInterface::class )->allows( 'obtenerAprobadosDe' )->andReturn( array() )->getMock(),
			new AnalizadorAudiencia( Mockery::mock( LenguajeInterface::class ), new PresupuestoLenguaje( new RelojFijo() ) ),
			new GeneradorRespuestaComentario( Mockery::mock( LenguajeInterface::class ) ),
			new VerificadorComentarioSustantivo(),
			Mockery::mock( RepositorioMemoriaEditorialInterface::class ),
			Mockery::mock( RepositorioRespuestasComentariosInterface::class ),
			Mockery::mock( RepositorioPeriodistasInterface::class ),
			new RelojFijo(),
			new ResolutorDisputas( Mockery::mock( LenguajeInterface::class ) ),
			new DetectorHuecos( Mockery::mock( LenguajeInterface::class ) ),
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
			)
		);

		$resultado = $orquestador->ejecutarTick();

		self::assertNotEmpty(
			array_filter( $resultado['errores'], static fn ( string $e ): bool => str_contains( $e, 'Escasez honesta' ) ),
			'Un déficit de cuota debe registrarse explícitamente en la bitácora, nunca silenciarse.'
		);
	}
}
