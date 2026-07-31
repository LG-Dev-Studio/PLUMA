<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Datos\RepositorioBitacoraInterface;
use Pluma\Datos\RepositorioLlamadasModeloInterface;
use Pluma\Kernel\Activador;
use Pluma\Kernel\AlmacenPerfilEntornoInterface;
use Pluma\Kernel\Capacidades;
use Pluma\Kernel\Cifrado;
use Pluma\Kernel\ContextoEjecucion;
use Pluma\Kernel\ExportadorDiagnostico;
use Pluma\Kernel\RelojInterface;
use Pluma\Pipeline\Orquestador;
use Pluma\Proveedores\OrigenLlamada;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Proveedores\ProveedorCerebroRemoto;
use Pluma\Proveedores\ProveedorGoogleTrends;
use Pluma\Proveedores\ProveedorOpenRouter;
use Pluma\Proveedores\TelemetriaInterface;
use Pluma\Proveedores\ValidadorUrl;
use Pluma\Redaccion\CreadorAutomaticoPeriodistas;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Sala de Máquinas (Libro Cap. 10.2): "la bitácora del motor... coste por
 * pieza y por día contra presupuesto, estado de cada API conectada, y las
 * llaves/configuración técnica". Protegida con `pluma_configurar_motor`
 * (la misma capacidad que ya exige la pantalla).
 *
 * "Coste por pieza" y "reintentos" quedan fuera deliberadamente: no existe
 * atribución de gasto por Pieza (`pluma_bitacora_motor` solo agrega por
 * ejecución) ni mecanismo de reintento con backoff todavía (`PLUMA-E3-7`,
 * deuda abierta) — cero invención, se muestra el gasto agregado del día y
 * los errores tal como se registraron.
 *
 * La llave de OpenRouter nunca se devuelve en texto plano por ningún
 * endpoint: solo un booleano "configurada" y, como mucho, sus últimos 4
 * caracteres para que el editor confirme cuál puso.
 */
final class RestSalaMaquinas {

	private const RUTA_BITACORA     = '/motor/bitacora';
	private const RUTA_ESTADO       = '/motor/estado';
	private const RUTA_LLAVE        = '/motor/llave-openrouter';
	private const RUTA_PROBAR_LLAVE = '/motor/llave-openrouter/probar';
	private const RUTA_PRESUPUESTO  = '/motor/presupuesto';
	private const RUTA_TELEMETRIA   = '/motor/telemetria';
	private const RUTA_DIAGNOSTICO  = '/motor/diagnostico';
	private const RUTA_EJECUTAR     = '/motor/ejecutar';
	// Trabajo posterior a la Etapa 9 (creación automática de periodistas).
	private const RUTA_CREACION_AUTOMATICA_PERIODISTAS = '/motor/creacion-automatica-periodistas';
	// NCP-1 (`ADR 0010`): instrumento de medición de llamadas al modelo.
	private const RUTA_LLAMADAS_MODELO = '/motor/llamadas-modelo';
	// NCP-1 · Sonda de Capacidades (`ADR 0013`).
	private const RUTA_CEREBRO_REMOTO        = '/motor/cerebro-remoto';
	private const RUTA_PROBAR_CEREBRO_REMOTO = '/motor/cerebro-remoto/probar';
	private const RUTA_SONDA                 = '/motor/sonda';

	private const LIMITE_BITACORA = 20;
	// Misma ventana que la auditoría de NCP-1 espera consultar por defecto:
	// suficiente para ver una tendencia semanal sin cargar toda la bitácora.
	private const DIAS_VENTANA_LLAMADAS_MODELO = 30;

