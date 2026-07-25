<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

/**
 * Registro auditable exigido por la excepción del Art. 50 del Reglamento
 * (UE) 2024/1689 (Nivel Tres N.3 (c)): cómo llegó a publicarse una pieza en
 * modo Copiloto. Solo se registra en la transición programada→publicada.
 */
enum TipoAprobacion: string {

	case HumanaActiva            = 'humana_activa';
	case AutomaticaPorExpiracion = 'automatica_por_expiracion';
}
