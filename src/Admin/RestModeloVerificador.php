<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Kernel\Capacidades;
use Pluma\Proveedores\EnrutadorModelos;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Modelo verificador (Nivel Tres J.1-J.2): el cliente puede declarar un
 * modelo distinto al premium para el Corrector Interno. Alcance de Etapa 7:
 * solo el contrato existe — con la configuración de fábrica (sin valor
 * propio), redactor y verificador comparten modelo y familia, y Autónomo NO
 * falla por eso todavía (la obligatoriedad dura espera validación empírica
 * en Piloto, ADR 0003).
 */
final class RestModeloVerificador {

	private const RUTA = '/motor/modelo-verificador';

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
		$enrutador = new EnrutadorModelos();

		return new WP_REST_Response(
			array(
				'modeloVerificador'       => $enrutador->modeloVerificador(),
				'obligatoriedadDeFabrica' => false,
			),
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function actualizar( WP_REST_Request $request ) {
		$modelo = $request->get_param( 'modeloVerificador' );

		if ( ! is_string( $modelo ) || '' === trim( $modelo ) ) {
			return new WP_Error(
				'pluma_modelo_verificador_invalido',
				__( 'El modelo verificador no puede estar vacío.', 'pluma-engine' ),
				array( 'status' => 400 )
			);
		}

		update_option( EnrutadorModelos::OPCION_MODELO_VERIFICADOR, sanitize_text_field( $modelo ), false );

		return new WP_REST_Response( array( 'modeloVerificador' => $modelo ), 200 );
	}
}
