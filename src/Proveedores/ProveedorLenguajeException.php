<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

use RuntimeException;

final class ProveedorLenguajeException extends RuntimeException {

	/**
	 * `circuitoAbierto` (NCP-1, `ADR 0010`) es solo OBSERVABILIDAD: distingue
	 * "no se intentó porque el proveedor venía fallando" de un fallo técnico
	 * real, para que la bitácora de llamadas no mezcle ambos. Deliberadamente
	 * NO se consulta en ninguna decisión de negocio — `RedactorConFallbackMecanico`
	 * sigue tratando el circuito abierto como fallo técnico que se propaga
	 * (la Pieza se marca FALLIDA), exactamente igual que antes.
	 */
	public function __construct(
		string $mensaje,
		public readonly bool $presupuestoAgotado = false,
		public readonly bool $sinCredenciales = false,
		public readonly bool $circuitoAbierto = false
	) {
		parent::__construct( $mensaje );
	}
}
