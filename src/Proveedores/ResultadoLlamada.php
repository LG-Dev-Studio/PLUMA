<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Desenlace de una llamada al proveedor (NCP-1, `ADR 0010`).
 *
 * Los caminos de excepción se registran igual que el camino feliz: si solo
 * se contaran las llamadas exitosas, el "% de llamadas eliminadas" que mide
 * el criterio de salida de NCP-1 quedaría contaminado por el "% de llamadas
 * que fallaron", y las dos cosas son opuestas (una es ahorro deliberado, la
 * otra es una avería).
 */
enum ResultadoLlamada: string {

	case Ok                 = 'ok';
	case PresupuestoAgotado = 'presupuesto_agotado';
	case SinCredenciales    = 'sin_credenciales';
	case CircuitoAbierto    = 'circuito_abierto';
	case Error              = 'error';

	/**
	 * Traduce la excepción del proveedor a su desenlace, sin inspeccionar
	 * mensajes de texto: cada caso viene de una bandera tipada de
	 * {@see ProveedorLenguajeException}.
	 */
	public static function desdeExcepcion( ProveedorLenguajeException $excepcion ): self {
		if ( $excepcion->presupuestoAgotado ) {
			return self::PresupuestoAgotado;
		}

		if ( $excepcion->sinCredenciales ) {
			return self::SinCredenciales;
		}

		if ( $excepcion->circuitoAbierto ) {
			return self::CircuitoAbierto;
		}

		return self::Error;
	}
}
