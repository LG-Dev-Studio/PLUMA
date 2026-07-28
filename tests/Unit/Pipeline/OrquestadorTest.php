<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Pipeline;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Mockery;
use Pluma\Compuertas\ClasificadorGravedadTendencia;
use Pluma\Compuertas\CompuertaCalidad;
use Pluma\Compuertas\CompuertaOriginalidad;
use Pluma\Compuertas\CompuertaRiesgo;
use Pluma\Compuertas\DiagnosticoCalidad;
use Pluma\Compuertas\DiagnosticoOriginalidad;
use Pluma\Compuertas\DiagnosticoRiesgo;
use Pluma\Compuertas\EstadoModoRespeto;
use Pluma\Compuertas\EvaluadorCompuertas;
use Pluma\Compuertas\GestorDegradacion;
use Pluma\Compuertas\GestorModoRespeto;
use Pluma\Compuertas\ModoOperacion;
use Pluma\Compuertas\ResultadoEvaluacion;
use Pluma\Compuertas\VerificadorLegibilidad;
use Pluma\Datos\CandadoGlobalInterface;
use Pluma\Datos\RepositorioAuditoriaInterface;
use Pluma\Datos\RepositorioBitacoraInterface;
use Pluma\Datos\RepositorioBorradoresInterface;
use Pluma\Datos\RepositorioColaPublicacionInterface;
use Pluma\Datos\RepositorioMemoriaEditorialInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Datos\RepositorioRespuestasComentariosInterface;
use Pluma\Datos\RepositorioModoRespetoInterface;
use Pluma\Datos\RepositorioTendenciasInterface;
use Pluma\Datos\RepositorioVocabularioInterface;
use Pluma\Investigacion\DetectorHuecos;
use Pluma\Investigacion\DimensionEncuadre;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\InvestigadorInterface;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Investigacion\ResolutorDisputas;
use Pluma\Pipeline\EstadoColaPublicacion;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\LectorConfiguracionCadencia;
use Pluma\Pipeline\Orquestador;
use Pluma\Pipeline\Pieza;
use Pluma\Pipeline\ProgramadorCadencia;
use Pluma\Pipeline\RanuraPublicacion;
use Pluma\Pipeline\TipoAprobacion;
use Pluma\Pipeline\Transicionador;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Proveedores\ProveedorTendenciasException;
use Pluma\Publicacion\ComentarioWordPress;
use Pluma\Publicacion\CreadorBorradorInterface;
use Pluma\Publicacion\LectorComentariosInterface;
use Pluma\Publicacion\PublicacionException;
use Pluma\Publicacion\PublicadorInterface;
use Pluma\Publicacion\SnapshotPublicacion;
use Pluma\Redaccion\AnalizadorAudiencia;
use Pluma\Redaccion\AnotacionCorrector;
use Pluma\Redaccion\Borrador;
use Pluma\Redaccion\CandidatoTesis;
use Pluma\Redaccion\ClasificacionNoticia;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EsqueletoPieza;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\EstadoRespuestaComentario;
use Pluma\Redaccion\FichaDecisionEditorial;
use Pluma\Redaccion\GeneradorRespuestaComentario;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\NovedadNoticia;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\PuntoCorrector;
use Pluma\Redaccion\RedactorInterface;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\ResultadoRedaccion;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoMemoria;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Redaccion\VerificadorComentarioSustantivo;
use Pluma\Sensores\ComparadorHistorias;
use Pluma\Sensores\PuntuacionOportunidad;
use Pluma\Sensores\SensorInterface;
use Pluma\Sensores\TendenciaDetectada;
use Pluma\Seo\AuditorCanibalizacion;
use Pluma\Seo\DetectorPluginSeo;
use Pluma\Seo\EnlazadorInterno;
use Pluma\Seo\ExtractorPalabrasClave;
use Pluma\Seo\GeneradorMetadatosSeo;
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
 * `MotorSeo`/`Taxonomo`/`EvaluadorCompuertas` son clases `final` (mismo
 * criterio que en sus propios tests): las instancias que este test construye
 * son REALES, con sub-dependencias mínimas (dobles de proveedor de lenguaje,
 * repos-interfaz mockeados) — ningún test que no ejercite Optimización/
 * Compuertas realmente las invoca, así que su comportamiento interno es
 * irrelevante salvo en los tests dedicados a esas etapas.
 *
 * @covers \Pluma\Pipeline\Orquestador
 */
final class OrquestadorTest extends CasoDePruebaUnitario {

	private function pieza(
		int $id,
		EstadoPieza $estado,
		?Expediente $expediente = null,
		?FichaDecisionEditorial $ficha = null,
		?int $periodistaId = null
	): Pieza {
		$reloj = new RelojFijo();

		return new Pieza( $id, 100, $estado, $expediente, null, $reloj->ahora(), $reloj->ahora(), $periodistaId, null, $ficha );
	}

