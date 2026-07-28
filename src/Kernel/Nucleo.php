<?php

declare(strict_types=1);

namespace Pluma\Kernel;

use Pluma\Admin\NotificadorRevision;
use Pluma\Admin\NotificadorSinPeriodistaIdoneo;
use Pluma\Admin\PantallaPanel;
use Pluma\Admin\RestBancoPeriodistas;
use Pluma\Admin\RestEstudioSeo;
use Pluma\Admin\RestInformesEditoriales;
use Pluma\Admin\RestOnboarding;
use Pluma\Admin\RestOrquestador;
use Pluma\Admin\RestMesaEditorial;
use Pluma\Admin\RestPeriodistas;
use Pluma\Admin\RestPortada;
use Pluma\Admin\RestRespuestasComentarios;
use Pluma\Admin\RestSalaMaquinas;
use Pluma\Admin\RestSalaRevision;
use Pluma\Admin\RestSalaTendencias;
use Pluma\Admin\RestSearchConsole;
use Pluma\Admin\RestModeloVerificador;
use Pluma\Admin\RestImagenDestacada;
use Pluma\Admin\RestModoRespeto;
use Pluma\Admin\RestRiesgoLegal;
use Pluma\Admin\RestTransparencia;
use Pluma\Compuertas\ClasificadorGravedadTendencia;
use Pluma\Compuertas\CompuertaCalidad;
use Pluma\Compuertas\CompuertaOriginalidad;
use Pluma\Compuertas\CompuertaRiesgo;
use Pluma\Compuertas\EvaluadorCompuertas;
use Pluma\Compuertas\GestorDegradacion;
use Pluma\Compuertas\GestorModoRespeto;
use Pluma\Compuertas\VerificadorLegibilidad;
use Pluma\Datos\CandadoGlobal;
use Pluma\Datos\CandadoGlobalInterface;
use Pluma\Datos\RepositorioAuditoria;
use Pluma\Datos\RepositorioAuditoriaInterface;
use Pluma\Datos\RepositorioBitacora;
use Pluma\Datos\RepositorioBitacoraInterface;
use Pluma\Datos\RepositorioBorradores;
use Pluma\Datos\RepositorioBorradoresInterface;
use Pluma\Datos\RepositorioColaPublicacion;
use Pluma\Datos\RepositorioColaPublicacionInterface;
use Pluma\Datos\RepositorioHistorias;
use Pluma\Datos\RepositorioHistoriasInterface;
use Pluma\Datos\RepositorioMemoriaEditorial;
use Pluma\Datos\RepositorioMemoriaEditorialInterface;
use Pluma\Datos\RepositorioMetricasSearchConsole;
use Pluma\Datos\RepositorioMetricasSearchConsoleInterface;
use Pluma\Datos\RepositorioModoRespeto;
use Pluma\Datos\RepositorioModoRespetoInterface;
use Pluma\Datos\RepositorioPeriodistas;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezas;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Datos\RepositorioRespuestasComentarios;
use Pluma\Datos\RepositorioRespuestasComentariosInterface;
use Pluma\Datos\RepositorioTendencias;
use Pluma\Datos\RepositorioTendenciasInterface;
use Pluma\Datos\RepositorioVocabulario;
use Pluma\Datos\RepositorioVocabularioInterface;
use Pluma\Investigacion\ClasificadorNivelFuente;
use Pluma\Investigacion\DetectorHuecos;
use Pluma\Investigacion\InvestigadorInterface;
use Pluma\Investigacion\InvestigadorMecanico;
use Pluma\Investigacion\ResolutorDisputas;
use Pluma\Investigacion\SelectorImagenPorAutoridad;
use Pluma\Investigacion\VerificadorProcedenciaDeclaracion;
use Pluma\Pipeline\GestorHistorias;
use Pluma\Pipeline\GestorRespuestasComentarios;
use Pluma\Pipeline\GestorSalaRevision;
use Pluma\Pipeline\GestorSalaTendencias;
use Pluma\Pipeline\LectorConfiguracionCadencia;
use Pluma\Pipeline\Orquestador;
use Pluma\Pipeline\ProgramadorCadencia;
use Pluma\Pipeline\Transicionador;
use Pluma\Proveedores\EmbeddingsInterface;
use Pluma\Proveedores\EnrutadorModelos;
use Pluma\Proveedores\ExtractorImagenFuente;
use Pluma\Proveedores\ExtractorImagenFuenteInterface;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Proveedores\ProveedorGoogleTrends;
use Pluma\Proveedores\ProveedorOpenRouter;
use Pluma\Proveedores\ProveedorSearchConsole;
use Pluma\Proveedores\ProveedorSearchConsoleInterface;
use Pluma\Proveedores\ProveedorTelemetria;
use Pluma\Proveedores\ProveedorTendenciasInterface;
use Pluma\Proveedores\TelemetriaInterface;
use Pluma\Publicacion\AsignadorImagenDestacada;
use Pluma\Publicacion\AsignadorImagenDestacadaInterface;
use Pluma\Publicacion\AsignadorTaxonomiaWp;
use Pluma\Publicacion\CreadorBorrador;
use Pluma\Publicacion\CreadorBorradorInterface;
use Pluma\Publicacion\EscritorCamposSeo;
use Pluma\Publicacion\LectorComentarios;
use Pluma\Publicacion\LectorComentariosInterface;
use Pluma\Publicacion\Publicador;
use Pluma\Publicacion\PublicadorComentario;
use Pluma\Publicacion\PublicadorComentarioInterface;
use Pluma\Publicacion\PublicadorInterface;
use Pluma\Redaccion\AnalizadorAudiencia;
use Pluma\Redaccion\AsignadorPeriodista;
use Pluma\Redaccion\AvisoTransparenciaIa;
use Pluma\Redaccion\ClasificadorNoticia;
use Pluma\Redaccion\CompiladorDirectrices;
use Pluma\Redaccion\CorrectorInterno;
use Pluma\Redaccion\DecisionEditorial;
use Pluma\Redaccion\DeclaracionIdentidadSintetica;
use Pluma\Redaccion\ExportadorBancoPeriodistas;
use Pluma\Redaccion\GeneradorBloqueEditor;
use Pluma\Redaccion\GeneradorEsqueleto;
use Pluma\Redaccion\GeneradorRespuestaComentario;
use Pluma\Redaccion\GeneradorVistaPrevia;
use Pluma\Redaccion\ImportadorBancoPeriodistas;
use Pluma\Redaccion\RedactorConFallbackMecanico;
use Pluma\Redaccion\RedactorInterface;
use Pluma\Redaccion\RedactorMecanico;
use Pluma\Redaccion\RedactorSintetico;
use Pluma\Redaccion\SegmentadorUnidadesFactuales;
use Pluma\Redaccion\SelectorAngulo;
use Pluma\Redaccion\VerificadorComentarioSustantivo;
use Pluma\Redaccion\VerificadorFalseabilidad;
use Pluma\Redaccion\VerificadorNGramas;
use Pluma\Redaccion\VerificadorTrazabilidadDeterminista;
use Pluma\Redaccion\VerificadorVoz;
use Pluma\Seo\AuditorCanibalizacion;
use Pluma\Seo\ConstructorEsquemaNewsArticle;
use Pluma\Seo\DetectorPluginSeo;
use Pluma\Seo\EmisorEsquemaFrontend;
use Pluma\Seo\EnlazadorInterno;
use Pluma\Seo\ExtractorPalabrasClave;
use Pluma\Seo\GeneradorMetadatosSeo;
use Pluma\Seo\HistoriaHub;
use Pluma\Seo\MotorSeo;
use Pluma\Seo\PaginaAutorPeriodista;
use Pluma\Sensores\ComparadorHistorias;
use Pluma\Sensores\EvaluadorLegitimidadInsumo;
use Pluma\Sensores\SensorGoogleTrends;
use Pluma\Sensores\SensorInterface;
use Pluma\Taxonomia\AsignadorCategoria;
use Pluma\Taxonomia\ExtractorEntidades;
use Pluma\Taxonomia\GestorEtiquetas;
use Pluma\Taxonomia\ReconciliadorVocabulario;
use Pluma\Taxonomia\Taxonomo;
use wpdb;

