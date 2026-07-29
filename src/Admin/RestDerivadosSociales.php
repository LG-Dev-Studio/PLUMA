<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Datos\RepositorioDerivadosSocialesInterface;
use Pluma\Kernel\Capacidades;
use Pluma\Publicacion\EstadoDerivadoSocial;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Nivel Cuatro W.2 — derivados por canal: el editor los revisa antes de
 * usarlos (PLUMA no publica solo a ninguna red social todavía,
 * `PLUMA-E9-4`). Protegido con `pluma_aprobar_piezas`.
 */
final class RestDerivadosSociales {

	private const RUTA_LISTAR    = '/derivados-sociales';
	private const RUTA_APROBAR   = '/derivados-sociales/(?P<id>\d+)/aprobar';
	private const RUTA_DESCARTAR = '/derivados-sociales/(?P<id>\d+)/descartar';

	public function __construct( private readonly RepositorioDerivadosSocialesInterface $derivados ) {
	}

	public function registrar(): void {
		add_action( 'rest_api_init', array( $this, 'registrarRutas' ) );
	}

	public function registrarRutas(): void {
		register_rest_route(
			'pluma/v1',
			self::RUTA_LISTAR,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'listar' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		foreach ( array(
			self::RUTA_APROBAR   => EstadoDerivadoSocial::Aprobado,
			self::RUTA_DESCARTAR => EstadoDerivadoSocial::Descartado,
		) as $ruta => $estado ) {
			register_rest_route(
				'pluma/v1',
				$ruta,
				array(
					'methods'             => 'POST',
					'callback'            => fn ( WP_REST_Request $request ) => $this->actualizarEstado( $request, $estado ),
					'permission_callback' => array( $this, 'autorizado' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => static fn ( $valor ): bool => is_numeric( $valor ),
						),
					),
				)
			);
		}
	}

	public function autorizado(): bool {
		return current_user_can( Capacidades::APROBAR_PIEZAS );
	}

	public function listar(): WP_REST_Response {
		$derivados = array_map(
			static fn ( $d ): array => array(
				'id'              => $d->id,
				'piezaId'         => $d->piezaId,
				'extractoSocial'  => $d->extractoSocial,
				'titularDiscover' => $d->titularDiscover,
				'estado'          => $d->estado->value,
				'creadoEn'        => $d->creadoEn->format( DATE_ATOM ),
			),
			$this->derivados->obtenerPorEstado( EstadoDerivadoSocial::Pendiente, 50 )
		);

		return new WP_REST_Response( $derivados, 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	private function actualizarEstado( WP_REST_Request $request, EstadoDerivadoSocial $estado ) {
		$id = (int) $request->get_param( 'id' );

		if ( null === $this->derivados->obtenerPorId( $id ) ) {
			return new WP_Error( 'pluma_derivado_social_no_encontrado', __( 'Derivado social no encontrado.', 'pluma-engine' ), array( 'status' => 404 ) );
		}

		$this->derivados->actualizarEstado( $id, $estado );

		return new WP_REST_Response( array( 'id' => $id ), 200 );
	}
}
