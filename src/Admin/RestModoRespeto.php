<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Compuertas\GestorModoRespeto;
use Pluma\Compuertas\ModoRespetoAunNoDesactivableException;
use Pluma\Kernel\Capacidades;
use Pluma\Kernel\RelojInterface;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Modo respeto (Nivel Dos F.1-F.3): estado actual + activación manual de un
 * clic + desactivación (bloqueada mientras no se cumpla el piso de duración
 * mínima).
 */
final class RestModoRespeto {

	private const RUTA            = '/motor/modo-respeto';
	private const RUTA_ACTIVAR    = '/motor/modo-respeto/activar';
	private const RUTA_DESACTIVAR = '/motor/modo-respeto/desactivar';

	public function __construct(
		private readonly GestorModoRespeto $gestor,
		private readonly RelojInterface $reloj,
	) {
	}

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
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_ACTIVAR,
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'activar' ),
					'permission_callback' => array( $this, 'autorizado' ),
				),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_DESACTIVAR,
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'desactivar' ),
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

	public function activar( WP_REST_Request $request ): WP_REST_Response {
		$motivo = $request->get_param( 'motivo' );

		$this->gestor->activarManualmente(
			is_string( $motivo ) && '' !== trim( $motivo ) ? $motivo : __( 'Activado manualmente por el editor.', 'pluma-engine' ),
			$this->reloj->ahora()
		);

		return new WP_REST_Response( $this->estadoComoArray(), 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function desactivar() {
		try {
			$this->gestor->desactivar( $this->reloj->ahora() );
		} catch ( ModoRespetoAunNoDesactivableException $e ) {
			return new WP_Error(
				'pluma_modo_respeto_aun_no_desactivable',
				$e->getMessage(),
				array(
					'status'                 => 409,
					'puedeDesactivarseDesde' => $e->puedeDesactivarseDesde->format( DATE_ATOM ),
				)
			);
		}

		return new WP_REST_Response( $this->estadoComoArray(), 200 );
	}

	/**
	 * @return array{activo: bool, activadoEn: ?string, activadoPor: ?string, motivo: ?string, puedeDesactivarseDesde: ?string}
	 */
	private function estadoComoArray(): array {
		$estado = $this->gestor->estadoActual();

		return array(
			'activo'                 => $estado->activo,
			'activadoEn'             => $estado->activadoEn?->format( DATE_ATOM ),
			'activadoPor'            => $estado->activadoPor?->value,
			'motivo'                 => $estado->motivo,
			'puedeDesactivarseDesde' => $estado->puedeDesactivarseDesde?->format( DATE_ATOM ),
		);
	}
}
