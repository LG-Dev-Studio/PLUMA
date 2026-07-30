<?php

declare(strict_types=1);

namespace Pluma\Kernel;

use Pluma\Proveedores\OrigenLlamada;

/**
 * NCP-1, `ADR 0010`: quién disparó la petición HTTP actual, para que los
 * decoradores del proveedor de lenguaje ({@see \Pluma\Proveedores\LenguajeInstrumentado},
 * {@see \Pluma\Proveedores\EmbeddingsInstrumentado}) sepan qué `origen`
 * escribir en `pluma_llamadas_modelo` sin que ninguna capa editorial tenga
 * que conocer que está siendo medida (§5.1.2).
 *
 * Servicio singleton (una instancia por petición, vía el contenedor):
 * `declarar()` se llama UNA vez, al principio del único punto de entrada
 * real que va a disparar una llamada al modelo — nunca se infiere con
 * `is_admin()`/`wp_doing_cron()`, porque el motor de PLUMA corre por REST
 * con token, no por WP-Cron, y esas heurísticas mentirían.
 *
 * El valor por defecto es {@see OrigenLlamada::Visitante}: un camino de
 * ejecución que nadie declaró se cuenta como el peor caso, nunca se
 * subestima la exposición — es exactamente lo que hace medible la violación
 * de §5.1.4 ya presente en `CompuertaComentarios` (que nunca declara nada).
 */
final class ContextoEjecucion {

	private OrigenLlamada $origen = OrigenLlamada::Visitante;

	public function declarar( OrigenLlamada $origen ): void {
		$this->origen = $origen;
	}

	public function obtener(): OrigenLlamada {
		return $this->origen;
	}
}