/**
 * Raíz de composición del plugin: registra servicios en el Contenedor y
 * arranca los módulos activos en `plugins_loaded`.
 *
 * Cada Etapa futura añade su registro aquí (o en un registrador dedicado que
 * este Núcleo invoque) — jamás llamadas directas entre capas no adyacentes.
 */
final class Nucleo {

	private readonly Contenedor $contenedor;

	public function __construct() {
		$this->contenedor = new Contenedor();
		$this->registrarServicios();
	}

	public function contenedor(): Contenedor {
		return $this->contenedor;
	}

	private function registrarServicios(): void {
		$this->contenedor->registrar( RelojInterface::class, static fn (): RelojSistema => new RelojSistema() );
		$this->contenedor->registrar( AzarInterface::class, static fn (): AzarSistema => new AzarSistema() );

		$this->contenedor->registrar(
			'wpdb',
			static function (): wpdb {
				global $wpdb;
				assert( $wpdb instanceof wpdb );

				return $wpdb;
			}
		);

		$this->contenedor->registrar(
			DetectorEntorno::class,
			fn ( Contenedor $c ): DetectorEntorno => new DetectorEntorno( $c->obtener( 'wpdb' ) )
		);

		$this->contenedor->registrar(
			DetectorConflictos::class,
			static fn (): DetectorConflictos => new DetectorConflictos()
		);

		$this->contenedor->registrar(
			RepositorioPiezasInterface::class,
			fn ( Contenedor $c ): RepositorioPiezas => new RepositorioPiezas( $c->obtener( 'wpdb' ) )
		);
		$this->contenedor->registrar(
			RepositorioTendenciasInterface::class,
			fn ( Contenedor $c ): RepositorioTendencias => new RepositorioTendencias( $c->obtener( 'wpdb' ) )
		);
		$this->contenedor->registrar(
			RepositorioHistoriasInterface::class,
			fn ( Contenedor $c ): RepositorioHistorias => new RepositorioHistorias( $c->obtener( 'wpdb' ) )
		);
		$this->contenedor->registrar(
			RepositorioModoRespetoInterface::class,
			fn ( Contenedor $c ): RepositorioModoRespeto => new RepositorioModoRespeto( $c->obtener( 'wpdb' ) )
		);
		$this->contenedor->registrar(
			RepositorioBitacoraInterface::class,
			fn ( Contenedor $c ): RepositorioBitacora => new RepositorioBitacora( $c->obtener( 'wpdb' ) )
		);
		$this->contenedor->registrar(
			RepositorioAuditoriaInterface::class,
			fn ( Contenedor $c ): RepositorioAuditoria => new RepositorioAuditoria( $c->obtener( 'wpdb' ) )
		);
		$this->contenedor->registrar(
			CandadoGlobalInterface::class,
			fn ( Contenedor $c ): CandadoGlobal => new CandadoGlobal( $c->obtener( 'wpdb' ) )
		);
		$this->contenedor->registrar(
			RepositorioPeriodistasInterface::class,
			fn ( Contenedor $c ): RepositorioPeriodistas => new RepositorioPeriodistas( $c->obtener( 'wpdb' ) )
		);
		$this->contenedor->registrar(
			RepositorioMemoriaEditorialInterface::class,
			fn ( Contenedor $c ): RepositorioMemoriaEditorial => new RepositorioMemoriaEditorial( $c->obtener( 'wpdb' ) )
		);
		$this->contenedor->registrar(
			RepositorioBorradoresInterface::class,
			fn ( Contenedor $c ): RepositorioBorradores => new RepositorioBorradores( $c->obtener( 'wpdb' ) )
		);
		$this->contenedor->registrar(
			RepositorioVocabularioInterface::class,
			fn ( Contenedor $c ): RepositorioVocabulario => new RepositorioVocabulario( $c->obtener( 'wpdb' ) )
		);
		$this->contenedor->registrar(
			RepositorioColaPublicacionInterface::class,
			fn ( Contenedor $c ): RepositorioColaPublicacion => new RepositorioColaPublicacion( $c->obtener( 'wpdb' ) )
		);
		$this->contenedor->registrar(
			RepositorioMetricasSearchConsoleInterface::class,
			fn ( Contenedor $c ): RepositorioMetricasSearchConsole => new RepositorioMetricasSearchConsole(
				$c->obtener( 'wpdb' ),
				$c->obtener( RepositorioPiezasInterface::class )
			)
		);
		$this->contenedor->registrar(
			RepositorioRespuestasComentariosInterface::class,
			fn ( Contenedor $c ): RepositorioRespuestasComentarios => new RepositorioRespuestasComentarios( $c->obtener( 'wpdb' ) )
		);

		$this->contenedor->registrar(
			Transicionador::class,
			fn ( Contenedor $c ): Transicionador => new Transicionador(
				$c->obtener( RepositorioPiezasInterface::class ),
				$c->obtener( RepositorioAuditoriaInterface::class ),
				$c->obtener( RelojInterface::class )
			)
		);

		$this->contenedor->registrar(
			ProveedorTendenciasInterface::class,
			fn ( Contenedor $c ): ProveedorGoogleTrends => new ProveedorGoogleTrends( $c->obtener( RelojInterface::class ) )
		);
		// Registro adicional del tipo concreto (Sala de Máquinas, Cap. 10.2:
		// "estado de cada API conectada") — `circuitoAbierto()` es propio de
		// esta implementación, no del contrato `ProveedorTendenciasInterface`.
		$this->contenedor->registrar(
			ProveedorGoogleTrends::class,
			fn ( Contenedor $c ): ProveedorGoogleTrends => new ProveedorGoogleTrends( $c->obtener( RelojInterface::class ) )
		);
		$this->contenedor->registrar(
			SensorInterface::class,
			fn ( Contenedor $c ): SensorGoogleTrends => new SensorGoogleTrends( $c->obtener( ProveedorTendenciasInterface::class ) )
		);
		$this->contenedor->registrar(
			ProveedorSearchConsoleInterface::class,
			fn ( Contenedor $c ): ProveedorSearchConsole => new ProveedorSearchConsole( $c->obtener( RelojInterface::class ) )
		);
		$this->contenedor->registrar( VerificadorProcedenciaDeclaracion::class, static fn (): VerificadorProcedenciaDeclaracion => new VerificadorProcedenciaDeclaracion() );
		$this->contenedor->registrar(
			ResolutorDisputas::class,
			fn ( Contenedor $c ): ResolutorDisputas => new ResolutorDisputas( $c->obtener( LenguajeInterface::class ) )
		);
		$this->contenedor->registrar(
			DetectorHuecos::class,
			fn ( Contenedor $c ): DetectorHuecos => new DetectorHuecos( $c->obtener( LenguajeInterface::class ) )
		);
		$this->contenedor->registrar(
			ClasificadorGravedadTendencia::class,
			fn ( Contenedor $c ): ClasificadorGravedadTendencia => new ClasificadorGravedadTendencia( $c->obtener( LenguajeInterface::class ) )
		);
		$this->contenedor->registrar(
			EvaluadorLegitimidadInsumo::class,
			static fn (): EvaluadorLegitimidadInsumo => new EvaluadorLegitimidadInsumo()
		);
		$this->contenedor->registrar(
			GestorModoRespeto::class,
			fn ( Contenedor $c ): GestorModoRespeto => new GestorModoRespeto(
				$c->obtener( RepositorioModoRespetoInterface::class ),
				$c->obtener( RepositorioTendenciasInterface::class ),
				$c->obtener( RepositorioColaPublicacionInterface::class ),
				$c->obtener( ProgramadorCadencia::class ),
				$c->obtener( LectorConfiguracionCadencia::class )
			)
		);
		// Imagen destacada por autoridad de fuente (ADR 0006).
		$this->contenedor->registrar( ClasificadorNivelFuente::class, static fn (): ClasificadorNivelFuente => new ClasificadorNivelFuente() );
		$this->contenedor->registrar( ExtractorImagenFuenteInterface::class, static fn (): ExtractorImagenFuente => new ExtractorImagenFuente() );
		$this->contenedor->registrar(
			SelectorImagenPorAutoridad::class,
			fn ( Contenedor $c ): SelectorImagenPorAutoridad => new SelectorImagenPorAutoridad(
				$c->obtener( ClasificadorNivelFuente::class ),
				$c->obtener( ExtractorImagenFuenteInterface::class )
			)
		);
		$this->contenedor->registrar(
			AsignadorImagenDestacadaInterface::class,
			fn ( Contenedor $c ): AsignadorImagenDestacada => new AsignadorImagenDestacada( $c->obtener( SelectorImagenPorAutoridad::class ) )
		);
		$this->contenedor->registrar(
			InvestigadorInterface::class,
			fn ( Contenedor $c ): InvestigadorMecanico => new InvestigadorMecanico(
				$c->obtener( RelojInterface::class ),
				$c->obtener( VerificadorProcedenciaDeclaracion::class )
			)
		);
		$this->contenedor->registrar( CreadorBorradorInterface::class, static fn (): CreadorBorrador => new CreadorBorrador() );

		$this->contenedor->registrar( EnrutadorModelos::class, static fn (): EnrutadorModelos => new EnrutadorModelos() );
		$this->contenedor->registrar(
			PresupuestoLenguaje::class,
			fn ( Contenedor $c ): PresupuestoLenguaje => new PresupuestoLenguaje( $c->obtener( RelojInterface::class ) )
		);
		$this->contenedor->registrar(
			LenguajeInterface::class,
			fn ( Contenedor $c ): ProveedorOpenRouter => new ProveedorOpenRouter(
				$c->obtener( EnrutadorModelos::class ),
				$c->obtener( PresupuestoLenguaje::class ),
				$c->obtener( RelojInterface::class )
			)
		);
		// Registro adicional del tipo concreto (Sala de Máquinas, Cap. 10.2:
		// "estado de cada API conectada" + "prueba en vivo" de la llave) —
		// métodos propios de OpenRouter, no del contrato `LenguajeInterface`.
		$this->contenedor->registrar(
			ProveedorOpenRouter::class,
			fn ( Contenedor $c ): ProveedorOpenRouter => new ProveedorOpenRouter(
				$c->obtener( EnrutadorModelos::class ),
				$c->obtener( PresupuestoLenguaje::class ),
				$c->obtener( RelojInterface::class )
			)
		);
		// EmbeddingsInterface (Nivel Dos A.5 + Nivel Tres J.3): misma cuenta de
		// OpenRouter, mismo circuito, otro endpoint — ver `ProveedorOpenRouter`.
		$this->contenedor->registrar(
			EmbeddingsInterface::class,
			fn ( Contenedor $c ): ProveedorOpenRouter => new ProveedorOpenRouter(
				$c->obtener( EnrutadorModelos::class ),
				$c->obtener( PresupuestoLenguaje::class ),
				$c->obtener( RelojInterface::class )
			)
		);

		$this->contenedor->registrar(
			TelemetriaInterface::class,
			fn ( Contenedor $c ): ProveedorTelemetria => new ProveedorTelemetria(
				$c->obtener( DetectorEntorno::class ),
				$c->obtener( RepositorioPiezasInterface::class ),
				$c->obtener( RepositorioPeriodistasInterface::class ),
				$c->obtener( RelojInterface::class )
			)
		);

		$this->contenedor->registrar(
			ClasificadorNoticia::class,
			fn ( Contenedor $c ): ClasificadorNoticia => new ClasificadorNoticia( $c->obtener( LenguajeInterface::class ) )
		);
		$this->contenedor->registrar(
			AsignadorPeriodista::class,
			fn ( Contenedor $c ): AsignadorPeriodista => new AsignadorPeriodista( $c->obtener( AzarInterface::class ) )
		);
		$this->contenedor->registrar(
			SelectorAngulo::class,
			fn ( Contenedor $c ): SelectorAngulo => new SelectorAngulo( $c->obtener( LenguajeInterface::class ) )
		);
		$this->contenedor->registrar(
			GeneradorEsqueleto::class,
			fn ( Contenedor $c ): GeneradorEsqueleto => new GeneradorEsqueleto( $c->obtener( LenguajeInterface::class ) )
		);
		$this->contenedor->registrar(
			VerificadorFalseabilidad::class,
			fn ( Contenedor $c ): VerificadorFalseabilidad => new VerificadorFalseabilidad( $c->obtener( LenguajeInterface::class ) )
		);
		$this->contenedor->registrar(
			DecisionEditorial::class,
			fn ( Contenedor $c ): DecisionEditorial => new DecisionEditorial(
				$c->obtener( ClasificadorNoticia::class ),
				$c->obtener( AsignadorPeriodista::class ),
				$c->obtener( SelectorAngulo::class ),
				$c->obtener( GeneradorEsqueleto::class ),
				$c->obtener( RepositorioPeriodistasInterface::class ),
				$c->obtener( RepositorioMemoriaEditorialInterface::class ),
				$c->obtener( RepositorioPiezasInterface::class ),
				$c->obtener( RelojInterface::class ),
				$c->obtener( VerificadorFalseabilidad::class ),
				$c->obtener( GestorModoRespeto::class )
			)
		);

		$this->contenedor->registrar( CompiladorDirectrices::class, static fn (): CompiladorDirectrices => new CompiladorDirectrices() );
		$this->contenedor->registrar( VerificadorVoz::class, static fn (): VerificadorVoz => new VerificadorVoz() );
		$this->contenedor->registrar( VerificadorNGramas::class, static fn (): VerificadorNGramas => new VerificadorNGramas() );
		$this->contenedor->registrar( SegmentadorUnidadesFactuales::class, static fn (): SegmentadorUnidadesFactuales => new SegmentadorUnidadesFactuales() );
		$this->contenedor->registrar(
			VerificadorTrazabilidadDeterminista::class,
			fn ( Contenedor $c ): VerificadorTrazabilidadDeterminista => new VerificadorTrazabilidadDeterminista(
				$c->obtener( EmbeddingsInterface::class ),
				$c->obtener( SegmentadorUnidadesFactuales::class )
			)
		);
		$this->contenedor->registrar(
			CorrectorInterno::class,
			fn ( Contenedor $c ): CorrectorInterno => new CorrectorInterno(
				$c->obtener( LenguajeInterface::class ),
				$c->obtener( VerificadorVoz::class ),
				$c->obtener( VerificadorNGramas::class ),
				$c->obtener( VerificadorTrazabilidadDeterminista::class )
			)
		);
		$this->contenedor->registrar(
			GeneradorBloqueEditor::class,
			fn ( Contenedor $c ): GeneradorBloqueEditor => new GeneradorBloqueEditor( $c->obtener( LenguajeInterface::class ) )
		);
		$this->contenedor->registrar( AvisoTransparenciaIa::class, static fn (): AvisoTransparenciaIa => new AvisoTransparenciaIa() );
		$this->contenedor->registrar(
			RedactorSintetico::class,
			fn ( Contenedor $c ): RedactorSintetico => new RedactorSintetico(
				$c->obtener( LenguajeInterface::class ),
				$c->obtener( CompiladorDirectrices::class ),
				$c->obtener( CorrectorInterno::class ),
				$c->obtener( GeneradorBloqueEditor::class ),
				$c->obtener( AvisoTransparenciaIa::class ),
				$c->obtener( RepositorioBorradoresInterface::class ),
				$c->obtener( RelojInterface::class )
			)
		);
		$this->contenedor->registrar( RedactorMecanico::class, static fn (): RedactorMecanico => new RedactorMecanico() );
		$this->contenedor->registrar(
			RedactorInterface::class,
			fn ( Contenedor $c ): RedactorConFallbackMecanico => new RedactorConFallbackMecanico(
				$c->obtener( DecisionEditorial::class ),
				$c->obtener( RedactorSintetico::class ),
				$c->obtener( RedactorMecanico::class ),
				$c->obtener( RepositorioPiezasInterface::class ),
				$c->obtener( RelojInterface::class )
			)
		);

		$this->contenedor->registrar( ExtractorPalabrasClave::class, static fn (): ExtractorPalabrasClave => new ExtractorPalabrasClave() );
		$this->contenedor->registrar(
			GeneradorMetadatosSeo::class,
			fn ( Contenedor $c ): GeneradorMetadatosSeo => new GeneradorMetadatosSeo( $c->obtener( LenguajeInterface::class ) )
		);
		$this->contenedor->registrar( DetectorPluginSeo::class, static fn (): DetectorPluginSeo => new DetectorPluginSeo() );
		$this->contenedor->registrar(
			EnlazadorInterno::class,
			fn ( Contenedor $c ): EnlazadorInterno => new EnlazadorInterno(
				$c->obtener( RepositorioMemoriaEditorialInterface::class ),
				$c->obtener( RepositorioPiezasInterface::class )
			)
		);
		$this->contenedor->registrar(
			AuditorCanibalizacion::class,
			fn ( Contenedor $c ): AuditorCanibalizacion => new AuditorCanibalizacion( $c->obtener( RepositorioPiezasInterface::class ) )
		);
		$this->contenedor->registrar(
			MotorSeo::class,
			fn ( Contenedor $c ): MotorSeo => new MotorSeo(
				$c->obtener( ExtractorPalabrasClave::class ),
				$c->obtener( GeneradorMetadatosSeo::class ),
				$c->obtener( DetectorPluginSeo::class ),
				$c->obtener( EnlazadorInterno::class ),
				$c->obtener( AuditorCanibalizacion::class )
			)
		);

		$this->contenedor->registrar( ExtractorEntidades::class, static fn (): ExtractorEntidades => new ExtractorEntidades() );
		$this->contenedor->registrar( ReconciliadorVocabulario::class, static fn (): ReconciliadorVocabulario => new ReconciliadorVocabulario() );
		$this->contenedor->registrar(
			AsignadorCategoria::class,
			fn ( Contenedor $c ): AsignadorCategoria => new AsignadorCategoria(
				$c->obtener( ReconciliadorVocabulario::class ),
				$c->obtener( RepositorioVocabularioInterface::class )
			)
		);
		$this->contenedor->registrar(
			GestorEtiquetas::class,
			fn ( Contenedor $c ): GestorEtiquetas => new GestorEtiquetas(
				$c->obtener( ExtractorEntidades::class ),
				$c->obtener( ReconciliadorVocabulario::class ),
				$c->obtener( RepositorioVocabularioInterface::class ),
				$c->obtener( RelojInterface::class )
			)
		);
		$this->contenedor->registrar(
			Taxonomo::class,
			fn ( Contenedor $c ): Taxonomo => new Taxonomo(
				$c->obtener( AsignadorCategoria::class ),
				$c->obtener( GestorEtiquetas::class )
			)
		);

		$this->contenedor->registrar( VerificadorLegibilidad::class, static fn (): VerificadorLegibilidad => new VerificadorLegibilidad() );
		$this->contenedor->registrar(
			CompuertaCalidad::class,
			fn ( Contenedor $c ): CompuertaCalidad => new CompuertaCalidad( $c->obtener( VerificadorLegibilidad::class ) )
		);
		$this->contenedor->registrar(
			CompuertaRiesgo::class,
			fn ( Contenedor $c ): CompuertaRiesgo => new CompuertaRiesgo( $c->obtener( LenguajeInterface::class ) )
		);
		$this->contenedor->registrar( CompuertaOriginalidad::class, static fn (): CompuertaOriginalidad => new CompuertaOriginalidad() );
		$this->contenedor->registrar( GestorDegradacion::class, static fn (): GestorDegradacion => new GestorDegradacion() );
		$this->contenedor->registrar(
			EvaluadorCompuertas::class,
			fn ( Contenedor $c ): EvaluadorCompuertas => new EvaluadorCompuertas(
				$c->obtener( CompuertaCalidad::class ),
				$c->obtener( CompuertaRiesgo::class ),
				$c->obtener( CompuertaOriginalidad::class ),
				$c->obtener( GestorDegradacion::class )
			)
		);

		$this->contenedor->registrar( LectorConfiguracionCadencia::class, static fn (): LectorConfiguracionCadencia => new LectorConfiguracionCadencia() );
		$this->contenedor->registrar(
			ProgramadorCadencia::class,
			fn ( Contenedor $c ): ProgramadorCadencia => new ProgramadorCadencia( $c->obtener( AzarInterface::class ) )
		);
		$this->contenedor->registrar( EscritorCamposSeo::class, static fn (): EscritorCamposSeo => new EscritorCamposSeo() );
		$this->contenedor->registrar( AsignadorTaxonomiaWp::class, static fn (): AsignadorTaxonomiaWp => new AsignadorTaxonomiaWp() );
		$this->contenedor->registrar(
			PublicadorInterface::class,
			fn ( Contenedor $c ): Publicador => new Publicador(
				$c->obtener( EscritorCamposSeo::class ),
				$c->obtener( AsignadorTaxonomiaWp::class )
			)
		);

		$this->contenedor->registrar(
			ComparadorHistorias::class,
			fn ( Contenedor $c ): ComparadorHistorias => new ComparadorHistorias(
				$c->obtener( LenguajeInterface::class ),
				$c->obtener( PresupuestoLenguaje::class )
			)
		);
		$this->contenedor->registrar( LectorComentariosInterface::class, static fn (): LectorComentarios => new LectorComentarios() );
		$this->contenedor->registrar( PublicadorComentarioInterface::class, static fn (): PublicadorComentario => new PublicadorComentario() );
		$this->contenedor->registrar( VerificadorComentarioSustantivo::class, static fn (): VerificadorComentarioSustantivo => new VerificadorComentarioSustantivo() );
		$this->contenedor->registrar(
			AnalizadorAudiencia::class,
			fn ( Contenedor $c ): AnalizadorAudiencia => new AnalizadorAudiencia(
				$c->obtener( LenguajeInterface::class ),
				$c->obtener( PresupuestoLenguaje::class )
			)
		);
		$this->contenedor->registrar(
			GeneradorRespuestaComentario::class,
			fn ( Contenedor $c ): GeneradorRespuestaComentario => new GeneradorRespuestaComentario( $c->obtener( LenguajeInterface::class ) )
		);
		$this->contenedor->registrar(
			Orquestador::class,
			fn ( Contenedor $c ): Orquestador => new Orquestador(
				$c->obtener( CandadoGlobalInterface::class ),
				$c->obtener( RepositorioBitacoraInterface::class ),
				$c->obtener( RepositorioPiezasInterface::class ),
				$c->obtener( RepositorioTendenciasInterface::class ),
				$c->obtener( RepositorioBorradoresInterface::class ),
				$c->obtener( RepositorioColaPublicacionInterface::class ),
				$c->obtener( Transicionador::class ),
				$c->obtener( SensorInterface::class ),
				$c->obtener( InvestigadorInterface::class ),
				$c->obtener( RedactorInterface::class ),
				$c->obtener( MotorSeo::class ),
				$c->obtener( Taxonomo::class ),
				$c->obtener( EvaluadorCompuertas::class ),
				$c->obtener( LectorConfiguracionCadencia::class ),
				$c->obtener( ProgramadorCadencia::class ),
				$c->obtener( CreadorBorradorInterface::class ),
				$c->obtener( PublicadorInterface::class ),
				$c->obtener( ComparadorHistorias::class ),
				$c->obtener( LectorComentariosInterface::class ),
				$c->obtener( AnalizadorAudiencia::class ),
				$c->obtener( GeneradorRespuestaComentario::class ),
				$c->obtener( VerificadorComentarioSustantivo::class ),
				$c->obtener( RepositorioMemoriaEditorialInterface::class ),
				$c->obtener( RepositorioRespuestasComentariosInterface::class ),
				$c->obtener( RepositorioPeriodistasInterface::class ),
				$c->obtener( RelojInterface::class ),
				$c->obtener( ResolutorDisputas::class ),
				$c->obtener( DetectorHuecos::class ),
				$c->obtener( ClasificadorGravedadTendencia::class ),
				$c->obtener( GestorModoRespeto::class ),
				$c->obtener( AsignadorImagenDestacadaInterface::class ),
				$c->obtener( EvaluadorLegitimidadInsumo::class ),
				$c->obtener( GestorHistorias::class )
			)
		);

		$this->contenedor->registrar(
			RestOrquestador::class,
			fn ( Contenedor $c ): RestOrquestador => new RestOrquestador( $c->obtener( Orquestador::class ) )
		);

		$this->contenedor->registrar(
			RestModoRespeto::class,
			fn ( Contenedor $c ): RestModoRespeto => new RestModoRespeto(
				$c->obtener( GestorModoRespeto::class ),
				$c->obtener( RelojInterface::class )
			)
		);

		$this->contenedor->registrar( RestImagenDestacada::class, static fn (): RestImagenDestacada => new RestImagenDestacada() );

		$this->contenedor->registrar(
			GestorSalaRevision::class,
			fn ( Contenedor $c ): GestorSalaRevision => new GestorSalaRevision(
				$c->obtener( RepositorioPiezasInterface::class ),
				$c->obtener( RepositorioColaPublicacionInterface::class ),
				$c->obtener( Transicionador::class )
			)
		);
		$this->contenedor->registrar(
			RestSalaRevision::class,
			function ( Contenedor $c ): RestSalaRevision {
				$ventanaVetoHoras = get_option( Orquestador::OPCION_VENTANA_VETO_HORAS, 2 );

				return new RestSalaRevision(
					$c->obtener( GestorSalaRevision::class ),
					$c->obtener( RepositorioTendenciasInterface::class ),
					$c->obtener( RepositorioPeriodistasInterface::class ),
					$c->obtener( RepositorioBorradoresInterface::class ),
					is_numeric( $ventanaVetoHoras ) ? (int) $ventanaVetoHoras : 2
				);
			}
		);
		$this->contenedor->registrar( NotificadorRevision::class, static fn (): NotificadorRevision => new NotificadorRevision() );
		$this->contenedor->registrar( NotificadorSinPeriodistaIdoneo::class, static fn (): NotificadorSinPeriodistaIdoneo => new NotificadorSinPeriodistaIdoneo() );

		$this->contenedor->registrar(
			GestorHistorias::class,
			fn ( Contenedor $c ): GestorHistorias => new GestorHistorias(
				$c->obtener( RepositorioHistoriasInterface::class ),
				$c->obtener( RepositorioPiezasInterface::class ),
				$c->obtener( RelojInterface::class )
			)
		);
		$this->contenedor->registrar(
			GestorSalaTendencias::class,
			fn ( Contenedor $c ): GestorSalaTendencias => new GestorSalaTendencias(
				$c->obtener( RepositorioTendenciasInterface::class ),
				$c->obtener( RepositorioPiezasInterface::class ),
				$c->obtener( Transicionador::class ),
				$c->obtener( RelojInterface::class ),
				$c->obtener( GestorHistorias::class )
			)
		);
		$this->contenedor->registrar(
			RestSalaTendencias::class,
			fn ( Contenedor $c ): RestSalaTendencias => new RestSalaTendencias( $c->obtener( GestorSalaTendencias::class ) )
		);

		$this->contenedor->registrar(
			GestorRespuestasComentarios::class,
			fn ( Contenedor $c ): GestorRespuestasComentarios => new GestorRespuestasComentarios(
				$c->obtener( RepositorioRespuestasComentariosInterface::class ),
				$c->obtener( RepositorioPiezasInterface::class ),
				$c->obtener( RepositorioPeriodistasInterface::class ),
				$c->obtener( PublicadorComentarioInterface::class ),
				$c->obtener( RelojInterface::class )
			)
		);
		$this->contenedor->registrar(
			RestRespuestasComentarios::class,
			fn ( Contenedor $c ): RestRespuestasComentarios => new RestRespuestasComentarios( $c->obtener( GestorRespuestasComentarios::class ) )
		);

		$this->contenedor->registrar(
			RestMesaEditorial::class,
			fn ( Contenedor $c ): RestMesaEditorial => new RestMesaEditorial(
				$c->obtener( RepositorioPiezasInterface::class ),
				$c->obtener( RepositorioTendenciasInterface::class ),
				$c->obtener( RepositorioPeriodistasInterface::class ),
				$c->obtener( RepositorioBorradoresInterface::class ),
				$c->obtener( GestorSalaRevision::class ),
				$c->obtener( RelojInterface::class )
			)
		);

		$this->contenedor->registrar(
			RestPortada::class,
			fn ( Contenedor $c ): RestPortada => new RestPortada(
				$c->obtener( RepositorioPiezasInterface::class ),
				$c->obtener( RepositorioTendenciasInterface::class ),
				$c->obtener( RepositorioColaPublicacionInterface::class ),
				$c->obtener( RepositorioBitacoraInterface::class ),
				$c->obtener( RepositorioRespuestasComentariosInterface::class ),
				$c->obtener( LectorConfiguracionCadencia::class ),
				$c->obtener( PresupuestoLenguaje::class ),
				$c->obtener( RelojInterface::class )
			)
		);

		$this->contenedor->registrar(
			ExportadorBancoPeriodistas::class,
			fn ( Contenedor $c ): ExportadorBancoPeriodistas => new ExportadorBancoPeriodistas(
				$c->obtener( RepositorioPeriodistasInterface::class ),
				$c->obtener( RepositorioMemoriaEditorialInterface::class ),
				$c->obtener( RelojInterface::class )
			)
		);
		$this->contenedor->registrar(
			ImportadorBancoPeriodistas::class,
			fn ( Contenedor $c ): ImportadorBancoPeriodistas => new ImportadorBancoPeriodistas(
				$c->obtener( RepositorioPeriodistasInterface::class ),
				$c->obtener( RepositorioMemoriaEditorialInterface::class ),
				$c->obtener( RelojInterface::class )
			)
		);
		$this->contenedor->registrar(
			RestBancoPeriodistas::class,
			fn ( Contenedor $c ): RestBancoPeriodistas => new RestBancoPeriodistas(
				$c->obtener( ExportadorBancoPeriodistas::class ),
				$c->obtener( ImportadorBancoPeriodistas::class )
			)
		);

		$this->contenedor->registrar(
			GeneradorVistaPrevia::class,
			fn ( Contenedor $c ): GeneradorVistaPrevia => new GeneradorVistaPrevia( $c->obtener( LenguajeInterface::class ) )
		);
		$this->contenedor->registrar(
			RestPeriodistas::class,
			fn ( Contenedor $c ): RestPeriodistas => new RestPeriodistas(
				$c->obtener( RepositorioPeriodistasInterface::class ),
				$c->obtener( RepositorioPiezasInterface::class ),
				$c->obtener( RepositorioMemoriaEditorialInterface::class ),
				$c->obtener( GeneradorVistaPrevia::class ),
				$c->obtener( RelojInterface::class )
			)
		);
		$this->contenedor->registrar(
			ExportadorDiagnostico::class,
			fn ( Contenedor $c ): ExportadorDiagnostico => new ExportadorDiagnostico(
				$c->obtener( DetectorEntorno::class ),
				$c->obtener( DetectorConflictos::class ),
				$c->obtener( RepositorioBitacoraInterface::class ),
				$c->obtener( RelojInterface::class )
			)
		);
		$this->contenedor->registrar(
			RestSalaMaquinas::class,
			fn ( Contenedor $c ): RestSalaMaquinas => new RestSalaMaquinas(
				$c->obtener( RepositorioBitacoraInterface::class ),
				$c->obtener( PresupuestoLenguaje::class ),
				$c->obtener( ProveedorOpenRouter::class ),
				$c->obtener( ProveedorGoogleTrends::class ),
				$c->obtener( TelemetriaInterface::class ),
				$c->obtener( ExportadorDiagnostico::class )
			)
		);
		$this->contenedor->registrar(
			RestEstudioSeo::class,
			fn ( Contenedor $c ): RestEstudioSeo => new RestEstudioSeo(
				$c->obtener( RepositorioPiezasInterface::class ),
				$c->obtener( RepositorioVocabularioInterface::class ),
				$c->obtener( ReconciliadorVocabulario::class )
			)
		);
		$this->contenedor->registrar(
			RestOnboarding::class,
			fn ( Contenedor $c ): RestOnboarding => new RestOnboarding(
				$c->obtener( DetectorEntorno::class ),
				$c->obtener( Orquestador::class ),
				$c->obtener( RepositorioVocabularioInterface::class ),
				$c->obtener( RelojInterface::class )
			)
		);
		$this->contenedor->registrar(
			RestSearchConsole::class,
			fn ( Contenedor $c ): RestSearchConsole => new RestSearchConsole(
				$c->obtener( ProveedorSearchConsoleInterface::class ),
				$c->obtener( RepositorioMetricasSearchConsoleInterface::class ),
				$c->obtener( RelojInterface::class )
			)
		);

		$this->contenedor->registrar(
			RestInformesEditoriales::class,
			fn ( Contenedor $c ): RestInformesEditoriales => new RestInformesEditoriales(
				$c->obtener( RepositorioPiezasInterface::class ),
				$c->obtener( RepositorioTendenciasInterface::class ),
				$c->obtener( RepositorioBitacoraInterface::class ),
				$c->obtener( RepositorioRespuestasComentariosInterface::class ),
				$c->obtener( RepositorioMemoriaEditorialInterface::class ),
				$c->obtener( RepositorioPeriodistasInterface::class ),
				$c->obtener( RelojInterface::class )
			)
		);
		$this->contenedor->registrar( RestTransparencia::class, static fn (): RestTransparencia => new RestTransparencia() );
		$this->contenedor->registrar( RestRiesgoLegal::class, static fn (): RestRiesgoLegal => new RestRiesgoLegal() );
		$this->contenedor->registrar( RestModeloVerificador::class, static fn (): RestModeloVerificador => new RestModeloVerificador() );
		$this->contenedor->registrar( ConstructorEsquemaNewsArticle::class, static fn (): ConstructorEsquemaNewsArticle => new ConstructorEsquemaNewsArticle() );
		$this->contenedor->registrar(
			EmisorEsquemaFrontend::class,
			fn ( Contenedor $c ): EmisorEsquemaFrontend => new EmisorEsquemaFrontend(
				$c->obtener( ConstructorEsquemaNewsArticle::class ),
				$c->obtener( RepositorioPeriodistasInterface::class )
			)
		);
		$this->contenedor->registrar( DeclaracionIdentidadSintetica::class, static fn (): DeclaracionIdentidadSintetica => new DeclaracionIdentidadSintetica() );
		$this->contenedor->registrar(
			PaginaAutorPeriodista::class,
			fn ( Contenedor $c ): PaginaAutorPeriodista => new PaginaAutorPeriodista(
				$c->obtener( RepositorioPeriodistasInterface::class ),
				$c->obtener( DeclaracionIdentidadSintetica::class )
			)
		);
		$this->contenedor->registrar(
			HistoriaHub::class,
			fn ( Contenedor $c ): HistoriaHub => new HistoriaHub(
				$c->obtener( GestorHistorias::class ),
				$c->obtener( RepositorioPeriodistasInterface::class )
			)
		);
	}

