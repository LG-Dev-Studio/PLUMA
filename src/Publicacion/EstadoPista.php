<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

/**
 * Nivel Cuatro X.3 — el buzón de pistas: "NUNCA directo al expediente...
 * sirve solo como disparador de investigación dirigida... por los canales
 * normales". El editor revisa cada pista; si decide investigarla, lo hace
 * por el camino ya existente (p. ej. como fuente aportada al preparar
 * cobertura de un evento, Nivel Cuatro V.2) — nunca automático.
 */
enum EstadoPista: string {

	case Pendiente  = 'pendiente';
	case Revisada   = 'revisada';
	case Descartada = 'descartada';
}
