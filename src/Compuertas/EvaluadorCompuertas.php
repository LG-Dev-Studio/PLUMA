<?php

declare(strict_types=1);

namespace Pluma\Compuertas;

use Pluma\Investigacion\Expediente;
use Pluma\Redaccion\Borrador;
use Pluma\Redaccion\ClasificacionNoticia;
use Pluma\Redaccion\EsqueletoPieza;

/**
 * Único camino legal hacia `Publicacion` (pl-compuertas §1): ninguna Pieza
 * avanza a `Aprobada` sin pasar por las tres compuertas. Solo evaluación,
 * cero mutación — ni `$wpdb` ni transiciones de estado; el Orquestador
 * decide qué hacer con el `ResultadoEvaluacion` vía `Transicionador`.
 */
final class EvaluadorCompuertas {

	public function __construct(
		private readonly CompuertaCalidad $compuertaCalidad,
		private readonly CompuertaRiesgo $compuertaRiesgo,
		private readonly CompuertaOriginalidad $compuertaOriginalidad,
		private readonly GestorDegradacion $gestorDegradacion,
	) {
	}

	/**
	 * @param list<string> $textosPropiosRecientes contenido en texto plano de piezas ya publicadas del sitio (auto-plagio/canibalización)
	 *
	 * @throws CompuertaException
	 * @throws \Pluma\Proveedores\ProveedorLenguajeException
	 */
	public function evaluar(
		Expediente $expediente,
		ClasificacionNoticia $clasificacion,
		EsqueletoPieza $esqueleto,
		Borrador $ultimoBorrador,
		string $textoFinal,
		bool $bloqueEditorPresente,
		array $textosPropiosRecientes,
		ModoOperacion $modoGlobal
	): ResultadoEvaluacion {
		$riesgo       = $this->compuertaRiesgo->evaluar( $expediente, $textoFinal, $clasificacion );
		$modoEfectivo = $this->gestorDegradacion->modoEfectivo( $modoGlobal, $riesgo );
		$calidad      = $this->compuertaCalidad->evaluar( $ultimoBorrador, $esqueleto, $textoFinal, $bloqueEditorPresente );
		$originalidad = $this->compuertaOriginalidad->evaluar( $expediente, $textoFinal, $textosPropiosRecientes );

		$motivos = array();

		if ( $riesgo->riesgoDifamacion ) {
			$motivos[] = "Riesgo de difamación: {$riesgo->detalleDifamacion}";
		}

		if ( $riesgo->hechosDisputadosSinSenalar ) {
			$motivos[] = 'Hechos disputados en el expediente presentados como consenso sin señalar la disputa.';
		}

		if ( $riesgo->posturaSenaladoAusente ) {
			$motivos[] = 'Postura del señalado ausente en las fuentes recolectadas — derecho de réplica previa no satisfecho (Nivel Tres M.1).';
		}

		if ( $riesgo->retencionObligatoriaPorRegimenPenal ) {
			$motivos[] = 'Afirmación fáctica negativa sobre persona identificable bajo régimen de responsabilidad penal — retención humana obligatoria, modo Autónomo excluido (Nivel Tres N.1).';
		}

		if ( ! $calidad->aprobada() ) {
			$motivos[] = sprintf(
				'Calidad insuficiente (%d/100, umbral %d): %s',
				$calidad->puntuacionTotal,
				$calidad->umbral,
				implode( ' ', $calidad->detalle )
			);
		}

		if ( ! $originalidad->aprobada() ) {
			$motivos[] = $this->motivoOriginalidad( $originalidad );
		}

		return new ResultadoEvaluacion(
			array() === $motivos,
			array() !== $motivos,
			$motivos,
			$modoEfectivo,
			$calidad,
			$riesgo,
			$originalidad
		);
	}

	private function motivoOriginalidad( DiagnosticoOriginalidad $originalidad ): string {
		if ( $originalidad->solapamientoConFuentes ) {
			return 'Solapamiento textual con las fuentes del expediente.';
		}

		if ( $originalidad->solapamientoConSitioPropio ) {
			return 'Solapamiento textual con una pieza propia ya publicada (auto-plagio/canibalización).';
		}

		return sprintf(
			'Ganancia de información insuficiente (%.0f%%, umbral %.0f%%).',
			$originalidad->ratioGananciaInformacion * 100,
			$originalidad->umbralGananciaMinima * 100
		);
	}
}