	public function arrancar( string $archivoPrincipalPlugin, string $versionEsquemaObjetivo ): void {
		Activador::actualizarEsquemaSiHaceFalta( $this->contenedor->obtener( RelojInterface::class ), $versionEsquemaObjetivo );

		load_plugin_textdomain(
			'pluma-engine',
			false,
			dirname( plugin_basename( $archivoPrincipalPlugin ) ) . '/languages'
		);

		( new PantallaPanel( $this->contenedor->obtener( DetectorEntorno::class ) ) )->registrar();
		$this->contenedor->obtener( RestOrquestador::class )->registrar();
		$this->contenedor->obtener( RestModoRespeto::class )->registrar();
		$this->contenedor->obtener( RestImagenDestacada::class )->registrar();
		$this->contenedor->obtener( RestBancoPeriodistas::class )->registrar();
		$this->contenedor->obtener( RestSalaRevision::class )->registrar();
		$this->contenedor->obtener( NotificadorRevision::class )->registrar();
		$this->contenedor->obtener( NotificadorSinPeriodistaIdoneo::class )->registrar();
		$this->contenedor->obtener( RestPortada::class )->registrar();
		$this->contenedor->obtener( RestSalaTendencias::class )->registrar();
		$this->contenedor->obtener( RestMesaEditorial::class )->registrar();
		$this->contenedor->obtener( RestPeriodistas::class )->registrar();
		$this->contenedor->obtener( RestSalaMaquinas::class )->registrar();
		$this->contenedor->obtener( RestEstudioSeo::class )->registrar();
		$this->contenedor->obtener( RestOnboarding::class )->registrar();
		$this->contenedor->obtener( RestSearchConsole::class )->registrar();
		$this->contenedor->obtener( RestRespuestasComentarios::class )->registrar();
		$this->contenedor->obtener( RestInformesEditoriales::class )->registrar();
		$this->contenedor->obtener( RestTransparencia::class )->registrar();
		$this->contenedor->obtener( RestRiesgoLegal::class )->registrar();
		$this->contenedor->obtener( RestModeloVerificador::class )->registrar();
		$this->contenedor->obtener( EmisorEsquemaFrontend::class )->registrar();
		$this->contenedor->obtener( PaginaAutorPeriodista::class )->registrar();
		$this->contenedor->obtener( HistoriaHub::class )->registrar();
	}
}
