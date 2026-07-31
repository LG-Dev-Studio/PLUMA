<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

use Pluma\Compuertas\ClasificadorGravedadTendencia;
use Pluma\Compuertas\EvaluadorCompuertas;
use Pluma\Compuertas\GestorModoRespeto;
use Pluma\Compuertas\ModoOperacion;
use Pluma\Datos\CandadoGlobalInterface;
use Pluma\Datos\RepositorioBitacoraInterface;
use Pluma\Datos\RepositorioBorradoresInterface;
use Pluma\Datos\RepositorioColaPublicacionInterface;
use Pluma\Datos\RepositorioLlamadasModeloInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Datos\RepositorioTendenciasInterface;
use Pluma\Investigacion\DetectorHuecos;
use Pluma\Investigacion\InvestigadorInterface;
use Pluma\Investigacion\ResolutorDisputas;
use Pluma\Kernel\AlmacenPerfilEntornoInterface;
use Pluma\Kernel\RelojInterface;
use Pluma\Proveedores\ProveedorTendenciasException;
use Pluma\Publicacion\AsignadorImagenDestacadaInterface;
use Pluma\Publicacion\CreadorBorradorInterface;
use Pluma\Publicacion\PublicadorInterface;
use Pluma\Publicacion\SnapshotPublicacion;
use Pluma\Redaccion\CreadorAutomaticoPeriodistas;
use Pluma\Redaccion\RedactorInterface;
use Pluma\Seo\GestorExperimentosTitular;
use Pluma\Sensores\ComparadorHistorias;
use Pluma\Sensores\EvaluadorLegitimidadInsumo;
use Pluma\Sensores\RelacionHistoria;
use Pluma\Sensores\SensorInterface;
use Pluma\Seo\MetadatosSeo;
use Pluma\Seo\MotorSeo;
use Pluma\Seo\TipoEsquemaArticulo;
use Pluma\Seo\TipoPluginSeo;
use Pluma\Taxonomia\ResultadoTaxonomia;
use Pluma\Taxonomia\Taxonomo;
use Throwable;

/**
 * "Cada vez que me ejecuto, cumplo mi cuota del día" (Libro Cap. 9.1):
 * candado global, avance de todo el pipeline (RADAR → INVESTIGADOR → SALA
 * DE REDACCIÓN → MOTOR SEO → TAXÓNOMO → COMPUERTAS → PUBLICADOR) con
 * presupuesto de tiempo y corte limpio entre lotes (Cap. 9.4), cadencia
 * real con cuota elástica/ventanas/jitter/topes (Cap. 9.2-9.3), modos reales
 * con ventana de veto en Copiloto (Cap. 2.4), y escasez honesta (Cap. 9.3
 * punto 6: nunca se rebajan umbrales para rellenar la cuota).
 */
final class Orquestador {

	private const LIMITE_POR_LOTE                    = 5;
	private const DIAS_VENTANA_COMPARACION_HISTORIAS = 14;
	private const LIMITE_CANDIDATAS_COMPARACION      = 20;
	// NCP-1 (`ADR 0010`): retención de la bitácora de llamadas al modelo —
	// mismo criterio de mantenimiento que el resto del pipeline, sin
	// acumular indefinidamente filas que ya no informan ninguna decisión.
	private const DIAS_RETENCION_LLAMADAS_MODELO = 90;

	public const OPCION_MODO_OPERACION     = 'pluma_modo_operacion';
	public const OPCION_VENTANA_VETO_HORAS = 'pluma_ventana_veto_horas';

	private const MODO_OPERACION_DEFECTO     = 'copiloto';
	private const VENTANA_VETO_HORAS_DEFECTO = 2;
	private const PIEZAS_PROPIAS_RECIENTES   = 20;

