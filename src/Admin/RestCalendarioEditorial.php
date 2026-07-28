<?php

declare(strict_types=1);

namespace Pluma\Admin;

use DateTimeImmutable;
use Exception;
use Pluma\Kernel\Capacidades;
use Pluma\Pipeline\EventoProgramado;
use Pluma\Pipeline\EventoProgramadoNoEncontradoException;
use Pluma\Pipeline\EventoProgramadoSinFuentesException;
use Pluma\Pipeline\GestorCalendarioEditorial;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Calendario Editorial (Nivel Cuatro V.1-V.2): "la mitad del calendario
 * noticioso se conoce con semanas de anticipación". Protegido con
 * `pluma_aprobar_piezas` — planificar y disparar cobertura es una decisión
 * editorial, igual que la Sala de Tendencias, nunca `manage_options`.
 */
final class RestCalendarioEditorial {

	private const RUTA_EVENTOS         = '/calendario-editorial';
	private const RUTA_PREPARAR        = '/calendario-editorial/(?P<id>\d+)/preparar';
	private const RUTA_MARCAR_EN_CURSO = '/calendario-editorial/(?P<id>\d+)/marcar-en-curso';
	private const RUTA_MARCAR_CUBIERTO = '/calendario-editorial/(?P<id>\d+)/marcar-cubierto';

	public function __construct( private readonly GestorCalendarioEditorial $gestor ) {
	}

	public function registrar(): void {
		add_action( 'rest_api_init', array( $this, 'registrarRutas' ) );
	}

	public function registrarRutas(): void {
		register_rest_route(
			'pluma/v1',
			self::RUTA_EVENTOS,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'listar' ),
					'permission_callback' => array( $this, 'autorizado' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'crear' ),
					'permission_callback' => array( $this, 'autorizado' ),
				),
			)
		);

		foreach ( array(
			self::RUTA_PREPARAR        => 'preparar',
			self::RUTA_MARCAR_EN_CURSO => 'marcarEnCurso',
			self::RUTA_MARCAR_CUBIERTO => 'marcarCubierto',
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

	public function listar(): WP_REST_Response {
		$eventos = array_map(
			array( $this, 'eventoComoArray' ),
			$this->gestor->listar()
		);

		return new WP_REST_Response( $eventos, 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function crear( WP_REST_Request $request ) {
		$titulo   = sanitize_text_field( (string) $request->get_param( 'titulo' ) );
		$vertical = sanitize_text_field( (string) $request->get_param( 'vertical' ) );
		$fecha    = (string) $request->get_param( 'fechaEsperada' );

		if ( '' === $titulo || '' === $vertical || '' === $fecha ) {
			return new WP_Error( 'pluma_calendario_datos_incompletos', __( 'Título, vertical y fecha esperada son obligatorios.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		try {
			$fechaEsperada = new DateTimeImmutable( $fecha );
		} catch ( Exception $e ) {
			return new WP_Error( 'pluma_calendario_fecha_invalida', __( 'La fecha esperada no es una fecha válida.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$periodistaAsignadoId = $request->get_param( 'periodistaAsignadoId' );

		$eventoId = $this->gestor->crear(
			$titulo,
			$vertical,
			$fechaEsperada,
			null !== $periodistaAsignadoId && is_numeric( $periodistaAsignadoId ) ? (int) $periodistaAsignadoId : null
		);

		return new WP_REST_Response( array( 'eventoId' => $eventoId ), 201 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function preparar( WP_REST_Request $request ) {
		$eventoId = (int) $request->get_param( 'id' );

		$articulosCrudos = $request->get_param( 'articulosRelacionados' );

		if ( ! is_array( $articulosCrudos ) ) {
			return new WP_Error( 'pluma_calendario_fuentes_invalidas', __( 'Se necesita al menos un artículo relacionado (título, url, fuente).', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$articulosRelacionados = array();

		foreach ( $articulosCrudos as $articulo ) {
			if ( ! is_array( $articulo ) ) {
				continue;
			}

			$articulosRelacionados[] = array(
				'titulo' => sanitize_text_field( (string) ( $articulo['titulo'] ?? '' ) ),
				'url'    => esc_url_raw( (string) ( $articulo['url'] ?? '' ) ),
				'fuente' => sanitize_text_field( (string) ( $articulo['fuente'] ?? '' ) ),
			);
		}

		try {
			$piezaId = $this->gestor->prepararCobertura( $eventoId, $articulosRelacionados );
		} catch ( EventoProgramadoNoEncontradoException $e ) {
			return new WP_Error( 'pluma_calendario_evento_no_encontrado', $e->getMessage(), array( 'status' => 404 ) );
		} catch ( EventoProgramadoSinFuentesException $e ) {
			return new WP_Error( 'pluma_calendario_sin_fuentes', $e->getMessage(), array( 'status' => 400 ) );
		}

		return new WP_REST_Response(
			array(
				'eventoId' => $eventoId,
				'piezaId'  => $piezaId,
			),
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function marcarEnCurso( WP_REST_Request $request ) {
		return $this->ejecutarTransicion( (int) $request->get_param( 'id' ), fn ( int $id ) => $this->gestor->marcarEnCurso( $id ) );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function marcarCubierto( WP_REST_Request $request ) {
		return $this->ejecutarTransicion( (int) $request->get_param( 'id' ), fn ( int $id ) => $this->gestor->marcarCubierto( $id ) );
	}

	/**
	 * @param callable(int): bool $accion
	 * @return WP_REST_Response|WP_Error
	 */
	private function ejecutarTransicion( int $eventoId, callable $accion ) {
		try {
			$accion( $eventoId );
		} catch ( EventoProgramadoNoEncontradoException $e ) {
			return new WP_Error( 'pluma_calendario_evento_no_encontrado', $e->getMessage(), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( array( 'eventoId' => $eventoId ), 200 );
	}

	/**
	 * @return array{id: int, titulo: string, vertical: string, fechaEsperada: string, estado: string, periodistaAsignadoId: int|null, historiaId: int|null, tendenciaId: int|null}
	 */
	private function eventoComoArray( EventoProgramado $evento ): array {
		return array(
			'id'                   => $evento->id,
			'titulo'               => $evento->titulo,
			'vertical'             => $evento->vertical,
			'fechaEsperada'        => $evento->fechaEsperada->format( DATE_ATOM ),
			'estado'               => $evento->estado->value,
			'periodistaAsignadoId' => $evento->periodistaAsignadoId,
			'historiaId'           => $evento->historiaId,
			'tendenciaId'          => $evento->tendenciaId,
		);
	}
}
