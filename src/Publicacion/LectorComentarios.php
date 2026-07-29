<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use DateTimeImmutable;
use Pluma\Compuertas\CategoriaComentario;
use Pluma\Compuertas\CompuertaComentarios;
use WP_Comment;

/**
 * Único punto del plugin que llama `get_comments()` (CLAUDE.md § Ley de
 * Arquitectura, mismo principio que `CreadorBorrador` con `wp_insert_post`).
 */
final class LectorComentarios implements LectorComentariosInterface {

	public function obtenerAprobadosDe( int $postId ): array {
		$comentarios = get_comments(
			array(
				'post_id' => $postId,
				'status'  => 'approve',
				'type'    => 'comment',
				'order'   => 'ASC',
			)
		);

		if ( ! is_array( $comentarios ) ) {
			return array();
		}

		$resultado = array();

		foreach ( $comentarios as $comentario ) {
			if ( ! $comentario instanceof WP_Comment ) {
				continue;
			}

			$categoriaCruda = get_comment_meta( (int) $comentario->comment_ID, CompuertaComentarios::META_CATEGORIA, true );

			$resultado[] = new ComentarioWordPress(
				(int) $comentario->comment_ID,
				(int) $comentario->comment_post_ID,
				wp_strip_all_tags( $comentario->comment_author ),
				wp_strip_all_tags( $comentario->comment_content ),
				new DateTimeImmutable( $comentario->comment_date_gmt ),
				is_string( $categoriaCruda ) ? CategoriaComentario::tryFrom( $categoriaCruda ) : null
			);
		}

		return $resultado;
	}
}
