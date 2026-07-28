<?php

declare(strict_types=1);

namespace Pluma\Admin;

use DateTimeImmutable;
use Pluma\Datos\RepositorioBitacoraInterface;
use Pluma\Datos\RepositorioMemoriaEditorialInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Datos\RepositorioRespuestasComentariosInterface;
use Pluma\Datos\RepositorioTendenciasInterface;
use Pluma\Kernel\Capacidades;
use Pluma\Kernel\RelojInterface;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\Pieza;
use Pluma\Redaccion\EstadoRespuestaComentario;
use Pluma\Redaccion\TipoMemoria;
use Pluma\Sensores\EstadoTendencia;
use WP_REST_Response;

/**
 * Informes editoriales semanales (Libro Cap. 14, Etapa 5: "la máquina que
 * aprende"): una fotografía real de los últimos 7 días, calculada bajo
 * demanda — sin correo automático ni estado de "último envío" (decisión del
 * propietario, 2026-07-23: el Libro no detalla el mecanismo de entrega).
 *
 * Mismo criterio de "cero invención" que `RestPortada`: Search Console
 * queda fuera (el bucle actual agrega por página+consulta, sin fecha real
 * por fila — ver deuda `PLUMA-E5-1`) y el gasto en USD no se agrega en la
 * semana (`PresupuestoLenguaje` solo persiste el gasto del día actual, sin
 * histórico) — ningún dato se aproxima en silencio.
 */
final class RestInformesEditoriales {

	private const RUTA = '/panel/informes';

	private const DIAS_VENTANA_INFORME          = 7;
	private const LIMITE_PIEZAS                 = 200;
	private const LIMITE_MEMORIA_POR_PERIODISTA = 200;