	private function ficha( int $periodistaId = 5 ): FichaDecisionEditorial {
		return new FichaDecisionEditorial(
			$periodistaId,
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

	private function motorSeoFalso(): MotorSeo {
		return new MotorSeo(
			new ExtractorPalabrasClave(),
			new GeneradorMetadatosSeo( new ProveedorLenguajeFalso( '{"tituloSeo": "t", "metaDescripcion": "d"}' ) ),
			new DetectorPluginSeo(),
			new EnlazadorInterno( Mockery::mock( RepositorioMemoriaEditorialInterface::class )->allows( 'obtenerPosturasPorTema' )->andReturn( array() )->getMock(), Mockery::mock( RepositorioPiezasInterface::class ) ),
			new AuditorCanibalizacion( Mockery::mock( RepositorioPiezasInterface::class )->allows( 'existePiezaPublicadaConKeyword' )->andReturn( false )->getMock() )
		);
	}

	private function taxonomoFalso(): Taxonomo {
		$repoVocabulario = Mockery::mock( RepositorioVocabularioInterface::class );
		$repoVocabulario->allows( 'obtenerPorTipo' )->andReturn( array() );

		$reconciliador = new ReconciliadorVocabulario();

		return new Taxonomo(
			new AsignadorCategoria( $reconciliador, $repoVocabulario ),
			new GestorEtiquetas( new ExtractorEntidades(), $reconciliador, $repoVocabulario, new RelojFijo() )
		);
	}

	private function evaluadorCompuertasFalso(): EvaluadorCompuertas {
		return new EvaluadorCompuertas(
			new CompuertaCalidad( new VerificadorLegibilidad() ),
			new CompuertaRiesgo( new ProveedorLenguajeFalso( '{"implicaMenores": false, "implicaSalud": false, "implicaViolencia": false, "riesgoDifamacion": false, "detalleDifamacion": "", "hechosDisputadosSinSenalar": false, "temaRegulado": null, "afirmacionNegativaSobrePersonaIdentificable": false, "posturaSenaladoAusente": false}' ) ),
			new CompuertaOriginalidad(),
			new GestorDegradacion()
		);
	}

	private function coloPermisivo(): RepositorioColaPublicacionInterface {
		$cola = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$cola->allows( 'obtenerVencidas' )->andReturn( array() );
		$cola->allows( 'obtenerEntre' )->andReturn( array() );

		return $cola;
	}

	private function borradoresPermisivo(): RepositorioBorradoresInterface {
		$repo = Mockery::mock( RepositorioBorradoresInterface::class );
		$repo->allows( 'obtenerUltimo' )->andReturn( null );

		return $repo;
	}

	private function candadoPermisivo(): CandadoGlobalInterface {
		$candado = Mockery::mock( CandadoGlobalInterface::class );
		$candado->allows( 'adquirir' )->andReturn( true );
		$candado->allows( 'liberar' );

		return $candado;
	}

	private function bitacoraPermisiva(): RepositorioBitacoraInterface {
		$bitacora = Mockery::mock( RepositorioBitacoraInterface::class );
		$bitacora->allows( 'iniciarEjecucion' )->andReturn( 1 );
		$bitacora->allows( 'finalizarEjecucion' );

		return $bitacora;
	}

	private function piezasPermisivas(): RepositorioPiezasInterface {
		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );

		return $piezas;
	}

	private function sensorPermisivo(): SensorInterface {
		$sensor = Mockery::mock( SensorInterface::class );
		$sensor->allows( 'detectar' )->andReturn( array() );

		return $sensor;
	}

	private function auditoriaPermisiva(): RepositorioAuditoriaInterface {
		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		return $auditoria;
	}

	/**
	 * `PresupuestoLenguaje` es una clase `final` (mismo criterio que
	 * `MotorSeo`/`Taxonomo`): instancia real con un reloj fijo — nunca se
	 * invoca de verdad en estos tests porque `ComparadorHistorias::comparar()`
	 * devuelve `SinRelacion` sin tocar el proveedor cuando no hay candidatas.
	 */
	private function comparadorHistoriasPermisivo(): ComparadorHistorias {
		return new ComparadorHistorias( Mockery::mock( LenguajeInterface::class ), new PresupuestoLenguaje( new RelojFijo() ) );
	}

	private function lectorComentariosPermisivo(): LectorComentariosInterface {
		$lector = Mockery::mock( LectorComentariosInterface::class );
		$lector->allows( 'obtenerAprobadosDe' )->andReturn( array() );

		return $lector;
	}

	/**
	 * `PresupuestoLenguaje` es una clase `final` (mismo criterio que arriba):
	 * instancia real con un reloj fijo — nunca se invoca de verdad en estos
	 * tests porque `lectorComentariosPermisivo()` no devuelve comentarios.
	 */
	private function analizadorAudienciaPermisivo(): AnalizadorAudiencia {
		// Proveedor que siempre "cae": mantiene el fail-safe de `analizar()`
		// aunque un test entregue un comentario real y lo ejercite de verdad.
		$proveedor = Mockery::mock( LenguajeInterface::class );
		$proveedor->allows( 'completar' )->andThrow( new ProveedorLenguajeException( 'proveedor caído (doble permisivo)' ) );

		return new AnalizadorAudiencia( $proveedor, new PresupuestoLenguaje( new RelojFijo() ) );
	}

	private function generadorRespuestaComentarioPermisivo(): GeneradorRespuestaComentario {
		return new GeneradorRespuestaComentario( Mockery::mock( LenguajeInterface::class ) );
	}

	private function memoriaEditorialPermisiva(): RepositorioMemoriaEditorialInterface {
		$memoria = Mockery::mock( RepositorioMemoriaEditorialInterface::class );
		$memoria->allows( 'registrar' )->andReturn( 1 );

		return $memoria;
	}

	private function respuestasComentariosPermisivo(): RepositorioRespuestasComentariosInterface {
		$respuestas = Mockery::mock( RepositorioRespuestasComentariosInterface::class );
		$respuestas->allows( 'yaProcesado' )->andReturn( false );
		$respuestas->allows( 'registrar' )->andReturn( 1 );

		return $respuestas;
	}

	private function periodistasPermisivo(): RepositorioPeriodistasInterface {
		$periodistas = Mockery::mock( RepositorioPeriodistasInterface::class );
		$periodistas->allows( 'obtenerPorId' )->andReturn( null );

		return $periodistas;
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function construir( array $overrides = array() ): Orquestador {
		Functions\when( 'get_option' )->justReturn( false );
		// `textosPropiosRecientes()` (Compuertas de Originalidad) consulta
		// las últimas piezas publicadas vía WordPress core.
		Functions\when( 'get_posts' )->justReturn( array() );
		Functions\when( 'get_post_field' )->justReturn( '' );
		// `Taxonomo` real (ver `taxonomoFalso()`) llama `sanitize_title()`
		// (WordPress core) para reconciliar/crear etiquetas.
		Functions\when( 'sanitize_title' )->alias(
			static function ( string $texto ): string {
				$slug = strtolower( $texto );
				$slug = (string) preg_replace( '/[^a-z0-9]+/', '-', $slug );

				return trim( $slug, '-' );
			}
		);

		$piezas = $overrides['piezas'] ?? $this->piezasPermisivas();

		return new Orquestador(
			$overrides['candado'] ?? $this->candadoPermisivo(),
			$overrides['bitacora'] ?? $this->bitacoraPermisiva(),
			$piezas,
			$overrides['tendencias'] ?? Mockery::mock( RepositorioTendenciasInterface::class ),
			$overrides['borradores'] ?? $this->borradoresPermisivo(),
			$overrides['colaPublicacion'] ?? $this->coloPermisivo(),
			$overrides['transicionador'] ?? new Transicionador(
				$piezas,
				$this->auditoriaPermisiva(),
				new RelojFijo()
			),
			$overrides['sensor'] ?? $this->sensorPermisivo(),
			$overrides['investigador'] ?? Mockery::mock( InvestigadorInterface::class ),
			$overrides['redactor'] ?? Mockery::mock( RedactorInterface::class ),
			$overrides['motorSeo'] ?? $this->motorSeoFalso(),
			$overrides['taxonomo'] ?? $this->taxonomoFalso(),
			$overrides['evaluadorCompuertas'] ?? $this->evaluadorCompuertasFalso(),
			$overrides['lectorCadencia'] ?? new LectorConfiguracionCadencia(),
			$overrides['programadorCadencia'] ?? new ProgramadorCadencia( new AzarFijo( 0 ) ),
			$overrides['creadorBorrador'] ?? Mockery::mock( CreadorBorradorInterface::class ),
			$overrides['publicador'] ?? Mockery::mock( PublicadorInterface::class ),
			$overrides['comparadorHistorias'] ?? $this->comparadorHistoriasPermisivo(),
			$overrides['lectorComentarios'] ?? $this->lectorComentariosPermisivo(),
			$overrides['analizadorAudiencia'] ?? $this->analizadorAudienciaPermisivo(),
			$overrides['generadorRespuestaComentario'] ?? $this->generadorRespuestaComentarioPermisivo(),
			$overrides['verificadorComentarioSustantivo'] ?? new VerificadorComentarioSustantivo(),
			$overrides['memoriaEditorial'] ?? $this->memoriaEditorialPermisiva(),
			$overrides['respuestasComentarios'] ?? $this->respuestasComentariosPermisivo(),
			$overrides['periodistas'] ?? $this->periodistasPermisivo(),
			new RelojFijo(),
			$overrides['resolutorDisputas'] ?? new ResolutorDisputas( Mockery::mock( LenguajeInterface::class ) ),
			$overrides['detectorHuecos'] ?? new DetectorHuecos( Mockery::mock( LenguajeInterface::class ) ),
			$overrides['clasificadorGravedad'] ?? new ClasificadorGravedadTendencia( Mockery::mock( LenguajeInterface::class ) ),
			$overrides['gestorModoRespeto'] ?? new GestorModoRespeto( $this->modoRespetoPermisivo(), Mockery::mock( RepositorioTendenciasInterface::class ) )
		);
	}

	private function modoRespetoPermisivo(): RepositorioModoRespetoInterface {
		$repo = Mockery::mock( RepositorioModoRespetoInterface::class );
		$repo->allows( 'estadoActual' )->andReturn( EstadoModoRespeto::inactivo() );

		return $repo;
	}

	public function test_si_no_adquiere_el_candado_no_ejecuta_nada(): void {
		$candado = Mockery::mock( CandadoGlobalInterface::class );
		$candado->expects( 'adquirir' )->andReturn( false );
		$candado->expects( 'liberar' )->never();

		$bitacora = Mockery::mock( RepositorioBitacoraInterface::class );
		$bitacora->expects( 'iniciarEjecucion' )->once()->andReturn( 1 );
		$bitacora->expects( 'finalizarEjecucion' )->once();

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->expects( 'obtenerPorEstado' )->never();

		$sensor = Mockery::mock( SensorInterface::class );
		$sensor->expects( 'detectar' )->never();

		$orquestador = $this->construir(
			array(
				'candado'  => $candado,
				'bitacora' => $bitacora,
				'piezas'   => $piezas,
				'sensor'   => $sensor,
			)
		);

		$resultado = $orquestador->ejecutarTick();

		self::assertFalse( $resultado['ejecutado'] );
		self::assertSame( 0, $resultado['lotesProcesados'] );
	}

	public function test_detecta_una_tendencia_nueva_y_crea_su_pieza(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$bitacora = Mockery::mock( RepositorioBitacoraInterface::class );
		$bitacora->expects( 'iniciarEjecucion' )->once()->andReturn( 1 );
		$bitacora->expects( 'finalizarEjecucion' )->once();

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'crear' )->once()->with( 55, Mockery::any() )->andReturn( 1 );

		$tendencia = new TendenciaDetectada( 'tendencia nueva', PuntuacionOportunidad::calcular( 80, 80 ), new DateTimeImmutable(), array(), 'google_trends' );

		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'existePorTermino' )->with( 'tendencia nueva', 'google_trends' )->andReturn( false );
		$tendencias->allows( 'obtenerRecientesConPiezaViva' )->andReturn( array() );
		$tendencias->expects( 'guardar' )->once()->andReturn( 55 );

		$sensor = Mockery::mock( SensorInterface::class );
		$sensor->expects( 'detectar' )->once()->andReturn( array( $tendencia ) );
		$sensor->allows( 'nombre' )->andReturn( 'google_trends' );

		$resultado = $this->construir(
			array(
				'bitacora'   => $bitacora,
				'piezas'     => $piezas,
				'tendencias' => $tendencias,
				'sensor'     => $sensor,
			)
		)->ejecutarTick();

		self::assertTrue( $resultado['ejecutado'] );
	}

