<?php

declare(strict_types=1);

namespace Pluma\Compuertas;

use Pluma\Redaccion\AnotacionCorrector;
use Pluma\Redaccion\Borrador;
use Pluma\Redaccion\EsqueletoPieza;
use Pluma\Redaccion\PuntoCorrector;

/**
 * Compuerta de Calidad (Libro Cap. 8.1, endurecida por Nivel Tres K.2 — "el
 * hallazgo más grave"): densidad de sustento y estructura completa son PISOS
 * eliminatorios, no contribuyentes de un promedio — una pieza sin alguno de
 * los dos reprueba con puntuación 0, sin importar cuánto sumen proporción
 * interpretativa/legibilidad/voz. Solo tras superar ambos pisos, esos tres
 * factores de PRIORIDAD se ponderan: proporción interpretativa 0.40,
 * legibilidad 0.35, presencia de voz 0.25 (`docs/puntuaciones.md`).
 *
 * Reaprovecha el juicio ya hecho por el Corrector Interno (Etapa 2, guardado
 * en el último `Borrador` aprobado) para sustento/proporción/voz — no vuelve
 * a llamar al proveedor de lenguaje para no duplicar coste; legibilidad y
 * estructura se verifican aquí de forma determinista, "cinturón y tirantes"
 * sobre el mismo texto final que de hecho se va a publicar.
 */
final class CompuertaCalidad {

	public const OPCION_UMBRAL   = 'pluma_compuerta_calidad_umbral';
	private const UMBRAL_DEFECTO = 70;

	private const PESO_PROPORCION_INTERPRETATIVA = 0.40;
	private const PESO_LEGIBILIDAD               = 0.35;
	private const PESO_VOZ                       = 0.25;
	private const UMBRAL_LEGIBILIDAD_ACEPTABLE   = 10;

	public function __construct( private readonly VerificadorLegibilidad $verificadorLegibilidad ) {
	}

	public function evaluar( Borrador $ultimoBorrador, EsqueletoPieza $esqueleto, string $textoFinal, bool $bloqueEditorPresente ): DiagnosticoCalidad {
		$detalle = array();

		$sustento         = $this->anotacion( $ultimoBorrador, PuntoCorrector::Hechos );
		$sustentoAprobado = null !== $sustento && $sustento->aprobado;

		if ( ! $sustentoAprobado ) {
			$detalle[] = 'Sustento en el expediente: ' . ( $sustento->detalle ?? 'sin evaluar' );
		}

		$estructuraCompleta = $this->estructuraCompleta( $esqueleto, $bloqueEditorPresente );

		if ( ! $estructuraCompleta ) {
			$detalle[] = 'Estructura incompleta: falta gancho, movimientos argumentales, contraargumento, remate o Bloque del Editor.';
		}

		$proporcion         = $this->anotacion( $ultimoBorrador, PuntoCorrector::ProporcionInterpretativa );
		$proporcionAprobada = null !== $proporcion && $proporcion->aprobado;

		if ( ! $proporcionAprobada ) {
			$detalle[] = 'Proporción interpretativa: ' . ( $proporcion->detalle ?? 'sin evaluar' );
		}

		$voz         = $this->anotacion( $ultimoBorrador, PuntoCorrector::Voz );
		$vozAprobada = null !== $voz && $voz->aprobado;

		if ( ! $vozAprobada ) {
			$detalle[] = 'Voz del periodista: ' . ( $voz->detalle ?? 'sin evaluar' );
		}

		$puntosLegibilidad = $this->verificadorLegibilidad->puntuar( $textoFinal );

		if ( $puntosLegibilidad < self::UMBRAL_LEGIBILIDAD_ACEPTABLE ) {
			$detalle[] = "Legibilidad: {$puntosLegibilidad}/20 (longitud de frases fuera del rango cómodo).";
		}

		$elegible = $sustentoAprobado && $estructuraCompleta;

		if ( ! $elegible ) {
			$detalle[] = 'Puntuación en 0: sin sustento aprobado o sin estructura completa, ninguno de los dos pisos es compensable con el resto.';
		}

		$puntos = $elegible
			? (int) round(
				self::PESO_PROPORCION_INTERPRETATIVA * ( $proporcionAprobada ? 100 : 0 )
				+ self::PESO_LEGIBILIDAD * ( $puntosLegibilidad * 5 )
				+ self::PESO_VOZ * ( $vozAprobada ? 100 : 0 )
			)
			: 0;

		$umbral = $this->umbralConfigurado();

		return new DiagnosticoCalidad( $puntos, $umbral, $sustentoAprobado, $estructuraCompleta, $detalle );
	}

	private function anotacion( Borrador $borrador, PuntoCorrector $punto ): ?AnotacionCorrector {
		foreach ( $borrador->anotaciones as $anotacion ) {
			if ( $punto === $anotacion->punto ) {
				return $anotacion;
			}
		}

		return null;
	}

	private function estructuraCompleta( EsqueletoPieza $esqueleto, bool $bloqueEditorPresente ): bool {
		return '' !== trim( $esqueleto->gancho )
			&& array() !== $esqueleto->movimientosArgumentales
			&& '' !== trim( $esqueleto->contraargumentoReconocido )
			&& '' !== trim( $esqueleto->remate )
			&& $bloqueEditorPresente;
	}

	private function umbralConfigurado(): int {
		$umbral = get_option( self::OPCION_UMBRAL, self::UMBRAL_DEFECTO );

		return is_numeric( $umbral ) ? max( 0, min( 100, (int) $umbral ) ) : self::UMBRAL_DEFECTO;
	}
}
