<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Datos\RepositorioBorradoresInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioTendenciasInterface;
use Pluma\Kernel\Capacidades;
use Pluma\Pipeline\AccionNoDisponibleException;
use Pluma\Pipeline\EntradaColaDeVeto;
use Pluma\Pipeline\GestorSalaRevision;
use Pluma\Pipeline\Pieza;
use Pluma\Pipeline\PiezaNoEncontradaException;
use Pluma\Pipeline\TransicionInvalidaException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Sala de Revisión (Libro Cap. 10.2): "la bandeja de lo que espera decisión
 * humana... diseñada para decidir rápido: lectura limpia, diagnóstico
 * arriba, tres botones". Protegido con la capacidad propia
 * `pluma_aprobar_piezas` (CLAUDE.md § Estándares WordPress) — nunca
 * `manage_options`.
 */
final class RestSalaRevision {

	private const RUTA_RETENIDAS      = '/revision/retenidas';
	private const RUTA_VETO           = '/revision/veto';
	private const RUTA_APROBAR        = '/revision/(?P<id>\d+)/aprobar';
	private const RUTA_APROBAR_AHORA  = '/revision/(?P<id>\d+)/aprobar-ahora';
	private const RUTA_DEVOLVER       = '/revision/(?P<id>\d+)/devolver';
	private const RUTA_DESCARTAR      = '/revision/(?P<id>\d+)/descartar';
	private const RUTA_SIN_PERIODISTA = '/revision/sin-periodista-idoneo';
	private const RUTA_REANUDAR       = '/revision/(?P<id>\d+)/reanudar';

	public function __construct(
		private readonly GestorSalaRevision $gestor,
		private readonly RepositorioTendenciasInterface $tendencias,
		private readonly RepositorioPeriodistasInterface $periodistas,
		private readonly RepositorioBorradoresInterface $borradores,
		private readonly int $ventanaVetoHoras,
	) {
	}

	public function registrar(): void {
		add_action( 'rest_api_init', array( $this, 'registrarRutas' ) );
	}

	public function registrarRutas(): void {
		register_rest_route(
			'pluma/v1',
			self::RUTA_RETENIDAS,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'retenidas' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_SIN_PERIODISTA,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'sinPeriodistaIdoneo' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_REANUDAR,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reanudar' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_VETO,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'colaDeVeto' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_APROBAR,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'aprobar' ),
				'permission_callback' => array( $this, 'autorizado' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => static fn ( $valor ): bool => is_numeric( $valor ),
					),
				),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_APROBAR_AHORA,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'aprobarAhora' ),
				'permission_callback' => array( $this, 'autorizado' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => static fn ( $valor ): bool => is_numeric( $valor ),
					),
				),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_DEVOLVER,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'devolver' ),
				'permission_callback' => array( $this, 'autorizado' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => static fn ( $valor ): bool => is_numeric( $valor ),
					),
				),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_DESCARTAR,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'descartar' ),
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

	public function autorizado(): bool {
		return current_user_can( Capacidades::APROBAR_PIEZAS );
	}

	public function retenidas(): WP_REST_Response {
		$piezas = array_map(
			array( $this, 'piezaComoArray' ),
			$this->gestor->obtenerRetenidas()
		);

		return new WP_REST_Response( $piezas, 200 );
	}

	public function colaDeVeto(): WP_REST_Response {
		$entradas = array_map(
			fn ( EntradaColaDeVeto $entrada ): array => array_merge(
				$this->piezaComoArray( $entrada->pieza ),
				array(
					'horaProgramada' => $entrada->ranura->horaProgramada->format( DATE_ATOM ),
					'horaLimiteVeto' => $entrada->horaLimiteVeto->format( DATE_ATOM ),
				)
			),
			$this->gestor->obtenerColaDeVeto( $this->ventanaVetoHoras )
		);

		return new WP_REST_Response( $entradas, 200 );
	}

	public function sinPeriodistaIdoneo(): WP_REST_Response {
		return new WP_REST_Response(
			array_map( array( $this, 'piezaComoArray' ), $this->gestor->obtenerSinPeriodistaIdoneo() ),
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function reanudar( WP_REST_Request $request ) {
		return $this->ejecutarAccion(
			(int) $request->get_param( 'id' ),
			fn ( int $id ) => $this->gestor->reanudarSinPeriodistaIdoneo( $id )
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function aprobar( WP_REST_Request $request ) {
		return $this->ejecutarAccion(
			(int) $request->get_param( 'id' ),
			fn ( int $id ) => $this->gestor->aprobar( $id )
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function aprobarAhora( WP_REST_Request $request ) {
		return $this->ejecutarAccion(
			(int) $request->get_param( 'id' ),
			fn ( int $id ) => $this->gestor->aprobarAhora( $id )
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function devolver( WP_REST_Request $request ) {
		$nota = $request->get_param( 'nota' );

		return $this->ejecutarAccion(
			(int) $request->get_param( 'id' ),
			fn ( int $id ) => $this->gestor->devolver( $id, is_string( $nota ) ? sanitize_textarea_field( $nota ) : '' )
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function descartar( WP_REST_Request $request ) {
		return $this->ejecutarAccion(
			(int) $request->get_param( 'id' ),
			fn ( int $id ) => $this->gestor->descartar( $id )
		);
	}

	/**
	 * @param callable(int): void $accion
	 * @return WP_REST_Response|WP_Error
	 */
	private function ejecutarAccion( int $piezaId, callable $accion ) {
		try {
			$accion( $piezaId );
		} catch ( PiezaNoEncontradaException $e ) {
			return new WP_Error( 'pluma_pieza_no_encontrada', $e->getMessage(), array( 'status' => 404 ) );
		} catch ( TransicionInvalidaException $e ) {
			return new WP_Error( 'pluma_transicion_invalida', $e->getMessage(), array( 'status' => 409 ) );
		} catch ( AccionNoDisponibleException $e ) {
			return new WP_Error( 'pluma_accion_no_disponible', $e->getMessage(), array( 'status' => 409 ) );
		}

		return new WP_REST_Response( array( 'piezaId' => $piezaId ), 200 );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function piezaComoArray( Pieza $pieza ): array {
		$periodista = null !== $pieza->periodistaId ? $this->periodistas->obtenerPorId( $pieza->periodistaId ) : null;
		$borrador   = $this->borradores->obtenerUltimo( $pieza->id );

		return array(
			'id'                  => $pieza->id,
			'tendenciaId'         => $pieza->tendenciaId,
			'tendenciaTermino'    => $this->tendencias->obtenerPorId( $pieza->tendenciaId )['termino'] ?? null,
			'periodista'          => null !== $periodista ? array(
				'id'     => $periodista->id,
				'nombre' => $periodista->nombre,
			) : null,
			'actualizadaEn'       => $pieza->actualizadaEn->format( DATE_ATOM ),
			'motivos'             => $pieza->resultadoCompuertas->motivos ?? array(),
			'modoEfectivo'        => $pieza->resultadoCompuertas->modoEfectivo->value ?? null,
			'resultadoCompuertas' => $pieza->resultadoCompuertas?->aArray(),
			'contenido'           => $borrador?->contenido,
		);
	}
}