	public function __construct(
		private readonly CandadoGlobalInterface $candado,
		private readonly RepositorioBitacoraInterface $bitacora,
		private readonly RepositorioPiezasInterface $piezas,
		private readonly RepositorioTendenciasInterface $tendencias,
		private readonly RepositorioBorradoresInterface $borradores,
		private readonly RepositorioColaPublicacionInterface $colaPublicacion,
		private readonly Transicionador $transicionador,
		private readonly SensorInterface $sensor,
		private readonly InvestigadorInterface $investigador,
		private readonly RedactorInterface $redactor,
		private readonly MotorSeo $motorSeo,
		private readonly Taxonomo $taxonomo,
		private readonly EvaluadorCompuertas $evaluadorCompuertas,
		private readonly LectorConfiguracionCadencia $lectorCadencia,
		private readonly ProgramadorCadencia $programadorCadencia,
		private readonly CreadorBorradorInterface $creadorBorrador,
		private readonly PublicadorInterface $publicador,
		private readonly ComparadorHistorias $comparadorHistorias,
		private readonly RepositorioPeriodistasInterface $periodistas,
		private readonly RelojInterface $reloj,
		private readonly ResolutorDisputas $resolutorDisputas,
		private readonly DetectorHuecos $detectorHuecos,
		private readonly ClasificadorGravedadTendencia $clasificadorGravedad,
		private readonly GestorModoRespeto $gestorModoRespeto,
		private readonly AsignadorImagenDestacadaInterface $asignadorImagenDestacada,
		private readonly EvaluadorLegitimidadInsumo $evaluadorLegitimidad,
		private readonly GestorHistorias $gestorHistorias,
		private readonly GestorExperimentosTitular $gestorExperimentosTitular,
		private readonly CreadorAutomaticoPeriodistas $creadorAutomaticoPeriodistas,
		private readonly GestorSalaRevision $gestorSalaRevision,
		private readonly RepositorioLlamadasModeloInterface $llamadasModelo,
		private readonly AlmacenPerfilEntornoInterface $almacenPerfilEntorno,
	) {
	}

	/**
	 * @return array{ejecutado: bool, lotesProcesados: int, errores: list<string>}
	 */
	public function ejecutarTick( int $presupuestoSegundos = 90 ): array {
		if ( ! $this->candado->adquirir() ) {
			$this->bitacora->finalizarEjecucion( $this->bitacora->iniciarEjecucion( $this->reloj->ahora() ), $this->reloj->ahora(), 0, array( 'candado ocupado: otra ejecución en curso' ) );

			return array(
				'ejecutado'       => false,
				'lotesProcesados' => 0,
				'errores'         => array(),
			);
		}

		$inicio          = microtime( true );
		$bitacoraId      = $this->bitacora->iniciarEjecucion( $this->reloj->ahora() );
		$errores         = array();
		$lotesProcesados = 0;

		try {
			$errores = array( ...$errores, ...$this->detectarTendencias() );
			$errores = array( ...$errores, ...$this->evaluarModoRespeto() );

			[$avanzados, $erroresPipeline] = $this->avanzarPipeline( $inicio, $presupuestoSegundos );
			$lotesProcesados               = $avanzados;
			$errores                       = array( ...$errores, ...$erroresPipeline );

			$this->procesarPublicacionesVencidas( $errores );
			$this->verificarEscasezHonesta( $errores );
			$this->procesarHistoriasInactivas( $errores );
			$this->purgarLlamadasModeloAntiguas( $errores );
			$this->refrescarPerfilEntorno( $errores );
			$this->gestorExperimentosTitular->consolidarVencidos();
			$errores = array( ...$errores, ...$this->evaluarCreacionAutomaticaPeriodistas() );
			$this->procesarPeriodistasPropuestosVencidos( $errores );
		} finally {
			$this->bitacora->finalizarEjecucion( $bitacoraId, $this->reloj->ahora(), $lotesProcesados, $errores );
			$this->candado->liberar();
		}

		return array(
			'ejecutado'       => true,
			'lotesProcesados' => $lotesProcesados,
			'errores'         => $errores,
		);
	}

