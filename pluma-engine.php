<?php

/**
 * Plugin Name:       PLUMA Engine
 * Plugin URI:        https://github.com/jhonnfrank1995/PLUMA
 * Description:       Sala de redacción sintética: detecta tendencias, investiga multifuente, redacta con periodistas sintéticos parametrizables y publica de forma autónoma bajo compuertas de calidad, riesgo y originalidad.
 * Version:           0.14.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            PLUMA
 * License:           Proprietary
 * Text Domain:       pluma-engine
 * Domain Path:       /languages
 *
 * @package Pluma
 */

declare(strict_types=1);

// Archivo puente de WordPress (CLAUDE.md § Estándares PHP): fuera del
// estándar PSR-4/namespace de src/ por convención, contiene solo bootstrap.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PLUMA_ENGINE_VERSION', '0.14.0' );
define( 'PLUMA_ENGINE_DB_VERSION_OBJETIVO', '0.13.0' );
define( 'PLUMA_ENGINE_ARCHIVO', __FILE__ );
define( 'PLUMA_ENGINE_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLUMA_ENGINE_URL', plugin_dir_url( __FILE__ ) );

$pluma_autoload = PLUMA_ENGINE_DIR . 'vendor/autoload.php';

if ( ! is_readable( $pluma_autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__(
					'PLUMA Engine no pudo cargar sus dependencias (vendor/autoload.php ausente). Reinstala el plugin desde un paquete de distribución válido.',
					'pluma-engine'
				)
			);
		}
	);

	return;
}

require_once $pluma_autoload;
unset( $pluma_autoload );

register_activation_hook(
	PLUMA_ENGINE_ARCHIVO,
	static function ( bool $red_completa ): void {
		\Pluma\Kernel\Activador::activarParaRed(
			$red_completa,
			new \Pluma\Kernel\RelojSistema(),
			PLUMA_ENGINE_DB_VERSION_OBJETIVO
		);
	}
);

register_deactivation_hook(
	PLUMA_ENGINE_ARCHIVO,
	static function (): void {
		\Pluma\Kernel\Desactivador::desactivar( new \Pluma\Kernel\RelojSistema() );
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		( new \Pluma\Kernel\Nucleo() )->arrancar( PLUMA_ENGINE_ARCHIVO, PLUMA_ENGINE_DB_VERSION_OBJETIVO );
	}
);
