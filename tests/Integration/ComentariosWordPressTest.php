<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Publicacion\LectorComentarios;
use Pluma\Publicacion\PublicadorComentario;
use WP_UnitTestCase;

/**
 * Único punto de contacto con comentarios reales de WordPress (Libro Cap.
 * 5.7, Etapa 5) — `get_comments()`/`wp_insert_comment()` contra WordPress real.
 *
 * @covers \Pluma\Publicacion\LectorComentarios
 * @covers \Pluma\Publicacion\PublicadorComentario
 */
final class ComentariosWordPressTest extends WP_UnitTestCase {

	public function test_obtener_aprobados_de_devuelve_solo_comentarios_aprobados(): void {
		$postId = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$aprobadoId = wp_insert_comment(
			array(
				'comment_post_ID'  => $postId,
				'comment_author'   => 'Lector Uno',
				'comment_content'  => 'Un comentario aprobado y sustantivo.',
				'comment_approved' => 1,
			)
		);
		wp_insert_comment(
			array(
				'comment_post_ID'  => $postId,
				'comment_author'   => 'Lector Dos',
				'comment_content'  => 'Un comentario todavía en moderación.',
				'comment_approved' => 0,
			)
		);

		$comentarios = ( new LectorComentarios() )->obtenerAprobadosDe( $postId );

		self::assertCount( 1, $comentarios );
		self::assertSame( $aprobadoId, $comentarios[0]->id );
		self::assertSame( $postId, $comentarios[0]->postId );
		self::assertSame( 'Lector Uno', $comentarios[0]->autor );
		self::assertSame( 'Un comentario aprobado y sustantivo.', $comentarios[0]->contenidoTexto );
	}

	public function test_obtener_aprobados_de_cruza_la_categoria_de_x1_desde_el_comment_meta(): void {
		$postId = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$comentarioId = wp_insert_comment(
			array(
				'comment_post_ID'  => $postId,
				'comment_author'   => 'Lector',
				'comment_content'  => 'Un aporte informativo real.',
				'comment_approved' => 1,
			)
		);
		add_comment_meta( $comentarioId, \Pluma\Compuertas\CompuertaComentarios::META_CATEGORIA, 'aporte_informativo', true );

		$comentarios = ( new LectorComentarios() )->obtenerAprobadosDe( $postId );

		self::assertCount( 1, $comentarios );
		self::assertSame( \Pluma\Compuertas\CategoriaComentario::AporteInformativo, $comentarios[0]->categoria );
	}

	public function test_obtener_aprobados_de_sin_categoria_persistida_devuelve_null(): void {
		$postId = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		wp_insert_comment(
			array(
				'comment_post_ID'  => $postId,
				'comment_author'   => 'Lector',
				'comment_content'  => 'Comentario sin clasificar.',
				'comment_approved' => 1,
			)
		);

		$comentarios = ( new LectorComentarios() )->obtenerAprobadosDe( $postId );

		self::assertCount( 1, $comentarios );
		self::assertNull( $comentarios[0]->categoria );
	}

	public function test_obtener_aprobados_de_un_post_sin_comentarios_devuelve_lista_vacia(): void {
		$postId = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		self::assertSame( array(), ( new LectorComentarios() )->obtenerAprobadosDe( $postId ) );
	}

	public function test_publicar_crea_un_comentario_real_como_autor_invitado(): void {
		$postId = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$comentarioOriginalId = wp_insert_comment(
			array(
				'comment_post_ID'  => $postId,
				'comment_author'   => 'Lector',
				'comment_content'  => 'Comentario original.',
				'comment_approved' => 1,
			)
		);

		$respuestaId = ( new PublicadorComentario() )->publicar( $postId, $comentarioOriginalId, 'Valentina Ruiz', 'Gracias por comentar.' );

		$comentario = get_comment( $respuestaId );
		self::assertNotNull( $comentario );
		self::assertSame( (string) $postId, $comentario->comment_post_ID );
		self::assertSame( (string) $comentarioOriginalId, $comentario->comment_parent );
		self::assertSame( 'Valentina Ruiz', $comentario->comment_author );
		self::assertSame( 'Gracias por comentar.', $comentario->comment_content );
		self::assertSame( '0', $comentario->user_id, 'Autor invitado: sin cuenta WP real vinculada.' );
		self::assertSame( '1', $comentario->comment_approved );
	}
}
