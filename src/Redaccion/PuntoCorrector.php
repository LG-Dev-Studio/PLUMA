<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

/**
 * Los 6 puntos de la lista de verificación del Corrector Interno
 * (Libro Cap. 5.6, pl-periodistas §Contratos innegociables 5).
 */
enum PuntoCorrector: string {

	case Hechos                   = 'hechos';
	case ProporcionInterpretativa = 'proporcion_interpretativa';
	case SolapamientoNGrama       = 'solapamiento_ngrama';
	case Voz                      = 'voz';
	case TitularHonesto           = 'titular_honesto';
	case MatrizYLineasRojas       = 'matriz_y_lineas_rojas';

	/**
	 * Nivel Dos A.6: el orden de {@see revisar()} es orden de VERIFICACIÓN
	 * (el orden en que se listan en Cap. 5.6), no de REPARACIÓN. Cuando el
	 * Corrector Interno reprueba varios puntos a la vez, el redactor debe
	 * corregirlos en esta secuencia — los hechos y las líneas rojas primero
	 * (reescriben el fondo de la pieza), la voz y el titular al final
	 * (ajustes de forma que no vale la pena tocar si el fondo aún va a
	 * cambiar).
	 *
	 * @return list<self>
	 */
	public static function ordenDeReparacion(): array {
		return array(
			self::Hechos,
			self::MatrizYLineasRojas,
			self::SolapamientoNGrama,
			self::ProporcionInterpretativa,
			self::Voz,
			self::TitularHonesto,
		);
	}
}
