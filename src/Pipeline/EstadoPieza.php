<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

/**
 * Grafo de estados de la Pieza (pl-pipeline, `references/estados.md`).
 *
 * DETECTADA → EN_INVESTIGACION → INVESTIGADA → EN_REDACCION → REDACTADA →
 * OPTIMIZADA → EN_REVISION → APROBADA → PROGRAMADA → PUBLICADA, con salidas
 * laterales RETENIDA/DESCARTADA/FALLIDA desde cualquier estado no terminal.
 *
 * Nivel Dos C.3: `SIN_PERIODISTA_IDONEO` es una salida lateral adicional
 * desde `EN_REDACCION` (el paso de asignación de periodista) — ningún
 * periodista del banco superó el umbral de dominio mínimo para el vertical
 * detectado. Reanudable a `EN_REDACCION` tras ajuste del banco por el
 * editor, o descartable si la tendencia caduca mientras espera.
 */
enum EstadoPieza: string {

	case Detectada           = 'detectada';
	case EnInvestigacion     = 'en_investigacion';
	case Investigada         = 'investigada';
	case EnRedaccion         = 'en_redaccion';
	case Redactada           = 'redactada';
	case Optimizada          = 'optimizada';
	case EnRevision          = 'en_revision';
	case Aprobada            = 'aprobada';
	case Programada          = 'programada';
	case Publicada           = 'publicada';
	case Retenida            = 'retenida';
	case Descartada          = 'descartada';
	case Fallida             = 'fallida';
	case SinPeriodistaIdoneo = 'sin_periodista_idoneo';

	public function esTerminal(): bool {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- falso positivo: $this en un método de enum (PHP 8.1) es válido; el sniff aún no reconoce enums.
		return match ( $this ) {
			self::Publicada, self::Descartada => true,
			default => false,
		};
	}
}
