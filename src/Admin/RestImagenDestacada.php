<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Kernel\Capacidades;
use Pluma\Publicacion\AsignadorImagenDestacada;
use Pluma\Publicacion\ModoImagenDestacada;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Imagen destacada por autoridad de fuente (Nivel Dos, decisión del
 * propietario — `ADR 0006`): el cliente elige el modo (ninguna/enlazada/
 * descargada) y si el crédito a la fuente es visible. Default de fábrica
 * `ninguna` — nadie queda expuesto al riesgo legal de usar imágenes de
 * terceros sin activarlo explícitamente.
 */
final class RestImagenDestacada {

	private const RUTA = '/motor/imagen-destacada';

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
		return new WP_REST_Response( $this->estadoComoArray(), 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function actualizar( WP_REST_Request $request ) {
		$modo = $request->get_param( 'modo' );

		if ( ! is_string( $modo ) || null === ModoImagenDestacada::tryFrom( $modo ) ) {
			return new WP_Error(
				'pluma_modo_imagen_destacada_invalido',
				__( 'El modo debe ser "ninguna", "enlazada" o "descargada".', 'pluma-engine' ),
				array( 'status' => 400 )
			);
		}

		update_option( AsignadorImagenDestacada::OPCION_MODO, $modo, false );
		update_option( AsignadorImagenDestacada::OPCION_CREDITO_VISIBLE, (bool) $request->get_param( 'creditoVisible' ), false );

		return new WP_REST_Response( $this->estadoComoArray(), 200 );
	}

	/**
	 * @return array{modo: string, creditoVisible: bool}
	 */
	private function estadoComoArray(): array {
		$modo = get_option( AsignadorImagenDestacada::OPCION_MODO, ModoImagenDestacada::Ninguna->value );

		return array(
			'modo'           => is_string( $modo ) ? $modo : ModoImagenDestacada::Ninguna->value,
			'creditoVisible' => (bool) get_option( AsignadorImagenDestacada::OPCION_CREDITO_VISIBLE, true ),
		);
	}
}
