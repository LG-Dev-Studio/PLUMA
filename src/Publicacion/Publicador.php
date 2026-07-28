<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use Pluma\Seo\MetadatosSeo;
use Pluma\Seo\TipoPluginSeo;
use Pluma\Taxonomia\ResultadoTaxonomia;

final class Publicador implements PublicadorInterface {

	public function __construct(
		private readonly EscritorCamposSeo $escritorSeo,
		private readonly AsignadorTaxonomiaWp $asignadorTaxonomia,
	) {
	}

	/**
	 * Post metas que consume `Pluma\Seo\EmisorEsquemaFrontend` en `wp_head`.
	 * Se escriben una sola vez al publicar; el render no vuelve a calcularlos.
	 */
	public const META_PIEZA_ID       = '_pluma_pieza_id';
	public const META_GENERADO_IA    = '_pluma_generado_ia';
	public const META_MODO           = '_pluma_modo_publicacion';
	public const META_ESQUEMA_TIPO   = '_pluma_esquema_tipo';
	public const META_AUTOR_NOMBRE   = '_pluma_autor_nombre';
	public const META_TIPO_CONTENIDO = '_pluma_tipo_contenido';

	public function publicar( int $postId, MetadatosSeo $metadatos, TipoPluginSeo $plugin, ResultadoTaxonomia $taxonomia, SnapshotPublicacion $snapshot ): void {
		$resultado = wp_update_post(
			array(
				'ID'          => $postId,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $resultado ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new PublicacionException( $resultado->get_error_message() );
		}

		$this->escritorSeo->escribir( $postId, $metadatos, $plugin );
		$this->asignadorTaxonomia->asignar( $postId, $taxonomia );

		update_post_meta( $postId, self::META_PIEZA_ID, $snapshot->piezaId );
		update_post_meta( $postId, self::META_GENERADO_IA, $snapshot->generadoIa ? '1' : '' );
		update_post_meta( $postId, self::META_MODO, $snapshot->modoPublicacion );
		update_post_meta( $postId, self::META_ESQUEMA_TIPO, $snapshot->tipoEsquema );
		update_post_meta( $postId, self::META_AUTOR_NOMBRE, $snapshot->autorNombre );
		// Nivel Cuatro Y.1 (Etapa 9): la muralla entre redacción y
		// publicidad, como código — este es el ÚNICO punto del plugin que
		// crea/publica el post de una Pieza (CLAUDE.md § Ley de
		// Arquitectura), y escribe SIEMPRE `Editorial`, sin aceptar el tipo
		// como parámetro. El pipeline editorial no puede producir contenido
		// `Patrocinada` por construcción, no por convención.
		update_post_meta( $postId, self::META_TIPO_CONTENIDO, TipoContenido::Editorial->value );
	}
}
