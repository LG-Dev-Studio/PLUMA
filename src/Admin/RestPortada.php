<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Compuertas\ModoOperacion;
use Pluma\Datos\RepositorioBitacoraInterface;
use Pluma\Datos\RepositorioColaPublicacionInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Datos\RepositorioRespuestasComentariosInterface;
use Pluma\Datos\RepositorioTendenciasInterface;
use Pluma\Kernel\Capacidades;
use Pluma\Kernel\RelojInterface;
use Pluma\Pipeline\ConfiguracionCadencia;
use Pluma\Pipeline\EstadoColaPublicacion;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\LectorConfiguracionCadencia;
use Pluma\Pipeline\Orquestador;
use Pluma\Pipeline\Pieza;
use Pluma\Pipeline\RanuraPublicacion;
use Pluma\Proveedores\PresupuestoLenguaje;
use WP_REST_Response;

/**
 * La Portada (Libro Cap. 10.2): "el día de un vistazo" — modo activo, cuota
 * y programación de hoy, salud del motor, piezas por estado del pipeline
 * (kanban compacto) y alertas. Protegido con `pluma_configurar_motor`, la
 * misma capacidad que abre la página del panel (Cap. 10.1).
 *
 * "Resultados de ayer" (tráfico, piezas top, comentarios) del Libro queda
 * fuera deliberadamente: no existe todavía ninguna fuente real de tráfico
 * en PLUMA (el bucle de Search Console es Etapa 5) y "cero invención"
 * prohíbe fabricar esa cifra. Se añade cuando exista el dato real.
 */
final class RestPortada {

	private const RUTA = '/panel/portada';

	private const LIMITE_TENDENCIAS_CALIENTES = 5;
	private const LIMITE_ALERTAS              = 10;

	public function __construct(
		private readonly RepositorioPiezasInterface $piezas,
		private readonly RepositorioTendenciasInterface $tendencias,
		private readonly RepositorioColaPublicacionInterface $colaPublicacion,
		private readonly RepositorioBitacoraInterface $bitacora,
		private readonly RepositorioRespuestasComentariosInterface $respuestasComentarios,
		private readonly LectorConfiguracionCadencia $lectorCadencia,
		private readonly PresupuestoLenguaje $presupuesto,
		private readonly RelojInterface $reloj,
	) {
	}

	public function registrar(): void {
		add_action( 'rest_api_init', array( $this, 'registrarRuta' ) );
	}

	public function registrarRuta(): void {
		register_rest_route(
			'pluma/v1',
			self::RUTA,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'obtener' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);
	}

	public function autorizado(): bool {
		return current_user_can( Capacidades::CONFIGURAR_MOTOR );
	}

	public function obtener(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'modoOperacion'                 => $this->modoOperacion()->value,
				'cuota'                         => $this->cuotaHoy(),
				'salud'                         => $this->salud(),
				'piezasPorEstado'               => $this->piezasPorEstado(),
				'alertas'                       => $this->alertas(),
				'tendenciasCalientes'           => $this->tendencias->obtenerRecientes( self::LIMITE_TENDENCIAS_CALIENTES ),
				'borradoresRespuestaPendientes' => $this->respuestasComentarios->contarPendientes(),
			),
			200
		);
	}

	private function modoOperacion(): ModoOperacion {
		$valor = get_option( Orquestador::OPCION_MODO_OPERACION, 'copiloto' );

		return ModoOperacion::tryFrom( is_string( $valor ) ? $valor : '' ) ?? ModoOperacion::Copiloto;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function cuotaHoy(): array {
		$ahora         = $this->reloj->ahora();
		$inicioHoy     = $ahora->setTime( 0, 0 );
		$finHoy        = $inicioHoy->modify( '+1 day' );
		$configuracion = $this->lectorCadencia->leer();
		$ranurasHoy    = $this->colaPublicacion->obtenerEntre( $inicioHoy, $finHoy );

		$publicadas  = 0;
		$programadas = 0;
		$proxima     = null;

		foreach ( $ranurasHoy as $ranura ) {
			if ( EstadoColaPublicacion::Publicada === $ranura->estado ) {
				++$publicadas;
			} elseif ( EstadoColaPublicacion::Programada === $ranura->estado ) {
				++$programadas;

				if ( $ranura->horaProgramada > $ahora && ( null === $proxima || $ranura->horaProgramada < $proxima->horaProgramada ) ) {
					$proxima = $ranura;
				}
			}
		}

		return array(
			'objetivo'           => $configuracion->cuotaObjetivo,
			'minima'             => $configuracion->cuotaMinima,
			'maxima'             => $configuracion->cuotaMaxima,
			'publicadasHoy'      => $publicadas,
			'programadasHoy'     => $programadas,
			'proximaPublicacion' => $proxima instanceof RanuraPublicacion ? $proxima->horaProgramada->format( DATE_ATOM ) : null,
			'deficit'            => ( $publicadas + $programadas ) < $configuracion->cuotaMinima,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function salud(): array {
		return array(
			'ultimaEjecucion' => $this->bitacora->obtenerUltima(),
			'gastoHoyUsd'     => round( $this->presupuesto->gastoHoyUsd(), 4 ),
			'limiteDiarioUsd' => $this->presupuesto->limiteDiarioUsd(),
		);
	}

	/**
	 * @return array<string, int>
	 */
	private function piezasPorEstado(): array {
		$conteos = array();

		foreach ( EstadoPieza::cases() as $estado ) {
			$conteos[ $estado->value ] = $this->piezas->contarPorEstado( $estado );
		}

		return $conteos;
	}

	/**
	 * @return array<string, list<array<string, mixed>>>
	 */
	private function alertas(): array {
		return array(
			'retenidas'           => array_map(
				array( $this, 'piezaResumen' ),
				$this->piezas->obtenerPorEstado( EstadoPieza::Retenida, self::LIMITE_ALERTAS )
			),
			'fallidas'            => array_map(
				array( $this, 'piezaResumen' ),
				$this->piezas->obtenerPorEstado( EstadoPieza::Fallida, self::LIMITE_ALERTAS )
			),
			'sinPeriodistaIdoneo' => array_map(
				array( $this, 'piezaResumen' ),
				$this->piezas->obtenerPorEstado( EstadoPieza::SinPeriodistaIdoneo, self::LIMITE_ALERTAS )
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function piezaResumen( Pieza $pieza ): array {
		return array(
			'id'            => $pieza->id,
			'tendenciaId'   => $pieza->tendenciaId,
			'actualizadaEn' => $pieza->actualizadaEn->format( DATE_ATOM ),
			'motivos'       => $pieza->resultadoCompuertas->motivos ?? array(),
		);
	}
}
