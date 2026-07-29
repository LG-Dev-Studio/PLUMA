<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Kernel\Capacidades;
use Pluma\Publicacion\GestorBoletines;
use Pluma\Publicacion\PeriodistaNoEncontradoParaBoletinException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Nivel Cuatro W.1 — el boletín como producto del periodista: composición
 * automática, disparo manual del editor. Protegido con
 * `pluma_aprobar_piezas` (misma capacidad que el resto de superficie
 * editorial, nunca `manage_options`).
 */
final class RestBoletines {

	private const RUTA_ENVIAR = '/boletines/(?P<periodistaId>\d+)/enviar';

	public function __construct( private readonly GestorBoletines $gestor ) {
	}

	public function registrar(): void {
		add_action( 'rest_api_init', array( $this, 'registrarRutas' ) );
	}

	public function registrarRutas(): void {
		register_rest_route(
			'pluma/v1',
			self::RUTA_ENVIAR,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'enviar' ),
				'permission_callback' => array( $this, 'autorizado' ),
				'args'                => array(
					'periodistaId' => array(
						'required'          => true,
						'validate_callback' => static fn ( $valor ): bool => is_numeric( $valor ),
					),
				),
			)
		);
	}

	public function autorizado(): bool {
		return current_user_can( Capacidades::APROBAR_PIEZAS );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function enviar( WP_REST_Request $request ) {
		try {
			$resultado = $this->gestor->enviar( (int) $request->get_param( 'periodistaId' ) );
		} catch ( PeriodistaNoEncontradoParaBoletinException $e ) {
			return new WP_Error( 'pluma_boletin_periodista_no_encontrado', $e->getMessage(), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $resultado, 200 );
	}
}
