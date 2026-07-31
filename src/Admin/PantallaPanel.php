<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Datos\Migrador;
use Pluma\Kernel\AlmacenPerfilEntornoInterface;
use Pluma\Kernel\Capacidades;
use Pluma\Kernel\DetectorEntorno;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Proveedores\LenguajeInterface;

/**
 * La página única del panel (Libro de Arquitectura Cap. 10): registra el
 * menú de wp-admin, encola el bundle React y le inyecta los datos iniciales
 * (URL/nonce de REST + textos traducibles + la foto de salud del sistema).
 *
 * Los assets solo se encolan en esta pantalla (pl-wp-core §5): cero peso en
 * cualquier otra pantalla de wp-admin y cero peso en el frontend público.
 * Dentro del bundle, el "shell" de React (barra de estado persistente +
 * enrutado por hash, Cap. 10.1) decide qué pantalla mostrar — Portada,
 * Sala de Máquinas, y las que se añadan en próximas porciones de la Etapa 4.
 */
final class PantallaPanel {

	private const SLUG   = 'pluma-engine-panel';
	private const HANDLE = 'pluma-engine-panel';

	private ?string $hookSuffix = null;

	public function __construct(
		private readonly DetectorEntorno $detector,
		private readonly LenguajeInterface $proveedorLenguaje,
		private readonly AlmacenPerfilEntornoInterface $almacenPerfilEntorno,
	) {
	}

