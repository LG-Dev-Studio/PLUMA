<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use DateTimeImmutable;
use Pluma\Investigacion\ClasificadorNivelFuente;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Investigacion\SelectorImagenPorAutoridad;
use Pluma\Proveedores\ExtractorImagenFuente;
use Pluma\Publicacion\AsignadorImagenDestacada;
use WP_Error;
use WP_UnitTestCase;

/**
 * Imagen destacada por autoridad de fuente (Nivel Dos, decisión del
 * propietario — `ADR 0006`) de punta a punta contra WordPress real —
 * intercepta `pre_http_request` (patrón nativo de WP para tests) en vez de
 * tocar la red (GOVERNANCE §4.4).
 *
 * Las URLs de fuente usan una IP literal (`8.8.8.8`), no un nombre de
 * dominio: `Pluma\Proveedores\ValidadorUrl::esSegura()` (anti-SSRF) solo
 * hace `gethostbyname()` — una resolución DNS real — cuando el host NO es
 * ya una IP. Un dominio de prueba (`*.example.com`) forzaría esa resolución
 * real contra la red, justo lo que GOVERNANCE §4.4 prohíbe en esta suite.
 * `8.8.8.8` es pública y no pertenece a ningún rango privado/reservado, así
 * que pasa el filtro anti-SSRF sin resolución ni conexión real —
 * `pre_http_request` sigue interceptando cualquier intento de tráfico.
 *
 * @covers \Pluma\Publicacion\AsignadorImagenDestacada
 * @covers \Pluma\Investigacion\SelectorImagenPorAutoridad
 * @covers \Pluma\Proveedores\ExtractorImagenFuente
 */
final class AsignadorImagenDestacadaTest extends WP_UnitTestCase {

	private const HOST = '8.8.8.8';

	public function test_modo_enlazada_inserta_la_imagen_de_la_fuente_de_mayor_autoridad(): void {
		update_option( AsignadorImagenDestacada::OPCION_MODO, 'enlazada' );
		update_option( AsignadorImagenDestacada::OPCION_CREDITO_VISIBLE, true );

		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) {
				if ( str_contains( (string) $url, self::HOST . '/articulo' ) ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => '<meta property="og:image" content="https://' . self::HOST . '/foto.jpg" />',
					);
				}

				return $preempt;
			},
			10,
			3
		);

		$postId = wp_insert_post(
			array(
				'post_title'   => 'Pieza de prueba',
				'post_content' => 'cuerpo',
				'post_status'  => 'draft',
			),
			true
		);
		self::assertIsInt( $postId );

		$expediente = new Expediente(
			'tendencia',
			array(
				new HechoFuente( 'extracto', 'https://' . self::HOST . '/articulo', new DateTimeImmutable( '2026-07-27T12:00:00+00:00' ), NivelVerificacion::Atribuido ),
			)
		);

		$asignador = new AsignadorImagenDestacada(
			new SelectorImagenPorAutoridad( new ClasificadorNivelFuente(), new ExtractorImagenFuente() )
		);
		$asignador->asignar( $postId, $expediente );

		$post = get_post( $postId );
		self::assertNotNull( $post );
		self::assertStringContainsString( 'https://' . self::HOST . '/foto.jpg', $post->post_content );
		self::assertStringContainsString( self::HOST, $post->post_content );
		self::assertStringContainsString( 'cuerpo', $post->post_content );
	}

	public function test_modo_ninguna_no_toca_el_contenido(): void {
		update_option( AsignadorImagenDestacada::OPCION_MODO, 'ninguna' );

		$postId = wp_insert_post(
			array(
				'post_title'   => 'Pieza sin imagen',
				'post_content' => 'cuerpo intacto',
				'post_status'  => 'draft',
			),
			true
		);
		self::assertIsInt( $postId );

		$expediente = new Expediente(
			'tendencia',
			array(
				new HechoFuente( 'extracto', 'https://' . self::HOST . '/articulo', new DateTimeImmutable( '2026-07-27T12:00:00+00:00' ), NivelVerificacion::Atribuido ),
			)
		);

		$asignador = new AsignadorImagenDestacada(
			new SelectorImagenPorAutoridad( new ClasificadorNivelFuente(), new ExtractorImagenFuente() )
		);
		$asignador->asignar( $postId, $expediente );

		$post = get_post( $postId );
		self::assertNotNull( $post );
		self::assertSame( 'cuerpo intacto', $post->post_content );
	}

	/**
	 * Modo "descargada" contra WordPress real: se intercepta también la
	 * petición de descarga de la imagen en sí (no solo la página del
	 * artículo) y se le responde con un `WP_Error`, simulando una descarga
	 * que falla — sin tocar la red y sin depender de que
	 * `media_sideload_image()` reciba bytes de imagen reales. Verifica que
	 * `is_wp_error()` se respeta y que no se fija miniatura ni se toca el
	 * contenido cuando la descarga no se completa.
	 */
	public function test_modo_descargada_no_falla_si_la_descarga_de_la_imagen_no_se_completa(): void {
		update_option( AsignadorImagenDestacada::OPCION_MODO, 'descargada' );

		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) {
				$url = (string) $url;

				if ( str_contains( $url, self::HOST . '/articulo' ) ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => '<meta property="og:image" content="https://' . self::HOST . '/foto-inexistente.jpg" />',
					);
				}

				if ( str_contains( $url, self::HOST . '/foto-inexistente.jpg' ) ) {
					return new WP_Error( 'http_request_failed', 'Simulado: descarga fallida en la suite de pruebas.' );
				}

				return $preempt;
			},
			10,
			3
		);

		$postId = wp_insert_post(
			array(
				'post_title'   => 'Pieza descargada',
				'post_content' => 'cuerpo',
				'post_status'  => 'draft',
			),
			true
		);
		self::assertIsInt( $postId );

		$expediente = new Expediente(
			'tendencia',
			array(
				new HechoFuente( 'extracto', 'https://' . self::HOST . '/articulo', new DateTimeImmutable( '2026-07-27T12:00:00+00:00' ), NivelVerificacion::Atribuido ),
			)
		);

		$asignador = new AsignadorImagenDestacada(
			new SelectorImagenPorAutoridad( new ClasificadorNivelFuente(), new ExtractorImagenFuente() )
		);

		// No debe lanzar ni producir un fatal aunque la descarga falle.
		$asignador->asignar( $postId, $expediente );

		self::assertFalse( has_post_thumbnail( $postId ) );

		$post = get_post( $postId );
		self::assertNotNull( $post );
		self::assertSame( 'cuerpo', $post->post_content );
	}
}
