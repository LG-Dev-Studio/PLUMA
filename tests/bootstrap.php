<?php

/**
 * Bootstrap de la suite Unit (Brain\Monkey, sin WordPress cargado).
 *
 * @package Pluma
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'PLUMA_ENGINE_VERSION' ) ) {
	define( 'PLUMA_ENGINE_VERSION', '0.0.0-test' );
}

if ( ! defined( 'PLUMA_ENGINE_DIR' ) ) {
	// `Pluma\Proveedores\ProveedorNliEntrenado` (`ADR 0024`) carga el
	// artefacto real de `recursos/modelos/` — mismo directorio que en
	// producción, sin doble: el archivo es pequeño (~100 KB) y determinista.
	define( 'PLUMA_ENGINE_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	// Constante real de WordPress (wp-includes/default-constants.php): no es
	// una función, así que Brain\Monkey no puede simularla como tal.
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Doble mínimo de `WP_Error` para la suite Unit: solo lo que
	 * `Pluma\Proveedores\ProveedorGoogleTrends` y sus tests consumen.
	 */
	class WP_Error {

		public function __construct( private readonly string $code = '', private readonly string $message = '' ) {
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_code(): string {
			return $this->code;
		}
	}
}

if ( ! class_exists( 'wpdb' ) ) {
	/**
	 * Doble mínimo de `wpdb` para la suite Unit: WordPress no está cargado
	 * (Brain\Monkey solo simula funciones sueltas), pero `Pluma\Datos\Migrador`
	 * y `Pluma\Kernel\DetectorEntorno` tipan su constructor contra la clase
	 * real. Este doble expone solo la forma que ambos consumen; ninguna
	 * consulta real se ejecuta jamás a través de él.
	 */
	// phpcs:ignore PEAR.NamingConventions.ValidClassName.StartWithCapital -- debe llamarse exactamente como la clase real de WordPress.
	class wpdb {

		public string $prefix = 'wp_';

		public function db_version(): string {
			return '8.0.36';
		}

		public function get_charset_collate(): string {
			return 'DEFAULT CHARACTER SET utf8mb4';
		}

		/**
		 * @return int|false
		 */
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- firma debe calzar con la real de wpdb; el doble nunca ejecuta consultas.
		public function query( string $query ) {
			return 0;
		}
	}
}
