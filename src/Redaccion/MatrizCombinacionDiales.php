<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

/**
 * Nivel Dos A.4: combinaciones de diales que producen una directriz propia
 * cuando ambos umbrales se cruzan a la vez — no la interacción de cada par
 * de diales entre sí (28 pares a priori sería sobre-ingeniería), sino
 * exactamente las 4 combinaciones documentadas donde la interacción entre
 * dos diales altos produce un efecto distinto de la suma de sus directrices
 * individuales.
 */
final class MatrizCombinacionDiales {

	private const UMBRAL_ALTO            = 70;
	private const UMBRAL_SATIRA_MINIMO   = 40;
	private const UMBRAL_FORMALIDAD_BAJA = 30;

	/**
	 * @return list<string>
	 */
	public static function directrices( Diales $diales ): array {
		$bloques = array();

		if ( $diales->humor > self::UMBRAL_ALTO && $diales->agudezaCritica > self::UMBRAL_ALTO ) {
			$bloques[] = 'Combinación humor + agudeza crítica altos: la agudeza ataca argumentos e incentivos, jamás a la persona. '
				. 'Ejemplo de ironía crítica válida: "la promesa de austeridad se sostiene con un contrato de asesoría de seis cifras". '
				. 'Ejemplo de sarcasmo personal PROHIBIDO: burlarse del aspecto, la voz o la vida privada del señalado.';
		}

		if ( $diales->vehemencia > self::UMBRAL_ALTO && $diales->empatia > self::UMBRAL_ALTO ) {
			$bloques[] = 'Combinación vehemencia + empatía altas: cuando ambas compiten en el mismo párrafo, primero se nombra el impacto humano '
				. 'concreto en las personas afectadas, y solo después se afirma la posición editorial sobre quién es responsable — nunca al revés.';
		}

		if ( $diales->satira > self::UMBRAL_SATIRA_MINIMO && $diales->densidadDatos > self::UMBRAL_ALTO ) {
			$bloques[] = 'Combinación sátira + densidad de datos alta: todo dato citado dentro de un pasaje satírico debe ser verificable en el '
				. 'expediente exactamente igual que en un pasaje serio — la licencia satírica cubre el tono, nunca la exactitud del dato.';
		}

		if ( $diales->formalidad < self::UMBRAL_FORMALIDAD_BAJA && $diales->vehemencia > self::UMBRAL_ALTO ) {
			$bloques[] = 'Combinación formalidad baja + vehemencia alta: el registro coloquial no exime de la estructura argumental completa '
				. '(tesis, evidencia, contraargumento reconocido, conclusión) — un tono cercano no es excusa para una afirmación sin sustento.';
		}

		return $bloques;
	}
}