	public function test_tendencia_ya_existente_no_se_duplica(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'crear' )->never();

		$tendencia = new TendenciaDetectada( 'ya vista', PuntuacionOportunidad::calcular( 50, 50 ), new DateTimeImmutable(), array(), 'google_trends' );

		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'existePorTermino' )->andReturn( true );
		$tendencias->expects( 'guardar' )->never();

		$sensor = Mockery::mock( SensorInterface::class );
		$sensor->expects( 'detectar' )->andReturn( array( $tendencia ) );

		$this->construir(
			array(
				'piezas'     => $piezas,
				'tendencias' => $tendencias,
				'sensor'     => $sensor,
			)
		)->ejecutarTick();

		$this->expectNotToPerformAssertions();
	}

	public function test_el_sensor_caido_no_detiene_el_resto_del_tick(): void {
		$sensor = Mockery::mock( SensorInterface::class );
		$sensor->expects( 'detectar' )->andThrow( new ProveedorTendenciasException( 'feed caído' ) );
		$sensor->allows( 'nombre' )->andReturn( 'google_trends' );

		$resultado = $this->construir( array( 'sensor' => $sensor ) )->ejecutarTick();

		self::assertTrue( $resultado['ejecutado'] );
		self::assertNotEmpty(
			array_filter( $resultado['errores'], static fn ( string $e ): bool => str_contains( $e, 'google_trends' ) )
		);
	}

	public function test_avanza_una_pieza_detectada_hasta_investigada(): void {
		$piezaDetectada = $this->pieza( 7, EstadoPieza::Detectada );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->expects( 'obtenerPorEstado' )->with( EstadoPieza::Detectada, Mockery::any() )->andReturn( array( $piezaDetectada ) );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'obtenerPorId' )->with( 7 )->twice()->andReturn(
			$piezaDetectada,
			$this->pieza( 7, EstadoPieza::EnInvestigacion )
		);
		$piezas->expects( 'actualizarEstado' )->with( 7, EstadoPieza::Detectada, EstadoPieza::EnInvestigacion, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarEstado' )->with( 7, EstadoPieza::EnInvestigacion, EstadoPieza::Investigada, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarExpediente' )->once()->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		Functions\when( 'do_action' )->justReturn( null );

		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'obtenerPorId' )->with( 100 )->andReturn(
			array(
				'termino'               => 'una tendencia',
				'articulosRelacionados' => array(),
			)
		);

		$investigador = Mockery::mock( InvestigadorInterface::class );
		$investigador->expects( 'investigar' )->with( 'una tendencia', array() )->andReturn( new Expediente( 'una tendencia', array() ) );

		$resultado = $this->construir(
			array(
				'piezas'         => $piezas,
				'tendencias'     => $tendencias,
				'transicionador' => new Transicionador( $piezas, $auditoria, new RelojFijo() ),
				'investigador'   => $investigador,
			)
		)->ejecutarTick();

		self::assertSame( 1, $resultado['lotesProcesados'] );
	}

	/**
	 * Nivel Dos B.1+B.2 + B.4/O.2: `procesarInvestigacion` enriquece el
	 * expediente que devuelve el Investigador con la resolución de disputas
	 * y la detección de huecos ANTES de persistirlo — no lo reemplaza.
	 */
	public function test_el_expediente_persistido_incluye_la_resolucion_de_disputas_y_los_huecos_detectados(): void {
		$piezaDetectada = $this->pieza( 7, EstadoPieza::Detectada );

		$hechoUno = new HechoFuente( 'hecho uno', 'https://example.com/1', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido );
		$hechoDos = new HechoFuente( 'hecho dos', 'https://example.com/2', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->expects( 'obtenerPorEstado' )->with( EstadoPieza::Detectada, Mockery::any() )->andReturn( array( $piezaDetectada ) );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'obtenerPorId' )->with( 7 )->twice()->andReturn(
			$piezaDetectada,
			$this->pieza( 7, EstadoPieza::EnInvestigacion )
		);
		$piezas->expects( 'actualizarEstado' )->with( 7, EstadoPieza::Detectada, EstadoPieza::EnInvestigacion, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarEstado' )->with( 7, EstadoPieza::EnInvestigacion, EstadoPieza::Investigada, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarExpediente' )
			->once()
			->with(
				7,
				Mockery::on(
					static function ( Expediente $expediente ): bool {
						return NivelVerificacion::Disputado === $expediente->hechos[0]->nivel
							&& NivelVerificacion::Disputado === $expediente->hechos[1]->nivel
							&& array( DimensionEncuadre::Legal ) === $expediente->huecosDetectados;
					}
				),
				Mockery::any()
			)
			->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		Functions\when( 'do_action' )->justReturn( null );

		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'obtenerPorId' )->with( 100 )->andReturn(
			array(
				'termino'               => 'una tendencia',
				'articulosRelacionados' => array(),
			)
		);

		$investigador = Mockery::mock( InvestigadorInterface::class );
		$investigador->expects( 'investigar' )->with( 'una tendencia', array() )->andReturn( new Expediente( 'una tendencia', array( $hechoUno, $hechoDos ) ) );

		$resolutorDisputas = new ResolutorDisputas( new ProveedorLenguajeFalso( '{"contradicciones": [{"indiceA": 0, "indiceB": 1, "tipo": "ocurrencia"}]}' ) );

		$respuestaHuecos = '{"economica": {"cubierta": true, "datosDisponibles": true, "relevanciaCausal": true}, '
			. '"humana": {"cubierta": true, "datosDisponibles": true, "relevanciaCausal": true}, '
			. '"politica": {"cubierta": true, "datosDisponibles": true, "relevanciaCausal": true}, '
			. '"tecnica": {"cubierta": true, "datosDisponibles": true, "relevanciaCausal": true}, '
			. '"historica": {"cubierta": true, "datosDisponibles": true, "relevanciaCausal": true}, '
			. '"legal": {"cubierta": false, "datosDisponibles": true, "relevanciaCausal": true}}';
		$detectorHuecos  = new DetectorHuecos( new ProveedorLenguajeFalso( $respuestaHuecos ) );

		$resultado = $this->construir(
			array(
				'piezas'            => $piezas,
				'tendencias'        => $tendencias,
				'transicionador'    => new Transicionador( $piezas, $auditoria, new RelojFijo() ),
				'investigador'      => $investigador,
				'resolutorDisputas' => $resolutorDisputas,
				'detectorHuecos'    => $detectorHuecos,
			)
		)->ejecutarTick();

		self::assertSame( 1, $resultado['lotesProcesados'] );
	}

	public function test_una_pieza_investigada_se_redacta_y_crea_el_post_borrador(): void {
		$expediente       = new Expediente( 'una tendencia', array() );
		$piezaInvestigada = $this->pieza( 9, EstadoPieza::Investigada, $expediente );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->expects( 'obtenerPorEstado' )->with( EstadoPieza::Investigada, Mockery::any() )->andReturn( array( $piezaInvestigada ) );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'obtenerPorId' )->with( 9 )->twice()->andReturn(
			$piezaInvestigada,
			$this->pieza( 9, EstadoPieza::EnRedaccion, $expediente )
		);
		$piezas->expects( 'actualizarEstado' )->with( 9, EstadoPieza::Investigada, EstadoPieza::EnRedaccion, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarEstado' )->with( 9, EstadoPieza::EnRedaccion, EstadoPieza::Redactada, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarPostId' )->once()->with( 9, 321, Mockery::any() )->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		Functions\when( 'do_action' )->justReturn( null );

		$resultadoRedaccion = new ResultadoRedaccion( 'Titulo', '<p>cuerpo</p>', false, null, 1 );
		$redactor           = Mockery::mock( RedactorInterface::class );
		$redactor->expects( 'redactar' )
			->with( Mockery::on( static fn ( Pieza $p ): bool => 9 === $p->id && EstadoPieza::EnRedaccion === $p->estado ) )
			->andReturn( $resultadoRedaccion );

		$creadorBorrador = Mockery::mock( CreadorBorradorInterface::class );
		$creadorBorrador->expects( 'crear' )->with( 'Titulo', '<p>cuerpo</p>' )->andReturn( 321 );

		$resultado = $this->construir(
			array(
				'piezas'          => $piezas,
				'transicionador'  => new Transicionador( $piezas, $auditoria, new RelojFijo() ),
				'redactor'        => $redactor,
				'creadorBorrador' => $creadorBorrador,
			)
		)->ejecutarTick();

		self::assertSame( 1, $resultado['lotesProcesados'] );
	}

	public function test_una_pieza_retenida_por_el_corrector_no_crea_borrador(): void {
		$expediente       = new Expediente( 'una tendencia', array() );
		$piezaInvestigada = $this->pieza( 11, EstadoPieza::Investigada, $expediente );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->expects( 'obtenerPorEstado' )->with( EstadoPieza::Investigada, Mockery::any() )->andReturn( array( $piezaInvestigada ) );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'obtenerPorId' )->with( 11 )->twice()->andReturn(
			$piezaInvestigada,
			$this->pieza( 11, EstadoPieza::EnRedaccion, $expediente )
		);
		$piezas->expects( 'actualizarEstado' )->with( 11, EstadoPieza::Investigada, EstadoPieza::EnRedaccion, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarEstado' )->with( 11, EstadoPieza::EnRedaccion, EstadoPieza::Retenida, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarPostId' )->never();

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		Functions\when( 'do_action' )->justReturn( null );

		$resultadoRedaccion = new ResultadoRedaccion( '', '', true, 'El Corrector Interno no aprobó la pieza tras 2 ciclos de revisión.', 2 );
		$redactor           = Mockery::mock( RedactorInterface::class );
		$redactor->expects( 'redactar' )->once()->andReturn( $resultadoRedaccion );

		$creadorBorrador = Mockery::mock( CreadorBorradorInterface::class );
		$creadorBorrador->expects( 'crear' )->never();

		$resultado = $this->construir(
			array(
				'piezas'          => $piezas,
				'transicionador'  => new Transicionador( $piezas, $auditoria, new RelojFijo() ),
				'redactor'        => $redactor,
				'creadorBorrador' => $creadorBorrador,
			)
		)->ejecutarTick();

		self::assertSame( 1, $resultado['lotesProcesados'] );
	}

	/**
	 * Nivel Dos C.3: si ningún periodista supera el umbral de dominio, la
	 * Pieza transita a `SinPeriodistaIdoneo` — nunca crea un borrador.
	 */
	public function test_una_pieza_sin_periodista_idoneo_no_crea_borrador(): void {
		$expediente       = new Expediente( 'una tendencia', array() );
		$piezaInvestigada = $this->pieza( 11, EstadoPieza::Investigada, $expediente );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->expects( 'obtenerPorEstado' )->with( EstadoPieza::Investigada, Mockery::any() )->andReturn( array( $piezaInvestigada ) );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'obtenerPorId' )->with( 11 )->twice()->andReturn(
			$piezaInvestigada,
			$this->pieza( 11, EstadoPieza::EnRedaccion, $expediente )
		);
		$piezas->expects( 'actualizarEstado' )->with( 11, EstadoPieza::Investigada, EstadoPieza::EnRedaccion, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarEstado' )->with( 11, EstadoPieza::EnRedaccion, EstadoPieza::SinPeriodistaIdoneo, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarPostId' )->never();

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		Functions\when( 'do_action' )->justReturn( null );

		$resultadoRedaccion = new ResultadoRedaccion( '', '', false, null, 0, true, 'Ningún periodista del banco supera el umbral de dominio.' );
		$redactor           = Mockery::mock( RedactorInterface::class );
		$redactor->expects( 'redactar' )->once()->andReturn( $resultadoRedaccion );

		$creadorBorrador = Mockery::mock( CreadorBorradorInterface::class );
		$creadorBorrador->expects( 'crear' )->never();

		$resultado = $this->construir(
			array(
				'piezas'          => $piezas,
				'transicionador'  => new Transicionador( $piezas, $auditoria, new RelojFijo() ),
				'redactor'        => $redactor,
				'creadorBorrador' => $creadorBorrador,
			)
		)->ejecutarTick();

		self::assertSame( 1, $resultado['lotesProcesados'] );
	}

	public function test_un_error_al_investigar_marca_la_pieza_como_fallida(): void {
		$piezaDetectada = $this->pieza( 3, EstadoPieza::Detectada );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->expects( 'obtenerPorEstado' )->with( EstadoPieza::Detectada, Mockery::any() )->andReturn( array( $piezaDetectada ) );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'obtenerPorId' )->with( 3 )->twice()->andReturn(
			$piezaDetectada,
			$this->pieza( 3, EstadoPieza::EnInvestigacion )
		);
		$piezas->expects( 'actualizarEstado' )->with( 3, EstadoPieza::Detectada, EstadoPieza::EnInvestigacion, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarEstado' )->with( 3, EstadoPieza::EnInvestigacion, EstadoPieza::Fallida, Mockery::any() )->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		Functions\when( 'do_action' )->justReturn( null );

		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'obtenerPorId' )->with( 100 )->andReturn( null );

		$resultado = $this->construir(
			array(
				'piezas'         => $piezas,
				'tendencias'     => $tendencias,
				'transicionador' => new Transicionador( $piezas, $auditoria, new RelojFijo() ),
			)
		)->ejecutarTick();

		self::assertSame( 1, $resultado['lotesProcesados'] );
		self::assertNotEmpty( array_filter( $resultado['errores'], static fn ( string $e ): bool => str_contains( $e, 'pieza 3' ) ) );
	}

	public function test_una_pieza_redactada_sin_ficha_avanza_a_optimizada_sin_datos_seo(): void {
		$piezaRedactada = $this->pieza( 13, EstadoPieza::Redactada, new Expediente( 'x', array() ) );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->expects( 'obtenerPorEstado' )->with( EstadoPieza::Redactada, Mockery::any() )->andReturn( array( $piezaRedactada ) );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'obtenerPorId' )->with( 13 )->andReturn( $piezaRedactada );
		$piezas->expects( 'actualizarEstado' )->with( 13, EstadoPieza::Redactada, EstadoPieza::Optimizada, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarDatosSeo' )->never();
		$piezas->expects( 'actualizarResultadoTaxonomia' )->never();

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		Functions\when( 'do_action' )->justReturn( null );

		$resultado = $this->construir(
			array(
				'piezas'         => $piezas,
				'transicionador' => new Transicionador( $piezas, $auditoria, new RelojFijo() ),
			)
		)->ejecutarTick();

		self::assertSame( 1, $resultado['lotesProcesados'] );
	}

	public function test_una_pieza_redactada_con_ficha_se_optimiza_y_se_taxonomiza(): void {
		$expediente     = new Expediente( 'x', array() );
		$ficha          = $this->ficha();
		$piezaRedactada = $this->pieza( 15, EstadoPieza::Redactada, $expediente, $ficha, 5 );

		Functions\when( 'get_post' )->justReturn( (object) array( 'post_title' => 'Titular editorial' ) );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->expects( 'obtenerPorEstado' )->with( EstadoPieza::Redactada, Mockery::any() )->andReturn( array( $piezaRedactada ) );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'obtenerPorId' )->with( 15 )->andReturn( $piezaRedactada );
		$piezas->expects( 'actualizarEstado' )->with( 15, EstadoPieza::Redactada, EstadoPieza::Optimizada, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarDatosSeo' )->once()->andReturn( true );
		$piezas->expects( 'actualizarResultadoTaxonomia' )->once()->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		Functions\when( 'do_action' )->justReturn( null );

		$resultado = $this->construir(
			array(
				'piezas'         => $piezas,
				'transicionador' => new Transicionador( $piezas, $auditoria, new RelojFijo() ),
			)
		)->ejecutarTick();

		self::assertSame( 1, $resultado['lotesProcesados'] );
	}

	public function test_una_pieza_optimizada_sin_borrador_queda_en_revision(): void {
		$expediente      = new Expediente( 'x', array() );
		$ficha           = $this->ficha();
		$piezaOptimizada = $this->pieza( 17, EstadoPieza::Optimizada, $expediente, $ficha, 5 );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->expects( 'obtenerPorEstado' )->with( EstadoPieza::Optimizada, Mockery::any() )->andReturn( array( $piezaOptimizada ) );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'obtenerPorId' )->with( 17 )->andReturn( $piezaOptimizada );
		$piezas->expects( 'actualizarEstado' )->with( 17, EstadoPieza::Optimizada, EstadoPieza::EnRevision, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarResultadoCompuertas' )->never();

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		Functions\when( 'do_action' )->justReturn( null );

		$resultado = $this->construir(
			array(
				'piezas'         => $piezas,
				'transicionador' => new Transicionador( $piezas, $auditoria, new RelojFijo() ),
				'borradores'     => $this->borradoresPermisivo(),
			)
		)->ejecutarTick();

		self::assertSame( 1, $resultado['lotesProcesados'] );
	}

	public function test_una_pieza_optimizada_que_aprueba_compuertas_en_autonomo_llega_a_aprobada(): void {
		$expediente      = new Expediente( 'x', array( new HechoFuente( 'un hecho', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ) ) );
		$ficha           = $this->ficha();
		$piezaOptimizada = $this->pieza( 19, EstadoPieza::Optimizada, $expediente, $ficha, 5 );

		$piezaEnRevision = $this->pieza( 19, EstadoPieza::EnRevision, $expediente, $ficha, 5 );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->expects( 'obtenerPorEstado' )->with( EstadoPieza::Optimizada, Mockery::any() )->andReturn( array( $piezaOptimizada ) );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		// Dos transiciones reales (Optimizada→EnRevision, EnRevision→Aprobada):
		// cada una lee el estado ACTUAL de la pieza vía `obtenerPorId()` antes
		// de validar la arista del grafo — debe reflejar la transición previa.
		$piezas->expects( 'obtenerPorId' )->with( 19 )->twice()->andReturn( $piezaOptimizada, $piezaEnRevision );
		$piezas->expects( 'actualizarEstado' )->with( 19, EstadoPieza::Optimizada, EstadoPieza::EnRevision, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarEstado' )->with( 19, EstadoPieza::EnRevision, EstadoPieza::Aprobada, Mockery::any() )->andReturn( true );
		$piezas->expects( 'actualizarResultadoCompuertas' )->once();

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'get_option' )->justReturn( ModoOperacion::Autonomo->value );

		$borrador = new Borrador(
			1,
			19,
			1,
			'El banco central subió la tasa de interés al nueve por ciento este martes. Los analistas esperaban un movimiento más cauto según el informe trimestral.',
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

		$resultado = $this->construir(
			array(
				'piezas'         => $piezas,
				'transicionador' => new Transicionador( $piezas, $auditoria, new RelojFijo() ),
				'borradores'     => $borradores,
			)
		)->ejecutarTick();

		self::assertSame( 1, $resultado['lotesProcesados'] );
	}

	public function test_una_pieza_aprobada_se_programa_en_la_cola_de_publicacion(): void {
		$ficha         = $this->ficha();
		$piezaAprobada = $this->pieza( 21, EstadoPieza::Aprobada, new Expediente( 'x', array() ), $ficha, 5 );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->expects( 'obtenerPorEstado' )->with( EstadoPieza::Aprobada, Mockery::any() )->andReturn( array( $piezaAprobada ) );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'obtenerPorId' )->with( 21 )->andReturn( $piezaAprobada );
		$piezas->expects( 'actualizarEstado' )->with( 21, EstadoPieza::Aprobada, EstadoPieza::Programada, Mockery::any() )->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		Functions\when( 'do_action' )->justReturn( null );

		$cola = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$cola->allows( 'obtenerVencidas' )->andReturn( array() );
		$cola->expects( 'obtenerEntre' )->twice()->andReturn( array() );
		$cola->expects( 'programar' )->once()->with( 21, 'economia', 5, Mockery::any(), Mockery::any() )->andReturn( 1 );

		$resultado = $this->construir(
			array(
				'piezas'          => $piezas,
				'transicionador'  => new Transicionador( $piezas, $auditoria, new RelojFijo() ),
				'colaPublicacion' => $cola,
			)
		)->ejecutarTick();

		self::assertSame( 1, $resultado['lotesProcesados'] );
	}

	public function test_una_pieza_aprobada_sin_espacio_hoy_no_se_programa(): void {
		$ficha         = $this->ficha();
		$piezaAprobada = $this->pieza( 23, EstadoPieza::Aprobada, new Expediente( 'x', array() ), $ficha, 5 );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->expects( 'obtenerPorEstado' )->with( EstadoPieza::Aprobada, Mockery::any() )->andReturn( array( $piezaAprobada ) );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'actualizarEstado' )->never();

		$cola = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$cola->allows( 'obtenerVencidas' )->andReturn( array() );
		$cola->expects( 'obtenerEntre' )->twice()->andReturn( array() );
		$cola->expects( 'programar' )->never();

		$orquestador = $this->construir(
			array(
				'piezas'          => $piezas,
				'colaPublicacion' => $cola,
			)
		);

		// Cuota máxima 0: `ProgramadorCadencia::siguienteRanura()` rechaza en
		// el primer guardián (sin espacio hoy), sin necesidad de mockear
		// `LectorConfiguracionCadencia` (clase `final`, no mockeable). Se
		// registra DESPUÉS de `construir()` porque este helper ya fija su
		// propio `get_option` por defecto.
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto ) {
				return LectorConfiguracionCadencia::OPCION_CUOTA_MAXIMA === $opcion ? 0 : $defecto;
			}
		);

		$resultado = $orquestador->ejecutarTick();

		self::assertSame( 1, $resultado['lotesProcesados'] );
	}

	public function test_una_ranura_vencida_en_autonomo_se_publica(): void {
		$piezaProgramada = $this->pieza( 25, EstadoPieza::Programada );
		$piezaProgramada = new Pieza(
			25,
			100,
			EstadoPieza::Programada,
			null,
			987,
			$piezaProgramada->creadaEn,
			$piezaProgramada->actualizadaEn
		);

		$ranura = new RanuraPublicacion( 1, 25, 'economia', 5, new DateTimeImmutable( '2026-07-22T09:00:00+00:00' ), EstadoColaPublicacion::Programada, false, new DateTimeImmutable( '2026-07-22T08:00:00+00:00' ) );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'obtenerPorId' )->with( 25 )->twice()->andReturn( $piezaProgramada );
		$piezas->expects( 'actualizarEstado' )->with( 25, EstadoPieza::Programada, EstadoPieza::Publicada, Mockery::any() )->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->allows( 'registrar' );

		Functions\when( 'do_action' )->justReturn( null );

		$cola = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$cola->expects( 'obtenerVencidas' )->andReturn( array( $ranura ) );
		$cola->allows( 'obtenerEntre' )->andReturn( array() );
		$cola->expects( 'marcarPublicada' )->once()->with( 1 );

		$publicador = Mockery::mock( PublicadorInterface::class );
		$publicador->expects( 'publicar' )->once()->with( 987, Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any() );

		$resultado = $this->construir(
			array(
				'piezas'          => $piezas,
				'transicionador'  => new Transicionador( $piezas, $auditoria, new RelojFijo() ),
				'colaPublicacion' => $cola,
				'publicador'      => $publicador,
			)
		)->ejecutarTick();

		self::assertTrue( $resultado['ejecutado'] );
	}

	private function resultadoCompuertas( ModoOperacion $modo ): ResultadoEvaluacion {
		return new ResultadoEvaluacion(
			true,
			false,
			array(),
			$modo,
			new DiagnosticoCalidad( 80, 70, true, true, array() ),
			new DiagnosticoRiesgo( false, false, false, false, true, 'x', false, null ),
			new DiagnosticoOriginalidad( false, false, 0.8, 0.4 )
		);
	}

	/**
	 * Etapa 6, porción 4c (Nivel Tres N.3 (c)): una ranura de Copiloto cuya
	 * ventana de veto todavía no expiró, y que nadie aprobó activamente
	 * ("aprobar ahora"), no se publica todavía — el veto sigue vivo.
	 */
	public function test_una_ranura_de_copiloto_dentro_de_la_ventana_de_veto_no_se_publica(): void {
		$piezaProgramada = new Pieza(
			30,
			100,
			EstadoPieza::Programada,
			null,
			987,
			( new RelojFijo() )->ahora(),
			( new RelojFijo() )->ahora(),
			null,
			null,
			null,
			$this->resultadoCompuertas( ModoOperacion::Copiloto )
		);

		// RelojFijo "ahora" = 2026-07-22T12:00:00+00:00; ventana por defecto 2h;
		// hora_programada 11:00 + 2h = 13:00, todavía en el futuro.
		$ranura = new RanuraPublicacion( 3, 30, 'economia', 5, new DateTimeImmutable( '2026-07-22T11:00:00+00:00' ), EstadoColaPublicacion::Programada, false, new DateTimeImmutable( '2026-07-22T08:00:00+00:00' ) );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'obtenerPorId' )->with( 30 )->andReturn( $piezaProgramada );
		$piezas->expects( 'actualizarEstado' )->never();

		$cola = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$cola->expects( 'obtenerVencidas' )->andReturn( array( $ranura ) );
		$cola->allows( 'obtenerEntre' )->andReturn( array() );
		$cola->expects( 'marcarPublicada' )->never();

		$publicador = Mockery::mock( PublicadorInterface::class );
		$publicador->expects( 'publicar' )->never();

		$resultado = $this->construir(
			array(
				'piezas'          => $piezas,
				'colaPublicacion' => $cola,
				'publicador'      => $publicador,
			)
		)->ejecutarTick();

		self::assertTrue( $resultado['ejecutado'] );
	}

	/**
	 * "Aprobar ahora" (porción 4c): una ranura de Copiloto con
	 * `aprobacionActiva` se publica ya, aunque la ventana de veto no haya
	 * expirado, y sin marcado de IA en el frontend (el humano ya aprobó
	 * activamente antes de publicar — misma excepción del Art. 50 que Piloto).
	 */
	public function test_una_ranura_de_copiloto_con_aprobacion_activa_se_publica_sin_esperar_la_ventana(): void {
		$piezaProgramada = new Pieza(
			31,
			100,
			EstadoPieza::Programada,
			null,
			987,
			( new RelojFijo() )->ahora(),
			( new RelojFijo() )->ahora(),
			null,
			null,
			null,
			$this->resultadoCompuertas( ModoOperacion::Copiloto )
		);

		$ranura = new RanuraPublicacion( 4, 31, 'economia', 5, new DateTimeImmutable( '2026-07-22T11:00:00+00:00' ), EstadoColaPublicacion::Programada, true, new DateTimeImmutable( '2026-07-22T08:00:00+00:00' ) );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'obtenerPorId' )->with( 31 )->twice()->andReturn( $piezaProgramada );
		$piezas->expects( 'actualizarEstado' )->with( 31, EstadoPieza::Programada, EstadoPieza::Publicada, Mockery::any() )->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->expects( 'registrar' )->with( 31, EstadoPieza::Programada, EstadoPieza::Publicada, Mockery::any(), Mockery::any(), Mockery::any(), TipoAprobacion::HumanaActiva );

		Functions\when( 'do_action' )->justReturn( null );

		$cola = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$cola->expects( 'obtenerVencidas' )->andReturn( array( $ranura ) );
		$cola->allows( 'obtenerEntre' )->andReturn( array() );
		$cola->expects( 'marcarPublicada' )->once()->with( 4 );

		$publicador = Mockery::mock( PublicadorInterface::class );
		$publicador->expects( 'publicar' )->once()->with(
			987,
			Mockery::any(),
			Mockery::any(),
			Mockery::any(),
			Mockery::on( static fn ( SnapshotPublicacion $s ): bool => false === $s->generadoIa )
		);

		$resultado = $this->construir(
			array(
				'piezas'          => $piezas,
				'transicionador'  => new Transicionador( $piezas, $auditoria, new RelojFijo() ),
				'colaPublicacion' => $cola,
				'publicador'      => $publicador,
			)
		)->ejecutarTick();

		self::assertTrue( $resultado['ejecutado'] );
	}

	/**
	 * Publicación automática por expiración de ventana (sin "aprobar ahora"):
	 * conserva el marcado de IA de fábrica y registra el tipo de aprobación
	 * correspondiente en la auditoría.
	 */
	public function test_una_ranura_de_copiloto_vencida_sin_aprobacion_activa_registra_tipo_automatica(): void {
		$piezaProgramada = new Pieza(
			32,
			100,
			EstadoPieza::Programada,
			null,
			987,
			( new RelojFijo() )->ahora(),
			( new RelojFijo() )->ahora(),
			null,
			null,
			null,
			$this->resultadoCompuertas( ModoOperacion::Copiloto )
		);

		// hora_programada 09:00 + ventana 2h = 11:00, ya pasó frente a las 12:00.
		$ranura = new RanuraPublicacion( 5, 32, 'economia', 5, new DateTimeImmutable( '2026-07-22T09:00:00+00:00' ), EstadoColaPublicacion::Programada, false, new DateTimeImmutable( '2026-07-22T08:00:00+00:00' ) );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'obtenerPorId' )->with( 32 )->twice()->andReturn( $piezaProgramada );
		$piezas->expects( 'actualizarEstado' )->with( 32, EstadoPieza::Programada, EstadoPieza::Publicada, Mockery::any() )->andReturn( true );

		$auditoria = Mockery::mock( RepositorioAuditoriaInterface::class );
		$auditoria->expects( 'registrar' )->with( 32, EstadoPieza::Programada, EstadoPieza::Publicada, Mockery::any(), Mockery::any(), Mockery::any(), TipoAprobacion::AutomaticaPorExpiracion );

		Functions\when( 'do_action' )->justReturn( null );

		$cola = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$cola->expects( 'obtenerVencidas' )->andReturn( array( $ranura ) );
		$cola->allows( 'obtenerEntre' )->andReturn( array() );
		$cola->expects( 'marcarPublicada' )->once()->with( 5 );

		$publicador = Mockery::mock( PublicadorInterface::class );
		$publicador->expects( 'publicar' )->once()->with(
			987,
			Mockery::any(),
			Mockery::any(),
			Mockery::any(),
			Mockery::on( static fn ( SnapshotPublicacion $s ): bool => true === $s->generadoIa )
		);

		$resultado = $this->construir(
			array(
				'piezas'          => $piezas,
				'transicionador'  => new Transicionador( $piezas, $auditoria, new RelojFijo() ),
				'colaPublicacion' => $cola,
				'publicador'      => $publicador,
			)
		)->ejecutarTick();

		self::assertTrue( $resultado['ejecutado'] );
	}

	/**
	 * Escenario de fallo (Delivery Guardian: "si toca Orquestador, probar
	 * API caída/timeout"): un fallo real de `Publicador` (p. ej. el post ya
	 * no existe, o `wp_update_post` devuelve `WP_Error`) no debe tumbar el
	 * resto del tick — se registra en `errores` y el motor sigue.
	 */
	public function test_un_fallo_del_publicador_se_registra_sin_detener_el_resto_del_tick(): void {
		$piezaProgramada = new Pieza(
			27,
			100,
			EstadoPieza::Programada,
			null,
			987,
			( new RelojFijo() )->ahora(),
			( new RelojFijo() )->ahora()
		);

		$ranura = new RanuraPublicacion( 2, 27, 'economia', 5, new DateTimeImmutable( '2026-07-22T09:00:00+00:00' ), EstadoColaPublicacion::Programada, false, new DateTimeImmutable( '2026-07-22T08:00:00+00:00' ) );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array() );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->expects( 'obtenerPorId' )->with( 27 )->andReturn( $piezaProgramada );
		$piezas->expects( 'actualizarEstado' )->never();

		$cola = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$cola->expects( 'obtenerVencidas' )->andReturn( array( $ranura ) );
		$cola->allows( 'obtenerEntre' )->andReturn( array() );
		$cola->expects( 'marcarPublicada' )->never();

		$publicador = Mockery::mock( PublicadorInterface::class );
		$publicador->expects( 'publicar' )->once()->andThrow( new PublicacionException( 'el post ya no existe' ) );

		$resultado = $this->construir(
			array(
				'piezas'          => $piezas,
				'colaPublicacion' => $cola,
				'publicador'      => $publicador,
			)
		)->ejecutarTick();

		self::assertTrue( $resultado['ejecutado'] );
		self::assertNotEmpty(
			array_filter( $resultado['errores'], static fn ( string $e ): bool => str_contains( $e, 'ranura 2' ) && str_contains( $e, 'el post ya no existe' ) )
		);
	}

	public function test_escasez_honesta_se_reporta_como_informativo_no_como_fallo(): void {
		// Sin overrides de cadencia: `LectorConfiguracionCadencia` real con
		// pisos de fábrica (cuotaMinima=3) y la cola vacía (0 comprometidas
		// hoy) — 0 < 3 dispara el aviso de escasez honesta.
		$cola = Mockery::mock( RepositorioColaPublicacionInterface::class );
		$cola->allows( 'obtenerVencidas' )->andReturn( array() );
		$cola->allows( 'obtenerEntre' )->andReturn( array() );

		$resultado = $this->construir( array( 'colaPublicacion' => $cola ) )->ejecutarTick();

		self::assertNotEmpty( array_filter( $resultado['errores'], static fn ( string $e ): bool => str_contains( $e, 'Escasez honesta' ) ) );
	}

	private function periodistaConRespuestas( bool $habilitadas ): Periodista {
		$diales   = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas   = new ReglasConducta( 'linea', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 1, 5, $diales, $reglas, $matriz, $habilitadas, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

		return new Periodista( 5, 'Valentina Ruiz', null, 'Bio.', RolPeriodista::Columnista, array(), EstadoPeriodista::Activo, $conducta, new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ), new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ) );
	}

	public function test_un_comentario_sustantivo_sin_periodista_solo_se_marca_procesado(): void {
		$ficha          = $this->ficha();
		$piezaPublicada = new Pieza( 40, 100, EstadoPieza::Publicada, null, 555, ( new RelojFijo() )->ahora(), ( new RelojFijo() )->ahora(), null, null, $ficha );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array( $piezaPublicada ) );

		$comentario = new ComentarioWordPress( 900, 555, 'Lector', 'Un comentario largo y sustantivo sobre el artículo publicado.', new DateTimeImmutable() );

		$lector = Mockery::mock( LectorComentariosInterface::class );
		$lector->expects( 'obtenerAprobadosDe' )->with( 555 )->andReturn( array( $comentario ) );

		$respuestas = Mockery::mock( RepositorioRespuestasComentariosInterface::class );
		$respuestas->expects( 'yaProcesado' )->with( 900 )->andReturn( false );
		$respuestas->expects( 'registrar' )->once()->with( 40, 900, null, null, \Pluma\Redaccion\EstadoRespuestaComentario::Procesado, Mockery::any() )->andReturn( 1 );

		$memoria = Mockery::mock( RepositorioMemoriaEditorialInterface::class );
		$memoria->expects( 'registrar' )->never();

		$this->construir(
			array(
				'piezas'                => $piezas,
				'lectorComentarios'     => $lector,
				'respuestasComentarios' => $respuestas,
				'memoriaEditorial'      => $memoria,
			)
		)->ejecutarTick();

		$this->expectNotToPerformAssertions();
	}

	public function test_un_comentario_sustantivo_con_periodista_registra_memoria_de_audiencia(): void {
		$ficha          = $this->ficha();
		$piezaPublicada = new Pieza( 41, 100, EstadoPieza::Publicada, null, 556, ( new RelojFijo() )->ahora(), ( new RelojFijo() )->ahora(), 5, null, $ficha );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array( $piezaPublicada ) );

		$comentario = new ComentarioWordPress( 901, 556, 'Lector', 'Un comentario largo y sustantivo sobre el artículo publicado.', new DateTimeImmutable() );

		$lector = Mockery::mock( LectorComentariosInterface::class );
		$lector->expects( 'obtenerAprobadosDe' )->with( 556 )->andReturn( array( $comentario ) );

		$respuestas = Mockery::mock( RepositorioRespuestasComentariosInterface::class );
		$respuestas->expects( 'yaProcesado' )->with( 901 )->andReturn( false );
		$respuestas->expects( 'registrar' )->once()->with( 41, 901, 5, null, \Pluma\Redaccion\EstadoRespuestaComentario::Procesado, Mockery::any() )->andReturn( 1 );

		$memoria = Mockery::mock( RepositorioMemoriaEditorialInterface::class );
		$memoria->expects( 'registrar' )->once()->with( 5, \Pluma\Redaccion\TipoMemoria::Audiencia, Mockery::any(), Mockery::any(), 41, Mockery::any() )->andReturn( 1 );

		$analizador = new AnalizadorAudiencia(
			new ProveedorLenguajeFalso( '{"resumen": "Le importa el precio.", "sentimiento": "mixto"}' ),
			new PresupuestoLenguaje( new RelojFijo() )
		);

		$periodistas = Mockery::mock( \Pluma\Datos\RepositorioPeriodistasInterface::class );
		$periodistas->expects( 'obtenerPorId' )->with( 5 )->andReturn( $this->periodistaConRespuestas( false ) );

		Functions\when( 'get_option' )->justReturn( false );

		$this->construir(
			array(
				'piezas'                => $piezas,
				'lectorComentarios'     => $lector,
				'respuestasComentarios' => $respuestas,
				'memoriaEditorial'      => $memoria,
				'analizadorAudiencia'   => $analizador,
				'periodistas'           => $periodistas,
			)
		)->ejecutarTick();

		$this->expectNotToPerformAssertions();
	}

	public function test_un_comentario_sustantivo_con_respuestas_habilitadas_registra_un_borrador_pendiente(): void {
		$ficha          = $this->ficha();
		$piezaPublicada = new Pieza( 42, 100, EstadoPieza::Publicada, null, 557, ( new RelojFijo() )->ahora(), ( new RelojFijo() )->ahora(), 5, null, $ficha );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array( $piezaPublicada ) );

		$comentario = new ComentarioWordPress( 902, 557, 'Lector', 'Un comentario largo y sustantivo sobre el artículo publicado.', new DateTimeImmutable() );

		$lector = Mockery::mock( LectorComentariosInterface::class );
		$lector->expects( 'obtenerAprobadosDe' )->with( 557 )->andReturn( array( $comentario ) );

		$respuestas = Mockery::mock( RepositorioRespuestasComentariosInterface::class );
		$respuestas->expects( 'yaProcesado' )->with( 902 )->andReturn( false );
		$respuestas->expects( 'registrar' )->once()->with( 42, 902, 5, 'Gracias por comentar, ahí va el contexto.', \Pluma\Redaccion\EstadoRespuestaComentario::PendienteAprobacion, Mockery::any() )->andReturn( 1 );

		$memoria = Mockery::mock( RepositorioMemoriaEditorialInterface::class );
		$memoria->allows( 'registrar' )->andReturn( 1 );

		$generador = new GeneradorRespuestaComentario( new ProveedorLenguajeFalso( '{"respuesta": "Gracias por comentar, ahí va el contexto."}' ) );

		$periodistas = Mockery::mock( \Pluma\Datos\RepositorioPeriodistasInterface::class );
		$periodistas->expects( 'obtenerPorId' )->with( 5 )->andReturn( $this->periodistaConRespuestas( true ) );

		Functions\when( 'get_option' )->justReturn( false );

		$this->construir(
			array(
				'piezas'                       => $piezas,
				'lectorComentarios'            => $lector,
				'respuestasComentarios'        => $respuestas,
				'memoriaEditorial'             => $memoria,
				'analizadorAudiencia'          => $this->analizadorAudienciaPermisivo(),
				'generadorRespuestaComentario' => $generador,
				'periodistas'                  => $periodistas,
			)
		)->ejecutarTick();

		$this->expectNotToPerformAssertions();
	}

	public function test_un_comentario_ya_procesado_se_omite(): void {
		$ficha          = $this->ficha();
		$piezaPublicada = new Pieza( 43, 100, EstadoPieza::Publicada, null, 558, ( new RelojFijo() )->ahora(), ( new RelojFijo() )->ahora(), null, null, $ficha );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array( $piezaPublicada ) );

		$comentario = new ComentarioWordPress( 903, 558, 'Lector', 'Un comentario largo y sustantivo sobre el artículo publicado.', new DateTimeImmutable() );

		$lector = Mockery::mock( LectorComentariosInterface::class );
		$lector->expects( 'obtenerAprobadosDe' )->with( 558 )->andReturn( array( $comentario ) );

		$respuestas = Mockery::mock( RepositorioRespuestasComentariosInterface::class );
		$respuestas->expects( 'yaProcesado' )->with( 903 )->andReturn( true );
		$respuestas->expects( 'registrar' )->never();

		$this->construir(
			array(
				'piezas'                => $piezas,
				'lectorComentarios'     => $lector,
				'respuestasComentarios' => $respuestas,
			)
		)->ejecutarTick();

		$this->expectNotToPerformAssertions();
	}

	public function test_un_comentario_no_sustantivo_se_omite(): void {
		$ficha          = $this->ficha();
		$piezaPublicada = new Pieza( 44, 100, EstadoPieza::Publicada, null, 559, ( new RelojFijo() )->ahora(), ( new RelojFijo() )->ahora(), null, null, $ficha );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'obtenerPorEstado' )->andReturn( array() );
		$piezas->allows( 'obtenerPublicadasParaSincronizarComentarios' )->andReturn( array( $piezaPublicada ) );

		$comentario = new ComentarioWordPress( 904, 559, 'Lector', 'primero!', new DateTimeImmutable() );

		$lector = Mockery::mock( LectorComentariosInterface::class );
		$lector->expects( 'obtenerAprobadosDe' )->with( 559 )->andReturn( array( $comentario ) );

		$respuestas = Mockery::mock( RepositorioRespuestasComentariosInterface::class );
		$respuestas->allows( 'yaProcesado' )->andReturn( false );
		$respuestas->expects( 'registrar' )->never();

		$this->construir(
			array(
				'piezas'                => $piezas,
				'lectorComentarios'     => $lector,
				'respuestasComentarios' => $respuestas,
			)
		)->ejecutarTick();

		$this->expectNotToPerformAssertions();
	}
}
