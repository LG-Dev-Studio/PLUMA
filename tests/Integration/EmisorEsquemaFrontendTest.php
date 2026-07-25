<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\RepositorioPeriodistas;
use Pluma\Kernel\RelojSistema;
use Pluma\Publicacion\AsignadorTaxonomiaWp;
use Pluma\Publicacion\EscritorCamposSeo;
use Pluma\Publicacion\Publicador;
use Pluma\Publicacion\SnapshotPublicacion;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Seo\ConstructorEsquemaNewsArticle;
use Pluma\Seo\EmisorEsquemaFrontend;
use Pluma\Seo\MetadatosSeo;
use Pluma\Seo\PaginaAutorPeriodista;
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
		global $wpdb;
		$this->go_to( (string) get_permalink( $postId ) );

		ob_start();
		( new EmisorEsquemaFrontend( new ConstructorEsquemaNewsArticle(), new RepositorioPeriodistas( $wpdb ) ) )->emitir();

		return (string) ob_get_clean();
	}

	private function crearPeriodista( string $nombre, EstadoPeriodista $estado = EstadoPeriodista::Activo ): int {
		global $wpdb;
		$diales = new Diales( 60, 40, 20, 60, 50, 50, 60, 50 );
		$reglas = new ReglasConducta( 'Línea de prueba.', array(), array(), array(), TratamientoLector::Tu, 'Pregunta de cierre.' );
		$matriz = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);

		return ( new RepositorioPeriodistas( $wpdb ) )->crear(
			$nombre,
			null,
			'Biografía de prueba.',
			RolPeriodista::Columnista,
			array(),
			$estado,
			$diales,
			$reglas,
			$matriz,
			( new RelojSistema() )->ahora()
		);
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

	public function test_author_url_apunta_a_la_pagina_de_autor_cuando_el_periodista_existe(): void {
		$this->crearPeriodista( 'Valentina Ruiz' );
		$postId = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$this->publicar( $postId, true );

		$html = $this->emitirSobre( $postId );

		self::assertStringContainsString( 'periodista\/valentina-ruiz', $html );
	}

	public function test_author_url_ausente_cuando_el_nombre_no_resuelve_a_un_periodista_real(): void {
		$postId = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$this->publicar( $postId, true, 'Nadie Registrado' );

		$html = $this->emitirSobre( $postId );

		self::assertStringNotContainsString( '/periodista/', $html );
	}
}
