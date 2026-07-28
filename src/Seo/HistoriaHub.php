<?php

declare(strict_types=1);

namespace Pluma\Seo;

use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Kernel\Activador;
use Pluma\Pipeline\BloqueConocimientoHistoria;
use Pluma\Pipeline\GestorHistorias;
use Pluma\Pipeline\Historia;
use Pluma\Pipeline\Pieza;
use Pluma\Redaccion\Periodista;
use WP_Query;

/**
 * Nivel Cuatro U.2 — el hub de historia como superficie pública: "cada
 * Historia con 2+ piezas genera automáticamente una página hub: cronología
 * navegable de la cobertura, el bloque 'lo que sabemos/lo que no', el
 * periodista titular". Segunda página virtual del plugin, mismo mecanismo
 * que `PaginaAutorPeriodista` (rewrite rule → query var →
 * `template_redirect` resuelve → `template_include` sirve plantilla propia,
 * nunca `exit`, GOVERNANCE §1.5).
 *
 * La URL usa el id numérico de la Historia, no un slug del título: el
 * título de una saga no es estable ni único, y generar/garantizar slugs
 * únicos no es algo que U.1/U.2 pidan — cero invención de una capa de
 * unicidad que el texto fuente no exige.
 */
final class HistoriaHub {

	public const QUERY_VAR = 'pluma_historia_id';

	private const UMBRAL_PIEZAS_HUB = 2;

	private static ?Historia $historiaActual                             = null;
	private static ?Periodista $periodistaTitularActual                  = null;
	private static ?BloqueConocimientoHistoria $bloqueConocimientoActual = null;
	/** @var list<array{titulo: string, url: string, fecha: string, tipo: string}>|null */
	private static ?array $cronologiaActual = null;

	public function __construct(
		private readonly GestorHistorias $gestorHistorias,
		private readonly RepositorioPeriodistasInterface $periodistas,
	) {
	}

	public function registrar(): void {
		add_action( 'init', array( self::class, 'registrarReglaReescritura' ) );
		add_action( 'init', array( self::class, 'flushSiHaceFalta' ), 20 );
		add_filter( 'query_vars', array( $this, 'registrarQueryVar' ) );
		add_action( 'template_redirect', array( $this, 'resolverPeticion' ) );
		add_filter( 'template_include', array( $this, 'servirPlantilla' ) );
		add_action( 'wp_head', array( $this, 'emitirEsquema' ) );
	}

