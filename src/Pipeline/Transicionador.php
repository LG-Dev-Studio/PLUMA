<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

use Pluma\Datos\RepositorioAuditoriaInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Kernel\RelojInterface;

/**
 * Único camino para mover una Pieza por el grafo de estados (pl-pipeline
 * §1, `references/estados.md`). Nunca se escribe el estado directo en el
 * repositorio: valida el grafo, aplica el candado por-Pieza (actualización
 * optimista — pl-pipeline §2) y registra la auditoría.
 */
final class Transicionador {

	/**
	 * @var array<string, list<string>>
	 */
	private const GRAFO = array(
		'detectada'             => array( 'en_investigacion', 'descartada', 'fallida' ),
		'en_investigacion'      => array( 'investigada', 'retenida', 'descartada', 'fallida' ),
		'investigada'           => array( 'en_redaccion', 'retenida', 'descartada', 'fallida' ),
		'en_redaccion'          => array( 'redactada', 'retenida', 'descartada', 'fallida', 'sin_periodista_idoneo' ),
		'redactada'             => array( 'optimizada', 'retenida', 'descartada', 'fallida' ),
		'optimizada'            => array( 'en_revision', 'retenida', 'descartada', 'fallida' ),
		// `fallida` es obligatoria en todo estado donde corra trabajo que
		// pueda reventar (llamadas al proveedor de lenguaje, I/O): sin esa
		// arista, `Orquestador::marcarFallida()` es rechazado por este mismo
		// grafo, el `catch` se lo traga y la Pieza queda varada para siempre
		// en un estado que nadie vuelve a sondear. Ocurrió de verdad: 9
		// Piezas atascadas 6 días en EN_REVISION con el diagnóstico de
		// Compuertas sin escribir.
		'en_revision'           => array( 'aprobada', 'retenida', 'descartada', 'fallida' ),
		'aprobada'              => array( 'programada', 'retenida', 'descartada', 'fallida' ),
		'programada'            => array( 'publicada', 'retenida', 'descartada', 'fallida' ),
		// FALLIDA/RETENIDA se reanudan al estado previo: el motivo de la
		// transición documenta a cuál. Se admite cualquier destino no
		// terminal para que la reanudación no dependa de recordar aquí
		// cada arista de recuperación posible.
		'fallida'               => array(
			'detectada',
			'en_investigacion',
			'investigada',
			'en_redaccion',
			'redactada',
			'optimizada',
			'en_revision',
			'aprobada',
			'programada',
			'descartada',
		),
		// Sala de Revisión (Libro Cap. 10.2, tres botones): "devolver con
		// nota" reingresa a OPTIMIZADA (no a EN_REVISION — este último es un
		// estado transitorio, atómico dentro de un único ciclo del
		// Orquestador, que nadie vuelve a consultar por sí solo; OPTIMIZADA
		// sí es una etapa real que `avanzarPipeline()` sondea en cada tick,
		// así que la pieza vuelve a pasar por Compuertas de verdad, no se
		// queda varada). "Aprobar" es la anulación humana informada de una
		// retención (Cap. 8.2: "RETENIDA para humano" — el humano ES la
		// autoridad final de ese caso, no un atajo automático alrededor de
		// las Compuertas).
		'retenida'              => array( 'optimizada', 'aprobada', 'descartada' ),
		// Nivel Dos C.3: ningún periodista del banco superó el umbral de
		// dominio mínimo — reanudable tras ajuste del banco por el editor, o
		// descartable si la tendencia caduca mientras espera.
		//
		// La reanudación apunta a INVESTIGADA, no a EN_REDACCION: toda ruta
		// de vuelta al pipeline debe terminar en un estado que
		// `avanzarPipeline()` SONDEA. EN_REDACCION es transitorio (se entra y
		// se sale dentro del mismo tick) y nadie lo consulta por sí solo:
		// reanudar ahí volvería a varar la Pieza, cambiando un callejón sin
		// salida por otro. Se conserva `en_redaccion` como destino legal
		// porque el propio pipeline lo usa al avanzar desde INVESTIGADA.
		'sin_periodista_idoneo' => array( 'investigada', 'en_redaccion', 'descartada' ),
	);

	public function __construct(
		private readonly RepositorioPiezasInterface $piezas,
		private readonly RepositorioAuditoriaInterface $auditoria,
		private readonly RelojInterface $reloj,
	) {
	}

	/**
	 * @throws PiezaNoEncontradaException
	 * @throws TransicionInvalidaException
	 */
	public function transitar(
		int $piezaId,
		EstadoPieza $nuevoEstado,
		string $motivo,
		string $actor = 'sistema',
		?TipoAprobacion $tipoAprobacion = null
	): ?Pieza {
		$pieza = $this->piezas->obtenerPorId( $piezaId );

		if ( null === $pieza ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new PiezaNoEncontradaException( $piezaId );
		}

		$this->validarArista( $pieza->estado, $nuevoEstado );

		$ahora    = $this->reloj->ahora();
		$aplicada = $this->piezas->actualizarEstado( $piezaId, $pieza->estado, $nuevoEstado, $ahora );

		if ( ! $aplicada ) {
			// Otra ejecución ya la movió (candado por-Pieza optimista):
			// no es un error, el lote actual simplemente la salta.
			return null;
		}

		$this->auditoria->registrar( $piezaId, $pieza->estado, $nuevoEstado, $actor, $motivo, $ahora, $tipoAprobacion );

		do_action( 'pluma/pieza_' . $nuevoEstado->value, $piezaId, $pieza->estado, $motivo );

		return $pieza->conEstado( $nuevoEstado, $ahora );
	}

	/**
	 * @throws TransicionInvalidaException
	 */
	private function validarArista( EstadoPieza $de, EstadoPieza $hacia ): void {
		$permitidas = self::GRAFO[ $de->value ] ?? array();

		if ( ! in_array( $hacia->value, $permitidas, true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new TransicionInvalidaException( $de, $hacia );
		}
	}
}
