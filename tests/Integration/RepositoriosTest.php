<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Compuertas\ActivadorModoRespeto;
use Pluma\Compuertas\DiagnosticoCalidad;
use Pluma\Compuertas\DiagnosticoOriginalidad;
use Pluma\Compuertas\DiagnosticoRiesgo;
use Pluma\Compuertas\ModoOperacion;
use Pluma\Compuertas\ResultadoEvaluacion;
use Pluma\Datos\RepositorioBitacora;
use Pluma\Datos\RepositorioModoRespeto;
use Pluma\Datos\RepositorioPiezas;
use Pluma\Datos\RepositorioTendencias;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Kernel\RelojSistema;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Seo\DatosSeo;
use Pluma\Seo\EnlaceInterno;
use Pluma\Seo\MetadatosSeo;
use Pluma\Seo\PalabrasClave;
use Pluma\Seo\TipoEsquemaArticulo;
use Pluma\Seo\TipoPluginSeo;
use Pluma\Sensores\DiagnosticoLegitimidadInsumo;
use Pluma\Sensores\EstadoTendencia;
use Pluma\Sensores\PuntuacionOportunidad;
use Pluma\Sensores\TendenciaDetectada;
use Pluma\Taxonomia\EtiquetaAsignada;
use Pluma\Taxonomia\ResultadoTaxonomia;
use WP_UnitTestCase;

/**
 * Repositorios `pluma_tendencias`/`pluma_piezas` contra tablas reales
 * (pl-testing: "Integración (wp-env): repositorios pluma_*").
 *
 * @covers \Pluma\Datos\RepositorioPiezas
 * @covers \Pluma\Datos\RepositorioTendencias
 * @covers \Pluma\Datos\RepositorioBitacora
 * @covers \Pluma\Datos\RepositorioModoRespeto
 */
final class RepositoriosTest extends WP_UnitTestCase {

	public function test_guardar_y_deduplicar_tendencias(): void {
		global $wpdb;
		$repo  = new RepositorioTendencias( $wpdb );
		$reloj = new RelojSistema();

		$tendencia = new TendenciaDetectada(
			'Una Tendencia',
			PuntuacionOportunidad::calcular( 70, 70 ),
			$reloj->ahora(),
			array(
				array(
					'titulo' => 'Artículo',
					'url'    => 'https://example.com/a',
					'fuente' => 'Example',
				),
			),
			'google_trends'
		);

		self::assertFalse( $repo->existePorTermino( 'una tendencia', 'google_trends' ) );

		$id = $repo->guardar( $tendencia, $reloj->ahora() );

		self::assertGreaterThan( 0, $id );
		self::assertTrue( $repo->existePorTermino( 'UNA TENDENCIA', 'google_trends' ) );

		$datos = $repo->obtenerPorId( $id );
		self::assertNotNull( $datos );
		self::assertSame( 'una tendencia', $datos['termino'] );
		self::assertSame( 'Example', $datos['articulosRelacionados'][0]['fuente'] );
	}