	/**
	 * @return list<string>
	 */
	private function detectarTendencias(): array {
		try {
			$detectadas = $this->sensor->detectar();
		} catch ( ProveedorTendenciasException $e ) {
			// El Sensor caído no detiene el resto del tick (pl-proveedor-ia
			// §4): se registra y el pipeline sigue avanzando lo que ya tiene.
			return array( 'sensor ' . $this->sensor->nombre() . ': ' . $e->getMessage() );
		}

		foreach ( $detectadas as $detectada ) {
			if ( $this->tendencias->existePorTermino( $detectada->termino, $detectada->fuenteSenal ) ) {
				continue;
			}

			$candidatas = $this->tendencias->obtenerRecientesConPiezaViva(
				self::DIAS_VENTANA_COMPARACION_HISTORIAS,
				self::LIMITE_CANDIDATAS_COMPARACION,
				$this->reloj->ahora()
			);
			$resultado  = $this->comparadorHistorias->comparar( $detectada, $candidatas );

			if ( RelacionHistoria::Identica === $resultado->relacion ) {
				// Ya cubierta bajo otro titular (huella semántica, Libro Cap.
				// 3.4) — extiende la deduplicación exacta de arriba, no duplica.
				continue;
			}

			if ( RelacionHistoria::Evoluciona === $resultado->relacion && null !== $resultado->tendenciaRelacionadaId ) {
				// "Dos golpes": NO se crea Pieza automáticamente — el editor
				// confirma desde la Sala de Tendencias (decisión del
				// propietario, 2026-07-23).
				$this->tendencias->guardarComoPosibleActualizacion( $detectada, $resultado->tendenciaRelacionadaId, $this->reloj->ahora() );
				continue;
			}

			$diagnosticoLegitimidad = $this->evaluadorLegitimidad->evaluar( $detectada );

			if ( ! $diagnosticoLegitimidad->legitimo ) {
				// Nivel Dos G.1: "antes de que una tendencia entre a la cola
				// editorial" — NO se crea Pieza. El editor revierte con
				// "Cubrir ahora" desde la Sala de Tendencias si juzga que es
				// un falso positivo (la heurística es imperfecta por diseño).
				$this->tendencias->guardarConSospechaLegitimidad( $detectada, $diagnosticoLegitimidad, $this->reloj->ahora() );
				continue;
			}

			$tendenciaId = $this->tendencias->guardar( $detectada, $this->reloj->ahora() );
			$this->piezas->crear( $tendenciaId, $this->reloj->ahora() );

			try {
				$gravedad = $this->clasificadorGravedad->clasificar( $detectada );
				$this->tendencias->actualizarGravedad( $tendenciaId, $gravedad->gravedad, $gravedad->campoTematico, $gravedad->campoGeografico );
			} catch ( Throwable $errorClasificacion ) {
				// La clasificación de gravedad (Nivel Dos F.1-F.2) es una capa
				// de seguridad adicional, no un requisito para que la Pieza
				// avance: si falla, la tendencia sigue su camino normal sin
				// gravedad clasificada — el disparador automático del modo
				// respeto simplemente no la contará, nunca bloquea el pipeline.
				unset( $errorClasificacion );
			}
		}

		return array();
	}

	/**
	 * Nivel Dos F.2, disparador automático: una capa de seguridad adicional,
	 * no un requisito para que el resto del pipeline avance — un fallo aquí
	 * se registra y el tick sigue, igual que un Sensor caído.
	 *
	 * @return list<string>
	 */
	private function evaluarModoRespeto(): array {
		try {
			$this->gestorModoRespeto->evaluarDisparadorAutomatico( $this->reloj->ahora() );
		} catch ( Throwable $e ) {
			return array( 'modo respeto (disparador automático): ' . $e->getMessage() );
		}

		return array();
	}

	/**
	 * @return array{0: int, 1: list<string>}
	 */
	private function avanzarPipeline( float $inicio, int $presupuestoSegundos ): array {
		$procesadas = 0;
		$errores    = array();

		foreach ( $this->piezas->obtenerPorEstado( EstadoPieza::Detectada, self::LIMITE_POR_LOTE ) as $pieza ) {
			if ( $this->presupuestoAgotado( $inicio, $presupuestoSegundos ) ) {
				return array( $procesadas, $errores );
			}

			$this->procesarInvestigacion( $pieza, $errores );
			++$procesadas;
		}

		foreach ( $this->piezas->obtenerPorEstado( EstadoPieza::Investigada, self::LIMITE_POR_LOTE ) as $pieza ) {
			if ( $this->presupuestoAgotado( $inicio, $presupuestoSegundos ) ) {
				return array( $procesadas, $errores );
			}

			$this->procesarRedaccionYBorrador( $pieza, $errores );
			++$procesadas;
		}

		foreach ( $this->piezas->obtenerPorEstado( EstadoPieza::Redactada, self::LIMITE_POR_LOTE ) as $pieza ) {
			if ( $this->presupuestoAgotado( $inicio, $presupuestoSegundos ) ) {
				return array( $procesadas, $errores );
			}

			$this->procesarOptimizacion( $pieza, $errores );
			++$procesadas;
		}

		foreach ( $this->piezas->obtenerPorEstado( EstadoPieza::Optimizada, self::LIMITE_POR_LOTE ) as $pieza ) {
			if ( $this->presupuestoAgotado( $inicio, $presupuestoSegundos ) ) {
				return array( $procesadas, $errores );
			}

			$this->procesarCompuertas( $pieza, $errores );
			++$procesadas;
		}

		// Rescate de Piezas varadas en EN_REVISION. En operación normal este
		// sondeo no encuentra nada: EN_REVISION se entra y se sale dentro del
		// mismo tick. Solo recoge las que quedaron a medias porque la
		// evaluación de Compuertas reventó (proveedor caído, presupuesto
		// agotado, proceso muerto) — antes se quedaban ahí para siempre,
		// invisibles para el motor y para el editor.
		foreach ( $this->piezas->obtenerPorEstado( EstadoPieza::EnRevision, self::LIMITE_POR_LOTE ) as $pieza ) {
			if ( $this->presupuestoAgotado( $inicio, $presupuestoSegundos ) ) {
				return array( $procesadas, $errores );
			}

			$this->procesarCompuertas( $pieza, $errores );
			++$procesadas;
		}

		foreach ( $this->piezas->obtenerPorEstado( EstadoPieza::Aprobada, self::LIMITE_POR_LOTE ) as $pieza ) {
			if ( $this->presupuestoAgotado( $inicio, $presupuestoSegundos ) ) {
				return array( $procesadas, $errores );
			}

			$this->procesarProgramacion( $pieza, $errores );
			++$procesadas;
		}

		return array( $procesadas, $errores );
	}

