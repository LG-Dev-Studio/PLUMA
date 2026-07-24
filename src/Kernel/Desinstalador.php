<?php

declare(strict_types=1);

namespace Pluma\Kernel;

use Pluma\Datos\Esquema;
use wpdb;

/**
 * Ciclo de vida — desinstalación (pl-wp-core §7, GOVERNANCE §5.4).
 *
 * Se invoca únicamente desde `uninstall.php`, y únicamente cuando el cliente
 * eligió explícitamente NO conservar datos. El comportamiento por defecto
 * (conservar) nunca llama a `purgar()`: el banco de periodistas del cliente
 * jamás se borra por accidente.
 */
final class Desinstalador {

	/**
	 * @param list<string> $opcionesAdicionales opciones propias registradas por módulos futuros
	 */
	public static function purgar( array $opcionesAdicionales = array() ): void {
		global $wpdb;
		assert( $wpdb instanceof wpdb );

		Capacidades::desinstalar();
		Esquema::eliminarTablas( $wpdb );

		$opciones = array(
			Activador::OPCION_CONSERVAR_DATOS,
			Activador::OPCION_ACTIVADO_EN,
			Activador::OPCION_TELEMETRIA_HABILITADA,
			Desactivador::OPCION_DESACTIVADO_EN,
			\Pluma\Datos\Migrador::OPCION_VERSION,
			...$opcionesAdicionales,
		);

		foreach ( $opciones as $opcion ) {
			delete_option( $opcion );
		}

		delete_transient( Desactivador::AVISO_CRON_TRANSIENT );
	}
}
