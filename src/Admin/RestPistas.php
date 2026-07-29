<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Kernel\Capacidades;
use Pluma\Publicacion\GestorPistas;
use Pluma\Publicacion\PistaNoEncontradaException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Nivel Cuatro X.3 — el buzón de pistas: reporte público desde el hub de
 * Historia, revisión protegida con `pluma_aprobar_piezas`.
 */
final class RestPistas {

	private const RUTA_REPORTAR          = '/pistas';
	private const RUTA_LISTAR            = '/pistas/pendientes';
	private const RUTA_MARCAR_REVISADA   = '/pistas/(?P<id>\d+)/revisar';
	private const RUTA_MARCAR_DESCARTADA = '/pistas/(?P<id>\d+)/descartar';

	public function __construct( private readonly GestorPistas $gestor ) {
	}

	public function registrar(): void {
		add_action( 'rest_api_init', array( $this, 'registrarRutas' ) );
	}

	public function registrarRutas(): void {
		register_rest_route(
			'pluma/v1',
			self::RUTA_REPORTAR,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reportar' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_LISTAR,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'pendientes' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		foreach ( array(
			self::RUTA_MARCAR_REVISADA   => 'marcarRevisada',
			self::RUTA_MARCAR_DESCARTADA => 'marcarDescartada',
		) as $ruta => $metodo ) {
			register_rest_route(
				'pluma/v1',
				$ruta,
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, $metodo ),
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

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function reportar( WP_REST_Request $request ) {
		$historiaId = $request->get_param( 'historiaId' );

		if ( ! is_numeric( $historiaId ) ) {
			return new WP_Error( 'pluma_pista_historia_invalida', __( 'Falta indicar a qué historia se refiere la pista.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$contenido = sanitize_textarea_field( (string) $request->get_param( 'contenido' ) );

		if ( '' === $contenido ) {
			return new WP_Error( 'pluma_pista_sin_contenido', __( 'Cuéntanos qué sabes sobre esta historia.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$emailCrudo = $request->get_param( 'contactoEmail' );
		$email      = is_string( $emailCrudo ) ? sanitize_email( $emailCrudo ) : '';

		if ( '' !== $email && ! is_email( $email ) ) {
			return new WP_Error( 'pluma_pista_email_invalido', __( 'El correo indicado no es válido.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$id = $this->gestor->reportar( (int) $historiaId, $contenido, '' !== $email ? $email : null );

		return new WP_REST_Response( array( 'id' => $id ), 201 );
	}

	public function pendientes(): WP_REST_Response {
		$pistas = array_map(
			static fn ( $p ): array => array(
				'id'            => $p->id,
				'historiaId'    => $p->historiaId,
				'contenido'     => $p->contenido,
				'contactoEmail' => $p->contactoEmail,
				'creadoEn'      => $p->creadoEn->format( DATE_ATOM ),
			),
			$this->gestor->pendientes()
		);

		return new WP_REST_Response( $pistas, 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function marcarRevisada( WP_REST_Request $request ) {
		return $this->resolver( $request, static fn ( GestorPistas $g, int $id ) => $g->marcarRevisada( $id ) );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function marcarDescartada( WP_REST_Request $request ) {
		return $this->resolver( $request, static fn ( GestorPistas $g, int $id ) => $g->marcarDescartada( $id ) );
	}

	/**
	 * @param callable(GestorPistas, int): void $accion
	 * @return WP_REST_Response|WP_Error
	 */
	private function resolver( WP_REST_Request $request, callable $accion ) {
		$id = (int) $request->get_param( 'id' );

		try {
			$accion( $this->gestor, $id );
		} catch ( PistaNoEncontradaException $e ) {
			return new WP_Error( 'pluma_pista_no_encontrada', $e->getMessage(), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( array( 'id' => $id ), 200 );
	}
}
