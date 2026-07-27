<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

/**
 * Nivel Tres L.2: registro explícito de si un hecho de origen audiovisual
 * fue corroborado independientemente. Un hecho `SinCorroboracionEncontrada`
 * nunca puede alcanzar `NivelVerificacion::Verificado` — queda `Atribuido`
 * como máximo (L.2).
 *
 * Deuda conocida (PLUMA-EV-4 / bloqueador nuevo, ver `docs/deuda.md`): el
 * Sensor/Radar no clasifica todavía si una tendencia origen es audiovisual,
 * así que `InvestigadorMecanico` no puede poblar este campo con criterio
 * propio hoy — todo hecho nuevo se marca `NoAplica` hasta que esa
 * clasificación exista aguas arriba.
 */
enum EstadoCorroboracionAudiovisual: string {

	case NoAplica                      = 'no_aplica';
	case CorroboradaIndependientemente = 'corroborada_independientemente';
	case SenaladaComoManipulada        = 'senalada_como_manipulada';
	case SinCorroboracionEncontrada    = 'sin_corroboracion_encontrada';
}
