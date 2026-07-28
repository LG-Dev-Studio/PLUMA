<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

/**
 * Nivel Cuatro V.1 — ciclo de vida de un `EventoProgramado` del Calendario
 * Editorial: `PREVISTO → PREPARADO → EN_CURSO → CUBIERTO`.
 *
 * `Previsto`: el editor (o, cuando exista, un sensor de calendario) cargó el
 * evento; todavía no hay expediente ni previa. `Preparado`: el editor
 * disparó `GestorCalendarioEditorial::prepararCobertura()` — existe
 * expediente y, si el pipeline editorial la aprobó, una previa publicable.
 * `EnCurso`/`Cubierto` son transiciones manuales del editor (el sistema no
 * tiene forma de saber por sí solo que un evento del mundo real ya ocurrió).
 */
enum EstadoEventoProgramado: string {

	case Previsto  = 'previsto';
	case Preparado = 'preparado';
	case EnCurso   = 'en_curso';
	case Cubierto  = 'cubierto';
}
