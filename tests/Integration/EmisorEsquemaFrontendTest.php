<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Publicacion\AsignadorTaxonomiaWp;
use Pluma\Publicacion\EscritorCamposSeo;
use Pluma\Publicacion\Publicador;
use Pluma\Publicacion\SnapshotPublicacion;
use Pluma\Seo\ConstructorEsquemaNewsArticle;
use Pluma\Seo\EmisorEsquemaFrontend;
use Pluma\Seo\MetadatosSeo;
use Pluma\Seo\TipoEsquemaArticulo;
use Pluma\Seo\TipoPluginSeo;
use Pluma\Taxonomia\ResultadoTaxonomia;
use WP_UnitTestCase;

/**
 * Art. 50 UE (Nivel Tres N.3) + deuda `PLUMA-E3-4`: `Publicador` persiste la
 * instantánea de emisión como post meta, y `EmisorEsquemaFrontend` emite en
 * `wp_head` el JSON-LD (que nunca se emitía) y el marcado IPTC de IA sobre las
 * piezas generadas por el sistema. Contra WordPress real (wp-env).
 *
 * @covers \Pluma\Publicacion\Publicador
 * @covers \Pluma\Seo\EmisorEsquemaFrontend
 */
final class EmisorEsquemaFrontendTest extends WP_UnitTestCase {

	private function publicar( int $postId, bool $generadoIa, string $autor = 'Valentina Ruiz' ): void {
		( new Publicador( new EscritorCamposSeo(), new AsignadorTaxonomiaWp() ) )->publicar(
			$postId,
			new MetadatosSeo( 'Título SEO', 'Meta descripción' ),
			TipoPluginSeo::Ninguno,
			new ResultadoTaxonomia( null, array() ),
			new SnapshotPublicacion( 55, $generadoIa, 'autonomo', TipoEsquemaArticulo::OpinionNewsArticle->value, $autor )
		);
	}

	private function emitirSobre( int $postId ): string {
		$this->go_to( (string) get_permalink( $postId ) );

		ob_start();
		( new EmisorEsquemaFrontend( new ConstructorEsquemaNewsArticle() ) )->emitir();

		return (string) ob_get_clean();
	}

	public function test_publicar_persiste_las_metas_de_emision(): void {
		$postId = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->publicar( $postId, true );

		self::assertSame( '55', (string) get_post_meta( $postId, Publicador::META_PIEZA_ID, true ) );
		self::assertSame( '1', (string) get_post_meta( $postId, Publicador::META_GENERADO_IA, true ) );
		self::assertSame( 'autonomo', get_post_meta( $postId, Publicador::META_MODO, true ) );
		self::assertSame( 'OpinionNewsArticle', get_post_meta( $postId, Publicador::META_ESQUEMA_TIPO, true ) );
		self::assertSame( 'Valentina Ruiz', get_post_meta( $postId, Publicador::META_AUTOR_NOMBRE, true ) );
		self::assertSame( 'publish', get_post_status( $postId ) );
	}

	public function test_pieza_generada_por_ia_emite_jsonld_y_marcado_iptc(): void {
		$postId = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_title'  => 'Una pieza sintética',
			)
		);
		$this->publicar( $postId, true );

		$html = $this->emitirSobre( $postId );

		self::assertStringContainsString( 'application/ld+json', $html );
		self::assertStringContainsString( 'OpinionNewsArticle', $html );
		self::assertStringContainsString( 'Valentina Ruiz', $html );
		self::assertStringContainsString( 'trainedAlgorithmicMedia', $html );
		self::assertStringContainsString( 'iptc.digitalSourceType', $html );
	}

	public function test_pieza_no_generada_por_ia_emite_jsonld_sin_marcado_iptc(): void {
		$postId = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$this->publicar( $postId, false );

		$html = $this->emitirSobre( $postId );

		self::assertStringContainsString( 'application/ld+json', $html );
		self::assertStringNotContainsString( 'trainedAlgorithmicMedia', $html );
	}

	public function test_post_ajeno_sin_metas_de_pluma_no_emite_nada(): void {
		$postId = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$html = $this->emitirSobre( $postId );

		self::assertSame( '', trim( $html ) );
	}
}
