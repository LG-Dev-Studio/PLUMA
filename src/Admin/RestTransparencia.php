<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Kernel\Capacidades;
use Pluma\Redaccion\AvisoTransparenciaIa;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Transparencia y cumplimiento (Art. 50 UE, Nivel Tres N.3). El cliente
 * configura SOLO el formato del bloque de transparencia visible (la opción
 * controla el formato, no la existencia — GOVERNANCE §2.6). El marcado
 * legible por máquina (`Pluma\Seo\EmisorEsquemaFrontend`) es piso de fábrica
 * y NO se expone como interruptor aquí — ver ADR 0002.
 */
final class RestTransparencia {

	private const RUTA = '/motor/transparencia';

	public function registrar(): void {
		add_action( 'rest_api_init', array( $this, 'registrarRutas' ) );
	}

	public function registrarRutas(): void {
		register_rest_route(
			'pluma/v1',
			self::RUTA,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'obtener' ),
					'permission_callback' => array( $this, 'autorizado' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'actualizar' ),
					'permission_callback' => array( $this, 'autorizado' ),
				),
			)
		);
	}

	public function autorizado(): bool {
		return current_user_can( Capacidades::CONFIGURAR_MOTOR );
	}

	public function obtener(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'formato'            => $this->formatoActual(),
				'marcadoIaDeFabrica' => true,
			),
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function actualizar( WP_REST_Request $request ) {
		$formato = $request->get_param( 'formato' );

		if ( ! is_string( $formato ) || ! in_array( $formato, array( AvisoTransparenciaIa::FORMATO_BREVE, AvisoTransparenciaIa::FORMATO_EXTENDIDO ), true ) ) {
			return new WP_Error(
				'pluma_transparencia_formato_invalido',
				__( 'El formato debe ser "breve" o "extendido".', 'pluma-engine' ),
				array( 'status' => 400 )
			);
		}

		update_option( AvisoTransparenciaIa::OPCION_FORMATO, $formato, false );

		return new WP_REST_Response( array( 'formato' => $formato ), 200 );
	}

	private function formatoActual(): string {
		$formato = get_option( AvisoTransparenciaIa::OPCION_FORMATO, AvisoTransparenciaIa::FORMATO_BREVE );

		return is_string( $formato ) && AvisoTransparenciaIa::FORMATO_EXTENDIDO === $formato
			? AvisoTransparenciaIa::FORMATO_EXTENDIDO
			: AvisoTransparenciaIa::FORMATO_BREVE;
	}
}
