<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Publicacion\EscritorCamposSeo;
use Pluma\Seo\DetectorPluginSeo;
use Pluma\Seo\MetadatosSeo;
use Pluma\Seo\TipoPluginSeo;
use WP_UnitTestCase;

/**
 * GOVERNANCE §5.3: matriz de compatibilidad — convivencia real con Yoast SEO
 * y Rank Math (Libro Cap. 6.3: "si el sitio ya usa Rank Math o Yoast, PLUMA
 * escribe en sus campos"). `DetectorPluginSeo`/`EscritorCamposSeo` ya están
 * probados en Unit con las constantes `WPSEO_VERSION`/`RANK_MATH_VERSION`
 * mockeadas — este test solo importa cuando el plugin real está instalado y
 * activo (lane de `.github/workflows/compatibilidad.yml`, job
 * `convivencia-seo`); en la suite rápida de cada commit, sin ninguno de los
 * dos plugins presente, se skippea sin alargarla.
 *
 * @covers \Pluma\Seo\DetectorPluginSeo
 * @covers \Pluma\Publicacion\EscritorCamposSeo
 */
final class CompatibilidadSeoTest extends WP_UnitTestCase {

	public function test_escribir_campos_yoast_cuando_yoast_esta_activo_de_verdad(): void {
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			self::markTestSkipped( 'Yoast SEO no está activo en este entorno — solo asevera en la lane de matriz de compatibilidad.' );
		}

		$postId = self::factory()->post->create();
		$tipo   = ( new DetectorPluginSeo() )->detectar();
		self::assertSame( TipoPluginSeo::Yoast, $tipo );

		( new EscritorCamposSeo() )->escribir(
			$postId,
			new MetadatosSeo( 'Título SEO de prueba de compatibilidad', 'Meta descripción de prueba de compatibilidad.' ),
			$tipo
		);

		self::assertSame( 'Título SEO de prueba de compatibilidad', get_post_meta( $postId, '_yoast_wpseo_title', true ) );
		self::assertSame( 'Meta descripción de prueba de compatibilidad.', get_post_meta( $postId, '_yoast_wpseo_metadesc', true ) );
	}

	public function test_escribir_campos_rank_math_cuando_rank_math_esta_activo_de_verdad(): void {
		if ( ! defined( 'RANK_MATH_VERSION' ) ) {
			self::markTestSkipped( 'Rank Math no está activo en este entorno — solo asevera en la lane de matriz de compatibilidad.' );
		}

		$postId = self::factory()->post->create();
		$tipo   = ( new DetectorPluginSeo() )->detectar();
		self::assertSame( TipoPluginSeo::RankMath, $tipo );

		( new EscritorCamposSeo() )->escribir(
			$postId,
			new MetadatosSeo( 'Título SEO de prueba de compatibilidad', 'Meta descripción de prueba de compatibilidad.' ),
			$tipo
		);

		self::assertSame( 'Título SEO de prueba de compatibilidad', get_post_meta( $postId, 'rank_math_title', true ) );
		self::assertSame( 'Meta descripción de prueba de compatibilidad.', get_post_meta( $postId, 'rank_math_description', true ) );
	}
}