	/**
	 * Estática y sin dependencias a propósito: se registra directamente en
	 * `init`, sin pasar por el contenedor DI (mismo patrón que
	 * `PaginaAutorPeriodista`).
	 */
	public static function registrarReglaReescritura(): void {
		add_rewrite_rule( '^historia/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

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

	public static function urlDe( int $historiaId ): string {
		return home_url( '/historia/' . $historiaId . '/' );
	}

	public function resolverPeticion(): void {
		// Se resetea incondicionalmente en cada petición: en producción un
		// proceso PHP nuevo por request lo garantiza solo, pero estas
		// propiedades estáticas sobreviven entre tests del mismo proceso
		// (WP_UnitTestCase) — sin este reset, un 404 después de una
		// resolución exitosa serviría los datos de la petición anterior.
		self::$historiaActual           = null;
		self::$periodistaTitularActual  = null;
		self::$bloqueConocimientoActual = null;
		self::$cronologiaActual         = null;

		$idCrudo = get_query_var( self::QUERY_VAR );

		if ( ! is_string( $idCrudo ) || '' === $idCrudo || ! ctype_digit( $idCrudo ) ) {
			return;
		}

		$historia = $this->gestorHistorias->obtener( (int) $idCrudo );

		// U.2: solo Historias con 2+ Piezas generan hub — con menos, la
		// página no existe todavía (404), no una página vacía a medias.
		if ( null === $historia || count( $historia->piezaIds ) < self::UMBRAL_PIEZAS_HUB ) {
			global $wp_query;
			assert( $wp_query instanceof WP_Query );
			$wp_query->set_404();
			status_header( 404 );

			return;
		}

		self::$historiaActual = $historia;

		$piezas = $this->gestorHistorias->piezasDe( $historia->id );

		self::$bloqueConocimientoActual = $this->gestorHistorias->bloqueConocimiento( $piezas );
		self::$cronologiaActual         = $this->cronologiaDe( $piezas );

		self::$periodistaTitularActual = null !== $historia->periodistaTitularId
			? $this->periodistas->obtenerPorId( $historia->periodistaTitularId )
			: null;
	}

	public function servirPlantilla( string $plantilla ): string {
		if ( null === self::$historiaActual ) {
			return $plantilla;
		}

		return PLUMA_ENGINE_DIR . 'src/Seo/templates/historia-hub.php';
	}

	/**
	 * Nivel Cuatro U.2: schema.org `CollectionPage` — el hub agrupa piezas
	 * NewsArticle ya publicadas independientes, no entradas de un live-blog
	 * real en el sentido estricto de schema.org (`LiveBlogPosting` exige
	 * `liveBlogUpdate`, semántica de actualizaciones dentro de UNA sola
	 * publicación, no una lista de artículos separados) — usar
	 * `LiveBlogPosting` aquí sería un mal tipado, no lo que U.2 realmente
	 * describe. `CollectionPage` con `hasPart` es el tipo correcto para
	 * "página que agrupa varias piezas de contenido publicadas por
	 * separado", en cualquier fase de la Historia.
	 */
	public function emitirEsquema(): void {
		if ( null === self::$historiaActual || null === self::$cronologiaActual ) {
			return;
		}

		$documento = array(
			'@context' => 'https://schema.org',
			'@type'    => 'CollectionPage',
			'name'     => self::$historiaActual->titulo,
			'url'      => self::urlDe( self::$historiaActual->id ),
			'hasPart'  => array_map(
				static fn ( array $entrada ): array => array(
					'@type'         => 'NewsArticle',
					'headline'      => $entrada['titulo'],
					'url'           => $entrada['url'],
					'datePublished' => $entrada['fecha'],
				),
				self::$cronologiaActual
			),
		);

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode con JSON_HEX_TAG|JSON_HEX_AMP escapa < > & a \uXXXX: imposible romper el <script>.
			(string) wp_json_encode( $documento, JSON_HEX_TAG | JSON_HEX_AMP )
		);
	}

	/**
	 * Expone a la plantilla los datos ya resueltos (CLAUDE.md §1.5: vista
	 * escapada, nunca lógica de negocio).
	 *
	 * @return array{historia: Historia, periodistaTitular: ?Periodista, bloqueConocimiento: BloqueConocimientoHistoria, cronologia: list<array{titulo: string, url: string, fecha: string, tipo: string}>}|null
	 */
	public static function datosParaPlantilla(): ?array {
		if ( null === self::$historiaActual || null === self::$bloqueConocimientoActual || null === self::$cronologiaActual ) {
			return null;
		}

		return array(
			'historia'           => self::$historiaActual,
			'periodistaTitular'  => self::$periodistaTitularActual,
			'bloqueConocimiento' => self::$bloqueConocimientoActual,
			'cronologia'         => self::$cronologiaActual,
		);
	}

	/**
	 * @param list<Pieza> $piezas
	 * @return list<array{titulo: string, url: string, fecha: string, tipo: string}>
	 */
	private function cronologiaDe( array $piezas ): array {
		$cronologia = array();

		foreach ( $piezas as $pieza ) {
			// Solo Piezas ya publicadas tienen un post real que enlazar — una
			// actualización todavía en redacción no aparece en el hub hasta
			// que se publica de verdad.
			if ( null === $pieza->postId ) {
				continue;
			}

			$titulo = get_the_title( $pieza->postId );
			$url    = get_permalink( $pieza->postId );

			if ( '' === $titulo || false === $url ) {
				continue;
			}

			$cronologia[] = array(
				'titulo' => $titulo,
				'url'    => $url,
				'fecha'  => $pieza->actualizadaEn->format( DATE_ATOM ),
				'tipo'   => $pieza->tipo->value,
			);
		}

		return $cronologia;
	}
}