	/**
	 * Bug real encontrado en producción (`localhost:8888`, "Cubrir ahora"
	 * fallaba con 404 sobre una tendencia recién detectada): `$wpdb->update()`
	 * reporta 0 filas afectadas cuando el UPDATE no cambia ningún valor (sin
	 * `CLIENT_FOUND_ROWS`), aunque la fila exista y el `WHERE` haya
	 * coincidido — una tendencia ya en `EN_PIPELINE` (el estado por defecto
	 * de toda tendencia recién detectada) nunca podía "cubrirse ahora" porque
	 * el propio código confundía "0 filas afectadas" con "no encontrada".
	 */
	public function test_actualizar_estado_a_un_valor_ya_vigente_no_se_confunde_con_no_encontrada(): void {
		global $wpdb;
		$repo  = new RepositorioTendencias( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->guardar(
			new TendenciaDetectada( 'tendencia idempotente', PuntuacionOportunidad::calcular( 70, 70 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);

		self::assertTrue( $repo->actualizarEstadoTendencia( $id, EstadoTendencia::EnPipeline ) );
		// Segunda llamada al MISMO estado: el UPDATE es un no-op para MySQL
		// (0 filas afectadas), pero la fila sigue existiendo — debe seguir
		// devolviendo true, no confundirse con "no encontrada".
		self::assertTrue( $repo->actualizarEstadoTendencia( $id, EstadoTendencia::EnPipeline ) );

		self::assertFalse( $repo->actualizarEstadoTendencia( 999999, EstadoTendencia::EnPipeline ) );
	}

	/**
	 * Nivel Dos F.1-F.2: `actualizarGravedad()` persiste la clasificación, y
	 * `obtenerGravedadMaximaRecientes()` — el material crudo del disparador
	 * automático del modo respeto — filtra por umbral y ventana de tiempo
	 * real usando el nuevo índice `gravedad`.
	 */
	public function test_actualizar_y_consultar_gravedad_de_tendencias_recientes(): void {
		global $wpdb;
		$repo  = new RepositorioTendencias( $wpdb );
		$reloj = new RelojSistema();

		$idGrave = $repo->guardar(
			new TendenciaDetectada( 'tendencia grave', PuntuacionOportunidad::calcular( 70, 70 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);
		$idLeve  = $repo->guardar(
			new TendenciaDetectada( 'tendencia leve', PuntuacionOportunidad::calcular( 70, 70 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);

		self::assertSame( array(), $repo->obtenerGravedadMaximaRecientes( 90, $reloj->ahora()->modify( '-1 hour' ) ) );

		$repo->actualizarGravedad( $idGrave, 95, 'atentado', 'Francia' );
		$repo->actualizarGravedad( $idLeve, 10, 'cultura_viral', null );

		$recientes = $repo->obtenerGravedadMaximaRecientes( 90, $reloj->ahora()->modify( '-1 hour' ) );

		self::assertCount( 1, $recientes );
		self::assertSame( $idGrave, $recientes[0]['id'] );
		self::assertSame( 'atentado', $recientes[0]['campoTematico'] );
		self::assertSame( 'Francia', $recientes[0]['campoGeografico'] );

		self::assertSame( array(), $repo->obtenerGravedadMaximaRecientes( 90, $reloj->ahora()->modify( '+1 hour' ) ) );
	}

	/**
	 * Nivel Dos F.1-F.3: el registro histórico append-only — el estado
	 * actual es siempre la fila más reciente sin `desactivado_en`, y
	 * `desactivar()` la cierra sin tocar activaciones ya cerradas.
	 */
	public function test_modo_respeto_activa_y_desactiva_contra_datos_reales(): void {
		global $wpdb;
		$repo  = new RepositorioModoRespeto( $wpdb );
		$reloj = new RelojSistema();

		self::assertFalse( $repo->estadoActual()->activo );
		self::assertFalse( $repo->desactivar( $reloj->ahora() ) );

		$repo->activar( ActivadorModoRespeto::Automatico, 'disparador automático', 6.0, $reloj->ahora() );

		$estado = $repo->estadoActual();
		self::assertTrue( $estado->activo );
		self::assertSame( ActivadorModoRespeto::Automatico, $estado->activadoPor );
		self::assertSame( 'disparador automático', $estado->motivo );
		self::assertNotNull( $estado->puedeDesactivarseDesde );

		self::assertTrue( $repo->desactivar( $reloj->ahora() ) );
		self::assertFalse( $repo->estadoActual()->activo );

		// Reactivar tras desactivar deja un segundo registro histórico
		// independiente — la vieja fila cerrada no interfiere.
		$repo->activar( ActivadorModoRespeto::Manual, 'reactivado por el editor', 1.0, $reloj->ahora() );
		$estado2 = $repo->estadoActual();
		self::assertTrue( $estado2->activo );
		self::assertSame( ActivadorModoRespeto::Manual, $estado2->activadoPor );
	}

	public function test_crear_pieza_y_avanzarla_por_el_grafo_con_candado_optimista(): void {
		global $wpdb;
		$repoTendencias = new RepositorioTendencias( $wpdb );
		$repoPiezas     = new RepositorioPiezas( $wpdb );
		$reloj          = new RelojSistema();

		$tendenciaId = $repoTendencias->guardar(
			new TendenciaDetectada( 'otra tendencia', PuntuacionOportunidad::calcular( 50, 50 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);

		$piezaId = $repoPiezas->crear( $tendenciaId, $reloj->ahora() );
		$pieza   = $repoPiezas->obtenerPorId( $piezaId );

		self::assertNotNull( $pieza );
		self::assertSame( EstadoPieza::Detectada, $pieza->estado );
		self::assertNull( $pieza->postId );

		// Candado optimista: la transición desde el estado REAL funciona...
		self::assertTrue( $repoPiezas->actualizarEstado( $piezaId, EstadoPieza::Detectada, EstadoPieza::EnInvestigacion, $reloj->ahora() ) );
		// ...pero reintentar la MISMA transición (estado ya avanzó) falla silenciosamente.
		self::assertFalse( $repoPiezas->actualizarEstado( $piezaId, EstadoPieza::Detectada, EstadoPieza::EnInvestigacion, $reloj->ahora() ) );

		$expediente = new Expediente(
			'otra tendencia',
			array(
				new HechoFuente( 'un hecho', 'https://example.com', $reloj->ahora(), NivelVerificacion::Atribuido ),
			)
		);
		self::assertTrue( $repoPiezas->actualizarExpediente( $piezaId, $expediente, $reloj->ahora() ) );
		self::assertTrue( $repoPiezas->actualizarPostId( $piezaId, 999, $reloj->ahora() ) );

		$piezaActualizada = $repoPiezas->obtenerPorId( $piezaId );
		self::assertSame( EstadoPieza::EnInvestigacion, $piezaActualizada->estado );
		self::assertSame( 999, $piezaActualizada->postId );
		self::assertNotNull( $piezaActualizada->expediente );
		self::assertSame( 'un hecho', $piezaActualizada->expediente->hechos[0]->extracto );

		$enDetectada = $repoPiezas->obtenerPorEstado( EstadoPieza::Detectada, 10 );
		self::assertNotContains( $piezaId, array_map( static fn ( $p ) => $p->id, $enDetectada ) );
	}

	public function test_persistir_y_rehidratar_el_resultado_de_compuertas(): void {
		global $wpdb;
		$repoTendencias = new RepositorioTendencias( $wpdb );
		$repoPiezas     = new RepositorioPiezas( $wpdb );
		$reloj          = new RelojSistema();

		$tendenciaId = $repoTendencias->guardar(
			new TendenciaDetectada( 'tendencia compuertas', PuntuacionOportunidad::calcular( 50, 50 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);
		$piezaId     = $repoPiezas->crear( $tendenciaId, $reloj->ahora() );

		$resultado = new ResultadoEvaluacion(
			false,
			true,
			array( 'riesgoDifamacion' ),
			ModoOperacion::Copiloto,
			new DiagnosticoCalidad( 82, 70, true, true, array() ),
			new DiagnosticoRiesgo( true, false, false, false, true, 'acusación sin atribución', false, null ),
			new DiagnosticoOriginalidad( false, false, 0.6, 0.4 )
		);

		self::assertTrue( $repoPiezas->actualizarResultadoCompuertas( $piezaId, $resultado, $reloj->ahora() ) );

		$piezaActualizada = $repoPiezas->obtenerPorId( $piezaId );
		self::assertNotNull( $piezaActualizada->resultadoCompuertas );
		self::assertSame( ModoOperacion::Copiloto, $piezaActualizada->resultadoCompuertas->modoEfectivo );
		self::assertTrue( $piezaActualizada->resultadoCompuertas->retenida );
		self::assertSame( 'acusación sin atribución', $piezaActualizada->resultadoCompuertas->riesgo->detalleDifamacion );
		self::assertSame( 82, $piezaActualizada->resultadoCompuertas->calidad->puntuacionTotal );
	}

	public function test_persistir_y_rehidratar_datos_seo_y_auditar_canibalizacion(): void {
		global $wpdb;
		$repoTendencias = new RepositorioTendencias( $wpdb );
		$repoPiezas     = new RepositorioPiezas( $wpdb );
		$reloj          = new RelojSistema();

		$crearPieza = function () use ( $repoTendencias, $repoPiezas, $reloj ): int {
			$tendenciaId = $repoTendencias->guardar(
				new TendenciaDetectada( 'tendencia seo ' . uniqid(), PuntuacionOportunidad::calcular( 50, 50 ), $reloj->ahora(), array(), 'google_trends' ),
				$reloj->ahora()
			);

			return $repoPiezas->crear( $tendenciaId, $reloj->ahora() );
		};

		$piezaId = $crearPieza();

		$datosSeo = new DatosSeo(
			new PalabrasClave( 'reforma pensional', array( 'gobierno', 'aportes' ) ),
			new MetadatosSeo( 'Titulo SEO', 'Meta descripción' ),
			TipoEsquemaArticulo::AnalysisNewsArticle,
			TipoPluginSeo::RankMath,
			array( new EnlaceInterno( 7, 'https://example.com/7', 'Pieza relacionada' ) ),
			false
		);

		self::assertTrue( $repoPiezas->actualizarDatosSeo( $piezaId, $datosSeo, $reloj->ahora() ) );

		$piezaActualizada = $repoPiezas->obtenerPorId( $piezaId );
		self::assertNotNull( $piezaActualizada->datosSeo );
		self::assertSame( 'reforma pensional', $piezaActualizada->datosSeo->palabrasClave->principal );
		self::assertSame( TipoPluginSeo::RankMath, $piezaActualizada->datosSeo->pluginDetectado );
		self::assertSame( 7, $piezaActualizada->datosSeo->enlacesInternos[0]->postId );

		// Todavía no hay ninguna pieza PUBLICADA con esta keyword: sin canibalización.
		self::assertFalse( $repoPiezas->existePiezaPublicadaConKeyword( 'reforma pensional', 0 ) );

		// Otra pieza distinta, publicada, con la MISMA keyword principal: canibalización real.
		$otraPiezaId = $crearPieza();
		self::assertTrue( $repoPiezas->actualizarEstado( $otraPiezaId, EstadoPieza::Detectada, EstadoPieza::EnInvestigacion, $reloj->ahora() ) );
		$estadosIntermedios = array(
			EstadoPieza::Investigada,
			EstadoPieza::EnRedaccion,
			EstadoPieza::Redactada,
			EstadoPieza::Optimizada,
			EstadoPieza::EnRevision,
			EstadoPieza::Aprobada,
			EstadoPieza::Programada,
			EstadoPieza::Publicada,
		);
		$estadoAnterior     = EstadoPieza::EnInvestigacion;
		foreach ( $estadosIntermedios as $estadoSiguiente ) {
			self::assertTrue( $repoPiezas->actualizarEstado( $otraPiezaId, $estadoAnterior, $estadoSiguiente, $reloj->ahora() ) );
			$estadoAnterior = $estadoSiguiente;
		}
		self::assertTrue( $repoPiezas->actualizarDatosSeo( $otraPiezaId, $datosSeo, $reloj->ahora() ) );

		self::assertTrue( $repoPiezas->existePiezaPublicadaConKeyword( 'reforma pensional', $piezaId ) );
		// La propia pieza publicada se excluye de su propia auditoría.
		self::assertFalse( $repoPiezas->existePiezaPublicadaConKeyword( 'reforma pensional', $otraPiezaId ) );
	}

	public function test_persistir_y_rehidratar_el_resultado_de_taxonomia(): void {
		global $wpdb;
		$repoTendencias = new RepositorioTendencias( $wpdb );
		$repoPiezas     = new RepositorioPiezas( $wpdb );
		$reloj          = new RelojSistema();

		$tendenciaId = $repoTendencias->guardar(
			new TendenciaDetectada( 'tendencia taxonomia', PuntuacionOportunidad::calcular( 50, 50 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);
		$piezaId     = $repoPiezas->crear( $tendenciaId, $reloj->ahora() );

		$resultadoTaxonomia = new ResultadoTaxonomia(
			'Economía',
			array( new EtiquetaAsignada( 1, 'Banco de la República', false, false ) )
		);

		self::assertTrue( $repoPiezas->actualizarResultadoTaxonomia( $piezaId, $resultadoTaxonomia, $reloj->ahora() ) );

		$piezaActualizada = $repoPiezas->obtenerPorId( $piezaId );
		self::assertNotNull( $piezaActualizada->resultadoTaxonomia );
		self::assertSame( 'Economía', $piezaActualizada->resultadoTaxonomia->categoriaAsignada );
		self::assertSame( 'Banco de la República', $piezaActualizada->resultadoTaxonomia->etiquetas[0]->nombre );
	}

	public function test_obtener_recientes_ordena_las_tendencias_por_puntuacion_total(): void {
		global $wpdb;
		$repo  = new RepositorioTendencias( $wpdb );
		$reloj = new RelojSistema();

		$repo->guardar(
			new TendenciaDetectada( 'tendencia baja', PuntuacionOportunidad::calcular( 10, 10 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);
		$idAlta = $repo->guardar(
			new TendenciaDetectada( 'tendencia alta', PuntuacionOportunidad::calcular( 90, 90 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);
		$repo->guardar(
			new TendenciaDetectada( 'tendencia media', PuntuacionOportunidad::calcular( 50, 50 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);

		$recientes = $repo->obtenerRecientes( 2 );

		self::assertCount( 2, $recientes );
		self::assertSame( $idAlta, $recientes[0]['id'] );
		self::assertSame( 'tendencia alta', $recientes[0]['termino'] );
		self::assertGreaterThan( $recientes[1]['puntuacionTotal'], $recientes[0]['puntuacionTotal'] );
	}

	public function test_crear_como_actualizacion_enlaza_la_pieza_original(): void {
		global $wpdb;
		$repoTendencias = new RepositorioTendencias( $wpdb );
		$repoPiezas     = new RepositorioPiezas( $wpdb );
		$reloj          = new RelojSistema();

		$tendenciaOriginalId = $repoTendencias->guardar(
			new TendenciaDetectada( 'historia original ' . uniqid(), PuntuacionOportunidad::calcular( 50, 50 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);
		$piezaOriginalId     = $repoPiezas->crear( $tendenciaOriginalId, $reloj->ahora() );

		$tendenciaActualizacionId = $repoTendencias->guardar(
			new TendenciaDetectada( 'historia actualización ' . uniqid(), PuntuacionOportunidad::calcular( 60, 60 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);
		$piezaActualizacionId     = $repoPiezas->crearComoActualizacion( $tendenciaActualizacionId, $piezaOriginalId, $reloj->ahora() );

		$piezaActualizacion = $repoPiezas->obtenerPorId( $piezaActualizacionId );

		self::assertNotNull( $piezaActualizacion );
		self::assertSame( $piezaOriginalId, $piezaActualizacion->piezaOriginalId );
		self::assertSame( EstadoPieza::Detectada, $piezaActualizacion->estado );
	}

	public function test_obtener_recientes_con_pieza_viva_excluye_descartadas_y_falladas(): void {
		global $wpdb;
		$repoTendencias = new RepositorioTendencias( $wpdb );
		$repoPiezas     = new RepositorioPiezas( $wpdb );
		$reloj          = new RelojSistema();

		$tendenciaViva = $repoTendencias->guardar(
			new TendenciaDetectada( 'historia viva ' . uniqid(), PuntuacionOportunidad::calcular( 50, 50 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);
		$repoPiezas->crear( $tendenciaViva, $reloj->ahora() );

		$tendenciaDescartada = $repoTendencias->guardar(
			new TendenciaDetectada( 'historia descartada ' . uniqid(), PuntuacionOportunidad::calcular( 50, 50 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);
		$piezaDescartadaId   = $repoPiezas->crear( $tendenciaDescartada, $reloj->ahora() );
		$repoPiezas->actualizarEstado( $piezaDescartadaId, EstadoPieza::Detectada, EstadoPieza::Descartada, $reloj->ahora() );

		$candidatas = $repoTendencias->obtenerRecientesConPiezaViva( 14, 50, $reloj->ahora() );
		$idsVivos   = array_column( $candidatas, 'id' );

		self::assertContains( $tendenciaViva, $idsVivos );
		self::assertNotContains( $tendenciaDescartada, $idsVivos );
	}

	public function test_guardar_como_posible_actualizacion_vincula_la_tendencia_original(): void {
		global $wpdb;
		$repo  = new RepositorioTendencias( $wpdb );
		$reloj = new RelojSistema();

		$tendenciaOriginalId = $repo->guardar(
			new TendenciaDetectada( 'historia original ' . uniqid(), PuntuacionOportunidad::calcular( 50, 50 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);

		$tendenciaActualizacionId = $repo->guardarComoPosibleActualizacion(
			new TendenciaDetectada( 'historia que evoluciona ' . uniqid(), PuntuacionOportunidad::calcular( 60, 60 ), $reloj->ahora(), array(), 'google_trends' ),
			$tendenciaOriginalId,
			$reloj->ahora()
		);

		self::assertSame( $tendenciaOriginalId, $repo->obtenerTendenciaOriginal( $tendenciaActualizacionId ) );

		$tarjeta = null;
		foreach ( $repo->obtenerParaSala( 100 ) as $candidata ) {
			if ( $candidata['id'] === $tendenciaActualizacionId ) {
				$tarjeta = $candidata;
			}
		}

		self::assertNotNull( $tarjeta );
		self::assertSame( EstadoTendencia::PosibleActualizacion, $tarjeta['estado'] );
		self::assertSame( $tendenciaOriginalId, $tarjeta['tendenciaOriginalId'] );
	}

	public function test_guardar_con_sospecha_legitimidad_persiste_el_diagnostico_y_no_crea_pieza(): void {
		global $wpdb;
		$repo  = new RepositorioTendencias( $wpdb );
		$reloj = new RelojSistema();

		$diagnostico = new DiagnosticoLegitimidadInsumo( 3, 1, 0.33, false, 'Concentración de fuente: 3 artículos de 1 fuente.' );

		$tendenciaId = $repo->guardarConSospechaLegitimidad(
			new TendenciaDetectada( 'tendencia sospechosa ' . uniqid(), PuntuacionOportunidad::calcular( 70, 70 ), $reloj->ahora(), array(), 'google_trends' ),
			$diagnostico,
			$reloj->ahora()
		);

		$tarjeta = null;
		foreach ( $repo->obtenerParaSala( 100 ) as $candidata ) {
			if ( $candidata['id'] === $tendenciaId ) {
				$tarjeta = $candidata;
			}
		}

		self::assertNotNull( $tarjeta );
		self::assertSame( EstadoTendencia::SospechaDeManipulacion, $tarjeta['estado'] );

		$fila = $wpdb->get_row( $wpdb->prepare( "SELECT diversidad_fuente, motivo_legitimidad FROM {$wpdb->prefix}pluma_tendencias WHERE id = %d", $tendenciaId ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna del plugin, prefijo de $wpdb.

		self::assertNotNull( $fila );
		self::assertEqualsWithDelta( 0.33, (float) $fila['diversidad_fuente'], 0.001 );
		self::assertSame( $diagnostico->motivo, $fila['motivo_legitimidad'] );
	}

	public function test_obtener_tendencia_original_es_nulo_sin_vinculo(): void {
		global $wpdb;
		$repo  = new RepositorioTendencias( $wpdb );
		$reloj = new RelojSistema();

		$tendenciaId = $repo->guardar(
			new TendenciaDetectada( 'historia sin vínculo ' . uniqid(), PuntuacionOportunidad::calcular( 50, 50 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);

		self::assertNull( $repo->obtenerTendenciaOriginal( $tendenciaId ) );
	}

	public function test_contar_por_estado_devuelve_el_total_exacto_sin_limitarlo(): void {
		global $wpdb;
		$repoTendencias = new RepositorioTendencias( $wpdb );
		$repoPiezas     = new RepositorioPiezas( $wpdb );
		$reloj          = new RelojSistema();

		$totalAntes = $repoPiezas->contarPorEstado( EstadoPieza::Detectada );

		for ( $i = 0; $i < 3; $i++ ) {
			$tendenciaId = $repoTendencias->guardar(
				new TendenciaDetectada( 'tendencia conteo ' . $i, PuntuacionOportunidad::calcular( 50, 50 ), $reloj->ahora(), array(), 'google_trends' ),
				$reloj->ahora()
			);
			$repoPiezas->crear( $tendenciaId, $reloj->ahora() );
		}

		self::assertSame( $totalAntes + 3, $repoPiezas->contarPorEstado( EstadoPieza::Detectada ) );
	}

	public function test_obtener_por_estado_entre_filtra_por_rango_de_fechas_explicito(): void {
		global $wpdb;
		$repoTendencias = new RepositorioTendencias( $wpdb );
		$repoPiezas     = new RepositorioPiezas( $wpdb );
		$reloj          = new RelojSistema();
		$ahora          = $reloj->ahora();

		$crearRetenidaEn = function ( \DateTimeImmutable $cuando ) use ( $repoTendencias, $repoPiezas ): int {
			$tendenciaId = $repoTendencias->guardar(
				new TendenciaDetectada( 'tendencia rango ' . uniqid(), PuntuacionOportunidad::calcular( 50, 50 ), $cuando, array(), 'google_trends' ),
				$cuando
			);
			$piezaId     = $repoPiezas->crear( $tendenciaId, $cuando );
			$repoPiezas->actualizarEstado( $piezaId, EstadoPieza::Detectada, EstadoPieza::Retenida, $cuando );

			return $piezaId;
		};

		$piezaDentro = $crearRetenidaEn( $ahora );
		$piezaFuera  = $crearRetenidaEn( $ahora->modify( '-10 days' ) );

		$encontradas = $repoPiezas->obtenerPorEstadoEntre( EstadoPieza::Retenida, $ahora->modify( '-7 days' ), $ahora, 100 );
		$ids         = array_map( static fn ( $p ) => $p->id, $encontradas );

		self::assertContains( $piezaDentro, $ids, 'Una Pieza retenida dentro del rango debe aparecer.' );
		self::assertNotContains( $piezaFuera, $ids, 'Una Pieza retenida fuera del rango no debe aparecer.' );
	}

	public function test_contar_por_estado_entre_filtra_tendencias_por_rango_de_fechas(): void {
		global $wpdb;
		$repo  = new RepositorioTendencias( $wpdb );
		$reloj = new RelojSistema();
		$ahora = $reloj->ahora();

		$totalAntes = $repo->contarPorEstadoEntre( EstadoTendencia::EnPipeline, $ahora->modify( '-7 days' ), $ahora );

		$repo->guardar(
			new TendenciaDetectada( 'tendencia dentro del rango ' . uniqid(), PuntuacionOportunidad::calcular( 50, 50 ), $ahora, array(), 'google_trends' ),
			$ahora
		);
		$repo->guardar(
			new TendenciaDetectada( 'tendencia fuera del rango ' . uniqid(), PuntuacionOportunidad::calcular( 50, 50 ), $ahora->modify( '-10 days' ), array(), 'google_trends' ),
			$ahora->modify( '-10 days' )
		);

		self::assertSame( $totalAntes + 1, $repo->contarPorEstadoEntre( EstadoTendencia::EnPipeline, $ahora->modify( '-7 days' ), $ahora ) );
	}

	public function test_obtener_ultima_ejecucion_de_la_bitacora(): void {
		global $wpdb;
		$repo  = new RepositorioBitacora( $wpdb );
		$reloj = new RelojSistema();

		self::assertNull( $repo->obtenerUltima() );

		$primeraId = $repo->iniciarEjecucion( $reloj->ahora() );
		$repo->finalizarEjecucion( $primeraId, $reloj->ahora(), 2, array() );

		$segundaId = $repo->iniciarEjecucion( $reloj->ahora()->modify( '+1 minute' ) );
		$repo->finalizarEjecucion( $segundaId, $reloj->ahora()->modify( '+1 minute' ), 1, array( 'fallo de proveedor' ) );

		$ultima = $repo->obtenerUltima();

		self::assertNotNull( $ultima );
		self::assertSame( 1, $ultima['lotesProcesados'] );
		self::assertSame( array( 'fallo de proveedor' ), $ultima['errores'] );
		self::assertNotNull( $ultima['finalizadaEn'] );

		// Fechas en DATE_ATOM (parseables por `Date` de JavaScript en el
		// panel), no la cadena cruda de MySQL — Sala de Máquinas, Cap. 10.2.
		self::assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $ultima['iniciadaEn'] );
	}

	public function test_obtener_recientes_devuelve_las_ejecuciones_mas_nuevas_primero(): void {
		global $wpdb;
		$repo  = new RepositorioBitacora( $wpdb );
		$reloj = new RelojSistema();

		$primeraId = $repo->iniciarEjecucion( $reloj->ahora() );
		$repo->finalizarEjecucion( $primeraId, $reloj->ahora(), 2, array() );

		$segundaId = $repo->iniciarEjecucion( $reloj->ahora()->modify( '+1 minute' ) );
		$repo->finalizarEjecucion( $segundaId, $reloj->ahora()->modify( '+1 minute' ), 4, array( 'fallo de proveedor' ) );

		$recientes = $repo->obtenerRecientes( 1 );

		self::assertCount( 1, $recientes );
		self::assertSame( 4, $recientes[0]['lotesProcesados'] );
		self::assertSame( array( 'fallo de proveedor' ), $recientes[0]['errores'] );
	}

	public function test_obtener_entre_filtra_ejecuciones_por_rango_de_fechas(): void {
		global $wpdb;
		$repo  = new RepositorioBitacora( $wpdb );
		$reloj = new RelojSistema();
		$ahora = $reloj->ahora();

		$dentroId = $repo->iniciarEjecucion( $ahora );
		$repo->finalizarEjecucion( $dentroId, $ahora, 3, array() );

		$fueraId = $repo->iniciarEjecucion( $ahora->modify( '-10 days' ) );
		$repo->finalizarEjecucion( $fueraId, $ahora->modify( '-10 days' ), 5, array() );

		$enRango = $repo->obtenerEntre( $ahora->modify( '-7 days' ), $ahora->modify( '+1 minute' ) );

		self::assertContains( 3, array_column( $enRango, 'lotesProcesados' ) );
		self::assertNotContains( 5, array_column( $enRango, 'lotesProcesados' ) );
	}
}
