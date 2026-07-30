<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Datos\RepositorioBitacoraInterface;
use Pluma\Kernel\Activador;
use Pluma\Kernel\Capacidades;
use Pluma\Kernel\Cifrado;
use Pluma\Kernel\ExportadorDiagnostico;
use Pluma\Pipeline\Orquestador;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Proveedores\ProveedorGoogleTrends;
use Pluma\Proveedores\ProveedorOpenRouter;
use Pluma\Proveedores\TelemetriaInterface;
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

	private const LIMITE_BITACORA = 20;

	public function __construct(
		private readonly RepositorioBitacoraInterface $bitacora,
		private readonly PresupuestoLenguaje $presupuesto,
		private readonly ProveedorOpenRouter $openRouter,
		private readonly ProveedorGoogleTrends $googleTrends,
		private readonly TelemetriaInterface $telemetria,
		private readonly ExportadorDiagnostico $exportadorDiagnostico,
		private readonly Orquestador $orquestador,
		private readonly CreadorAutomaticoPeriodistas $creadorAutomaticoPeriodistas,
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
		return new WP_REST_Response( $this->orquestador->ejecutarTick(), 200 );
	}

	public function autorizado(): bool {
		return current_user_can( Capacidades::CONFIGURAR_MOTOR );
	}

	public function bitacora(): WP_REST_Response {
		return new WP_REST_Response( $this->bitacora->obtenerRecientes( self::LIMITE_BITACORA ), 200 );
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

	private function errorLlaveVacia(): WP_Error {
		return new WP_Error( 'pluma_llave_invalida', __( 'La llave no puede estar vacía.', 'pluma-engine' ), array( 'status' => 400 ) );
	}
}
