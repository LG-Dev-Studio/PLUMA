<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Compuertas\ActivadorModoRespeto;
use Pluma\Compuertas\EstadoModoRespeto;

/**
 * Contrato del repositorio del modo respeto (Nivel Dos F.1-F.3): registro
 * histórico append-only de activaciones — el estado actual es siempre la
 * fila más reciente sin `desactivado_en`.
 */
interface RepositorioModoRespetoInterface {

	public function estadoActual(): EstadoModoRespeto;

	public function activar( ActivadorModoRespeto $activadoPor, string $motivo, float $duracionMinimaHoras, DateTimeImmutable $ahora ): int;

	/**
	 * Cierra la activación en curso. Devuelve `false` si no había ninguna
	 * activación abierta — el llamador decide si eso es un error o un no-op.
	 */
	public function desactivar( DateTimeImmutable $ahora ): bool;
}