	private function presupuestoAgotado( float $inicio, int $presupuestoSegundos ): bool {
		return ( microtime( true ) - $inicio ) >= $presupuestoSegundos;
	}

	/**
	 * @param list<string> $errores
	 */
	private function procesarInvestigacion( Pieza $pieza, array &$errores ): void {
		try {
			$transitada = $this->transicionador->transitar( $pieza->id, EstadoPieza::EnInvestigacion, 'inicio de investigación' );

			if ( null === $transitada ) {
				return;
			}

			$datosTendencia = $this->tendencias->obtenerPorId( $pieza->tendenciaId );

			if ( null === $datosTendencia ) {
				throw new PiezaNoEncontradaException( $pieza->tendenciaId );
			}

			$expediente = $this->investigador->investigar(
				$datosTendencia['termino'],
				$datosTendencia['articulosRelacionados']
			);

			// Nivel Dos B.1+B.2 (resolución de disputas) + B.4/O.2 (detección de hueco):
			// enriquecen el expediente ya construido, no lo reemplazan.
			$expediente = $this->resolutorDisputas->resolver( $expediente );
			$expediente = $this->detectorHuecos->detectar( $expediente );

			$this->piezas->actualizarExpediente( $pieza->id, $expediente, $this->reloj->ahora() );
			$this->transicionador->transitar( $pieza->id, EstadoPieza::Investigada, 'expediente construido' );
		} catch ( Throwable $e ) {
			$this->marcarFallida( $pieza->id, $e, $errores );
		}
	}

	/**
	 * @param list<string> $errores
	 */
	private function procesarRedaccionYBorrador( Pieza $pieza, array &$errores ): void {
		try {
			$transitada = $this->transicionador->transitar( $pieza->id, EstadoPieza::EnRedaccion, 'inicio de redacción' );

			if ( null === $transitada || null === $transitada->expediente ) {
				return;
			}

			$resultado = $this->redactor->redactar( $transitada );

			if ( $resultado->sinPeriodistaIdoneo ) {
				// Nivel Dos C.3: ningún periodista del banco supera el umbral de
				// dominio mínimo — salida honesta, nunca asignar "al menos malo".
				$this->transicionador->transitar(
					$pieza->id,
					EstadoPieza::SinPeriodistaIdoneo,
					$resultado->motivoSinPeriodistaIdoneo ?? 'Ningún periodista del banco supera el umbral de dominio mínimo para este vertical.'
				);

				return;
			}

			if ( $resultado->retenida ) {
				// El Corrector Interno no aprobó tras el máximo de ciclos (Libro
				// Cap. 5.6): revisión humana, no un fallo del sistema.
				$this->transicionador->transitar(
					$pieza->id,
					EstadoPieza::Retenida,
					$resultado->motivoRetenida ?? 'El Corrector Interno no aprobó la pieza.'
				);

				return;
			}

			$this->transicionador->transitar( $pieza->id, EstadoPieza::Redactada, 'borrador construido' );

			$postId = $this->creadorBorrador->crear( $resultado->titulo, $resultado->cuerpoHtml );
			$this->piezas->actualizarPostId( $pieza->id, $postId, $this->reloj->ahora() );

			// Imagen destacada por autoridad de fuente (ADR 0006): mejor
			// esfuerzo, nunca bloquea la Pieza — si falla, sigue sin imagen.
			try {
				$this->asignadorImagenDestacada->asignar( $postId, $transitada->expediente );
			} catch ( Throwable $errorImagen ) {
				$errores[] = "pieza {$pieza->id} (imagen destacada, no bloqueante): " . $errorImagen->getMessage();
			}
		} catch ( Throwable $e ) {
			$this->marcarFallida( $pieza->id, $e, $errores );
		}
	}

