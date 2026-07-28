<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Compuertas\CategoriaComentario;
use Pluma\Compuertas\ClasificadorComentarios;
use Pluma\Compuertas\CompuertaComentarios;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Publicacion\Publicador;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;
use Pluma\Tests\Unit\Dobles\RelojFijo;
use WP_UnitTestCase;

/**
 * Nivel Cuatro X.1 — compuertas de comentarios contra los hooks reales de
 * WordPress (`pre_comment_approved`/`comment_post`/`comment_class`,
 * verificados contra developer.wordpress.org antes de usarlos): un
 * `ClasificadorComentarios` con un proveedor de lenguaje falso (nunca se
 * llama a un proveedor real en tests), pero `wp_new_comment()` real de
 * principio a fin.
 *
 * @covers \Pluma\Compuertas\CompuertaComentarios
 */
final class CompuertaComentariosTest extends WP_UnitTestCase {

	private function registrarCompuerta( string $jsonRespuestaClasificador ): void {
		$clasificador = new ClasificadorComentarios(
			new ProveedorLenguajeFalso( $jsonRespuestaClasificador ),
			new PresupuestoLenguaje( new RelojFijo() )
		);

		( new CompuertaComentarios( $clasificador ) )->registrar();
	}

	private function crearPiezaPluma(): int {
		$postId = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		update_post_meta( $postId, Publicador::META_PIEZA_ID, 1 );

		return $postId;
	}

	public function test_comentario_aporte_informativo_en_pieza_pluma_se_aprueba_y_persiste_la_categoria(): void {
		$this->registrarCompuerta( '{"categoria": "aporte_informativo"}' );
		$postId = $this->crearPiezaPluma();

		$comentarioId = wp_new_comment(
			array(
				'comment_post_ID'      => $postId,
				'comment_author'       => 'Lector Uno',
				'comment_author_email' => 'lector1@example.test',
				'comment_author_url'   => '',
				'comment_content'      => 'Según el INE, el dato real es otro; aquí está la fuente.',
				'comment_type'         => '',
				'comment_parent'       => 0,
			),
			true
		);

		self::assertIsInt( $comentarioId );
		$comentario = get_comment( $comentarioId );
		self::assertNotNull( $comentario );
		self::assertSame( '1', $comentario->comment_approved );
		self::assertSame(
			CategoriaComentario::AporteInformativo->value,
			get_comment_meta( $comentarioId, 'pluma_categoria_comentario', true )
		);
	}

	public function test_comentario_de_odio_en_pieza_pluma_se_marca_spam_real(): void {
		$this->registrarCompuerta( '{"categoria": "odio_ataque_personal"}' );
		$postId = $this->crearPiezaPluma();

		$comentarioId = wp_new_comment(
			array(
				'comment_post_ID'      => $postId,
				'comment_author'       => 'Lector Dos',
				'comment_author_email' => 'lector2@example.test',
				'comment_author_url'   => '',
				'comment_content'      => 'contenido de odio',
				'comment_type'         => '',
				'comment_parent'       => 0,
			),
			true
		);

		self::assertIsInt( $comentarioId );
		$comentario = get_comment( $comentarioId );
		self::assertNotNull( $comentario );
		self::assertSame( 'spam', $comentario->comment_approved );
	}

	public function test_comentario_en_post_ajeno_a_pluma_no_se_clasifica_y_sigue_la_moderacion_normal(): void {
		$this->registrarCompuerta( '{"categoria": "odio_ataque_personal"}' );
		$postId = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$comentarioId = wp_new_comment(
			array(
				'comment_post_ID'      => $postId,
				'comment_author'       => 'Lector Tres',
				'comment_author_email' => 'lector3@example.test',
				'comment_author_url'   => '',
				'comment_content'      => 'un comentario cualquiera en un post que no es de PLUMA',
				'comment_type'         => '',
				'comment_parent'       => 0,
			),
			true
		);

		self::assertIsInt( $comentarioId );
		$comentario = get_comment( $comentarioId );
		self::assertNotNull( $comentario );
		self::assertNotSame( 'spam', $comentario->comment_approved, 'Sin META_PIEZA_ID, la compuerta nunca debe intervenir.' );
		self::assertSame( '', get_comment_meta( $comentarioId, 'pluma_categoria_comentario', true ) );
	}

	public function test_comentario_destacado_recibe_la_clase_css_real_via_comment_class(): void {
		$this->registrarCompuerta( '{"categoria": "critica_legitima"}' );
		$postId = $this->crearPiezaPluma();

		$comentarioId = wp_new_comment(
			array(
				'comment_post_ID'      => $postId,
				'comment_author'       => 'Lector Cuatro',
				'comment_author_email' => 'lector4@example.test',
				'comment_author_url'   => '',
				'comment_content'      => 'no estoy de acuerdo con este argumento y explico por qué',
				'comment_type'         => '',
				'comment_parent'       => 0,
			),
			true
		);

		self::assertIsInt( $comentarioId );

		$clases = get_comment_class( '', $comentarioId, $postId );

		self::assertContains( 'pluma-comentario--destacado', $clases );
		self::assertContains( 'pluma-comentario--critica-legitima', $clases );
	}
}