	public function __construct(
		private readonly RepositorioPiezasInterface $piezas,
		private readonly RepositorioTendenciasInterface $tendencias,
		private readonly RepositorioBitacoraInterface $bitacora,
		private readonly RepositorioRespuestasComentariosInterface $respuestasComentarios,
		private readonly RepositorioMemoriaEditorialInterface $memoriaEditorial,
		private readonly RepositorioPeriodistasInterface $periodistas,
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
		$hasta = $this->reloj->ahora();
		$desde = $hasta->modify( '-' . self::DIAS_VENTANA_INFORME . ' days' );

		return new WP_REST_Response(
			array(
				'rango'      => array(
					'desde' => $desde->format( DATE_ATOM ),
					'hasta' => $hasta->format( DATE_ATOM ),
				),
				'piezas'     => $this->piezas( $desde, $hasta ),
				'tendencias' => $this->tendencias( $desde, $hasta ),
				'motor'      => $this->motor( $desde, $hasta ),
				'audiencia'  => $this->audiencia( $desde, $hasta ),
			),
			200
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function piezas( DateTimeImmutable $desde, DateTimeImmutable $hasta ): array {
		$publicadas = $this->piezas->obtenerPorEstadoEntre( EstadoPieza::Publicada, $desde, $hasta, self::LIMITE_PIEZAS );

		$nombrePorId = array();
		foreach ( $this->periodistas->obtenerActivos() as $periodista ) {
			$nombrePorId[ $periodista->id ] = $periodista->nombre;
		}

		$porPeriodista = array();
		$porVertical   = array();

		foreach ( $publicadas as $pieza ) {
			if ( null !== $pieza->periodistaId ) {
				$porPeriodista[ $pieza->periodistaId ] = ( $porPeriodista[ $pieza->periodistaId ] ?? 0 ) + 1;
			}

			if ( null !== $pieza->fichaDecisionEditorial ) {
				$vertical = $pieza->fichaDecisionEditorial->clasificacion->tema;

				$porVertical[ $vertical ] = ( $porVertical[ $vertical ] ?? 0 ) + 1;
			}
		}

		return array(
			'publicadas'    => count( $publicadas ),
			'porPeriodista' => array_map(
				static fn ( int $periodistaId, int $total ): array => array(
					'periodistaId' => $periodistaId,
					'nombre'       => $nombrePorId[ $periodistaId ] ?? '',
					'publicadas'   => $total,
				),
				array_keys( $porPeriodista ),
				array_values( $porPeriodista )
			),
			'porVertical'   => array_map(
				static fn ( string $vertical, int $total ): array => array(
					'vertical'   => $vertical,
					'publicadas' => $total,
				),
				array_keys( $porVertical ),
				array_values( $porVertical )
			),
			'retenidas'     => array_map(
				array( $this, 'piezaResumen' ),
				$this->piezas->obtenerPorEstadoEntre( EstadoPieza::Retenida, $desde, $hasta, self::LIMITE_PIEZAS )
			),
			'fallidas'      => array_map(
				array( $this, 'piezaResumen' ),
				$this->piezas->obtenerPorEstadoEntre( EstadoPieza::Fallida, $desde, $hasta, self::LIMITE_PIEZAS )
			),
		);
	}

	/**
	 * @return array<string, int>
	 */
	private function tendencias( DateTimeImmutable $desde, DateTimeImmutable $hasta ): array {
		return array(
			'enPipeline'           => $this->tendencias->contarPorEstadoEntre( EstadoTendencia::EnPipeline, $desde, $hasta ),
			'posibleActualizacion' => $this->tendencias->contarPorEstadoEntre( EstadoTendencia::PosibleActualizacion, $desde, $hasta ),
			'ignoradas'            => $this->tendencias->contarPorEstadoEntre( EstadoTendencia::Ignorada, $desde, $hasta ),
			'vigiladas'            => $this->tendencias->contarPorEstadoEntre( EstadoTendencia::Vigilada, $desde, $hasta ),
			'sospechaManipulacion' => $this->tendencias->contarPorEstadoEntre( EstadoTendencia::SospechaDeManipulacion, $desde, $hasta ),
		);
	}

	/**
	 * @return array<string, int>
	 */
	private function motor( DateTimeImmutable $desde, DateTimeImmutable $hasta ): array {
		$ejecuciones = $this->bitacora->obtenerEntre( $desde, $hasta );

		$lotesProcesados = 0;
		$conErrores      = 0;

		foreach ( $ejecuciones as $ejecucion ) {
			$lotesProcesados += $ejecucion['lotesProcesados'];

			if ( array() !== $ejecucion['errores'] ) {
				++$conErrores;
			}
		}

		return array(
			'ejecuciones'           => count( $ejecuciones ),
			'lotesProcesados'       => $lotesProcesados,
			'ejecucionesConErrores' => $conErrores,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function audiencia( DateTimeImmutable $desde, DateTimeImmutable $hasta ): array {
		$sentimiento  = array(
			'positivo' => 0,
			'negativo' => 0,
			'mixto'    => 0,
			'neutral'  => 0,
		);
		$aprendizajes = 0;

		foreach ( $this->periodistas->obtenerActivos() as $periodista ) {
			$entradas = $this->memoriaEditorial->obtenerPorPeriodista( $periodista->id, TipoMemoria::Audiencia, self::LIMITE_MEMORIA_POR_PERIODISTA );

			foreach ( $entradas as $entrada ) {
				if ( $entrada->creadaEn < $desde || $entrada->creadaEn > $hasta ) {
					continue;
				}

				++$aprendizajes;
				$valor = $entrada->contenido['sentimiento'] ?? null;

				if ( is_string( $valor ) && array_key_exists( $valor, $sentimiento ) ) {
					++$sentimiento[ $valor ];
				}
			}
		}

		return array(
			'comentariosProcesados'   => $this->respuestasComentarios->contarCreadosEntre( $desde, $hasta ),
			'aprendizajesRegistrados' => $aprendizajes,
			'sentimiento'             => $sentimiento,
			'respuestasAprobadas'     => $this->respuestasComentarios->contarPorEstadoResueltoEntre( EstadoRespuestaComentario::Aprobado, $desde, $hasta ),
			'respuestasDescartadas'   => $this->respuestasComentarios->contarPorEstadoResueltoEntre( EstadoRespuestaComentario::Descartado, $desde, $hasta ),
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
