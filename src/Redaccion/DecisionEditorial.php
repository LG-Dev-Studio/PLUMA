<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Datos\RepositorioMemoriaEditorialInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Investigacion\Expediente;
use Pluma\Kernel\RelojInterface;

/**
 * Orquesta los cuatro pasos del Algoritmo de Decisión Editorial (Libro
 * Cap. 5.5): clasificación → asignación de periodista → selección de ángulo
 * (con memoria antes de tesis) → arquitectura de la pieza. Devuelve el
 * periodista asignado y la Ficha de Decisión Editorial completa — sin ficha
 * completa no hay paso a redacción (pl-periodistas §7).
 */
final class DecisionEditorial {

	public function __construct(
		private readonly ClasificadorNoticia $clasificador,
		private readonly AsignadorPeriodista $asignador,
		private readonly SelectorAngulo $selectorAngulo,
		private readonly GeneradorEsqueleto $generadorEsqueleto,
		private readonly RepositorioPeriodistasInterface $repoPeriodistas,
		private readonly RepositorioMemoriaEditorialInterface $repoMemoria,
		private readonly RepositorioPiezasInterface $repoPiezas,
		private readonly RelojInterface $reloj,
	) {
	}

	/**
	 * @return array{periodista: Periodista, ficha: FichaDecisionEditorial}
	 *
	 * @throws DecisionEditorialException si no hay periodistas activos o ningún candidato de tesis supera el umbral de sustento.
	 * @throws NingunPeriodistaIdoneoException si ningún periodista activo supera el umbral de dominio mínimo (Nivel Dos C.3).
	 * @throws \Pluma\Proveedores\ProveedorLenguajeException
	 */
	public function decidir( Expediente $expediente, ?int $piezaOriginalId = null ): array {
		$clasificacion = $this->clasificador->clasificar( $expediente );

		$activos = $this->repoPeriodistas->obtenerActivos();

		if ( array() === $activos ) {
			throw new DecisionEditorialException( 'No hay periodistas activos en el banco: no se puede asignar esta pieza.' );
		}

		$inicioDelDia = $this->reloj->ahora()->setTime( 0, 0 );

		$piezasHoyPorPeriodista      = array();
		$tieneHistorialPorPeriodista = array();

		foreach ( $activos as $periodista ) {
			$piezasHoyPorPeriodista[ $periodista->id ]      = $this->repoPiezas->contarAsignadasDesde( $periodista->id, $inicioDelDia );
			$tieneHistorialPorPeriodista[ $periodista->id ] = $this->repoMemoria->existeCoberturaDelTema( $periodista->id, $clasificacion->tema );
		}

		// Nivel Dos C.2 punto 2: "quien empezó esta historia, la sigue" — solo
		// si ese periodista sigue activo entre los candidatos actuales.
		$periodistaIdDeHistoriaEspecifica = null;

		if ( null !== $piezaOriginalId ) {
			$piezaOriginal = $this->repoPiezas->obtenerPorId( $piezaOriginalId );

			if ( null !== $piezaOriginal && null !== $piezaOriginal->periodistaId
				&& in_array( $piezaOriginal->periodistaId, array_map( static fn ( Periodista $p ): int => $p->id, $activos ), true ) ) {
				$periodistaIdDeHistoriaEspecifica = $piezaOriginal->periodistaId;
			}
		}

		$periodista = $this->asignador->asignar( $activos, $clasificacion, $piezasHoyPorPeriodista, $tieneHistorialPorPeriodista, $periodistaIdDeHistoriaEspecifica );

		// pl-periodistas §3 "memoria antes de tesis": se consulta ANTES de seleccionar ángulo.
		$posturasPrevias = $this->repoMemoria->obtenerPosturasPorTema( $periodista->id, $clasificacion->tema );

		$candidatos         = $this->selectorAngulo->generarCandidatos( $periodista, $expediente, $clasificacion, $posturasPrevias );
		$indiceTesisElegida = $this->selectorAngulo->elegirGanadora( $candidatos );
		$tesisElegida       = $candidatos[ $indiceTesisElegida ];

		$filaMatriz    = $periodista->conductaActual->matrizTonos->paraTipo( $clasificacion->tipoNoticia );
		$tonoDominante = $filaMatriz->tonoDominante;
		$tonoApoyo     = $filaMatriz->tonoApoyo;

		$esqueleto = $this->generadorEsqueleto->generar( $expediente, $tesisElegida, $tonoDominante, $tonoApoyo );

		$ficha = new FichaDecisionEditorial(
			$periodista->id,
			$periodista->conductaActual->id,
			$clasificacion,
			$candidatos,
			$indiceTesisElegida,
			$tonoDominante,
			$tonoApoyo,
			$esqueleto,
			$this->reloj->ahora()
		);

		return array(
			'periodista' => $periodista,
			'ficha'      => $ficha,
		);
	}
}
