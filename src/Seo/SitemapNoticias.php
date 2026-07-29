<?php

declare(strict_types=1);

namespace Pluma\Seo;

use DateTimeImmutable;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Kernel\Activador;
use Pluma\Kernel\RelojInterface;
use Pluma\Pipeline\EstadoPieza;

/**
 * Sitemap de noticias (Libro Cap. 6.2, protocolo Google News): últimas 48h
 * de piezas publicadas, vía el feed nativo de WordPress (`add_feed()`) — el
 * mismo mecanismo que WP usa para sus propios feeds RSS/Atom, no una ruta
 * inventada. `Pluma\Datos\RepositorioPiezasInterface::obtenerPorEstadoEntre()`
 * ya existe y filtra exactamente por la ventana temporal que este sitemap
 * necesita.
 *
 * El "ping de indexación" que el Libro pide (`PLUMA-E3-1`) queda deliberadamente
 * SIN construir: Google retiró el endpoint `google.com/ping?sitemap=...` en
 * junio de 2023 (verificado contra el blog oficial de Search Central) — el
 * propio Cap. 6.2 pide una integración con una API que ya no existe. Su
 * reemplazo real es referenciar el sitemap en `robots.txt` (que este sitemap
 * sí hace) y, opcionalmente, la Indexing API — fuera de alcance de esta
 * pieza de deuda, que solo pedía el ping.
 */
final class SitemapNoticias {

	public const NOMBRE_FEED = 'sitemap-noticias';

	private const VENTANA_HORAS = 48;
	private const LIMITE        = 1000;

	public function __construct(
		private readonly RepositorioPiezasInterface $piezas,
		private readonly RelojInterface $reloj,
	) {
	}

	public function registrar(): void {
		add_action( 'init', array( $this, 'registrarFeed' ) );
		add_action( 'init', array( self::class, 'flushSiHaceFalta' ), 20 );
		add_filter( 'robots_txt', array( $this, 'agregarReferenciaEnRobots' ), 10, 1 );
	}

	public function registrarFeed(): void {
		add_feed( self::NOMBRE_FEED, array( $this, 'renderizar' ) );
	}

	public static function flushSiHaceFalta(): void {
		if ( ! get_option( Activador::OPCION_FLUSH_REESCRITURA_PENDIENTE ) ) {
			return;
		}

		flush_rewrite_rules();
		delete_option( Activador::OPCION_FLUSH_REESCRITURA_PENDIENTE );
	}

	public static function url(): string {
		return get_feed_link( self::NOMBRE_FEED );
	}

	public function agregarReferenciaEnRobots( string $salida ): string {
		return $salida . "\nSitemap: " . self::url() . "\n";
	}

	/**
	 * Vista pura: construye y emite el XML directamente (mismo patrón que las
	 * plantillas de feed nativas de WordPress, `wp-includes/feed-rss2.php` —
	 * ninguna llama `exit`, así que esta tampoco).
	 */
	public function renderizar(): void {
		if ( ! headers_sent() ) {
			header( 'Content-Type: application/xml; charset=UTF-8' );
		}

		$ahora = $this->reloj->ahora();
		$desde = $ahora->modify( '-' . self::VENTANA_HORAS . ' hours' );

		$piezasPublicadas = $this->piezas->obtenerPorEstadoEntre( EstadoPieza::Publicada, $desde, $ahora, self::LIMITE );

		$idioma      = $this->idiomaPublicacion();
		$nombreSitio = $this->escaparXml( (string) get_bloginfo( 'name' ) );
		$entradasXml = array();

		foreach ( $piezasPublicadas as $pieza ) {
			if ( null === $pieza->postId ) {
				continue;
			}

			$entrada = $this->entradaXml( $pieza->postId, $nombreSitio, $idioma );

			if ( null !== $entrada ) {
				$entradasXml[] = $entrada;
			}
		}

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada entrada ya se construyó con escaparXml() en entradaXml().
		echo implode( '', $entradasXml );
		echo '</urlset>' . "\n";
	}

	private function entradaXml( int $postId, string $nombreSitio, string $idioma ): ?string {
		$url    = get_permalink( $postId );
		$titulo = get_the_title( $postId );

		if ( false === $url || '' === $titulo ) {
			return null;
		}

		$fecha = get_post_datetime( $postId, 'date' );

		if ( ! $fecha instanceof DateTimeImmutable ) {
			return null;
		}

		return '<url>'
			. '<loc>' . $this->escaparXml( $url ) . '</loc>'
			. '<news:news>'
			. '<news:publication>'
			. '<news:name>' . $nombreSitio . '</news:name>'
			. '<news:language>' . $this->escaparXml( $idioma ) . '</news:language>'
			. '</news:publication>'
			. '<news:publication_date>' . $this->escaparXml( $fecha->format( DATE_ATOM ) ) . '</news:publication_date>'
			. '<news:title>' . $this->escaparXml( $titulo ) . '</news:title>'
			. '</news:news>'
			. '</url>' . "\n";
	}

	/**
	 * Google News exige el código ISO 639-1 de dos letras — `get_bloginfo(
	 * 'language' )` devuelve el locale completo de WordPress (p. ej.
	 * `es-ES`), así que se recorta al prefijo.
	 */
	private function idiomaPublicacion(): string {
		$locale  = (string) get_bloginfo( 'language' );
		$prefijo = strtolower( explode( '-', $locale )[0] ?? 'es' );

		return '' !== $prefijo ? $prefijo : 'es';
	}

	private function escaparXml( string $texto ): string {
		return htmlspecialchars( $texto, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
	}
}