	/**
	 * Redactada → Optimizada (Libro Cap. 6-7): Motor SEO + Taxónomo. Sin
	 * Ficha de Decisión Editorial (redacción mecánica de respaldo, deuda
	 * PLUMA-E2-1) no hay clasificación/tesis con qué optimizar — la pieza
	 * avanza igual a Optimizada, sin datos SEO/taxonomía, para no bloquear
	 * el pipeline; la Compuerta de Calidad la retendrá para revisión humana
	 * en el siguiente paso por falta de Borrador con anotaciones del
	 * Corrector Interno.
	 *
	 * @param list<string> $errores
	 */
	private function procesarOptimizacion( Pieza $pieza, array &$errores ): void {
		try {
			$transitada = $this->transicionador->transitar( $pieza->id, EstadoPieza::Optimizada, 'optimización SEO y taxonomía' );

			if ( null === $transitada || null === $transitada->expediente || null === $transitada->fichaDecisionEditorial ) {
				return;
			}

			$ficha = $transitada->fichaDecisionEditorial;
			$post  = null !== $transitada->postId ? get_post( $transitada->postId ) : null;

			$datosSeo = $this->motorSeo->optimizar(
				$pieza->id,
				$transitada->expediente,
				$ficha,
				null !== $post ? $post->post_title : ''
			);
			$this->piezas->actualizarDatosSeo( $pieza->id, $datosSeo, $this->reloj->ahora() );

			$resultadoTaxonomia = $this->taxonomo->clasificar( $transitada->expediente, $ficha->clasificacion->tema, $ficha->tesisElegida()->tesis );
			$this->piezas->actualizarResultadoTaxonomia( $pieza->id, $resultadoTaxonomia, $this->reloj->ahora() );
		} catch ( Throwable $e ) {
			$this->marcarFallida( $pieza->id, $e, $errores );
		}
	}

	/**
	 * Optimizada → EnRevision → Aprobada/Retenida (Libro Cap. 8): único
	 * camino legal hacia Aprobada. Piloto nunca auto-aprueba (Cap. 2.4): la
	 * pieza queda en EnRevision para acción humana explícita en la Sala de
	 * Revisión.
	 *
	 * @param list<string> $errores
	 */
	private function procesarCompuertas( Pieza $pieza, array &$errores ): void {
		try {
			// Entrada idempotente: la Pieza puede llegar aquí desde OPTIMIZADA
			// (camino normal) o ya estando en EN_REVISION (rescate de una
			// varada por un fallo anterior — `avanzarPipeline()` sondea ese
			// estado precisamente para eso). Transicionar EN_REVISION →
			// EN_REVISION sería una arista inválida.
			$transitada = EstadoPieza::EnRevision === $pieza->estado
				? $pieza
				: $this->transicionador->transitar( $pieza->id, EstadoPieza::EnRevision, 'evaluación de compuertas' );

			if ( null === $transitada ) {
				return;
			}

			if ( null === $transitada->expediente || null === $transitada->fichaDecisionEditorial ) {
				// Sin expediente o sin ficha no hay materia que evaluar. Se
				// RETIENE explícitamente para decisión humana: dejarla en
				// EN_REVISION la volvería invisible (ninguna pantalla la
				// muestra, ningún paso del motor la recoge).
				$this->transicionador->transitar( $pieza->id, EstadoPieza::Retenida, 'sin expediente o sin ficha de decisión editorial: no hay materia que evaluar en Compuertas.' );

				return;
			}

			$ultimoBorrador = $this->borradores->obtenerUltimo( $pieza->id );

			if ( null === $ultimoBorrador ) {
				// Redacción mecánica de respaldo (deuda PLUMA-E2-1): sin
				// anotaciones del Corrector Interno no hay con qué evaluar
				// Calidad — se retiene para revisión humana, nunca se aprueba
				// a ciegas, y nunca se abandona en EN_REVISION.
				$this->transicionador->transitar( $pieza->id, EstadoPieza::Retenida, 'sin borrador con anotaciones del Corrector Interno: Calidad no es evaluable.' );

				return;
			}

			$ficha      = $transitada->fichaDecisionEditorial;
			$modoGlobal = $this->modoGlobalConfigurado();

			$resultado = $this->evaluadorCompuertas->evaluar(
				$transitada->expediente,
				$ficha->clasificacion,
				$ficha->esqueleto,
				$ultimoBorrador,
				$ultimoBorrador->contenido,
				true,
				$this->textosPropiosRecientes(),
				$modoGlobal
			);

			$this->piezas->actualizarResultadoCompuertas( $pieza->id, $resultado, $this->reloj->ahora() );

			if ( $resultado->retenida ) {
				$this->transicionador->transitar( $pieza->id, EstadoPieza::Retenida, implode( ' ', $resultado->motivos ) );

				return;
			}

			if ( ModoOperacion::Piloto === $resultado->modoEfectivo ) {
				return;
			}

			$this->transicionador->transitar( $pieza->id, EstadoPieza::Aprobada, 'compuertas superadas, modo ' . $resultado->modoEfectivo->value );
		} catch ( Throwable $e ) {
			$this->marcarFallida( $pieza->id, $e, $errores );
		}
	}

