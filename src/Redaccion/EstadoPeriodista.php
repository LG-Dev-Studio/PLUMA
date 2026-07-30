<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

/**
 * Estado del periodista en el banco (Libro Cap. 5.8): un periodista jubilado
 * conserva su identidad, memoria e historial (página de autor, atribución de
 * piezas pasadas), pero deja de ser candidato en la asignación (Paso 2 del
 * Algoritmo de Decisión Editorial).
 *
 * `Propuesto` (trabajo posterior a la Etapa 9 — creación automática de
 * periodistas): un periodista sembrado por `CreadorAutomaticoPeriodistas`
 * nace aquí, nunca directo en `Activo` — mismo principio de supervisión
 * humana que el modo Copiloto aplica a las Piezas (ventana de veto antes de
 * publicar). `RepositorioPeriodistas::obtenerActivos()` filtra
 * `estado = 'activo'` literal, así que un Propuesto queda excluido de
 * `AsignadorPeriodista` sin ningún cambio adicional en la asignación.
 */
enum EstadoPeriodista: string {

	case Activo    = 'activo';
	case Jubilado  = 'jubilado';
	case Propuesto = 'propuesto';
}