	public function registrar(): void {
		add_action( 'admin_menu', array( $this, 'registrarMenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'encolarAssets' ) );
	}

	public function registrarMenu(): void {
		$hook = add_menu_page(
			__( 'PLUMA Engine', 'pluma-engine' ),
			__( 'PLUMA Engine', 'pluma-engine' ),
			Capacidades::CONFIGURAR_MOTOR,
			self::SLUG,
			array( $this, 'renderizar' ),
			'dashicons-edit-large',
			3
		);

		$this->hookSuffix = false !== $hook ? $hook : null;
	}

	public function encolarAssets( string $hookActual ): void {
		if ( null === $this->hookSuffix || $hookActual !== $this->hookSuffix ) {
			return;
		}

		$entrada = $this->leerEntradaManifest();

		if ( null === $entrada ) {
			return;
		}

		[$archivoDir, $archivoUrl] = array( PLUMA_ENGINE_DIR . 'build/panel/', PLUMA_ENGINE_URL . 'build/panel/' );

		$rutaJs = $entrada['file'];
		wp_enqueue_script(
			self::HANDLE,
			$archivoUrl . $rutaJs,
			array(),
			$this->version( $archivoDir . $rutaJs ),
			true
		);
		wp_script_add_data( self::HANDLE, 'type', 'module' );

		foreach ( $entrada['css'] ?? array() as $rutaCss ) {
			wp_enqueue_style(
				self::HANDLE . '-' . md5( $rutaCss ),
				$archivoUrl . $rutaCss,
				array(),
				$this->version( $archivoDir . $rutaCss )
			);
		}

		wp_add_inline_script(
			self::HANDLE,
			'window.plumaPanel = ' . wp_json_encode( $this->datosParaElPanel() ) . ';',
			'before'
		);
	}

	public function renderizar(): void {
		if ( ! current_user_can( Capacidades::CONFIGURAR_MOTOR ) ) {
			return;
		}

		echo '<div id="pluma-panel-root"></div>';
	}

	/**
	 * @return array{file: string, css?: list<string>}|null
	 */
	private function leerEntradaManifest(): ?array {
		$rutaManifest = PLUMA_ENGINE_DIR . 'build/panel/.vite/manifest.json';

		/** @var array<string, array{file: string, css?: list<string>}>|null $manifest */
		$manifest = wp_json_file_decode( $rutaManifest, array( 'associative' => true ) );

		return $manifest['panel/src/main.tsx'] ?? null;
	}

	private function version( string $rutaArchivo ): string {
		$mtime = file_exists( $rutaArchivo ) ? filemtime( $rutaArchivo ) : false;

		return false !== $mtime ? (string) $mtime : PLUMA_ENGINE_VERSION;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function datosParaElPanel(): array {
		return array(
			'restUrl'                   => esc_url_raw( rest_url() ),
			'nonce'                     => wp_create_nonce( 'wp_rest' ),
			'salud'                     => $this->datosSalud(),
			'textosPortada'             => $this->textosPortada(),
			'textosTendencias'          => $this->textosTendencias(),
			'textosCalendarioEditorial' => $this->textosCalendarioEditorial(),
			'textosDistribucion'        => $this->textosDistribucion(),
			'textosMesaEditorial'       => $this->textosMesaEditorial(),
			'textosBancoPeriodistas'    => $this->textosBancoPeriodistas(),
			'textosSalaRevision'        => $this->textosSalaRevision(),
			'textosSalaMaquinas'        => $this->textosSalaMaquinas(),
			'textosEstudioSeo'          => $this->textosEstudioSeo(),
			'textosInformes'            => $this->textosInformesEditoriales(),
			// Sin credenciales de IA toda Pieza muere en investigación
			// (`PLUMA-E9-19`): el panel lo avisa en vez de dejar que el editor
			// lo descubra viendo piezas fallidas.
			'iaConfigurada'             => $this->proveedorLenguaje->tieneCredenciales(),
			'onboardingCompletado'      => (bool) get_option( RestOnboarding::OPCION_COMPLETADO, false ),
			'textosOnboarding'          => $this->textosOnboarding(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function datosSalud(): array {
		// NCP-1 · Sonda de Capacidades (`ADR 0013`): `leer()`, nunca
		// `refrescar()` aquí — la carga de página debe seguir barata.
		$perfilEntorno = $this->almacenPerfilEntorno->leer();

		return array(
			'versionPhp'           => $this->detector->versionPhp(),
			'versionWordPress'     => $this->detector->versionWordPress(),
			'versionBaseDatos'     => $this->detector->versionBaseDatos(),
			'versionEsquemaPlugin' => get_option( Migrador::OPCION_VERSION, '0.0.0' ),
			'cronRealConfigurado'  => $this->detector->cronRealConfigurado(),
			'esMultisitio'         => $this->detector->esMultisitio(),
			'sondaCapacidades'     => array(
				'transportePrioritario'    => $perfilEntorno->transportePrioritario->value,
				'ffiDisponible'            => $perfilEntorno->hechos->ffiDisponible,
				'procesoHijoDisponible'    => $perfilEntorno->hechos->procesoHijoDisponible,
				'cerebroRemotoConfigurado' => $perfilEntorno->hechos->cerebroRemotoConfigurado,
				'apiPagoConfigurada'       => $perfilEntorno->hechos->apiPagoConfigurada,
				'medidoEn'                 => $perfilEntorno->medidoEn->format( DATE_ATOM ),
			),
			'textos'               => array(
				'titulo'             => __( 'Configuración — Sala de Máquinas', 'pluma-engine' ),
				'etiquetaPhp'        => __( 'PHP', 'pluma-engine' ),
				'etiquetaWordPress'  => __( 'WordPress', 'pluma-engine' ),
				'etiquetaBaseDatos'  => __( 'Base de datos', 'pluma-engine' ),
				'etiquetaEsquema'    => __( 'Esquema PLUMA', 'pluma-engine' ),
				'etiquetaCron'       => __( 'Cron real', 'pluma-engine' ),
				'cronOk'             => __( 'Configurado', 'pluma-engine' ),
				'cronAdvertencia'    => __( 'WP-Cron activo: no recomendado para producción', 'pluma-engine' ),
				'etiquetaMultisitio' => __( 'Multisitio', 'pluma-engine' ),
				'multisitioSi'       => __( 'Sí', 'pluma-engine' ),
				'multisitioNo'       => __( 'No', 'pluma-engine' ),
				'sondaCapacidades'   => array(
					'titulo'                => __( 'Sonda de Capacidades', 'pluma-engine' ),
					'leyenda'               => __( 'Medición del transporte que se usaría si el Plano Semántico existiera. El Plano Semántico (ONNX) todavía no está construido; esto es preparación, no una capacidad activa hoy.', 'pluma-engine' ),
					'etiquetaTransporte'    => __( 'Transporte prioritario', 'pluma-engine' ),
					't1EnProceso'           => __( 'En proceso (T1)', 'pluma-engine' ),
					't2SidecarLocal'        => __( 'Sidecar local (T2)', 'pluma-engine' ),
					't3CerebroRemoto'       => __( 'Cerebro remoto (T3)', 'pluma-engine' ),
					'ninguno'               => __( 'Ninguno — modo P0-lite', 'pluma-engine' ),
					'etiquetaFfi'           => __( 'Extensión FFI', 'pluma-engine' ),
					'etiquetaProcesoHijo'   => __( 'Proceso hijo disponible', 'pluma-engine' ),
					'etiquetaCerebroRemoto' => __( 'Cerebro remoto configurado', 'pluma-engine' ),
					'etiquetaApiPago'       => __( 'API de pago configurada', 'pluma-engine' ),
					'disponible'            => __( 'Disponible', 'pluma-engine' ),
					'noDisponible'          => __( 'No disponible', 'pluma-engine' ),
				),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function textosPortada(): array {
		return array(
			'titulo'            => __( 'Portada', 'pluma-engine' ),
			'navPortada'        => __( 'Portada', 'pluma-engine' ),
			'navSalud'          => __( 'Configuración', 'pluma-engine' ),
			'cronNoConfigurado' => __( 'El motor lleva horas sin ejecutarse: las piezas no avanzarán por su cuenta. Comprueba que el cron real de tu servidor esté llamando a PLUMA, o ejecuta un ciclo a mano desde la pestaña Configuración.', 'pluma-engine' ),
			'cargando'          => __( 'Cargando…', 'pluma-engine' ),
			'errorCarga'        => __( 'No se pudo cargar la Portada. Reintenta en unos segundos.', 'pluma-engine' ),
			'modo'              => array(
				'piloto'   => __( 'Piloto', 'pluma-engine' ),
				'copiloto' => __( 'Copiloto', 'pluma-engine' ),
				'autonomo' => __( 'Autónomo', 'pluma-engine' ),
			),
			'cuota'             => array(
				'titulo'             => __( 'Cuota de hoy', 'pluma-engine' ),
				'publicadas'         => __( 'publicadas', 'pluma-engine' ),
				'programadas'        => __( 'programadas', 'pluma-engine' ),
				'objetivo'           => __( 'objetivo', 'pluma-engine' ),
				'proximaPublicacion' => __( 'Próxima publicación', 'pluma-engine' ),
				'sinProximo'         => __( 'sin ranuras programadas pendientes', 'pluma-engine' ),
				'deficit'            => __( 'Déficit de cuota: por debajo del mínimo configurado', 'pluma-engine' ),
			),
			'salud'             => array(
				'titulo'          => __( 'Salud del motor', 'pluma-engine' ),
				'ultimaEjecucion' => __( 'Última ejecución', 'pluma-engine' ),
				'nunca'           => __( 'el motor no se ha ejecutado todavía', 'pluma-engine' ),
				'gastoHoy'        => __( 'Gasto de hoy', 'pluma-engine' ),
				'deLimite'        => __( 'de', 'pluma-engine' ),
				'errores'         => __( 'con errores en la última ejecución', 'pluma-engine' ),
			),
			'pipeline'          => array(
				'titulo'  => __( 'Piezas en el pipeline', 'pluma-engine' ),
				'estados' => $this->etiquetasEstados(),
			),
			'alertas'           => array(
				'titulo'                   => __( 'Alertas', 'pluma-engine' ),
				'retenidas'                => __( 'Retenidas esperando decisión', 'pluma-engine' ),
				'fallidas'                 => __( 'Fallidas', 'pluma-engine' ),
				'sinPeriodistaIdoneo'      => __( 'Sin periodista idóneo', 'pluma-engine' ),
				'sinRetenidas'             => __( 'ninguna pieza retenida', 'pluma-engine' ),
				'sinFallidas'              => __( 'ninguna pieza fallida', 'pluma-engine' ),
				'sinPeriodistaIdoneoVacio' => __( 'ninguna pieza sin periodista idóneo', 'pluma-engine' ),
			),
			'tendencias'        => array(
				'titulo' => __( 'Tendencias calientes ahora', 'pluma-engine' ),
				'vacio'  => __( 'todavía no se ha detectado ninguna tendencia', 'pluma-engine' ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function textosInformesEditoriales(): array {
		return array(
			'titulo'     => __( 'Informes Editoriales', 'pluma-engine' ),
			'cargando'   => __( 'Cargando…', 'pluma-engine' ),
			'errorCarga' => __( 'No se pudo cargar el Informe Editorial. Reintenta en unos segundos.', 'pluma-engine' ),
			'rango'      => __( 'Semana', 'pluma-engine' ),
			'piezas'     => array(
				'titulo'        => __( 'Piezas publicadas', 'pluma-engine' ),
				'publicadas'    => __( 'piezas publicadas esta semana', 'pluma-engine' ),
				'porPeriodista' => __( 'Por periodista', 'pluma-engine' ),
				'porVertical'   => __( 'Por vertical', 'pluma-engine' ),
				'sinDatos'      => __( 'sin datos esta semana', 'pluma-engine' ),
				'retenidas'     => __( 'Retenidas esta semana', 'pluma-engine' ),
				'fallidas'      => __( 'Fallidas esta semana', 'pluma-engine' ),
				'sinRetenidas'  => __( 'ninguna pieza retenida esta semana', 'pluma-engine' ),
				'sinFallidas'   => __( 'ninguna pieza fallida esta semana', 'pluma-engine' ),
			),
			'tendencias' => array(
				'titulo'               => __( 'Tendencias de la semana', 'pluma-engine' ),
				'enPipeline'           => __( 'En el pipeline', 'pluma-engine' ),
				'posibleActualizacion' => __( 'Posibles actualizaciones detectadas', 'pluma-engine' ),
				'ignoradas'            => __( 'Ignoradas', 'pluma-engine' ),
				'vigiladas'            => __( 'En vigilancia', 'pluma-engine' ),
				'sospechaManipulacion' => __( 'Con sospecha de manipulación', 'pluma-engine' ),
			),
			'motor'      => array(
				'titulo'                => __( 'Salud del motor esta semana', 'pluma-engine' ),
				'ejecuciones'           => __( 'Ejecuciones', 'pluma-engine' ),
				'lotesProcesados'       => __( 'Lotes procesados', 'pluma-engine' ),
				'ejecucionesConErrores' => __( 'Ejecuciones con errores', 'pluma-engine' ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function textosTendencias(): array {
		return array(
			'titulo'                     => __( 'Sala de Tendencias', 'pluma-engine' ),
			'cargando'                   => __( 'Cargando…', 'pluma-engine' ),
			'errorCarga'                 => __( 'No se pudo cargar la Sala de Tendencias. Reintenta en unos segundos.', 'pluma-engine' ),
			'errorAccion'                => __( 'La acción no se pudo completar. Reintenta en unos segundos.', 'pluma-engine' ),
			'confirmacion'               => array(
				'cubrir'               => __( 'Cobertura priorizada: la pieza salta la cola y entra en el próximo ciclo del motor.', 'pluma-engine' ),
				'ignorar'              => __( 'Tendencia ignorada: sale del radar y su pieza en curso queda descartada.', 'pluma-engine' ),
				'vigilar'              => __( 'Tendencia en vigilancia: sigue en el radar, sin gastar investigación ni redacción.', 'pluma-engine' ),
				'cubrir-actualizacion' => __( 'Actualización priorizada: la pieza queda enlazada a la historia original.', 'pluma-engine' ),
			),
			'sinIaAviso'                 => __( 'No hay ninguna clave de IA configurada. El motor puede detectar tendencias, pero cada pieza morirá en la fase de investigación hasta que añadas una clave en la pestaña Configuración.', 'pluma-engine' ),
			'sinIaTrasAccion'            => __( 'Acción registrada, pero la pieza volverá a fallar en investigación: falta configurar una clave de IA en la pestaña Configuración.', 'pluma-engine' ),
			'vacio'                      => __( 'todavía no se ha detectado ninguna tendencia', 'pluma-engine' ),
			'velocidad'                  => __( 'Velocidad', 'pluma-engine' ),
			'afinidad'                   => __( 'Afinidad', 'pluma-engine' ),
			'total'                      => __( 'Puntuación de Oportunidad', 'pluma-engine' ),
			'desgloseParcial'            => __( 'Desglose sobre velocidad y afinidad; hueco competitivo y vida útil llegan con el Radar completo.', 'pluma-engine' ),
			'quienCubre'                 => __( 'Quién la está cubriendo ya', 'pluma-engine' ),
			'nadieCubre'                 => __( 'sin cobertura detectada en las señales', 'pluma-engine' ),
			'estadoVigilada'             => __( 'En vigilancia', 'pluma-engine' ),
			'estadoSospechaManipulacion' => __( 'Sospecha de manipulación (concentración de fuente) — heurística, no un veredicto', 'pluma-engine' ),
			'cubrirAhora'                => __( 'Cubrir ahora', 'pluma-engine' ),
			'ignorar'                    => __( 'Ignorar', 'pluma-engine' ),
			'vigilar'                    => __( 'Vigilar', 'pluma-engine' ),
			'posibleActualizacion'       => __( 'Posible actualización de una historia ya cubierta', 'pluma-engine' ),
			'cubrirActualizacion'        => __( 'Cubrir como actualización', 'pluma-engine' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function textosCalendarioEditorial(): array {
		return array(
			'titulo'               => __( 'Calendario Editorial', 'pluma-engine' ),
			'cargando'             => __( 'Cargando…', 'pluma-engine' ),
			'errorCarga'           => __( 'No se pudo cargar el Calendario Editorial. Reintenta en unos segundos.', 'pluma-engine' ),
			'errorAccion'          => __( 'La acción no se pudo completar. Reintenta en unos segundos.', 'pluma-engine' ),
			'vacio'                => __( 'todavía no hay eventos programados', 'pluma-engine' ),
			'nuevoTitulo'          => __( 'Título', 'pluma-engine' ),
			'nuevoVertical'        => __( 'Vertical', 'pluma-engine' ),
			'nuevaFecha'           => __( 'Fecha esperada', 'pluma-engine' ),
			'crear'                => __( 'Añadir a la agenda', 'pluma-engine' ),
			'estadoPrevisto'       => __( 'Previsto', 'pluma-engine' ),
			'estadoPreparado'      => __( 'Preparado', 'pluma-engine' ),
			'estadoEnCurso'        => __( 'En curso', 'pluma-engine' ),
			'estadoCubierto'       => __( 'Cubierto', 'pluma-engine' ),
			'prepararCobertura'    => __( 'Preparar cobertura', 'pluma-engine' ),
			'fuenteTitulo'         => __( 'Título del artículo', 'pluma-engine' ),
			'fuenteUrl'            => __( 'URL', 'pluma-engine' ),
			'fuenteNombre'         => __( 'Fuente', 'pluma-engine' ),
			'anadirFuente'         => __( 'Añadir otra fuente', 'pluma-engine' ),
			'confirmarPreparacion' => __( 'Confirmar preparación', 'pluma-engine' ),
			'marcarEnCurso'        => __( 'Marcar en curso', 'pluma-engine' ),
			'marcarCubierto'       => __( 'Marcar cubierto', 'pluma-engine' ),
			'necesitaFuentes'      => __( 'Añade al menos una fuente real (título y URL) antes de preparar la cobertura.', 'pluma-engine' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function textosDistribucion(): array {
		return array(
			'titulo'           => __( 'Distribución', 'pluma-engine' ),
			'cargando'         => __( 'Cargando…', 'pluma-engine' ),
			'errorCarga'       => __( 'No se pudo cargar Distribución. Reintenta en unos segundos.', 'pluma-engine' ),
			'errorAccion'      => __( 'La acción no se pudo completar. Reintenta en unos segundos.', 'pluma-engine' ),
			'seccionBoletines' => __( 'Boletines por periodista', 'pluma-engine' ),
			'enviarBoletin'    => __( 'Enviar boletín', 'pluma-engine' ),
			'sinPeriodistas'   => __( 'todavía no hay periodistas activos', 'pluma-engine' ),
			'piezasEnviadas'   => __( 'piezas incluidas', 'pluma-engine' ),
			'sinPiezasNuevas'  => __( 'sin piezas nuevas para enviar', 'pluma-engine' ),
			'seccionDerivados' => __( 'Derivados sociales pendientes de revisión', 'pluma-engine' ),
			'sinDerivados'     => __( 'todavía no hay derivados pendientes', 'pluma-engine' ),
			'extractoSocial'   => __( 'Extracto social', 'pluma-engine' ),
			'titularDiscover'  => __( 'Titular Discover', 'pluma-engine' ),
			'aprobar'          => __( 'Aprobar', 'pluma-engine' ),
			'descartar'        => __( 'Descartar', 'pluma-engine' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function textosMesaEditorial(): array {
		return array(
			'titulo'               => __( 'Mesa Editorial', 'pluma-engine' ),
			'cargando'             => __( 'Cargando…', 'pluma-engine' ),
			'errorCarga'           => __( 'No se pudo cargar la Mesa Editorial. Reintenta en unos segundos.', 'pluma-engine' ),
			'errorAccion'          => __( 'La acción no se pudo completar. Reintenta en unos segundos.', 'pluma-engine' ),
			'columnaVacia'         => __( 'sin piezas en este estado', 'pluma-engine' ),
			'sinPeriodista'        => __( 'sin periodista asignado', 'pluma-engine' ),
			'sinTesis'             => __( 'sin tesis todavía', 'pluma-engine' ),
			'cerrar'               => __( 'Cerrar', 'pluma-engine' ),
			'expediente'           => __( 'Expediente', 'pluma-engine' ),
			'sinExpediente'        => __( 'sin expediente todavía', 'pluma-engine' ),
			'nivelVerificado'      => __( 'Verificado', 'pluma-engine' ),
			'nivelAtribuido'       => __( 'Atribuido', 'pluma-engine' ),
			'nivelDisputado'       => __( 'Disputado', 'pluma-engine' ),
			'ficha'                => __( 'Ficha de Decisión Editorial', 'pluma-engine' ),
			'sinFicha'             => __( 'sin ficha de decisión editorial todavía', 'pluma-engine' ),
			'tesisElegida'         => __( 'Tesis elegida', 'pluma-engine' ),
			'tonoDominante'        => __( 'Tono dominante', 'pluma-engine' ),
			'tonoApoyo'            => __( 'Tono de apoyo', 'pluma-engine' ),
			'compuertas'           => __( 'Compuertas', 'pluma-engine' ),
			'sinCompuertas'        => __( 'sin evaluación de compuertas todavía', 'pluma-engine' ),
			'calidad'              => __( 'Calidad', 'pluma-engine' ),
			'riesgo'               => __( 'Riesgo', 'pluma-engine' ),
			'originalidad'         => __( 'Originalidad', 'pluma-engine' ),
			'motivos'              => __( 'Motivos', 'pluma-engine' ),
			'borradores'           => __( 'Borradores', 'pluma-engine' ),
			'sinBorradores'        => __( 'sin borradores todavía', 'pluma-engine' ),
			'cicloAnterior'        => __( 'Ciclo anterior', 'pluma-engine' ),
			'cicloActual'          => __( 'Ciclo', 'pluma-engine' ),
			'editadoManualmente'   => __( 'editado manualmente por un editor', 'pluma-engine' ),
			'aprobadoPorCorrector' => __( 'aprobado por el Corrector Interno', 'pluma-engine' ),
			'editar'               => __( 'Editar', 'pluma-engine' ),
			'guardarEdicion'       => __( 'Guardar edición', 'pluma-engine' ),
			'cancelar'             => __( 'Cancelar', 'pluma-engine' ),
			'contenidoVacio'       => __( 'El contenido no puede estar vacío.', 'pluma-engine' ),
			'reasignar'            => __( 'Periodista asignado', 'pluma-engine' ),
			'reasignarBoton'       => __( 'Reasignar', 'pluma-engine' ),
			'aprobar'              => __( 'Forzar aprobación', 'pluma-engine' ),
			'descartar'            => __( 'Descartar', 'pluma-engine' ),
			'confirmarDescartar'   => __( '¿Descartar esta Pieza? Esta acción queda registrada en la auditoría y no se puede deshacer.', 'pluma-engine' ),
			'actualizacionDe'      => __( 'Actualización de la pieza', 'pluma-engine' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function textosBancoPeriodistas(): array {
		return array(
			'titulo'                      => __( 'Banco de Periodistas', 'pluma-engine' ),
			'cargando'                    => __( 'Cargando…', 'pluma-engine' ),
			'errorCarga'                  => __( 'No se pudo cargar el Banco de Periodistas. Reintenta en unos segundos.', 'pluma-engine' ),
			'errorAccion'                 => __( 'La acción no se pudo completar. Reintenta en unos segundos.', 'pluma-engine' ),
			'sinPeriodistas'              => __( 'todavía no hay ningún periodista en el banco', 'pluma-engine' ),
			'piezasPublicadas'            => __( 'piezas publicadas', 'pluma-engine' ),
			'verticalesTop'               => __( 'Verticales donde más publica', 'pluma-engine' ),
			'sinVerticales'               => __( 'sin piezas publicadas todavía', 'pluma-engine' ),
			'estadoActivo'                => __( 'Activo', 'pluma-engine' ),
			'estadoJubilado'              => __( 'Jubilado', 'pluma-engine' ),
			'estadoPropuesto'             => __( 'Propuesto', 'pluma-engine' ),
			'ventanaVetoRestante'         => __( 'Se activa solo en', 'pluma-engine' ),
			'aprobarAhora'                => __( 'Aprobar ahora', 'pluma-engine' ),
			'descartarPropuesta'          => __( 'Descartar', 'pluma-engine' ),
			'confirmarDescartarPropuesta' => __( '¿Descartar esta propuesta de periodista? Las noticias que la originaron quedan intactas, esperando otra solución.', 'pluma-engine' ),
			'crearDesdePlantilla'         => __( 'Crear desde plantilla', 'pluma-engine' ),
			'crearPersonalizado'          => __( 'Crear personalizado', 'pluma-engine' ),
			'elegirPlantilla'             => __( 'Elegir plantilla', 'pluma-engine' ),
			'nombreOpcional'              => __( 'Nombre (opcional, por defecto el de la plantilla)', 'pluma-engine' ),
			'crear'                       => __( 'Crear', 'pluma-engine' ),
			'cancelar'                    => __( 'Cancelar', 'pluma-engine' ),
			'jubilar'                     => __( 'Jubilar', 'pluma-engine' ),
			'confirmarJubilar'            => __( '¿Jubilar a este periodista? Sus piezas quedan, pero deja de recibir asignaciones nuevas.', 'pluma-engine' ),
			'cerrar'                      => __( 'Cerrar', 'pluma-engine' ),
			'estudioDeConducta'           => __( 'Estudio de Conducta', 'pluma-engine' ),
			'identidad'                   => __( 'Identidad', 'pluma-engine' ),
			'nombre'                      => __( 'Nombre', 'pluma-engine' ),
			'biografia'                   => __( 'Biografía', 'pluma-engine' ),
			'avatarUrl'                   => __( 'URL del avatar (opcional)', 'pluma-engine' ),
			'rol'                         => array(
				'titulo'     => __( 'Rol', 'pluma-engine' ),
				'analista'   => __( 'Analista', 'pluma-engine' ),
				'columnista' => __( 'Columnista', 'pluma-engine' ),
				'cronista'   => __( 'Cronista', 'pluma-engine' ),
				'satirico'   => __( 'Satírico', 'pluma-engine' ),
			),
			'locale'                      => array(
				'titulo' => __( 'Locale editorial', 'pluma-engine' ),
				'es-ES'  => __( 'Español (España)', 'pluma-engine' ),
			),
			'especialidades'              => array(
				'titulo'              => __( 'Especialidades', 'pluma-engine' ),
				'cubreTodosLosTemas'  => __( 'Cubre todos los temas', 'pluma-engine' ),
				'nivelDominioComodin' => __( 'Nivel de dominio general (1-5)', 'pluma-engine' ),
				'vertical'            => __( 'Vertical', 'pluma-engine' ),
				'nivelDominio'        => __( 'Nivel de dominio (1-5)', 'pluma-engine' ),
				'anadir'              => __( 'Añadir especialidad', 'pluma-engine' ),
				'eliminar'            => __( 'Eliminar', 'pluma-engine' ),
				'sinEspecialidades'   => __( 'Declara al menos una especialidad, o activa "cubre todos los temas".', 'pluma-engine' ),
			),
			'guardarIdentidad'            => __( 'Guardar identidad', 'pluma-engine' ),
			'errorIdentidad'              => __( 'No se pudo guardar la identidad. Revisa los datos e inténtalo de nuevo.', 'pluma-engine' ),
			'diales'                      => array(
				'titulo'            => __( 'Diales de temperamento', 'pluma-engine' ),
				'agudezaCritica'    => __( 'Agudeza crítica', 'pluma-engine' ),
				'humor'             => __( 'Humor', 'pluma-engine' ),
				'satira'            => __( 'Sátira', 'pluma-engine' ),
				'formalidad'        => __( 'Formalidad', 'pluma-engine' ),
				'vehemencia'        => __( 'Vehemencia', 'pluma-engine' ),
				'empatia'           => __( 'Empatía', 'pluma-engine' ),
				'densidadDatos'     => __( 'Densidad de datos', 'pluma-engine' ),
				'longitudPreferida' => __( 'Longitud preferida', 'pluma-engine' ),
			),
			'reglas'                      => array(
				'titulo'               => __( 'Reglas de conducta', 'pluma-engine' ),
				'lineaEditorial'       => __( 'Línea editorial', 'pluma-engine' ),
				'lineasRojas'          => __( 'Líneas rojas', 'pluma-engine' ),
				'muletillas'           => __( 'Muletillas / rasgos de voz', 'pluma-engine' ),
				'vocabularioProhibido' => __( 'Vocabulario prohibido', 'pluma-engine' ),
				'tratamientoLector'    => __( 'Trato al lector', 'pluma-engine' ),
				'tratamientoTu'        => __( 'De tú', 'pluma-engine' ),
				'tratamientoUsted'     => __( 'De usted', 'pluma-engine' ),
				'estiloPreguntaFinal'  => __( 'Estilo de pregunta final', 'pluma-engine' ),
				'agregar'              => __( 'Agregar', 'pluma-engine' ),
			),
			'matriz'                      => array(
				'titulo'        => __( 'Matriz de tonos', 'pluma-engine' ),
				'tipoNoticia'   => array(
					'anuncio_corporativo' => __( 'Anuncio corporativo', 'pluma-engine' ),
					'escandalo_politico'  => __( 'Escándalo político', 'pluma-engine' ),
					'tragedia'            => __( 'Tragedia', 'pluma-engine' ),
					'cultura_viral'       => __( 'Cultura viral', 'pluma-engine' ),
					'dato_economico'      => __( 'Dato económico', 'pluma-engine' ),
				),
				'tonoDominante' => __( 'Tono dominante', 'pluma-engine' ),
				'tonoApoyo'     => __( 'Tono de apoyo', 'pluma-engine' ),
				'nivelSatira'   => __( 'Sátira permitida', 'pluma-engine' ),
				'tono'          => array(
					'analitico'            => __( 'Analítico', 'pluma-engine' ),
					'critico'              => __( 'Crítico', 'pluma-engine' ),
					'informativo_empatico' => __( 'Informativo empático', 'pluma-engine' ),
					'humoristico'          => __( 'Humorístico', 'pluma-engine' ),
					'opinion'              => __( 'Opinión', 'pluma-engine' ),
					'persuasivo'           => __( 'Persuasivo', 'pluma-engine' ),
				),
				'satira'        => array(
					'bloqueada'      => __( 'Bloqueada', 'pluma-engine' ),
					'no'             => __( 'No', 'pluma-engine' ),
					'con_moderacion' => __( 'Con moderación', 'pluma-engine' ),
					'en_remate'      => __( 'Solo en el remate', 'pluma-engine' ),
					'pieza_completa' => __( 'Pieza completa', 'pluma-engine' ),
				),
				'filaSistema'   => __( 'Informativo empático / Analítico / Sátira bloqueada — regla de sistema, no editable.', 'pluma-engine' ),
			),
			'memoria'                     => array(
				'titulo' => __( 'Memoria editorial reciente', 'pluma-engine' ),
				'vacia'  => __( 'sin memoria registrada todavía', 'pluma-engine' ),
				'tipo'   => array(
					'postura'   => __( 'Postura', 'pluma-engine' ),
					'cobertura' => __( 'Cobertura', 'pluma-engine' ),
					'audiencia' => __( 'Audiencia', 'pluma-engine' ),
				),
			),
			'vistaPrevia'                 => array(
				'titulo'           => __( 'Vista previa en vivo', 'pluma-engine' ),
				'generando'        => __( 'Redactando con esta conducta…', 'pluma-engine' ),
				'errorPresupuesto' => __( 'Presupuesto diario agotado — la vista previa se pausa igual que la producción real.', 'pluma-engine' ),
				'errorGeneral'     => __( 'No se pudo generar la vista previa. Reintenta en unos segundos.', 'pluma-engine' ),
			),
			'guardarCambios'              => __( 'Guardar cambios', 'pluma-engine' ),
			'clonar'                      => __( 'Clonar', 'pluma-engine' ),
			'nombreDelClon'               => __( 'Nombre del nuevo periodista clonado', 'pluma-engine' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function textosSalaRevision(): array {
		return array(
			'titulo'                         => __( 'Sala de Revisión', 'pluma-engine' ),
			'cargando'                       => __( 'Cargando…', 'pluma-engine' ),
			'errorCarga'                     => __( 'No se pudo cargar la Sala de Revisión. Reintenta en unos segundos.', 'pluma-engine' ),
			'errorAccion'                    => __( 'La acción no se pudo completar. Reintenta en unos segundos.', 'pluma-engine' ),
			'retenidas'                      => __( 'Retenidas esperando decisión', 'pluma-engine' ),
			'sinRetenidas'                   => __( 'ninguna pieza retenida', 'pluma-engine' ),
			'sinPeriodistaIdoneo'            => __( 'Sin periodista idóneo', 'pluma-engine' ),
			'sinPeriodistaIdoneoVacio'       => __( 'ninguna pieza esperando un periodista', 'pluma-engine' ),
			'sinPeriodistaIdoneoExplicacion' => __( 'Ningún periodista del banco domina lo suficiente el tema de estas piezas. Crea o ajusta un periodista que cubra ese vertical y pulsa Reanudar: la pieza vuelve al pipeline conservando su expediente.', 'pluma-engine' ),
			'reanudar'                       => __( 'Reanudar', 'pluma-engine' ),
			'colaDeVeto'                     => __( 'Cola de veto (modo Copiloto)', 'pluma-engine' ),
			'sinColaDeVeto'                  => __( 'ninguna pieza esperando la ventana de veto', 'pluma-engine' ),
			'diagnostico'                    => __( 'Diagnóstico', 'pluma-engine' ),
			'sinDiagnostico'                 => __( 'sin diagnóstico de compuertas todavía', 'pluma-engine' ),
			'calidad'                        => __( 'Calidad', 'pluma-engine' ),
			'riesgo'                         => __( 'Riesgo', 'pluma-engine' ),
			'originalidad'                   => __( 'Originalidad', 'pluma-engine' ),
			'sinDetalle'                     => __( 'sin motivos registrados', 'pluma-engine' ),
			'lectura'                        => __( 'Leer la pieza', 'pluma-engine' ),
			'sinContenido'                   => __( 'sin borrador todavía', 'pluma-engine' ),
			'aprobar'                        => __( 'Aprobar', 'pluma-engine' ),
			'devolver'                       => __( 'Devolver con nota', 'pluma-engine' ),
			'notaOpcional'                   => __( 'Nota (opcional)', 'pluma-engine' ),
			'descartar'                      => __( 'Descartar', 'pluma-engine' ),
			'vetar'                          => __( 'Vetar (descartar antes de publicar)', 'pluma-engine' ),
			'aprobarAhora'                   => __( 'Aprobar ahora (publicar sin esperar)', 'pluma-engine' ),
			'tiempoRestante'                 => __( 'Tiempo restante para vetar', 'pluma-engine' ),
			'tiempoAgotado'                  => __( 'La ventana de veto ya expiró — se publicará en el próximo tick del motor', 'pluma-engine' ),
			'confirmarDescartar'             => __( '¿Descartar esta Pieza? Esta acción queda registrada en la auditoría y no se puede deshacer.', 'pluma-engine' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function textosSalaMaquinas(): array {
		return array(
			'cargando'                      => __( 'Cargando…', 'pluma-engine' ),
			'errorCarga'                    => __( 'No se pudo cargar. Reintenta en unos segundos.', 'pluma-engine' ),
			'errorAccion'                   => __( 'La acción no se pudo completar. Reintenta en unos segundos.', 'pluma-engine' ),
			'ejecutarMotor'                 => array(
				'titulo'      => __( 'Ejecutar el motor ahora', 'pluma-engine' ),
				'explicacion' => __( 'En producción el motor lo dispara el cron real del servidor. Si el cron no está configurado, el motor no se ejecuta solo y las piezas no avanzan por su cuenta: úsalo para procesar un ciclo a mano.', 'pluma-engine' ),
				'boton'       => __( 'Ejecutar un ciclo ahora', 'pluma-engine' ),
				'ejecutando'  => __( 'Ejecutando…', 'pluma-engine' ),
				'resultado'   => __( 'Ciclo completado. Lotes procesados:', 'pluma-engine' ),
				'error'       => __( 'No se pudo ejecutar el ciclo. Reintenta en unos segundos.', 'pluma-engine' ),
			),
			'bitacora'                      => array(
				'titulo'     => __( 'Bitácora del motor', 'pluma-engine' ),
				'vacia'      => __( 'el motor no se ha ejecutado todavía', 'pluma-engine' ),
				'inicio'     => __( 'Inicio', 'pluma-engine' ),
				'duracion'   => __( 'Duración', 'pluma-engine' ),
				'lotes'      => __( 'Lotes procesados', 'pluma-engine' ),
				'errores'    => __( 'Errores', 'pluma-engine' ),
				'sinErrores' => __( 'sin errores', 'pluma-engine' ),
				'enCurso'    => __( 'en curso', 'pluma-engine' ),
			),
			'coste'                         => array(
				'titulo'        => __( 'Coste', 'pluma-engine' ),
				'gastoHoy'      => __( 'Gasto de hoy', 'pluma-engine' ),
				'limiteDiario'  => __( 'Límite diario (USD)', 'pluma-engine' ),
				'guardarLimite' => __( 'Guardar límite', 'pluma-engine' ),
				'guardado'      => __( 'Límite actualizado', 'pluma-engine' ),
			),
			'apis'                          => array(
				'titulo'          => __( 'Estado de las APIs conectadas', 'pluma-engine' ),
				'openRouter'      => __( 'OpenRouter (proveedor de lenguaje)', 'pluma-engine' ),
				'googleTrends'    => __( 'Google Trends (Radar)', 'pluma-engine' ),
				'configurada'     => __( 'configurada', 'pluma-engine' ),
				'noConfigurada'   => __( 'sin configurar', 'pluma-engine' ),
				'circuitoAbierto' => __( 'en enfriamiento tras fallos repetidos', 'pluma-engine' ),
				'circuitoCerrado' => __( 'conectada', 'pluma-engine' ),
			),
			'llave'                         => array(
				'titulo'          => __( 'Llave de OpenRouter', 'pluma-engine' ),
				'actual'          => __( 'Llave actual', 'pluma-engine' ),
				'campoNueva'      => __( 'Nueva llave', 'pluma-engine' ),
				'guardar'         => __( 'Guardar llave', 'pluma-engine' ),
				'probar'          => __( 'Probar conexión', 'pluma-engine' ),
				'probando'        => __( 'Probando…', 'pluma-engine' ),
				'valida'          => __( 'La llave es válida.', 'pluma-engine' ),
				'invalida'        => __( 'La llave no es válida o no se pudo verificar.', 'pluma-engine' ),
				'cambiar'         => __( 'Cambiar llave', 'pluma-engine' ),
				'quitar'          => __( 'Quitar llave', 'pluma-engine' ),
				'confirmarQuitar' => __( '¿Quitar la llave? Sin ella, la redacción vuelve al modo mecánico de respaldo.', 'pluma-engine' ),
			),
			'cerebroRemoto'                 => array(
				'titulo'          => __( 'Cerebro remoto (T3)', 'pluma-engine' ),
				'urlActual'       => __( 'URL actual', 'pluma-engine' ),
				'campoUrl'        => __( 'URL del cerebro remoto', 'pluma-engine' ),
				'campoToken'      => __( 'Token de autenticación', 'pluma-engine' ),
				'guardar'         => __( 'Guardar', 'pluma-engine' ),
				'probar'          => __( 'Probar cerebro remoto', 'pluma-engine' ),
				'probando'        => __( 'Probando…', 'pluma-engine' ),
				'valida'          => __( 'El cerebro remoto respondió correctamente.', 'pluma-engine' ),
				'invalida'        => __( 'No se pudo alcanzar el cerebro remoto con esos datos.', 'pluma-engine' ),
				'cambiar'         => __( 'Cambiar', 'pluma-engine' ),
				'quitar'          => __( 'Quitar', 'pluma-engine' ),
				'confirmarQuitar' => __( '¿Quitar la configuración del cerebro remoto?', 'pluma-engine' ),
			),
			'searchConsole'                 => array(
				'titulo'               => __( 'Search Console', 'pluma-engine' ),
				'cargando'             => __( 'Cargando…', 'pluma-engine' ),
				'errorCarga'           => __( 'No se pudo cargar Search Console. Reintenta en unos segundos.', 'pluma-engine' ),
				'errorAccion'          => __( 'La acción no se pudo completar. Reintenta en unos segundos.', 'pluma-engine' ),
				'avisoConectado'       => __( 'Conectado con Google Search Console.', 'pluma-engine' ),
				'avisoError'           => __( 'No se pudo completar la conexión con Google. Verifica las credenciales e inténtalo de nuevo.', 'pluma-engine' ),
				'campoClientId'        => __( 'Client ID', 'pluma-engine' ),
				'campoClientSecret'    => __( 'Client secret', 'pluma-engine' ),
				'conectar'             => __( 'Conectar', 'pluma-engine' ),
				'conectando'           => __( 'Conectando…', 'pluma-engine' ),
				'redirectUriTitulo'    => __( 'URI de redirección', 'pluma-engine' ),
				'redirectUriAyuda'     => __( 'Registra esta URI exacta en tu proyecto de Google Cloud antes de continuar.', 'pluma-engine' ),
				'irAGoogle'            => __( 'Ir a Google para autorizar', 'pluma-engine' ),
				'elegirSitio'          => __( 'Elegir sitio de Search Console', 'pluma-engine' ),
				'guardarSitio'         => __( 'Guardar sitio', 'pluma-engine' ),
				'sitioActual'          => __( 'Sitio conectado', 'pluma-engine' ),
				'sincronizarAhora'     => __( 'Sincronizar ahora', 'pluma-engine' ),
				'sincronizando'        => __( 'Sincronizando…', 'pluma-engine' ),
				'ultimaSincronizacion' => __( 'Última sincronización', 'pluma-engine' ),
				'nuncaSincronizado'    => __( 'todavía no se ha sincronizado', 'pluma-engine' ),
				'circuitoAbierto'      => __( 'en enfriamiento tras fallos repetidos', 'pluma-engine' ),
				'desconectar'          => __( 'Desconectar', 'pluma-engine' ),
				'confirmarDesconectar' => __( '¿Desconectar Search Console? Se borran las credenciales y el sitio elegido.', 'pluma-engine' ),
				'tablaPagina'          => __( 'Página (post_id)', 'pluma-engine' ),
				'tablaConsulta'        => __( 'Consulta', 'pluma-engine' ),
				'tablaClics'           => __( 'Clics', 'pluma-engine' ),
				'tablaImpresiones'     => __( 'Impresiones', 'pluma-engine' ),
				'tablaCtr'             => __( 'CTR', 'pluma-engine' ),
				'tablaPosicion'        => __( 'Posición', 'pluma-engine' ),
				'sinMetricas'          => __( 'todavía no hay métricas sincronizadas', 'pluma-engine' ),
			),
			'transparencia'                 => array(
				'titulo'           => __( 'Transparencia y cumplimiento', 'pluma-engine' ),
				'explicacion'      => __( 'Cada pieza redactada por IA lleva un aviso de transparencia visible. Elige su formato; el marcado legible por máquina (Reglamento UE de IA, Art. 50) se añade siempre y no es desactivable.', 'pluma-engine' ),
				'etiquetaFormato'  => __( 'Formato del aviso visible', 'pluma-engine' ),
				'formatoBreve'     => __( 'Breve', 'pluma-engine' ),
				'formatoExtendido' => __( 'Extendido', 'pluma-engine' ),
				'guardar'          => __( 'Guardar formato', 'pluma-engine' ),
				'guardado'         => __( 'Formato actualizado', 'pluma-engine' ),
				'marcadoDeFabrica' => __( 'Las piezas publicadas por el motor sin aprobación humana activa (Autónomo, o Copiloto por expiración de la ventana de veto) se marcan como generadas por IA en metadatos legibles por máquina. Es un requisito de fábrica, no una opción.', 'pluma-engine' ),
				'errorCarga'       => __( 'No se pudo cargar la configuración de transparencia.', 'pluma-engine' ),
				'errorAccion'      => __( 'No se pudo guardar. Reintenta en unos segundos.', 'pluma-engine' ),
			),
			'riesgoLegal'                   => array(
				'titulo'          => __( 'Perfil de riesgo legal', 'pluma-engine' ),
				'explicacion'     => __( 'Declara el régimen de responsabilidad de tu jurisdicción real para difamación. En régimen penal, toda pieza con una afirmación negativa sobre una persona u organización identificable exige retención humana y excluye el modo Autónomo, sin excepción — no es un ajuste para relajar protecciones, es un hecho sobre dónde opera tu medio.', 'pluma-engine' ),
				'etiquetaRegimen' => __( 'Régimen de responsabilidad', 'pluma-engine' ),
				'regimenCivil'    => __( 'Civil (responsabilidad civil, sin figura penal de difamación)', 'pluma-engine' ),
				'regimenPenal'    => __( 'Penal (la difamación conserva figuras penales en mi jurisdicción)', 'pluma-engine' ),
				'guardar'         => __( 'Guardar régimen', 'pluma-engine' ),
				'guardado'        => __( 'Régimen actualizado', 'pluma-engine' ),
				'errorCarga'      => __( 'No se pudo cargar el perfil de riesgo legal.', 'pluma-engine' ),
				'errorAccion'     => __( 'No se pudo guardar. Reintenta en unos segundos.', 'pluma-engine' ),
			),
			'modeloVerificador'             => array(
				'titulo'         => __( 'Modelo verificador', 'pluma-engine' ),
				'explicacion'    => __( 'Declara un modelo distinto al premium para que el Corrector Interno lo use como segunda opinión.', 'pluma-engine' ),
				'etiquetaModelo' => __( 'Slug del modelo verificador (formato proveedor/modelo)', 'pluma-engine' ),
				'guardar'        => __( 'Guardar modelo', 'pluma-engine' ),
				'guardado'       => __( 'Modelo actualizado', 'pluma-engine' ),
				'notaAlcance'    => __( 'Hoy es informativo: si coincide con el modelo premium, el modo Autónomo no falla por eso todavía. La obligatoriedad dura llega tras validar el requisito con datos reales en modo Piloto.', 'pluma-engine' ),
				'errorCarga'     => __( 'No se pudo cargar el modelo verificador.', 'pluma-engine' ),
				'errorAccion'    => __( 'No se pudo guardar. Reintenta en unos segundos.', 'pluma-engine' ),
			),
			'modoRespeto'                   => array(
				'titulo'                 => __( 'Modo respeto', 'pluma-engine' ),
				'explicacion'            => __( 'Ante una tragedia de gravedad excepcional, congela humor y sátira en TODO el sitio, no solo en la pieza sobre el evento — se activa automáticamente cuando 2 o más tendencias de gravedad máxima coinciden en tema o geografía en una ventana corta, o con un clic manual. Solo el editor lo desactiva, nunca el sistema, y solo tras el piso de duración mínima.', 'pluma-engine' ),
				'activo'                 => __( 'Activo', 'pluma-engine' ),
				'inactivo'               => __( 'Inactivo', 'pluma-engine' ),
				'activadoEn'             => __( 'Activado el', 'pluma-engine' ),
				'activadoPorAutomatico'  => __( 'Activado automáticamente por el sistema', 'pluma-engine' ),
				'activadoPorManual'      => __( 'Activado manualmente por el editor', 'pluma-engine' ),
				'motivo'                 => __( 'Motivo', 'pluma-engine' ),
				'puedeDesactivarseDesde' => __( 'Puede desactivarse a partir de', 'pluma-engine' ),
				'activar'                => __( 'Activar modo respeto ahora', 'pluma-engine' ),
				'desactivar'             => __( 'Desactivar modo respeto', 'pluma-engine' ),
				'errorCarga'             => __( 'No se pudo cargar el estado del modo respeto.', 'pluma-engine' ),
				'errorAccion'            => __( 'No se pudo completar la acción. Reintenta en unos segundos.', 'pluma-engine' ),
				'aunNoDesactivable'      => __( 'Todavía no se cumple el piso de duración mínima — no puede desactivarse aún.', 'pluma-engine' ),
			),
			'imagenDestacada'               => array(
				'titulo'          => __( 'Imagen destacada por autoridad de fuente', 'pluma-engine' ),
				'explicacion'     => __( 'Toma la imagen del artículo, entre las fuentes recolectadas, con mayor nivel de confianza (A, B o C). Desactivado por fábrica.', 'pluma-engine' ),
				'avisoRiesgo'     => __( 'Aviso legal: usar imágenes de otros medios sin licencia puede infringir derechos de autor. Al activar "enlazada" o "descargada" aceptas asumir ese riesgo — PLUMA no verifica licencias de las imágenes de terceros. Mostrar u ocultar el crédito a la fuente no cambia este riesgo.', 'pluma-engine' ),
				'etiquetaModo'    => __( 'Modo', 'pluma-engine' ),
				'modoNinguna'     => __( 'Ninguna (no usar imagen de la fuente)', 'pluma-engine' ),
				'modoEnlazada'    => __( 'Enlazada (se muestra sin copiarla al servidor)', 'pluma-engine' ),
				'modoDescargada'  => __( 'Descargada (se sube a la biblioteca de medios de WordPress)', 'pluma-engine' ),
				'etiquetaCredito' => __( 'Mostrar crédito visible a la fuente original', 'pluma-engine' ),
				'notaCredito'     => __( 'El crédito es una atribución, no una licencia — desactivarlo no reduce el riesgo legal descrito arriba.', 'pluma-engine' ),
				'guardar'         => __( 'Guardar', 'pluma-engine' ),
				'guardado'        => __( 'Ajustes actualizados', 'pluma-engine' ),
				'errorCarga'      => __( 'No se pudo cargar la configuración de imagen destacada.', 'pluma-engine' ),
				'errorAccion'     => __( 'No se pudo guardar. Reintenta en unos segundos.', 'pluma-engine' ),
			),
			'creacionAutomaticaPeriodistas' => array(
				'titulo'            => __( 'Creación automática de periodistas', 'pluma-engine' ),
				'explicacion'       => __( 'Cuando hay suficientes noticias sin cobertura sobre un mismo tema, el motor puede proponer un periodista nuevo dedicado a ese tema — nunca uno por cada noticia suelta. La propuesta nace "Propuesta" en el Banco de Periodistas, con una ventana de veto antes de activarse sola. Desactivado por fábrica.', 'pluma-engine' ),
				'activar'           => __( 'Activar creación automática', 'pluma-engine' ),
				'activada'          => __( 'Creación automática activada', 'pluma-engine' ),
				'etiquetaMinPiezas' => __( 'Mínimo de noticias sin cobertura para considerar un periodista nuevo', 'pluma-engine' ),
				'etiquetaVentana'   => __( 'Ventana de días considerados', 'pluma-engine' ),
				'etiquetaCooldown'  => __( 'Horas mínimas entre evaluaciones', 'pluma-engine' ),
				'etiquetaMax'       => __( 'Máximo de periodistas automáticos en juego a la vez', 'pluma-engine' ),
				'guardar'           => __( 'Guardar', 'pluma-engine' ),
				'guardado'          => __( 'Ajustes actualizados', 'pluma-engine' ),
				'errorCarga'        => __( 'No se pudo cargar la configuración de creación automática de periodistas.', 'pluma-engine' ),
				'errorAccion'       => __( 'No se pudo guardar. Verifica que los números sean enteros mayores que cero.', 'pluma-engine' ),
			),
			'telemetria'                    => array(
				'titulo'         => __( 'Telemetría', 'pluma-engine' ),
				'explicacion'    => __( 'Opcional y anónima: versiones, modo de operación y conteos agregados (nunca contenido de piezas ni llaves). Hoy no se envía a ningún servidor todavía — solo se prepara el dato que se compartiría.', 'pluma-engine' ),
				'habilitar'      => __( 'Habilitar telemetría', 'pluma-engine' ),
				'deshabilitar'   => __( 'Telemetría habilitada', 'pluma-engine' ),
				'verPayload'     => __( 'Ver qué se compartiría', 'pluma-engine' ),
				'ocultarPayload' => __( 'Ocultar', 'pluma-engine' ),
			),
			'diagnostico'                   => array(
				'titulo'      => __( 'Modo diagnóstico', 'pluma-engine' ),
				'explicacion' => __( 'Genera un reporte técnico (versiones, bitácora reciente, conflictos detectados) para pegar en un ticket de soporte. Nunca incluye contenido de piezas ni llaves.', 'pluma-engine' ),
				'descargar'   => __( 'Descargar reporte de diagnóstico', 'pluma-engine' ),
				'descargando' => __( 'Generando…', 'pluma-engine' ),
			),
			'llamadasModelo'                => array(
				'titulo'          => __( 'Llamadas al modelo de IA', 'pluma-engine' ),
				'explicacion'     => __( 'Gasto real de los últimos 30 días, por propósito, origen y resultado — quién disparó cada llamada (cron, panel o un visitante) y si tuvo éxito.', 'pluma-engine' ),
				'vacio'           => __( 'sin llamadas registradas en los últimos 30 días', 'pluma-engine' ),
				'errorCarga'      => __( 'No se pudo cargar el resumen de llamadas al modelo.', 'pluma-engine' ),
				'proposito'       => __( 'Propósito', 'pluma-engine' ),
				'origen'          => __( 'Origen', 'pluma-engine' ),
				'resultado'       => __( 'Resultado', 'pluma-engine' ),
				'llamadas'        => __( 'Llamadas', 'pluma-engine' ),
				'costeUsd'        => __( 'Coste (USD)', 'pluma-engine' ),
				'origenCron'      => __( 'Cron', 'pluma-engine' ),
				'origenPanel'     => __( 'Panel', 'pluma-engine' ),
				'origenVisitante' => __( 'Visitante', 'pluma-engine' ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function textosEstudioSeo(): array {
		return array(
			'titulo'         => __( 'Estudio SEO y Taxonomía', 'pluma-engine' ),
			'cargando'       => __( 'Cargando…', 'pluma-engine' ),
			'errorCarga'     => __( 'No se pudo cargar el Estudio SEO y Taxonomía. Reintenta en unos segundos.', 'pluma-engine' ),
			'canibalizacion' => array(
				'titulo'  => __( 'Auditoría de canibalización', 'pluma-engine' ),
				'vacio'   => __( 'ninguna keyword compartida entre piezas publicadas', 'pluma-engine' ),
				'keyword' => __( 'Keyword principal', 'pluma-engine' ),
				'piezas'  => __( 'Piezas publicadas', 'pluma-engine' ),
			),
			'taxonomia'      => array(
				'titulo'           => __( 'Salud taxonómica', 'pluma-engine' ),
				'cuarentenaTitulo' => __( 'En cuarentena', 'pluma-engine' ),
				'cuarentenaVacio'  => __( 'sin categorías ni etiquetas en cuarentena', 'pluma-engine' ),
				'vecesUsada'       => __( 'veces usada', 'pluma-engine' ),
				'fusionTitulo'     => __( 'Propuestas de fusión', 'pluma-engine' ),
				'fusionVacio'      => __( 'sin pares casi-duplicados detectados', 'pluma-engine' ),
				'similitud'        => __( 'similitud', 'pluma-engine' ),
			),
			'tipo'           => array(
				'categoria' => __( 'categoría', 'pluma-engine' ),
				'etiqueta'  => __( 'etiqueta', 'pluma-engine' ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function textosOnboarding(): array {
		return array(
			'titulo'     => __( 'Bienvenido a PLUMA Engine', 'pluma-engine' ),
			'saltar'     => __( 'Saltar por ahora', 'pluma-engine' ),
			'continuar'  => __( 'Continuar', 'pluma-engine' ),
			'atras'      => __( 'Atrás', 'pluma-engine' ),
			'finalizar'  => __( 'Finalizar', 'pluma-engine' ),
			'errorCarga' => __( 'No se pudo cargar. Reintenta en unos segundos.', 'pluma-engine' ),
			'acto1'      => array(
				'titulo'              => __( 'Verificación técnica y cron real', 'pluma-engine' ),
				'etiquetaPhp'         => __( 'PHP', 'pluma-engine' ),
				'etiquetaWordPress'   => __( 'WordPress', 'pluma-engine' ),
				'etiquetaBaseDatos'   => __( 'Base de datos', 'pluma-engine' ),
				'cronOk'              => __( 'WP-Cron ya está desactivado — falta solo configurar el cron real de tu hosting.', 'pluma-engine' ),
				'cronAdvertencia'     => __( 'WP-Cron sigue activo. Desactívalo en wp-config.php y configura un cron real con los datos de abajo.', 'pluma-engine' ),
				'cronDatosTitulo'     => __( 'Datos del cron real', 'pluma-engine' ),
				'cronUrl'             => __( 'URL', 'pluma-engine' ),
				'cronCabecera'        => __( 'Cabecera', 'pluma-engine' ),
				'cronComandoTitulo'   => __( 'Comando de ejemplo (curl)', 'pluma-engine' ),
				'recetaCpanelTitulo'  => __( 'Receta genérica: cron de cPanel', 'pluma-engine' ),
				'recetaCpanelTexto'   => __( 'En cPanel → Cron Jobs, añade una tarea cada 5-15 minutos que ejecute el comando curl de arriba. Ajusta el intervalo a la cadencia de publicación que necesites.', 'pluma-engine' ),
				'recetaSistemaTitulo' => __( 'Receta genérica: cron de sistema (Linux)', 'pluma-engine' ),
				'recetaSistemaTexto'  => __( 'Añade una línea a tu crontab (*/10 * * * * seguido del comando curl de arriba) para ejecutarlo cada 10 minutos.', 'pluma-engine' ),
				'avisoGenerico'       => __( 'Estas son recetas genéricas, no instrucciones específicas de tu proveedor de hosting — verifica el panel de control de tu propio hosting si tu caso difiere.', 'pluma-engine' ),
			),
			'acto2'      => array(
				'titulo'           => __( 'Conecta tus llaves de API', 'pluma-engine' ),
				'googleTrendsInfo' => __( 'Google Trends no necesita ninguna llave — es un feed público, ya está listo.', 'pluma-engine' ),
			),
			'acto3'      => array(
				'titulo'                    => __( 'Línea editorial y categorías', 'pluma-engine' ),
				'lineaEditorialLabel'       => __( 'Línea editorial', 'pluma-engine' ),
				'lineaEditorialPlaceholder' => __( 'Ej: escepticismo informado, siempre citando fuentes primarias, sin sensacionalismo.', 'pluma-engine' ),
				'importarCategorias'        => __( 'Importar categorías existentes del sitio', 'pluma-engine' ),
				'importando'                => __( 'Importando…', 'pluma-engine' ),
				'resultadoImportadas'       => __( 'Categorías importadas', 'pluma-engine' ),
				'resultadoYaExistian'       => __( 'Ya existían', 'pluma-engine' ),
				'sinCategorias'             => __( 'Este sitio todavía no tiene categorías propias.', 'pluma-engine' ),
			),
			'acto4'      => array(
				'titulo'          => __( 'Tu primer periodista sintético', 'pluma-engine' ),
				'elegirPlantilla' => __( 'Elige una plantilla', 'pluma-engine' ),
				'crear'           => __( 'Crear periodista', 'pluma-engine' ),
				'creando'         => __( 'Creando…', 'pluma-engine' ),
				'ajusteFino'      => __( 'Ajuste fino opcional de su conducta', 'pluma-engine' ),
			),
			'acto5'      => array(
				'titulo'                => __( 'Elige el modo y ejecuta el primer ciclo', 'pluma-engine' ),
				'modoTitulo'            => __( 'Modo de operación', 'pluma-engine' ),
				'modoPilotoDescripcion' => __( 'Empieza en Piloto: cada pieza queda como borrador para tu revisión — nada se publica solo. Es la forma honesta de ver qué produce el sistema antes de automatizar.', 'pluma-engine' ),
				'primerCiclo'           => __( 'Ejecutar el primer ciclo ahora', 'pluma-engine' ),
				'ejecutando'            => __( 'Ejecutando…', 'pluma-engine' ),
				'resultadoTitulo'       => __( 'Resultado', 'pluma-engine' ),
				'sinLotes'              => __( 'El motor corrió pero todavía no había nada que procesar — es normal en un sitio recién instalado.', 'pluma-engine' ),
			),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function etiquetasEstados(): array {
		return array(
			EstadoPieza::Detectada->value           => __( 'Detectada', 'pluma-engine' ),
			EstadoPieza::EnInvestigacion->value     => __( 'En investigación', 'pluma-engine' ),
			EstadoPieza::Investigada->value         => __( 'Investigada', 'pluma-engine' ),
			EstadoPieza::EnRedaccion->value         => __( 'En redacción', 'pluma-engine' ),
			EstadoPieza::Redactada->value           => __( 'Redactada', 'pluma-engine' ),
			EstadoPieza::Optimizada->value          => __( 'Optimizada', 'pluma-engine' ),
			EstadoPieza::EnRevision->value          => __( 'En revisión', 'pluma-engine' ),
			EstadoPieza::Aprobada->value            => __( 'Aprobada', 'pluma-engine' ),
			EstadoPieza::Programada->value          => __( 'Programada', 'pluma-engine' ),
			EstadoPieza::Publicada->value           => __( 'Publicada', 'pluma-engine' ),
			EstadoPieza::Retenida->value            => __( 'Retenida', 'pluma-engine' ),
			EstadoPieza::Descartada->value          => __( 'Descartada', 'pluma-engine' ),
			EstadoPieza::Fallida->value             => __( 'Fallida', 'pluma-engine' ),
			EstadoPieza::SinPeriodistaIdoneo->value => __( 'Sin periodista idóneo', 'pluma-engine' ),
		);
	}
}
