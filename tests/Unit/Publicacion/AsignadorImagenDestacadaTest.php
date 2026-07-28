<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Publicacion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Investigacion\ClasificadorNivelFuente;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Investigacion\SelectorImagenPorAutoridad;
use Pluma\Proveedores\ExtractorImagenFuenteInterface;
use Pluma\Publicacion\AsignadorImagenDestacada;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * Imagen destacada por autoridad de fuente (Nivel Dos, decisión del
 * propietario — `ADR 0006`). Default de fábrica `ninguna` — nadie queda
 * expuesto al riesgo legal sin activarlo explícitamente.
 *
 * @covers \Pluma\Publicacion\AsignadorImagenDestacada
 */
final class AsignadorImagenDestacadaTest extends CasoDePruebaUnitario {

	private const URL_ARTICULO = 'https://medio.example.com/articulo';
	private const URL_IMAGEN   = 'https://medio.example.com/foto.jpg';

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'get_option' )->justReturn( array() ); // ClasificadorNivelFuente: sin listas configuradas.
	}

	private function expediente(): Expediente {
		return new Expediente(
			'tendencia',
			array( new HechoFuente( 'extracto', self::URL_ARTICULO, new DateTimeImmutable( '2026-07-27T12:00:00+00:00' ), NivelVerificacion::Atribuido ) )
		);
	}

	/**
	 * `SelectorImagenPorAutoridad` es una clase final sin interfaz (mismo
	 * patrón que `SelectorAngulo`/`AsignadorPeriodista` en `Redaccion`): se
	 * construye real, con el único punto de red (`ExtractorImagenFuenteInterface`)
	 * como doble.
	 */
	private function asignador( ?string $urlImagenEncontrada ): AsignadorImagenDestacada {
		$extractor = $this->createMock( ExtractorImagenFuenteInterface::class );
		$extractor->method( 'extraerImagenDestacada' )->willReturn( $urlImagenEncontrada );

		return new AsignadorImagenDestacada( new SelectorImagenPorAutoridad( new ClasificadorNivelFuente(), $extractor ) );
	}

	public function test_modo_ninguna_no_consulta_al_extractor(): void {
		Functions\when( 'get_option' )->justReturn( 'ninguna' );

		$extractor = $this->createMock( ExtractorImagenFuenteInterface::class );
		$extractor->expects( self::never() )->method( 'extraerImagenDestacada' );

		( new AsignadorImagenDestacada( new SelectorImagenPorAutoridad( new ClasificadorNivelFuente(), $extractor ) ) )->asignar( 42, $this->expediente() );
	}

	public function test_sin_imagen_elegible_no_actualiza_el_post(): void {
		Functions\when( 'get_option' )->alias(
			static fn ( string $opcion, $defecto = array() ) => match ( $opcion ) {
				AsignadorImagenDestacada::OPCION_MODO => 'enlazada',
				default => $defecto,
			}
		);

		$actualizado = false;
		Functions\when( 'wp_update_post' )->alias(
			static function () use ( &$actualizado ) {
				$actualizado = true;
			}
		);

		$this->asignador( null )->asignar( 42, $this->expediente() );

		self::assertFalse( $actualizado );
	}

	public function test_modo_enlazada_inserta_la_imagen_y_el_credito_en_el_contenido(): void {
		Functions\when( 'get_option' )->alias(
			static fn ( string $opcion, $defecto = array() ) => match ( $opcion ) {
				AsignadorImagenDestacada::OPCION_MODO => 'enlazada',
				AsignadorImagenDestacada::OPCION_CREDITO_VISIBLE => true,
				default => $defecto,
			}
		);
		Functions\when( 'get_post' )->justReturn( (object) array( 'post_content' => 'cuerpo original' ) );

		$contenidoGuardado = null;
		Functions\when( 'wp_update_post' )->alias(
			static function ( array $datos ) use ( &$contenidoGuardado ) {
				$contenidoGuardado = $datos['post_content'];
			}
		);

		$this->asignador( self::URL_IMAGEN )->asignar( 42, $this->expediente() );

		self::assertNotNull( $contenidoGuardado );
		self::assertStringContainsString( '<img src="' . self::URL_IMAGEN . '"', $contenidoGuardado );
		self::assertStringContainsString( 'medio.example.com', $contenidoGuardado );
		self::assertStringContainsString( 'cuerpo original', $contenidoGuardado );
	}

	public function test_modo_enlazada_sin_credito_visible_omite_el_bloque_de_credito(): void {
		Functions\when( 'get_option' )->alias(
			static fn ( string $opcion, $defecto = array() ) => match ( $opcion ) {
				AsignadorImagenDestacada::OPCION_MODO => 'enlazada',
				AsignadorImagenDestacada::OPCION_CREDITO_VISIBLE => false,
				default => $defecto,
			}
		);
		Functions\when( 'get_post' )->justReturn( (object) array( 'post_content' => '' ) );

		$contenidoGuardado = null;
		Functions\when( 'wp_update_post' )->alias(
			static function ( array $datos ) use ( &$contenidoGuardado ) {
				$contenidoGuardado = $datos['post_content'];
			}
		);

		$this->asignador( self::URL_IMAGEN )->asignar( 42, $this->expediente() );

		self::assertNotNull( $contenidoGuardado );
		self::assertStringNotContainsString( 'pluma-credito-imagen', $contenidoGuardado );
	}

	// El modo "descargada" (media_sideload_image()/set_post_thumbnail())
	// se prueba contra WordPress real en
	// tests/Integration/AsignadorImagenDestacadaTest.php: Brain\Monkey/
	// Patchwork no puede redefinir media_sideload_image() en esta suite
	// porque los stubs de PHPStan (vendor/php-stubs/wordpress-stubs) ya
	// la declaran como función real global antes de que el test corra.
}
