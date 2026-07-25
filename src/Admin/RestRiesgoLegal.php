<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Compuertas\CompuertaRiesgo;
use Pluma\Compuertas\RegimenResponsabilidad;
use Pluma\Kernel\Capacidades;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Perfil de riesgo legal (Nivel Tres N.1): el cliente configura el régimen de
 * responsabilidad de su jurisdicción real. Default de fábrica `Civil`
 * (decisión del propietario, Etapa 7) — el cliente activa `Penal`
 * explícitamente si su jurisdicción lo exige. "Un perfil de jurisdicción no
 * es un dial que el cliente pueda relajar": aquí no hay piso que relajar, es
 * un hecho declarado por el cliente sobre dónde opera, igual que el `locale`
 * editorial.
 */
final class RestRiesgoLegal {

	private const RUTA = '/motor/riesgo-legal';

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
			array( 'regimenResponsabilidad' => $this->regimenActual()->value ),
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function actualizar( WP_REST_Request $request ) {
		$regimen = $request->get_param( 'regimenResponsabilidad' );

		if ( ! is_string( $regimen ) || null === RegimenResponsabilidad::tryFrom( $regimen ) ) {
			return new WP_Error(
				'pluma_regimen_responsabilidad_invalido',
				__( 'El régimen de responsabilidad debe ser "civil" o "penal".', 'pluma-engine' ),
				array( 'status' => 400 )
			);
		}

		update_option( CompuertaRiesgo::OPCION_REGIMEN_RESPONSABILIDAD, $regimen, false );

		return new WP_REST_Response( array( 'regimenResponsabilidad' => $regimen ), 200 );
	}

	private function regimenActual(): RegimenResponsabilidad {
		$regimen = get_option( CompuertaRiesgo::OPCION_REGIMEN_RESPONSABILIDAD, RegimenResponsabilidad::Civil->value );

		return is_string( $regimen ) ? ( RegimenResponsabilidad::tryFrom( $regimen ) ?? RegimenResponsabilidad::Civil ) : RegimenResponsabilidad::Civil;
	}
}
