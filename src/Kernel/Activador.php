<?php

declare(strict_types=1);

namespace Pluma\Kernel;

use Pluma\Datos\Esquema;
use Pluma\Datos\Migrador;
use wpdb;

/**
 * Ciclo de vida — activación (pl-wp-core §7).
 *
 * Instala capacidades, sube el esquema a la versión objetivo y dispara la
 * opción de conservación de datos con su valor por defecto (conservar).
 * Soporta activación de red completa en multisitio.
 */
final class Activador {

	public const OPCION_CONSERVAR_DATOS       = 'pluma_conservar_datos_al_desinstalar';
	public const OPCION_ACTIVADO_EN           = 'pluma_activado_en';
	public const OPCION_MOTOR_TOKEN           = 'pluma_motor_token';
	public const OPCION_TELEMETRIA_HABILITADA = 'pluma_telemetria_habilitada';

	public static function activarParaRed( bool $redCompleta, RelojInterface $reloj, string $versionEsquemaObjetivo ): void {
		if ( is_multisite() && $redCompleta ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $idSitio ) {
				switch_to_blog( (int) $idSitio );
				self::activar( $reloj, $versionEsquemaObjetivo );
				restore_current_blog();
			}

			return;
		}

		self::activar( $reloj, $versionEsquemaObjetivo );
	}

	public static function activar( RelojInterface $reloj, string $versionEsquemaObjetivo ): void {
		global $wpdb;
		assert( $wpdb instanceof wpdb );

		Capacidades::instalar();
		( new Migrador( $wpdb ) )->migrar( $versionEsquemaObjetivo, Esquema::sentenciasCreateTable( $wpdb ) );

		// `add_option` no sobrescribe un valor ya existente: reactivar el
		// plugin nunca resetea la elección de conservación del cliente ni
		// rota el token del cron sin que el usuario lo pida explícitamente.
		add_option( self::OPCION_CONSERVAR_DATOS, true, '', false );
		add_option( self::OPCION_MOTOR_TOKEN, wp_generate_password( 43, false, false ), '', false );
		// GOVERNANCE §5.5: opt-in explícito — nunca habilitada por defecto.
		add_option( self::OPCION_TELEMETRIA_HABILITADA, false, '', false );
		update_option( self::OPCION_ACTIVADO_EN, $reloj->ahora()->format( DATE_ATOM ), false );
	}

	/**
	 * Auto-actualización de esquema en `plugins_loaded` (no solo en
	 * activación manual): una actualización normal de WordPress reemplaza
	 * los archivos del plugin SIN disparar `register_activation_hook` — sin
	 * este chequeo, una instalación real de cliente que reciba una
	 * actualización con una migración de esquema nueva quedaría corriendo
	 * código nuevo contra tablas viejas (columnas/tablas faltantes) hasta
	 * que alguien desactive y reactive el plugin a mano. `dbDelta` es
	 * idempotente, así que reejecutar `activar()` cuando la versión
	 * instalada ya coincide sería inofensivo pero innecesario en cada carga
	 * de página — por eso el chequeo de versión evita el trabajo cuando no
	 * hace falta.
	 */
	public static function actualizarEsquemaSiHaceFalta( RelojInterface $reloj, string $versionEsquemaObjetivo ): void {
		global $wpdb;
		assert( $wpdb instanceof wpdb );

		if ( ( new Migrador( $wpdb ) )->versionInstalada() !== $versionEsquemaObjetivo ) {
			self::activar( $reloj, $versionEsquemaObjetivo );
		}
	}
}
