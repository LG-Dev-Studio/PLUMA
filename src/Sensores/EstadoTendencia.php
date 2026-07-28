<?php

declare(strict_types=1);

namespace Pluma\Sensores;

/**
 * Estado de una tendencia en el radar (Libro Cap. 10.2 — Sala de Tendencias,
 * decisión de producto del propietario, 2026-07-23):
 *
 * - EN_PIPELINE: la agenda automática — la Pieza creada al detectarse avanza.
 * - IGNORADA: el editor la sacó de la agenda; su Pieza se descarta y la
 *   tarjeta desaparece de la Sala (la deduplicación evita que reingrese).
 * - VIGILADA: "observar sin cubrir todavía" — su Pieza se descarta (no se
 *   gasta investigación/redacción/APIs) pero la tendencia sigue visible y
 *   destacada en la Sala; "Cubrir ahora" crea una Pieza nueva con prioridad.
 * - POSIBLE_ACTUALIZACION: el Radar detectó (Libro Cap. 3.4, huella
 *   semántica) que esta tendencia es probablemente la evolución de una
 *   historia ya cubierta ("dos golpes") — decisión del propietario,
 *   2026-07-23: NO se crea Pieza automáticamente, el editor confirma desde
 *   la Sala de Tendencias ("Cubrir como actualización") antes de gastar
 *   investigación/redacción.
 * - SOSPECHA_MANIPULACION: `Pluma\Sensores\EvaluadorLegitimidadInsumo`
 *   (Nivel Dos G.1, Etapa 8 Porción 9) detectó concentración de fuente
 *   anómala — NO se crea Pieza automáticamente. Igual que VIGILADA: la
 *   heurística es imperfecta por diseño, nunca un bloqueo silencioso
 *   permanente; el editor la revierte con "Cubrir ahora" si juzga que es
 *   un falso positivo.
 */
enum EstadoTendencia: string {

	case EnPipeline             = 'en_pipeline';
	case Ignorada               = 'ignorada';
	case Vigilada               = 'vigilada';
	case PosibleActualizacion   = 'posible_actualizacion';
	case SospechaDeManipulacion = 'sospecha_manipulacion';
}
