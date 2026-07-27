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
		private readonly VerificadorFalseabilidad $verificadorFalseabilidad,
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

		// Nivel Dos E.2, memoria colectiva del sitio: además de la memoria
		// individual del periodista asignado, se consulta si CUALQUIER OTRO
		// periodista (activo o jubilado) ya se pronunció sobre este tema —
		// el sitio como voz colectiva no debe contradecirse a sí mismo solo
		// porque quien firmó antes ya no está en el banco.
		$posturasColectivas = array();

		foreach ( $this->repoMemoria->obtenerPosturasColectivasPorTema( $clasificacion->tema ) as $entrada ) {
			if ( $entrada->periodistaId === $periodista->id ) {
				continue; // Ya cubierta por la memoria individual de arriba.
			}

			$autor = $this->repoPeriodistas->obtenerPorId( $entrada->periodistaId );

			if ( null === $autor ) {
				continue; // Registro huérfano (periodista eliminado) — no se inventa atribución.
			}

			$posturasColectivas[] = new PosturaColectiva( $entrada, $autor->nombre, EstadoPeriodista::Activo === $autor->estado );
		}

		$candidatos         = $this->selectorAngulo->generarCandidatos( $periodista, $expediente, $clasificacion, $posturasPrevias, $posturasColectivas );
		$indiceTesisElegida = $this->selectorAngulo->elegirGanadora( $candidatos );

		// Nivel Tres O.1, Fase 3.5 — Prueba de Falseabilidad: entre la
		// selección de ángulo (Paso 3) y la arquitectura de la pieza (Paso 4),
		// un intento genuino de derrotar la tesis ganadora usando solo el
		// expediente. Si el caso en contra domina claramente, la tesis se
		// descarta y se reevalúa entre los candidatos restantes — nunca se
		// sobre-corrige descartando toda la decisión.
		$tensionFalseabilidad = null;
		$umbralRetorno        = $this->verificadorFalseabilidad->umbralRegreso();

		while ( true ) {
			$tesisElegida       = $candidatos[ $indiceTesisElegida ];
			$resultadoFalseable = $this->verificadorFalseabilidad->evaluar( $expediente, $tesisElegida );

			if ( $resultadoFalseable->fuerzaSustento >= $umbralRetorno ) {
				unset( $candidatos[ $indiceTesisElegida ] );
				$candidatos = array_values( $candidatos );

				if ( array() === $candidatos ) {
					throw new DecisionEditorialException(
						'Todos los candidatos de tesis fueron derrotados por la Prueba de Falseabilidad (Nivel Tres O.1).'
					);
				}

				$indiceTesisElegida = $this->selectorAngulo->elegirGanadora( $candidatos );

				continue;
			}

			if ( $resultadoFalseable->fuerzaSustento >= $tesisElegida->puntuacionSustento ) {
				$tensionFalseabilidad = $resultadoFalseable->casoEnContra;
			}

			break;
		}

		$filaMatriz    = $periodista->conductaActual->matrizTonos->paraTipo( $clasificacion->tipoNoticia );
		$tonoDominante = $filaMatriz->tonoDominante;
		$tonoApoyo     = $filaMatriz->tonoApoyo;

		$esqueleto = $this->generadorEsqueleto->generar( $expediente, $tesisElegida, $tonoDominante, $tonoApoyo, $tensionFalseabilidad );

		$ficha = new FichaDecisionEditorial(
			$periodista->id,
			$periodista->conductaActual->id,
			$clasificacion,
			$candidatos,
			$indiceTesisElegida,
			$tonoDominante,
			$tonoApoyo,
			$esqueleto,
			$this->reloj->ahora(),
			$tensionFalseabilidad
		);

		return array(
			'periodista' => $periodista,
			'ficha'      => $ficha,
		);
	}
}
