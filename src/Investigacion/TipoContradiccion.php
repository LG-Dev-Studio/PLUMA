<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

/**
 * Nivel Dos B.2: tipo de contradicción entre dos hechos del expediente —
 * cada tipo tiene una salida de resolución distinta (Libro Cap. 4.3
 * extendido).
 */
enum TipoContradiccion: string {

	/**
	 * Dos números distintos para el mismo hecho ("300 asistentes" vs
	 * "3.000 asistentes"): ambas cifras entran al expediente, nunca se
	 * promedia ni se elige una.
	 */
	case Cifra = 'cifra';

	/**
	 * Mismo hecho, distinto responsable señalado: ambas versiones entran
	 * atribuidas a su fuente respectiva, nunca fusionadas.
	 */
	case Atribucion = 'atribucion';

	/**
	 * Una fuente afirma que algo pasó, otra que no: estado `Disputado`
	 * obligatorio — nunca se resuelve eligiendo la fuente "más confiable".
	 */
	case Ocurrencia = 'ocurrencia';
}
