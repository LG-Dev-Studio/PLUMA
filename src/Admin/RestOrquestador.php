<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Kernel\Activador;
use Pluma\Kernel\ContextoEjecucion;
use Pluma\Pipeline\Orquestador;
use Pluma\Proveedores\OrigenLlamada;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Punto de entrada del cron real (GOVERNANCE §3.6, Libro Cap. 9.4): token
 * secreto rotable + rate limit. El cron real del servidor golpea esta ruta;
 * WP-Cron queda deshabilitado para el motor.
 */
final class RestOrquestador {

	private const RUTA                         = '/motor/tick';
	private const CABECERA_TOKEN               = 'X-Pluma-Token';
	private const TRANSIENT_RATE_LIMIT         = 'pluma_motor_rate_limit';
	private const RATE_LIMIT_SEGUNDOS          = 30;
	private const PRESUPUESTO_SEGUNDOS_DEFECTO = 90;

	public function __construct(
		private readonly Orquestador $orquestador,
		private readonly ContextoEjecucion $contextoEjecucion,
	) {
	}

	public function registrar(): void {
		add_action( 'rest_api_init', array( $this, 'registrarRuta' ) );
	}

	public function registrarRuta(): void {
		register_rest_route(
			'pluma/v1',
			self::RUTA,
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'tick' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);
	}

	/**
	 * @return bool|WP_Error
	 */
	public function autorizado( WP_REST_Request $request ) {
		$tokenRecibido = $request->get_header( self::CABECERA_TOKEN );
		$tokenEsperado = get_option( Activador::OPCION_MOTOR_TOKEN );

		if ( ! is_string( $tokenRecibido ) || ! is_string( $tokenEsperado ) || '' === $tokenEsperado ) {
			return new WP_Error( 'pluma_sin_token', __( 'Falta el token del motor.', 'pluma-engine' ), array( 'status' => 401 ) );
		}

		if ( ! hash_equals( $tokenEsperado, $tokenRecibido ) ) {
			return new WP_Error( 'pluma_token_invalido', __( 'Token del motor inválido.', 'pluma-engine' ), array( 'status' => 401 ) );
		}

		if ( false !== get_transient( self::TRANSIENT_RATE_LIMIT ) ) {
			return new WP_Error(
				'pluma_rate_limit',
				__( 'El motor ya se ejecutó recientemente; espera antes de reintentar.', 'pluma-engine' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	public function tick( WP_REST_Request $request ): WP_REST_Response {
		set_transient( self::TRANSIENT_RATE_LIMIT, 1, self::RATE_LIMIT_SEGUNDOS );
		// NCP-1 (`ADR 0010`): este es el ÚNICO punto de entrada del cron real
		// autenticado por token — todo lo que dispare `ejecutarTick()` desde
		// aquí es origen `cron` para la bitácora de llamadas al modelo.
		$this->contextoEjecucion->declarar( OrigenLlamada::Cron );

		$resultado = $this->orquestador->ejecutarTick( $this->presupuestoSolicitado( $request ) );

		return new WP_REST_Response( $resultado, 200 );
	}

	/**
	 * Permite ajustar el presupuesto de la ejecución desde la configuración
	 * del cron real del hosting (`?presupuesto=60`), acotado a un rango
	 * sensato para que nadie convierta el endpoint en una petición eterna.
	 */
	private function presupuestoSolicitado( WP_REST_Request $request ): int {
		$parametro = $request->get_param( 'presupuesto' );

		if ( ! is_numeric( $parametro ) ) {
			return self::PRESUPUESTO_SEGUNDOS_DEFECTO;
		}

		return max( 10, min( 300, (int) $parametro ) );
	}
}
