<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Kernel\Capacidades;
use Pluma\Publicacion\CorreccionNoEncontradaException;
use Pluma\Publicacion\GestorCorrecciones;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Nivel Cuatro X.4 — la corrección con crédito: reporte público de error,
 * verificación humana obligatoria. Reporte público (`__return_true`, mismo
 * patrón de sanitización que `Pluma\Admin\RestSuscripciones`); gestión
 * protegida con `pluma_aprobar_piezas`.
 */
final class RestCorrecciones {

	private const RUTA_REPORTAR  = '/correcciones';
	private const RUTA_LISTAR    = '/correcciones/pendientes';
	private const RUTA_VERIFICAR = '/correcciones/(?P<id>\d+)/verificar';
	private const RUTA_RECHAZAR  = '/correcciones/(?P<id>\d+)/rechazar';

	public function __construct( private readonly GestorCorrecciones $gestor ) {
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
			self::RUTA_VERIFICAR => 'verificar',
			self::RUTA_RECHAZAR  => 'rechazar',
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
		$piezaId = $request->get_param( 'piezaId' );

		if ( ! is_numeric( $piezaId ) ) {
			return new WP_Error( 'pluma_correccion_pieza_invalida', __( 'Falta indicar qué pieza tiene el error.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$afirmacion = sanitize_textarea_field( (string) $request->get_param( 'afirmacionReportada' ) );
		$evidencia  = sanitize_textarea_field( (string) $request->get_param( 'evidenciaAportada' ) );

		if ( '' === $afirmacion || '' === $evidencia ) {
			return new WP_Error( 'pluma_correccion_datos_incompletos', __( 'Indica qué afirmación es incorrecta y qué evidencia lo demuestra.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$emailCrudo = $request->get_param( 'emailReportante' );
		$email      = is_string( $emailCrudo ) ? sanitize_email( $emailCrudo ) : '';

		if ( '' !== $email && ! is_email( $email ) ) {
			return new WP_Error( 'pluma_correccion_email_invalido', __( 'El correo indicado no es válido.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$creditoOptIn = (bool) $request->get_param( 'creditoOptIn' );
		$nombreCrudo  = $request->get_param( 'nombreCredito' );
		$nombre       = is_string( $nombreCrudo ) ? sanitize_text_field( $nombreCrudo ) : '';

		$id = $this->gestor->reportar(
			(int) $piezaId,
			$afirmacion,
			$evidencia,
			'' !== $email ? $email : null,
			'' !== $nombre ? $nombre : null,
			$creditoOptIn
		);

		return new WP_REST_Response( array( 'id' => $id ), 201 );
	}

	public function pendientes(): WP_REST_Response {
		$correcciones = array_map(
			static fn ( $c ): array => array(
				'id'                  => $c->id,
				'piezaId'             => $c->piezaId,
				'afirmacionReportada' => $c->afirmacionReportada,
				'evidenciaAportada'   => $c->evidenciaAportada,
				'nombreCredito'       => $c->nombreCredito,
				'creditoOptIn'        => $c->creditoOptIn,
				'creadoEn'            => $c->creadoEn->format( DATE_ATOM ),
			),
			$this->gestor->pendientes()
		);

		return new WP_REST_Response( $correcciones, 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function verificar( WP_REST_Request $request ) {
		return $this->resolver( $request, 'verificar' );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function rechazar( WP_REST_Request $request ) {
		return $this->resolver( $request, 'rechazar' );
	}

	/**
	 * @param 'verificar'|'rechazar' $accion
	 * @return WP_REST_Response|WP_Error
	 */
	private function resolver( WP_REST_Request $request, string $accion ) {
		$id         = (int) $request->get_param( 'id' );
		$notaCruda  = $request->get_param( 'notaEditor' );
		$notaEditor = is_string( $notaCruda ) && '' !== $notaCruda ? sanitize_textarea_field( $notaCruda ) : null;

		try {
			if ( 'verificar' === $accion ) {
				$this->gestor->verificar( $id, $notaEditor );
			} else {
				$this->gestor->rechazar( $id, $notaEditor );
			}
		} catch ( CorreccionNoEncontradaException $e ) {
			return new WP_Error( 'pluma_correccion_no_encontrada', $e->getMessage(), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( array( 'id' => $id ), 200 );
	}
}
