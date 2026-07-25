<?php

declare(strict_types=1);

namespace Pluma\Seo;

use DateTimeImmutable;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Publicacion\Publicador;
use Pluma\Redaccion\EstadoPeriodista;

/**
 * Único punto de emisión de metadatos de PLUMA en el frontend público
 * (`wp_head`). Emite dos cosas sobre las piezas publicadas por el plugin,
 * leyendo solo post meta ya persistidas al publicar — cero consultas a
 * repositorios en render (CLAUDE.md: peso adicional en frontend ≈ 0):
 *
 * 1. El documento JSON-LD `NewsArticle`/`OpinionNewsArticle`/`AnalysisNewsArticle`
 *    (Libro Cap. 6.2). Paga la deuda `PLUMA-E3-4`: `ConstructorEsquemaNewsArticle`
 *    existía y estaba testeado, pero nunca se emitía en una página real.
 * 2. El marcado de transparencia de IA legible por máquina (Art. 50 del
 *    Reglamento (UE) 2024/1689, Nivel Tres N.3) sobre las piezas generadas y
 *    publicadas sin aprobación humana activa: el valor de vocabulario
 *    controlado IPTC `trainedAlgorithmicMedia` ("Created using Generative AI").
 *    Art. 50 no manda un formato único; se emite el más reconocido (IPTC
 *    digitalSourceType) en un `<meta>` dedicado. Piso de fábrica no
 *    desactivable — no hay opción de panel para apagarlo.
 */
final class EmisorEsquemaFrontend {

	private const IPTC_TRAINED_ALGORITHMIC_MEDIA = 'http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia';

	public function __construct(
		private readonly ConstructorEsquemaNewsArticle $constructor,
		private readonly RepositorioPeriodistasInterface $periodistas,
	) {
	}

	public function registrar(): void {
		add_action( 'wp_head', array( $this, 'emitir' ) );
	}

	public function emitir(): void {
		if ( ! is_singular() ) {
			return;
		}

		$postId = get_queried_object_id();

		if ( 0 === $postId || '' === (string) get_post_meta( $postId, Publicador::META_PIEZA_ID, true ) ) {
			return;
		}

		$this->emitirJsonLd( $postId );

		if ( '' !== (string) get_post_meta( $postId, Publicador::META_GENERADO_IA, true ) ) {
			printf(
				'<meta name="iptc.digitalSourceType" content="%s" />' . "\n",
				esc_url( self::IPTC_TRAINED_ALGORITHMIC_MEDIA )
			);
		}
	}

	private function emitirJsonLd( int $postId ): void {
		$nombreAutor = $this->autorNombre( $postId );

		$documento = $this->constructor->construir(
			$this->tipoEsquema( $postId ),
			(string) get_the_title( $postId ),
			$this->urlsImagenes( $postId ),
			$this->fecha( $postId, 'date' ),
			$this->fecha( $postId, 'modified' ),
			$nombreAutor,
			$this->urlPerfilAutor( $nombreAutor ),
			(string) get_bloginfo( 'name' ),
			$this->urlLogoSitio(),
			(string) get_permalink( $postId )
		);

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode con JSON_HEX_TAG|JSON_HEX_AMP escapa < > & a \uXXXX: imposible romper el <script>. No hay entrada de usuario sin escapar aquí.
			(string) wp_json_encode( $documento, JSON_HEX_TAG | JSON_HEX_AMP )
		);
	}

	private function tipoEsquema( int $postId ): TipoEsquemaArticulo {
		return TipoEsquemaArticulo::tryFrom( (string) get_post_meta( $postId, Publicador::META_ESQUEMA_TIPO, true ) )
			?? TipoEsquemaArticulo::NewsArticle;
	}

	private function autorNombre( int $postId ): string {
		$nombre = (string) get_post_meta( $postId, Publicador::META_AUTOR_NOMBRE, true );

		// Sin periodista (redactor mecánico de respaldo): el autor recae en el
		// sitio como Organización — la página de autor con Persona llega en la
		// porción 4b.
		return '' !== $nombre ? $nombre : (string) get_bloginfo( 'name' );
	}

	/**
	 * URL de la página de autor (porción 4b) cuando el nombre resuelve a un
	 * periodista real y activo — un jubilado o el redactor mecánico de
	 * respaldo no tienen página, así que el JSON-LD omite `author.url`.
	 */
	private function urlPerfilAutor( string $nombreAutor ): ?string {
		foreach ( $this->periodistas->obtenerTodos() as $periodista ) {
			if ( EstadoPeriodista::Activo === $periodista->estado && $periodista->nombre === $nombreAutor ) {
				return PaginaAutorPeriodista::urlDe( $periodista );
			}
		}

		return null;
	}

	/**
	 * @return list<string>
	 */
	private function urlsImagenes( int $postId ): array {
		$url = get_the_post_thumbnail_url( $postId, 'full' );

		return is_string( $url ) && '' !== $url ? array( $url ) : array();
	}

	/**
	 * @param 'date'|'modified' $campo
	 */
	private function fecha( int $postId, string $campo ): DateTimeImmutable {
		$fecha = get_post_datetime( $postId, $campo );

		return $fecha instanceof DateTimeImmutable ? $fecha : new DateTimeImmutable();
	}

	private function urlLogoSitio(): ?string {
		$url = get_site_icon_url();

		return is_string( $url ) && '' !== $url ? $url : null;
	}
}
