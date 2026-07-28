<?php

declare(strict_types=1);

namespace Pluma\Compuertas;

use DateTimeImmutable;
use RuntimeException;

/**
 * El editor intentó desactivar el modo respeto antes de que se cumpliera el
 * piso de duración mínima (Nivel Dos F.3) — no es un fallo del sistema, es la
 * salvaguarda deliberada haciendo su trabajo.
 */
final class ModoRespetoAunNoDesactivableException extends RuntimeException {

	public function __construct( public readonly DateTimeImmutable $puedeDesactivarseDesde ) {
		parent::__construct(
			'El modo respeto no puede desactivarse todavía: el piso de duración mínima vence el ' . $puedeDesactivarseDesde->format( 'Y-m-d H:i:s' ) . '.'
		);
	}
}
