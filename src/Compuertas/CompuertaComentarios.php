<?php

declare(strict_types=1);

namespace Pluma\Compuertas;

use Pluma\Publicacion\Publicador;
use WP_Error;

/**
 * Nivel Cuatro X.1 — compuertas de comentarios: "la misma filosofía del
 * Capítulo 8, aplicada a la entrada". Se engancha en `pre_comment_approved`
 * (filtro nativo de WordPress, síncrono con el envío del visitante) — SOLO
 * para comentarios en Piezas publicadas por PLUMA (`Publicador::META_PIEZA_ID`);
 * cualquier otro contenido del sitio sigue el criterio nativo de WordPress
 * sin tocar.
 *
 * Pisos de fábrica, NUNCA configurables: `Spam`/`OdioAtaquePersonal` siempre
 * se filtran. `AfirmacionRiesgosa` se retiene para revisión humana por
 * defecto — el editor puede desactivar esa retención, salvo bajo régimen de
 * responsabilidad `Penal` (Nivel Tres N.1: "un perfil de jurisdicción no es
 * un dial que el cliente pueda relajar"), donde retenerla vuelve a ser un
 * piso no negociable. `CriticaLegitima`/`AporteInformativo` se publican y se
 * destacan (clase CSS vía `comment_class`, para que el tema los distinga).
 *
 * La "revisión humana" reutiliza la cola de moderación NATIVA de WordPress
 * (`wp-admin/edit-comments.php`, ya visible a cualquier rol con
 * `moderate_comments`) — devolver `0` desde `pre_comment_approved` ya logra
 * exactamente eso, sin construir una pantalla de administración nueva.
 */
final class CompuertaComentarios {

	public const OPCION_RETENER_AFIRMACION_RIESGOSA   = 'pluma_comentarios_retener_afirmacion_riesgosa';
	private const RETENER_AFIRMACION_RIESGOSA_DEFECTO = true;

	// Nivel Cuatro X.2 (Etapa 9): pública desde `Pluma\Publicacion\LectorComentarios`
	// para cruzar el comentario con su categoría de X.1 antes de generar
	// un borrador de respuesta — Compuertas es adyacente a Publicacion en
	// la Ley de Arquitectura de CLAUDE.md.
	public const META_CATEGORIA = 'pluma_categoria_comentario';

	private static ?CategoriaComentario $ultimaCategoriaClasificada = null;

	public function __construct( private readonly ClasificadorComentarios $clasificador ) {
	}

	public function registrar(): void {
		add_filter( 'pre_comment_approved', array( $this, 'evaluar' ), 10, 2 );
		add_action( 'comment_post', array( $this, 'persistirCategoria' ), 10, 1 );
		add_filter( 'comment_class', array( $this, 'destacarEnMarcado' ), 10, 3 );
	}

	/**
	 * @param int|string|WP_Error $aprobado
	 * @param array<string, mixed> $datosComentario
	 * @return int|string|WP_Error
	 */
	public function evaluar( $aprobado, array $datosComentario ) {
		// Akismet u otro plugin ya decidió spam/trash/WP_Error — se respeta
		// esa decisión, PLUMA no la revierte.
		if ( ! is_numeric( $aprobado ) ) {
			return $aprobado;
		}

		$postId = isset( $datosComentario['comment_post_ID'] ) ? (int) $datosComentario['comment_post_ID'] : 0;

		if ( 0 === $postId || '' === (string) get_post_meta( $postId, Publicador::META_PIEZA_ID, true ) ) {
			return $aprobado;
		}

		$texto     = isset( $datosComentario['comment_content'] ) ? (string) $datosComentario['comment_content'] : '';
		$categoria = $this->clasificador->clasificar( $texto );

		if ( null === $categoria ) {
			// Fail-safe: sin clasificación posible, se respeta el criterio
			// nativo de WordPress/Akismet — nunca se bloquea ni se aprueba a
			// ciegas un envío de comentario en vivo.
			return $aprobado;
		}

		self::$ultimaCategoriaClasificada = $categoria;

		if ( CategoriaComentario::Spam === $categoria || CategoriaComentario::OdioAtaquePersonal === $categoria ) {
			return 'spam';
		}

		if ( $this->debeRetenerse( $categoria ) ) {
			return 0;
		}

		return 1;
	}

	/**
	 * `comment_post` se registra con `accepted_args = 1`: solo hace falta el
	 * id — la categoría ya quedó en el puente estático que dejó `evaluar()`
	 * en la misma petición síncrona.
	 */
	public function persistirCategoria( int $comentarioId ): void {
		if ( null === self::$ultimaCategoriaClasificada ) {
			return;
		}

		add_comment_meta( $comentarioId, self::META_CATEGORIA, self::$ultimaCategoriaClasificada->value, true );
		self::$ultimaCategoriaClasificada = null;
	}

	/**
	 * Nivel Cuatro X.1: "los dos últimos se publican y se destacan" — clase
	 * CSS para que el tema activo los distinga visualmente, sin que PLUMA
	 * imponga una plantilla de comentarios propia (frontend peso ≈ 0).
	 * `comment_class` se registra con `accepted_args = 3`: no hace falta ni
	 * el objeto `WP_Comment` ni el post para decidir la clase.
	 *
	 * @param list<string>        $classes
	 * @param list<string>|string $cssClass
	 * @return list<string>
	 */
	public function destacarEnMarcado( array $classes, array|string $cssClass, string $commentId ): array {
		unset( $cssClass );

		$categoria = get_comment_meta( (int) $commentId, self::META_CATEGORIA, true );

		if ( CategoriaComentario::CriticaLegitima->value === $categoria || CategoriaComentario::AporteInformativo->value === $categoria ) {
			$classes[] = 'pluma-comentario--destacado';
			$classes[] = 'pluma-comentario--' . str_replace( '_', '-', (string) $categoria );
		}

		return $classes;
	}

	private function debeRetenerse( CategoriaComentario $categoria ): bool {
		if ( CategoriaComentario::AfirmacionRiesgosa !== $categoria ) {
			return false;
		}

		if ( $this->bajoRegimenSevero() ) {
			return true;
		}

		$valor = get_option( self::OPCION_RETENER_AFIRMACION_RIESGOSA, self::RETENER_AFIRMACION_RIESGOSA_DEFECTO );

		return (bool) $valor;
	}

	/**
	 * Nivel Tres N.1, reutilizado tal cual: bajo régimen `Penal`, retener
	 * afirmaciones riesgosas sobre terceros deja de ser un dial — "el medio
	 * responde por lo que aloja".
	 */
	private function bajoRegimenSevero(): bool {
		$regimen = get_option( CompuertaRiesgo::OPCION_REGIMEN_RESPONSABILIDAD, RegimenResponsabilidad::Civil->value );

		return is_string( $regimen ) && RegimenResponsabilidad::Penal === ( RegimenResponsabilidad::tryFrom( $regimen ) ?? RegimenResponsabilidad::Civil );
	}
}
