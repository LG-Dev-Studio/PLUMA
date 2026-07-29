<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Kernel\Capacidades;
use Pluma\Proveedores\ClavesVapid;
use Pluma\Publicacion\GestorSuscripciones;
use Pluma\Publicacion\NotificadorSuscripciones;
use Pluma\Publicacion\SuscripcionNoEncontradaException;
use Pluma\Publicacion\TipoSuscripcion;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Suscripciones de precisión (Nivel Cuatro W.3): alta pública con doble
 * opt-in por email, confirmación/baja de un clic (público, por token —
 * nadie necesita iniciar sesión para gestionar su propia suscripción), y
 * gestión administrativa protegida con `pluma_aprobar_piezas` (misma
 * capacidad que el resto de superficie editorial, nunca `manage_options`).
 */
final class RestSuscripciones {

	private const RUTA_SUSCRIBIRSE      = '/suscripciones';
	private const RUTA_SUSCRIBIRSE_PUSH = '/suscripciones/push';
	private const RUTA_CLAVE_PUBLICA    = '/suscripciones/clave-publica';
	private const RUTA_CONFIRMAR        = '/suscripciones/confirmar/(?P<token>[a-f0-9]{64})';
	private const RUTA_BAJA             = '/suscripciones/baja/(?P<token>[a-f0-9]{64})';
	private const RUTA_LISTAR           = '/suscripciones/listado';
	private const RUTA_EXPORTAR         = '/suscripciones/exportar';
	private const RUTA_BORRAR           = '/suscripciones/borrar';

	public function __construct(
		private readonly GestorSuscripciones $gestor,
		private readonly NotificadorSuscripciones $notificador,
	) {
	}

	public function registrar(): void {
		add_action( 'rest_api_init', array( $this, 'registrarRutas' ) );
	}

	public function registrarRutas(): void {
		register_rest_route(
			'pluma/v1',
			self::RUTA_SUSCRIBIRSE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'suscribirse' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_SUSCRIBIRSE_PUSH,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'suscribirsePush' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_CLAVE_PUBLICA,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'clavePublica' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_CONFIRMAR,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'confirmar' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_BAJA,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'darDeBaja' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_LISTAR,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'listar' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_EXPORTAR,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'exportar' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_BORRAR,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'borrar' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);
	}