	/**
	 * Aprobada → Programada (Libro Cap. 9.2-9.3): cuota elástica, ventanas
	 * con peso, separación mínima + jitter, topes por vertical/periodista.
	 * Sin espacio hoy, la pieza espera al próximo tick (Cap. 9.3: "mejor
	 * esperar que publicar de más").
	 *
	 * @param list<string> $errores
	 */
	private function procesarProgramacion( Pieza $pieza, array &$errores ): void {
		try {
			if ( null === $pieza->fichaDecisionEditorial ) {
				return;
			}

			$ahora     = $this->reloj->ahora();
			$inicioDia = $ahora->setTime( 0, 0 );
			$finDia    = $inicioDia->modify( '+1 day' );

			$config         = $this->lectorCadencia->leer();
			$yaProgramadas  = $this->colaPublicacion->obtenerEntre( $inicioDia, $finDia );
			$vertical       = $pieza->fichaDecisionEditorial->clasificacion->tema;
			$horaProgramada = $this->programadorCadencia->siguienteRanura( $config, $yaProgramadas, $vertical, $pieza->periodistaId, $ahora );

			if ( null === $horaProgramada ) {
				return;
			}

			$this->colaPublicacion->programar( $pieza->id, $vertical, $pieza->periodistaId, $horaProgramada, $ahora );
			$this->transicionador->transitar( $pieza->id, EstadoPieza::Programada, 'programada para ' . $horaProgramada->format( 'Y-m-d H:i' ) );
		} catch ( Throwable $e ) {
			$this->marcarFallida( $pieza->id, $e, $errores );
		}
	}

	/**
	 * Programada → Publicada (Libro Cap. 9.3, paso 5): convierte el
	 * borrador ya creado en post publicado. Copiloto respeta la ventana de
	 * veto (Cap. 2.4: "veto con ventana") — Autónomo publica sin esperar.
	 *
	 * @param list<string> $errores
	 */
	private function procesarPublicacionesVencidas( array &$errores ): void {
		$ahora            = $this->reloj->ahora();
		$ventanaVetoHoras = $this->ventanaVetoHorasConfigurada();

		foreach ( $this->colaPublicacion->obtenerVencidas( $ahora ) as $ranura ) {
			try {
				$pieza = $this->piezas->obtenerPorId( $ranura->piezaId );

				if ( null === $pieza || EstadoPieza::Programada !== $pieza->estado || null === $pieza->postId ) {
					continue;
				}

				$modoEfectivo = $pieza->resultadoCompuertas->modoEfectivo ?? ModoOperacion::Copiloto;

				if ( ModoOperacion::Copiloto === $modoEfectivo && ! $ranura->aprobacionActiva && $ahora < $ranura->horaProgramada->modify( "+{$ventanaVetoHoras} hours" ) ) {
					continue;
				}

				$metadatos = $pieza->datosSeo->metadatos ?? new MetadatosSeo( '', '' );
				$plugin    = $pieza->datosSeo->pluginDetectado ?? TipoPluginSeo::Ninguno;
				$taxonomia = $pieza->resultadoTaxonomia ?? new ResultadoTaxonomia( null, array() );

				$this->publicador->publicar( $pieza->postId, $metadatos, $plugin, $taxonomia, $this->snapshotPublicacion( $pieza, $modoEfectivo, $ranura->aprobacionActiva ) );
				$this->colaPublicacion->marcarPublicada( $ranura->id );

				$tipoAprobacion = ModoOperacion::Copiloto === $modoEfectivo
					? ( $ranura->aprobacionActiva ? TipoAprobacion::HumanaActiva : TipoAprobacion::AutomaticaPorExpiracion )
					: null;

				$this->transicionador->transitar( $pieza->id, EstadoPieza::Publicada, 'publicada por el Orquestador', 'sistema', $tipoAprobacion );
			} catch ( Throwable $e ) {
				$errores[] = "ranura {$ranura->id} (pieza {$ranura->piezaId}): " . $e->getMessage();
			}
		}
	}

