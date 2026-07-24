<?php

declare(strict_types=1);

namespace Pluma\Datos;

use RuntimeException;

/**
 * GOVERNANCE §5.1 / sub-agente ESQUEMA (AGENTS.md): una reversa de esquema
 * nunca se infiere — o está registrada explícitamente en
 * {@see Esquema::sentenciasReversaDesde()}, o la reversa no procede.
 */
final class ReversaNoDisponibleException extends RuntimeException {

	public function __construct( string $versionOrigen, string $versionDestino ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
		parent::__construct(
			"No hay reversa de esquema registrada de {$versionOrigen} a {$versionDestino}."
		);
	}
}