	public function autorizado(): bool {
		return current_user_can( Capacidades::APROBAR_PIEZAS );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function suscribirse( WP_REST_Request $request ) {
		$email = sanitize_email( (string) $request->get_param( 'email' ) );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'pluma_suscripcion_email_invalido', __( 'Introduce un correo válido.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$tipoCrudo = (string) $request->get_param( 'tipo' );
		$tipo      = TipoSuscripcion::tryFrom( $tipoCrudo );

		if ( null === $tipo ) {
			return new WP_Error( 'pluma_suscripcion_tipo_invalido', __( 'Tipo de suscripción no reconocido.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$referenciaId = $request->get_param( 'referenciaId' );
		$vertical     = $request->get_param( 'vertical' );

		if ( TipoSuscripcion::Vertical === $tipo && ( ! is_string( $vertical ) || '' === $vertical ) ) {
			return new WP_Error( 'pluma_suscripcion_vertical_requerido', __( 'Indica el vertical al que quieres suscribirte.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		if ( in_array( $tipo, array( TipoSuscripcion::Periodista, TipoSuscripcion::Historia ), true ) && ! is_numeric( $referenciaId ) ) {
			return new WP_Error( 'pluma_suscripcion_referencia_requerida', __( 'Falta a qué periodista o historia suscribirte.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$resultado = $this->gestor->suscribirEmail(
			$tipo,
			is_numeric( $referenciaId ) ? (int) $referenciaId : null,
			is_string( $vertical ) && '' !== $vertical ? sanitize_text_field( $vertical ) : null,
			$email
		);

		$this->notificador->enviarConfirmacion( $email, $resultado['token'] );

		return new WP_REST_Response( array( 'estado' => 'pendiente_confirmacion' ), 201 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function suscribirsePush( WP_REST_Request $request ) {
		$tipoCrudo = (string) $request->get_param( 'tipo' );
		$tipo      = TipoSuscripcion::tryFrom( $tipoCrudo );

		if ( null === $tipo ) {
			return new WP_Error( 'pluma_suscripcion_tipo_invalido', __( 'Tipo de suscripción no reconocido.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$endpoint = (string) $request->get_param( 'endpoint' );
		$claves   = $request->get_param( 'claves' );

		if ( '' === $endpoint || ! is_array( $claves ) || ! isset( $claves['p256dh'], $claves['auth'] ) || ! is_string( $claves['p256dh'] ) || ! is_string( $claves['auth'] ) ) {
			return new WP_Error( 'pluma_suscripcion_push_invalida', __( 'Faltan datos de la suscripción push del navegador.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$referenciaId = $request->get_param( 'referenciaId' );
		$vertical     = $request->get_param( 'vertical' );

		$id = $this->gestor->suscribirPush(
			$tipo,
			is_numeric( $referenciaId ) ? (int) $referenciaId : null,
			is_string( $vertical ) && '' !== $vertical ? sanitize_text_field( $vertical ) : null,
			esc_url_raw( $endpoint ),
			sanitize_text_field( $claves['p256dh'] ),
			sanitize_text_field( $claves['auth'] )
		);

		return new WP_REST_Response( array( 'id' => $id ), 201 );
	}

	public function clavePublica(): WP_REST_Response {
		return new WP_REST_Response( array( 'clavePublica' => ClavesVapid::publica() ), 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function confirmar( WP_REST_Request $request ) {
		try {
			$this->gestor->confirmar( (string) $request->get_param( 'token' ) );
		} catch ( SuscripcionNoEncontradaException $e ) {
			return new WP_Error( 'pluma_suscripcion_no_encontrada', $e->getMessage(), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( array( 'estado' => 'confirmada' ), 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function darDeBaja( WP_REST_Request $request ) {
		try {
			$this->gestor->darDeBaja( (string) $request->get_param( 'token' ) );
		} catch ( SuscripcionNoEncontradaException $e ) {
			return new WP_Error( 'pluma_suscripcion_no_encontrada', $e->getMessage(), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( array( 'estado' => 'dado_de_baja' ), 200 );
	}

	public function listar(): WP_REST_Response {
		$suscriptores = array_map(
			static fn ( $s ): array => array(
				'id'           => $s->id,
				'canal'        => $s->canal->value,
				'tipo'         => $s->tipo->value,
				'referenciaId' => $s->referenciaId,
				'vertical'     => $s->vertical,
				'email'        => $s->email,
				'confirmado'   => $s->confirmado,
				'creadoEn'     => $s->creadoEn->format( DATE_ATOM ),
			),
			$this->gestor->listar()
		);

		return new WP_REST_Response( $suscriptores, 200 );
	}

	/**
	 * RGPD (`PLUMA-EV-2`): exportación a petición — hoy disparada por el
	 * editor cuando un lector la solicita (autoservicio sin verificación de
	 * propiedad del email queda como deuda, `PLUMA-E9-5`).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function exportar( WP_REST_Request $request ) {
		$email = sanitize_email( (string) $request->get_param( 'email' ) );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'pluma_suscripcion_email_invalido', __( 'Introduce un correo válido.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$suscripciones = array_map(
			static fn ( $s ): array => array(
				'canal'    => $s->canal->value,
				'tipo'     => $s->tipo->value,
				'creadoEn' => $s->creadoEn->format( DATE_ATOM ),
			),
			$this->gestor->exportarPorEmail( $email )
		);

		return new WP_REST_Response( $suscripciones, 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function borrar( WP_REST_Request $request ) {
		$email = sanitize_email( (string) $request->get_param( 'email' ) );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'pluma_suscripcion_email_invalido', __( 'Introduce un correo válido.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$borradas = $this->gestor->borrarPorEmail( $email );

		return new WP_REST_Response( array( 'borradas' => $borradas ), 200 );
	}
}