	/**
	 * Escasez honesta (Libro Cap. 9.3, paso 6; CLAUDE.md § Contrato del
	 * Orquestador): "PROHIBIDO rebajar umbrales para rellenar". Esta función
	 * solo OBSERVA y registra — ninguna rama de código de esta clase toca
	 * los umbrales de las Compuertas.
	 *
	 * @param list<string> $errores
	 */
	private function verificarEscasezHonesta( array &$errores ): void {
		$ahora     = $this->reloj->ahora();
		$inicioDia = $ahora->setTime( 0, 0 );
		$finDia    = $inicioDia->modify( '+1 day' );
		$config    = $this->lectorCadencia->leer();

		$comprometidasHoy = count( $this->colaPublicacion->obtenerEntre( $inicioDia, $finDia ) );

		if ( $comprometidasHoy < $config->cuotaMinima ) {
			$errores[] = sprintf(
				'Escasez honesta: %d/%d piezas comprometidas hoy (mínimo %d, objetivo %d) — no se rebajan umbrales para rellenar.',
				$comprometidasHoy,
				$config->cuotaMaxima,
				$config->cuotaMinima,
				$config->cuotaObjetivo
			);
		}
	}

	/**
	 * Nivel Cuatro U.1 (Etapa 9): barrido de Historias sin actividad
	 * reciente → `Inactiva`. Capa de mantenimiento adicional, nunca
	 * bloquea el resto del tick si falla.
	 *
	 * @param list<string> $errores
	 */
	private function procesarHistoriasInactivas( array &$errores ): void {
		try {
			$this->gestorHistorias->marcarInactivasVencidas();
		} catch ( Throwable $error ) {
			$errores[] = 'historias inactivas (no bloqueante): ' . $error->getMessage();
		}
	}

	/**
	 * NCP-1 (`ADR 0010`): mantenimiento periódico de `pluma_llamadas_modelo`
	 * — mismo criterio no bloqueante que `procesarHistoriasInactivas()`.
	 *
	 * @param list<string> $errores
	 */
	private function purgarLlamadasModeloAntiguas( array &$errores ): void {
		try {
			$limite = $this->reloj->ahora()->modify( '-' . self::DIAS_RETENCION_LLAMADAS_MODELO . ' days' );
			$this->llamadasModelo->purgarAnterioresA( $limite );
		} catch ( Throwable $error ) {
			$errores[] = 'purga de llamadas al modelo (no bloqueante): ' . $error->getMessage();
		}
	}

	/**
	 * NCP-1 · Sonda de Capacidades (`ADR 0013`): refresca el snapshot del
	 * Perfil de Entorno una vez por tick — mismo criterio no bloqueante que
	 * `purgarLlamadasModeloAntiguas()`. Nunca hace red real: el sensor lee
	 * hechos de PHP puro y el flag cacheado de la última prueba del cerebro
	 * remoto, jamás lo prueba en vivo aquí.
	 *
	 * @param list<string> $errores
	 */
	private function refrescarPerfilEntorno( array &$errores ): void {
		try {
			$this->almacenPerfilEntorno->refrescar();
		} catch ( Throwable $error ) {
			$errores[] = 'sonda de capacidades (no bloqueante): ' . $error->getMessage();
		}
	}

	/**
	 * Trabajo posterior a la Etapa 9 (creación automática de periodistas):
	 * decide si el patrón real de Piezas atascadas justifica proponer un
	 * periodista nuevo. `CreadorAutomaticoPeriodistas` ya trae sus propias
	 * guardas (interruptor, cooldown, tope, volumen mínimo) — este paso solo
	 * delega y nunca bloquea el resto del tick.
	 *
	 * @return list<string>
	 */
	private function evaluarCreacionAutomaticaPeriodistas(): array {
		try {
			return $this->creadorAutomaticoPeriodistas->evaluarYProponer();
		} catch ( Throwable $e ) {
			return array( 'creación automática de periodistas (no bloqueante): ' . $e->getMessage() );
		}
	}

