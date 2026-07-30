<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Kernel\RelojInterface;
use Pluma\Pipeline\Pieza;
use Pluma\Proveedores\ProveedorLenguajeException;
use RuntimeException;

/**
 * Implementación de producción de `RedactorInterface` (Libro Cap. 5):
 * ejecuta el Algoritmo de Decisión Editorial y `RedactorSintetico`; si el
 * proveedor de lenguaje no tiene presupuesto o credenciales configuradas,
 * notifica vía `pluma/redactor_fallback_mecanico` y usa `RedactorMecanico`
 * (decisión explícita del propietario — no un valor por defecto silencioso).
 *
 * Cualquier otro fallo del proveedor (red, formato, circuito abierto) se
 * propaga: no es el escenario de fallback autorizado, y degradar la calidad
 * silenciosamente ante un fallo técnico transitorio violaría la "escasez
 * honesta" del Orquestador (CLAUDE.md § Contrato del Orquestador).
 */
final class RedactorConFallbackMecanico implements RedactorInterface {

	public function __construct(
		private readonly DecisionEditorial $decisionEditorial,
		private readonly RedactorSintetico $redactorSintetico,
		private readonly RedactorMecanico $redactorMecanico,
		private readonly RepositorioPiezasInterface $repoPiezas,
		private readonly RelojInterface $reloj,
	) {
	}

	public function redactar( Pieza $pieza ): ResultadoRedaccion {
		if ( null === $pieza->expediente ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new RuntimeException( "La pieza {$pieza->id} no tiene expediente; no se puede redactar." );
		}

		try {
			$decision = $this->decisionEditorial->decidir( $pieza->expediente, $pieza->piezaOriginalId );
		} catch ( ProveedorLenguajeException $e ) {
			return $this->usarFallbackMecanico( $pieza, $e );
		} catch ( NingunPeriodistaIdoneoException $e ) {
			// Nivel Dos C.3: "no se asigna a 'el menos malo'" — a diferencia del
			// fallback mecánico, aquí NO se escribe ningún borrador.
			//
			// Trabajo posterior a la Etapa 9 (creación automática de
			// periodistas): $e->tema ya está calculado aquí — se persiste antes
			// de descartar la excepción en la frase libre de motivoSinPeriodistaIdoneo,
			// es la señal real que consume `CreadorAutomaticoPeriodistas`.
			$this->repoPiezas->actualizarTemaSinCubrir( $pieza->id, $e->tema, $this->reloj->ahora() );

			return new ResultadoRedaccion( '', '', false, null, 0, true, $e->getMessage() );
		}

		$periodista = $decision['periodista'];
		$ficha      = $decision['ficha'];
		$ahora      = $this->reloj->ahora();

		$this->repoPiezas->asignarPeriodista( $pieza->id, $periodista->id, $periodista->conductaActual->id, $ahora );
		$this->repoPiezas->actualizarFichaDecisionEditorial( $pieza->id, $ficha, $ahora );

		try {
			return $this->redactorSintetico->redactar( $pieza->id, $periodista, $pieza->expediente, $ficha );
		} catch ( ProveedorLenguajeException $e ) {
			return $this->usarFallbackMecanico( $pieza, $e );
		}
	}

	private function usarFallbackMecanico( Pieza $pieza, ProveedorLenguajeException $causa ): ResultadoRedaccion {
		if ( ! $causa->sinCredenciales && ! $causa->presupuestoAgotado ) {
			// Fallo técnico real (red, HTTP, formato, circuito abierto): no es
			// el escenario "sin presupuesto/credenciales" que el propietario
			// autorizó degradar en silencio — se propaga para que la pieza se
			// marque FALLIDA y quede registrada en la bitácora del motor.
			throw $causa;
		}

		do_action( 'pluma/redactor_fallback_mecanico', $pieza->id, $causa->getMessage() );

		assert( null !== $pieza->expediente );
		$borrador = $this->redactorMecanico->redactar( $pieza->expediente );

		return new ResultadoRedaccion( $borrador->titulo, $borrador->cuerpoHtml, false, null, 0 );
	}
}