	public function __construct(
		private readonly RepositorioBitacoraInterface $bitacora,
		private readonly PresupuestoLenguaje $presupuesto,
		private readonly ProveedorOpenRouter $openRouter,
		private readonly ProveedorGoogleTrends $googleTrends,
		private readonly TelemetriaInterface $telemetria,
		private readonly ExportadorDiagnostico $exportadorDiagnostico,
		private readonly Orquestador $orquestador,
		private readonly CreadorAutomaticoPeriodistas $creadorAutomaticoPeriodistas,
		private readonly ContextoEjecucion $contextoEjecucion,
		private readonly RepositorioLlamadasModeloInterface $llamadasModelo,
		private readonly RelojInterface $reloj,
		private readonly ProveedorCerebroRemoto $cerebroRemoto,
		private readonly AlmacenPerfilEntornoInterface $almacenPerfilEntorno,
	) {
	}

	public function registrar(): void {
		add_action( 'rest_api_init', array( $this, 'registrarRutas' ) );
	}

	public function registrarRutas(): void {
		register_rest_route(
			'pluma/v1',
			self::RUTA_BITACORA,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'bitacora' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_ESTADO,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'estado' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_LLAVE,
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'guardarLlave' ),
					'permission_callback' => array( $this, 'autorizado' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'borrarLlave' ),
					'permission_callback' => array( $this, 'autorizado' ),
				),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_PROBAR_LLAVE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'probarLlave' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_PRESUPUESTO,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'actualizarPresupuesto' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_TELEMETRIA,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'telemetria' ),
					'permission_callback' => array( $this, 'autorizado' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'actualizarTelemetria' ),
					'permission_callback' => array( $this, 'autorizado' ),
				),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_CREACION_AUTOMATICA_PERIODISTAS,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'creacionAutomaticaPeriodistas' ),
					'permission_callback' => array( $this, 'autorizado' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'actualizarCreacionAutomaticaPeriodistas' ),
					'permission_callback' => array( $this, 'autorizado' ),
				),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_LLAMADAS_MODELO,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'llamadasModelo' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_CEREBRO_REMOTO,
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'guardarCerebroRemoto' ),
					'permission_callback' => array( $this, 'autorizado' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'borrarCerebroRemoto' ),
					'permission_callback' => array( $this, 'autorizado' ),
				),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_PROBAR_CEREBRO_REMOTO,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'probarCerebroRemoto' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_SONDA,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'sonda' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_DIAGNOSTICO,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'diagnostico' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_EJECUTAR,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'ejecutar' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);
	}

	/**
	 * Ejecución manual del motor desde el panel. El punto de entrada normal
	 * es el cron real autenticado por token (`RestOrquestador`), pero sin un
	 * cron configurado el motor no arranca nunca por su cuenta y el editor
	 * se queda mirando piezas que no avanzan. Aquí la autenticación es por
	 * capacidad (sesión de wp-admin), no por token: es un humano pulsando un
	 * botón, no una máquina. Mismo `ejecutarTick()`, mismo candado global —
	 * si el cron real corre a la vez, la segunda ejecución sale en silencio.
	 */
	public function ejecutar(): WP_REST_Response {
		// NCP-1 (`ADR 0010`): esta es la ejecución manual del motor desde el
		// panel (sesión autenticada por capacidad) — origen `panel` para la
		// bitácora de llamadas al modelo, nunca `visitante`.
		$this->contextoEjecucion->declarar( OrigenLlamada::Panel );

		return new WP_REST_Response( $this->orquestador->ejecutarTick(), 200 );
	}

	public function autorizado(): bool {
		return current_user_can( Capacidades::CONFIGURAR_MOTOR );
	}

	public function bitacora(): WP_REST_Response {
		return new WP_REST_Response( $this->bitacora->obtenerRecientes( self::LIMITE_BITACORA ), 200 );
	}

	/**
	 * NCP-1 (`ADR 0010`): resumen agregado por propósito/origen/resultado de
	 * los últimos {@see self::DIAS_VENTANA_LLAMADAS_MODELO} días — el dato
	 * crudo que hace visible en el panel la instrumentación de esta porción,
	 * incluida la exposición real de §5.1.4 (filas con origen `visitante`).
	 */
	public function llamadasModelo(): WP_REST_Response {
		$hasta = $this->reloj->ahora();
		$desde = $hasta->modify( '-' . self::DIAS_VENTANA_LLAMADAS_MODELO . ' days' );

		return new WP_REST_Response( $this->llamadasModelo->resumirEntre( $desde, $hasta ), 200 );
	}

	public function estado(): WP_REST_Response {
		$llave       = $this->llaveConfigurada();
		$configurada = null !== $llave;

		return new WP_REST_Response(
			array(
				'gastoHoyUsd'     => round( $this->presupuesto->gastoHoyUsd(), 4 ),
				'limiteDiarioUsd' => $this->presupuesto->limiteDiarioUsd(),
				'openRouter'      => array(
					'configurada'     => $configurada,
					'ultimosCuatro'   => $configurada ? substr( $llave, -4 ) : null,
					'circuitoAbierto' => $this->openRouter->circuitoAbierto(),
				),
				'googleTrends'    => array(
					'circuitoAbierto' => $this->googleTrends->circuitoAbierto(),
				),
				'cerebroRemoto'   => array(
					'configurada'    => $this->cerebroRemoto->configurado(),
					'url'            => $this->urlCerebroRemotoConfigurada(),
					'ultimaPruebaOk' => $this->cerebroRemoto->ultimaPruebaOk(),
				),
			),
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function guardarLlave( WP_REST_Request $request ) {
		$llave = $request->get_param( 'llave' );

		if ( ! is_string( $llave ) || '' === trim( $llave ) ) {
			return $this->errorLlaveVacia();
		}

		update_option( ProveedorOpenRouter::OPCION_LLAVE_CIFRADA, Cifrado::cifrar( trim( $llave ) ), false );

		return new WP_REST_Response( array( 'guardada' => true ), 200 );
	}

	public function borrarLlave(): WP_REST_Response {
		delete_option( ProveedorOpenRouter::OPCION_LLAVE_CIFRADA );

		return new WP_REST_Response( array( 'borrada' => true ), 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function probarLlave( WP_REST_Request $request ) {
		$llave = $request->get_param( 'llave' );

		if ( ! is_string( $llave ) || '' === trim( $llave ) ) {
			return $this->errorLlaveVacia();
		}

		return new WP_REST_Response( array( 'valida' => $this->openRouter->probarLlave( trim( $llave ) ) ), 200 );
	}

	/**
	 * NCP-1 · Sonda de Capacidades (`ADR 0013`): guarda URL + token del
	 * cerebro remoto (T3). No auto-prueba — misma UX que la llave de
	 * OpenRouter, "Guardar" y "Probar" son acciones separadas.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function guardarCerebroRemoto( WP_REST_Request $request ) {
		$url   = $request->get_param( 'url' );
		$token = $request->get_param( 'token' );

		if ( ! is_string( $url ) || '' === trim( $url ) || ! ValidadorUrl::esSegura( trim( $url ) ) ) {
			return new WP_Error(
				'pluma_cerebro_remoto_url_invalida',
				__( 'La URL del cerebro remoto debe ser una dirección https válida y no privada.', 'pluma-engine' ),
				array( 'status' => 400 )
			);
		}

		if ( ! is_string( $token ) || '' === trim( $token ) ) {
			return new WP_Error(
				'pluma_cerebro_remoto_token_invalido',
				__( 'El token del cerebro remoto no puede estar vacío.', 'pluma-engine' ),
				array( 'status' => 400 )
			);
		}

		update_option( ProveedorCerebroRemoto::OPCION_URL, trim( $url ), false );
		update_option( ProveedorCerebroRemoto::OPCION_TOKEN_CIFRADO, Cifrado::cifrar( trim( $token ) ), false );

		return new WP_REST_Response( array( 'guardada' => true ), 200 );
	}

	public function borrarCerebroRemoto(): WP_REST_Response {
		delete_option( ProveedorCerebroRemoto::OPCION_URL );
		delete_option( ProveedorCerebroRemoto::OPCION_TOKEN_CIFRADO );
		delete_option( ProveedorCerebroRemoto::OPCION_ULTIMA_PRUEBA_OK );

		return new WP_REST_Response( array( 'borrada' => true ), 200 );
	}

	/**
	 * Prueba los valores candidatos del formulario (no necesariamente los
	 * guardados, misma UX que `probarLlave()`). Si coinciden con los
	 * realmente guardados, persiste el resultado — así probar una URL
	 * todavía no guardada nunca envenena el caché de la URL configurada de
	 * verdad (`SensorCapacidades` lee ese caché, jamás prueba en vivo).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function probarCerebroRemoto( WP_REST_Request $request ) {
		$url   = $request->get_param( 'url' );
		$token = $request->get_param( 'token' );

		if ( ! is_string( $url ) || '' === trim( $url ) || ! is_string( $token ) || '' === trim( $token ) ) {
			return new WP_Error(
				'pluma_cerebro_remoto_datos_incompletos',
				__( 'Falta la URL o el token a probar.', 'pluma-engine' ),
				array( 'status' => 400 )
			);
		}

		$valida = $this->cerebroRemoto->probar( trim( $url ), trim( $token ) );

		if ( trim( $url ) === get_option( ProveedorCerebroRemoto::OPCION_URL ) ) {
			update_option( ProveedorCerebroRemoto::OPCION_ULTIMA_PRUEBA_OK, $valida, false );
		}

		return new WP_REST_Response( array( 'valida' => $valida ), 200 );
	}

	/**
	 * NCP-1 · Sonda de Capacidades (`ADR 0013`): el snapshot cacheado del
	 * Perfil de Entorno, sin refrescarlo (`leer()` falla abierto por su
	 * cuenta si hace falta).
	 */
	public function sonda(): WP_REST_Response {
		$perfil = $this->almacenPerfilEntorno->leer();

		return new WP_REST_Response(
			array(
				'transportePrioritario' => $perfil->transportePrioritario->value,
				'medidoEn'              => $perfil->medidoEn->format( DATE_ATOM ),
				'hechos'                => array(
					'ffiDisponible'                 => $perfil->hechos->ffiDisponible,
					'memoriaLimiteMb'               => $perfil->hechos->memoriaLimiteMb,
					'tiempoMaximoEjecucionSegundos' => $perfil->hechos->tiempoMaximoEjecucionSegundos,
					'procesoHijoDisponible'         => $perfil->hechos->procesoHijoDisponible,
					'cerebroRemotoConfigurado'      => $perfil->hechos->cerebroRemotoConfigurado,
					'apiPagoConfigurada'            => $perfil->hechos->apiPagoConfigurada,
				),
			),
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function actualizarPresupuesto( WP_REST_Request $request ) {
		$limite = $request->get_param( 'limiteDiarioUsd' );

		if ( ! is_numeric( $limite ) || (float) $limite < 0 ) {
			return new WP_Error(
				'pluma_presupuesto_invalido',
				__( 'El límite diario debe ser un número mayor o igual a cero.', 'pluma-engine' ),
				array( 'status' => 400 )
			);
		}

		update_option( PresupuestoLenguaje::OPCION_LIMITE_DIARIO, (float) $limite, false );

		return new WP_REST_Response( array( 'limiteDiarioUsd' => (float) $limite ), 200 );
	}

	public function telemetria(): WP_REST_Response {
		$habilitada = (bool) get_option( Activador::OPCION_TELEMETRIA_HABILITADA, false );

		return new WP_REST_Response(
			array(
				'habilitada'         => $habilitada,
				'vistaPreviaPayload' => $this->telemetria->construirPayload(),
			),
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function actualizarTelemetria( WP_REST_Request $request ) {
		$habilitada = $request->get_param( 'habilitada' );

		if ( ! is_bool( $habilitada ) ) {
			return new WP_Error(
				'pluma_telemetria_invalida',
				__( 'El valor de telemetría debe ser verdadero o falso.', 'pluma-engine' ),
				array( 'status' => 400 )
			);
		}

		update_option( Activador::OPCION_TELEMETRIA_HABILITADA, $habilitada, false );

		return new WP_REST_Response( array( 'habilitada' => $habilitada ), 200 );
	}

	/**
	 * Trabajo posterior a la Etapa 9 (creación automática de periodistas):
	 * expone siempre el valor EFECTIVO de cada ajuste (opción real, o el
	 * defecto de fábrica cuando no está fijada) — mismo criterio que
	 * `estado()` con `limiteDiarioUsd()`.
	 */
	public function creacionAutomaticaPeriodistas(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'activada'       => $this->creadorAutomaticoPeriodistas->activada(),
				'minPiezasGrupo' => $this->creadorAutomaticoPeriodistas->minPiezasGrupo(),
				'ventanaDias'    => $this->creadorAutomaticoPeriodistas->ventanaDias(),
				'cooldownHoras'  => $this->creadorAutomaticoPeriodistas->cooldownHoras(),
				'maxPeriodistas' => $this->creadorAutomaticoPeriodistas->maxPeriodistas(),
			),
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function actualizarCreacionAutomaticaPeriodistas( WP_REST_Request $request ) {
		$activada = $request->get_param( 'activada' );

		if ( ! is_bool( $activada ) ) {
			return $this->errorCreacionAutomaticaPeriodistasInvalida( __( 'El valor de activación debe ser verdadero o falso.', 'pluma-engine' ) );
		}

		$campos = array(
			'minPiezasGrupo' => CreadorAutomaticoPeriodistas::OPCION_MIN_PIEZAS_GRUPO,
			'ventanaDias'    => CreadorAutomaticoPeriodistas::OPCION_VENTANA_DIAS,
			'cooldownHoras'  => CreadorAutomaticoPeriodistas::OPCION_COOLDOWN_HORAS,
			'maxPeriodistas' => CreadorAutomaticoPeriodistas::OPCION_MAX_PERIODISTAS,
		);

		$valores = array( 'activada' => $activada );

		foreach ( $campos as $campo => $opcion ) {
			$valor = $request->get_param( $campo );

			if ( ! is_numeric( $valor ) || (int) $valor <= 0 ) {
				return $this->errorCreacionAutomaticaPeriodistasInvalida(
					__( 'Los números de configuración deben ser enteros mayores que cero.', 'pluma-engine' )
				);
			}

			$valores[ $campo ] = (int) $valor;
			update_option( $opcion, (int) $valor, false );
		}

		update_option( CreadorAutomaticoPeriodistas::OPCION_ACTIVADA, $activada, false );

		return new WP_REST_Response( $valores, 200 );
	}

	private function errorCreacionAutomaticaPeriodistasInvalida( string $mensaje ): WP_Error {
		return new WP_Error( 'pluma_creacion_automatica_periodistas_invalida', $mensaje, array( 'status' => 400 ) );
	}

	public function diagnostico(): WP_REST_Response {
		return new WP_REST_Response( $this->exportadorDiagnostico->exportar(), 200 );
	}

	private function llaveConfigurada(): ?string {
		$sobre = get_option( ProveedorOpenRouter::OPCION_LLAVE_CIFRADA );

		if ( ! is_string( $sobre ) || '' === $sobre ) {
			return null;
		}

		return Cifrado::descifrar( $sobre );
	}

	private function urlCerebroRemotoConfigurada(): ?string {
		$url = get_option( ProveedorCerebroRemoto::OPCION_URL );

		return is_string( $url ) && '' !== $url ? $url : null;
	}

	private function errorLlaveVacia(): WP_Error {
		return new WP_Error( 'pluma_llave_invalida', __( 'La llave no puede estar vacía.', 'pluma-engine' ), array( 'status' => 400 ) );
	}
}
