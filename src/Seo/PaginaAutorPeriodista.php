<?php

declare(strict_types=1);

namespace Pluma\Seo;

use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Kernel\Activador;
use Pluma\Redaccion\DeclaracionIdentidadSintetica;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\Periodista;
use WP_Query;

/**
 * Página de autor por periodista (Libro Cap. 5.2 + Nivel Tres N.3, Art. 50
 * UE): declara sin ambigüedad que el nombre es una identidad editorial
 * sintética. Primera página virtual del plugin — CLAUDE.md § Ley de
 * Arquitectura amplía explícitamente el frontend público para incluirla.
 *
 * Se integra con el tema activo vía `template_include` (nunca `exit`,
 * GOVERNANCE §1.5): sirve una plantilla propia mínima que el propio tema
 * envuelve con su `get_header()`/`get_footer()`. Solo periodistas activos
 * resuelven — uno jubilado no gana página nueva, pero su firma en piezas ya
 * publicadas no se toca.
 */
final class PaginaAutorPeriodista {

	public const QUERY_VAR = 'pluma_periodista_slug';

	private static ?Periodista $periodistaActual  = null;
	private static ?string $declaracionHtmlActual = null;

	public function __construct(
		private readonly RepositorioPeriodistasInterface $periodistas,
		private readonly DeclaracionIdentidadSintetica $declaracion,
	) {
	}

	public function registrar(): void {
		add_action( 'init', array( self::class, 'registrarReglaReescritura' ) );
		add_action( 'init', array( self::class, 'flushSiHaceFalta' ), 20 );
		add_filter( 'query_vars', array( $this, 'registrarQueryVar' ) );
		add_action( 'template_redirect', array( $this, 'resolverPeticion' ) );
		add_filter( 'template_include', array( $this, 'servirPlantilla' ) );
	}

	/**
	 * Estática y sin dependencias a propósito: se registra directamente en
	 * `init`, sin pasar por el contenedor DI.
	 */
	public static function registrarReglaReescritura(): void {
		add_rewrite_rule( '^periodista/([^/]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * `Pluma\Kernel\Activador::activar()` corre en `plugins_loaded`, antes de
	 * que `$wp_rewrite` exista — no puede llamar `flush_rewrite_rules()` ahí
	 * mismo. En su lugar deja una opción-bandera que este `init` (posterior a
	 * `registrarReglaReescritura()` en la misma carga, por prioridad) consume
	 * y borra una sola vez, dejando la regla persistida en `rewrite_rules`
	 * desde la primera visita real tras la activación.
	 */
	public static function flushSiHaceFalta(): void {
		if ( ! get_option( Activador::OPCION_FLUSH_REESCRITURA_PENDIENTE ) ) {
			return;
		}

		flush_rewrite_rules();
		delete_option( Activador::OPCION_FLUSH_REESCRITURA_PENDIENTE );
	}

	/**
	 * @param list<string> $vars
	 * @return list<string>
	 */
	public function registrarQueryVar( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	public static function urlDe( Periodista $periodista ): string {
		return home_url( '/periodista/' . sanitize_title( $periodista->nombre ) . '/' );
	}

	public function resolverPeticion(): void {
		$slug = get_query_var( self::QUERY_VAR );

		if ( ! is_string( $slug ) || '' === $slug ) {
			return;
		}

		self::$periodistaActual = $this->resolverPorSlug( $slug );

		if ( null === self::$periodistaActual ) {
			global $wp_query;
			assert( $wp_query instanceof WP_Query );
			$wp_query->set_404();
			status_header( 404 );

			return;
		}

		self::$declaracionHtmlActual = $this->declaracion->comoHtml( self::$periodistaActual );
	}

	public function servirPlantilla( string $plantilla ): string {
		if ( null === self::$periodistaActual ) {
			return $plantilla;
		}

		return PLUMA_ENGINE_DIR . 'src/Seo/templates/pagina-autor.php';
	}

	/**
	 * Expone al archivo de plantilla los datos ya resueltos — la plantilla
	 * es una vista escapada (CLAUDE.md §1.5 permite `echo` ahí), nunca
	 * lógica de negocio.
	 *
	 * @return array{periodista: Periodista, declaracionHtml: string}|null
	 */
	public static function datosParaPlantilla(): ?array {
		if ( null === self::$periodistaActual || null === self::$declaracionHtmlActual ) {
			return null;
		}

		return array(
			'periodista'      => self::$periodistaActual,
			'declaracionHtml' => self::$declaracionHtmlActual,
		);
	}

	private function resolverPorSlug( string $slug ): ?Periodista {
		foreach ( $this->periodistas->obtenerTodos() as $periodista ) {
			if ( EstadoPeriodista::Activo === $periodista->estado && sanitize_title( $periodista->nombre ) === $slug ) {
				return $periodista;
			}
		}

		return null;
	}
}
