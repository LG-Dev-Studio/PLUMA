<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

/**
 * Nivel Tres L.1: procedencia de una declaración textual atribuida a una
 * persona u organización identificable. Eje independiente del nivel de
 * confianza del medio que la reporta (L.1 punto 3): un medio de nivel A
 * puede reportar de buena fe una cita de procedencia no verificada.
 */
enum EstadoProcedenciaDeclaracion: string {

	/**
	 * El hecho no es una declaración textual atribuida a alguien
	 * identificable — este eje no aplica.
	 */
	case NoAplica = 'no_aplica';

	/**
	 * Proviene de un canal verificado u oficial (cuenta verificada de la
	 * propia persona/organización, comunicado en dominio propio, o un medio
	 * de nivel A que confirma haberla obtenido directamente de la fuente).
	 */
	case VerificadaCanalOficial = 'verificada_canal_oficial';

	/**
	 * Sin verificación de canal: activa la Compuerta de Riesgo con la misma
	 * severidad que una afirmación sin doble fuente (L.1 punto 2).
	 */
	case NoVerificada = 'no_verificada';
}