	/**
	 * Trabajo posterior a la Etapa 9 (creación automática de periodistas):
	 * un periodista Propuesto cuya ventana de veto ya expiró se promueve a
	 * Activo solo — mismo criterio temporal que la cola de veto de Copiloto
	 * para Piezas (`OPCION_VENTANA_VETO_HORAS`, reutilizada, ningún ajuste
	 * nuevo) — y sus Piezas contribuyentes se reanudan de inmediato
	 * (`GestorSalaRevision::promoverPeriodistaPropuesto()`).
	 *
	 * @param list<string> $errores
	 */
	private function procesarPeriodistasPropuestosVencidos( array &$errores ): void {
		$ventanaVetoHoras = $this->ventanaVetoHorasConfigurada();
		$ahora            = $this->reloj->ahora();

		foreach ( $this->periodistas->obtenerPropuestos() as $periodista ) {
			if ( $ahora < $periodista->creadoEn->modify( "+{$ventanaVetoHoras} hours" ) ) {
				continue;
			}

			try {
				$this->gestorSalaRevision->promoverPeriodistaPropuesto( $periodista->id, $ahora );
			} catch ( Throwable $error ) {
				$errores[] = "periodista propuesto {$periodista->id} (no bloqueante): " . $error->getMessage();
			}
		}
	}

	/**
	 * Instantánea para el marcado de frontend (Art. 50 UE, Nivel Tres N.3).
	 * `generadoIa` es verdadero para todo lo que publica el sistema sin
	 * aprobación humana activa (Autónomo, o Copiloto por expiración de
	 * ventana) — Piloto nunca llega aquí: sus borradores los publica un
	 * humano a mano. Copiloto con `aprobacionActiva` (porción 4c: "aprobar
	 * ahora") tampoco lleva el marcado — un humano revisó y aprobó antes de
	 * publicar, la misma excepción que ya cubre a Piloto. El nombre del
	 * periodista viaja en la instantánea para no consultar repositorios en
	 * tiempo de render.
	 */
	private function snapshotPublicacion( Pieza $pieza, ModoOperacion $modoEfectivo, bool $aprobacionActiva = false ): SnapshotPublicacion {
		$autorNombre = '';

		if ( null !== $pieza->periodistaId ) {
			$periodista  = $this->periodistas->obtenerPorId( $pieza->periodistaId );
			$autorNombre = null !== $periodista ? $periodista->nombre : '';
		}

		$tipoEsquema = $pieza->datosSeo->tipoEsquema->value ?? TipoEsquemaArticulo::NewsArticle->value;

		return new SnapshotPublicacion(
			$pieza->id,
			ModoOperacion::Piloto !== $modoEfectivo && ! $aprobacionActiva,
			$modoEfectivo->value,
			$tipoEsquema,
			$autorNombre
		);
	}

	private function modoGlobalConfigurado(): ModoOperacion {
		$valor = get_option( self::OPCION_MODO_OPERACION, self::MODO_OPERACION_DEFECTO );

		return ModoOperacion::tryFrom( is_string( $valor ) ? $valor : '' ) ?? ModoOperacion::Copiloto;
	}

	private function ventanaVetoHorasConfigurada(): int {
		$valor = get_option( self::OPCION_VENTANA_VETO_HORAS, self::VENTANA_VETO_HORAS_DEFECTO );

		return is_numeric( $valor ) ? (int) $valor : self::VENTANA_VETO_HORAS_DEFECTO;
	}

	/**
	 * Material para la Compuerta de Originalidad (Libro Cap. 8.3):
	 * auto-plagio/canibalización contra el propio sitio.
	 *
	 * @return list<string>
	 */
	private function textosPropiosRecientes(): array {
		$idsRecientes = get_posts(
			array(
				'post_status'    => 'publish',
				'posts_per_page' => self::PIEZAS_PROPIAS_RECIENTES,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		return array_map(
			static function ( int $postId ): string {
				$contenido = get_post_field( 'post_content', $postId );

				return is_string( $contenido ) ? $contenido : '';
			},
			$idsRecientes
		);
	}

	/**
	 * @param list<string> $errores
	 */
	private function marcarFallida( int $piezaId, Throwable $error, array &$errores ): void {
		$errores[] = "pieza {$piezaId}: " . $error->getMessage();

		try {
			$this->transicionador->transitar( $piezaId, EstadoPieza::Fallida, $error->getMessage() );
		} catch ( Throwable $errorSecundario ) {
			// Si ni siquiera se puede marcar como Fallida (p. ej. la Pieza ya
			// no existe), el error ya quedó registrado en la bitácora arriba;
			// se añade este segundo motivo para no perder la pista.
			$errores[] = "pieza {$piezaId} (al marcar fallida): " . $errorSecundario->getMessage();
		}
	}
}
