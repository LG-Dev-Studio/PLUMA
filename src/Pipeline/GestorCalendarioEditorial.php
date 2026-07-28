<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

use DateTimeImmutable;
use Pluma\Datos\RepositorioEventosProgramadosInterface;
use Pluma\Datos\RepositorioHistoriasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Datos\RepositorioTendenciasInterface;
use Pluma\Kernel\RelojInterface;
use Pluma\Sensores\PuntuacionOportunidad;
use Pluma\Sensores\TendenciaDetectada;

/**
 * Nivel Cuatro V.1 (Calendario Editorial) + V.2 (la pieza preparada): "la
 * mitad del calendario noticioso se conoce con semanas de anticipación...
 * un sistema que espera a que el evento sea tendencia llega
 * estructuralmente tarde".
 *
 * `prepararCobertura()` NO duplica ninguna lógica de investigación,
 * redacción o compuertas: crea una tendencia sintética (`fuente_senal =
 * 'calendario_editorial'`) a partir del evento y de las fuentes que el
 * editor ya reunió, y deja que el pipeline normal (Orquestador →
 * Investigación → Redacción → Compuertas → Publicador) haga el resto —
 * exactamente el mismo camino que sigue cualquier tendencia detectada por
 * el Radar. La puntuación de la tendencia sintética usa el máximo honesto
 * (`PuntuacionOportunidad::calcular(100.0, 100.0)`, techo real 55/100,
 * `PLUMA-E1-1`): no es una medición orgánica — el editor ya decidió cubrir
 * este evento, la puntuación nunca debe competir mal contra tendencias
 * reales en la Sala de Tendencias.
 *
 * V.1 fija la agenda "carga manual del editor". Los sensores automáticos
 * por vertical (calendario económico/electoral/deportivo/de lanzamientos)
 * requieren elegir e integrar un proveedor externo real por vertical —
 * decisión de propietario pendiente, registrada en `PLUMA-E9-2`, mismo
 * tratamiento que `PLUMA-E8-1`/`PLUMA-E8-6`/`PLUMA-E8-7`.
 */
final class GestorCalendarioEditorial {

	public function __construct(
		private readonly RepositorioEventosProgramadosInterface $eventos,
		private readonly RepositorioTendenciasInterface $tendencias,
		private readonly RepositorioPiezasInterface $piezas,
		private readonly RepositorioHistoriasInterface $historias,
		private readonly RelojInterface $reloj,
	) {
	}

	public function crear( string $titulo, string $vertical, DateTimeImmutable $fechaEsperada, ?int $periodistaAsignadoId = null, ?int $historiaId = null ): int {
		return $this->eventos->crear( $titulo, $vertical, $fechaEsperada, $periodistaAsignadoId, $historiaId, $this->reloj->ahora() );
	}

	public function obtener( int $eventoId ): ?EventoProgramado {
		return $this->eventos->obtenerPorId( $eventoId );
	}

	/**
	 * @return list<EventoProgramado>
	 */
	public function listar( int $limite = 50 ): array {
		return $this->eventos->listar( $limite );
	}

	/**
	 * V.2: construye el expediente con antelación y deja que el pipeline
	 * normal produzca la previa. `$articulosRelacionados` son las fuentes
	 * que el editor ya reunió para este evento (misma forma que las que un
	 * Sensor automático entregaría) — nunca se inventan ni se buscan
	 * automáticamente (`PLUMA-E9-2`).
	 *
	 * @param list<array{titulo: string, url: string, fuente: string}> $articulosRelacionados
	 *
	 * @throws EventoProgramadoNoEncontradoException
	 * @throws EventoProgramadoSinFuentesException
	 */
	public function prepararCobertura( int $eventoId, array $articulosRelacionados ): int {
		$evento = $this->eventos->obtenerPorId( $eventoId );

		if ( null === $evento ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje interno construido por la propia excepción, sin entrada de usuario.
			throw new EventoProgramadoNoEncontradoException( $eventoId );
		}

		if ( array() === $articulosRelacionados ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje interno construido por la propia excepción, sin entrada de usuario.
			throw new EventoProgramadoSinFuentesException( $eventoId );
		}

		$ahora = $this->reloj->ahora();

		$historiaId = $evento->historiaId;

		if ( null === $historiaId ) {
			$historiaId = $this->historias->crear( $evento->titulo, $ahora );
			$this->eventos->vincularHistoria( $eventoId, $historiaId, $ahora );
		}

		$tendenciaSintetica = new TendenciaDetectada(
			$evento->titulo,
			PuntuacionOportunidad::calcular( 100.0, 100.0 ),
			$ahora,
			$articulosRelacionados,
			'calendario_editorial'
		);

		$tendenciaId = $this->tendencias->guardar( $tendenciaSintetica, $ahora );
		$this->eventos->vincularTendencia( $eventoId, $tendenciaId, $ahora );

		$piezaId = $this->piezas->crear( $tendenciaId, $ahora );
		$this->piezas->vincularHistoria( $piezaId, $historiaId, TipoPieza::Previa, $ahora );

		$this->eventos->actualizarEstado( $eventoId, EstadoEventoProgramado::Preparado, $ahora );

		return $piezaId;
	}

	/**
	 * @throws EventoProgramadoNoEncontradoException
	 */
	public function marcarEnCurso( int $eventoId ): bool {
		return $this->transitar( $eventoId, EstadoEventoProgramado::EnCurso );
	}

	/**
	 * @throws EventoProgramadoNoEncontradoException
	 */
	public function marcarCubierto( int $eventoId ): bool {
		return $this->transitar( $eventoId, EstadoEventoProgramado::Cubierto );
	}

	/**
	 * @throws EventoProgramadoNoEncontradoException
	 */
	private function transitar( int $eventoId, EstadoEventoProgramado $estado ): bool {
		if ( null === $this->eventos->obtenerPorId( $eventoId ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje interno construido por la propia excepción, sin entrada de usuario.
			throw new EventoProgramadoNoEncontradoException( $eventoId );
		}

		return $this->eventos->actualizarEstado( $eventoId, $estado, $this->reloj->ahora() );
	}
}
