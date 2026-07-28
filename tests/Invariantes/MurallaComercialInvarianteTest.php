<?php

declare(strict_types=1);

namespace Pluma\Tests\Invariantes;

use Brain\Monkey\Functions;
use Pluma\Publicacion\AsignadorTaxonomiaWp;
use Pluma\Publicacion\EscritorCamposSeo;
use Pluma\Publicacion\Publicador;
use Pluma\Publicacion\SnapshotPublicacion;
use Pluma\Publicacion\TipoContenido;
use Pluma\Seo\MetadatosSeo;
use Pluma\Seo\TipoEsquemaArticulo;
use Pluma\Seo\TipoPluginSeo;
use Pluma\Taxonomia\ResultadoTaxonomia;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * Nivel Cuatro Y.1 — "la muralla entre redacción y publicidad, como
 * código": "test de arquitectura: ninguna ruta de código permite a una
 * pieza patrocinada entrar al pipeline editorial normal ni viceversa".
 *
 * Si este test se pone en rojo, o bien `Publicador::publicar()` (el único
 * punto del plugin que crea/publica el post de una Pieza) empezó a aceptar
 * el tipo de contenido como parámetro externo en vez de escribirlo
 * hardcodeado, o bien algún archivo del pipeline editorial empezó a
 * referenciar `TipoContenido::Patrocinada` — cualquiera de las dos cosas
 * sería la muralla rompiéndose.
 */
final class MurallaComercialInvarianteTest extends CasoDePruebaUnitario {

	public function test_publicador_siempre_escribe_tipo_editorial_sin_importar_el_snapshot(): void {
		$postId = 42;

		$metaEscrita = array();
		Functions\when( 'wp_update_post' )->justReturn( $postId );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $id, string $clave, $valor ) use ( &$metaEscrita ): bool {
				$metaEscrita[ $clave ] = $valor;

				return true;
			}
		);

		// EscritorCamposSeo/AsignadorTaxonomiaWp son `final` sin interfaz
		// (mismo criterio que otras clases de dominio puro en esta base de
		// código): se construyen reales — con TipoPluginSeo::Ninguno y
		// ResultadoTaxonomia vacío, ninguna de las dos llama más funciones
		// de WordPress que el `update_post_meta` ya interceptado arriba.
		$publicador = new Publicador( new EscritorCamposSeo(), new AsignadorTaxonomiaWp() );

		$metadatos = new MetadatosSeo( 'titulo seo', 'meta descripcion' );
		$snapshot  = new SnapshotPublicacion( 1, false, 'copiloto', TipoEsquemaArticulo::NewsArticle->value, 'Periodista de prueba' );

		$publicador->publicar( $postId, $metadatos, TipoPluginSeo::Ninguno, new ResultadoTaxonomia( null, array() ), $snapshot );

		self::assertArrayHasKey( Publicador::META_TIPO_CONTENIDO, $metaEscrita );
		self::assertSame( TipoContenido::Editorial->value, $metaEscrita[ Publicador::META_TIPO_CONTENIDO ] );
	}

	/**
	 * Test de arquitectura literal (Nivel Cuatro Y.1(c)): ningún archivo del
	 * pipeline editorial referencia `TipoContenido::Patrocinada` — ninguna
	 * ruta de código puede producir contenido patrocinado hoy, porque
	 * ninguna ruta de código lo nombra siquiera.
	 */
	public function test_ningun_archivo_del_pipeline_editorial_referencia_tipo_patrocinada(): void {
		$directoriosPipeline = array( 'Pipeline', 'Redaccion', 'Investigacion', 'Compuertas', 'Publicacion', 'Seo', 'Taxonomia' );
		$raiz                = dirname( __DIR__, 2 ) . '/src/';

		foreach ( $directoriosPipeline as $directorio ) {
			$archivosEncontrados = glob( $raiz . $directorio . '/*.php' );
			$archivos            = false !== $archivosEncontrados ? $archivosEncontrados : array();

			foreach ( $archivos as $archivo ) {
				if ( str_ends_with( $archivo, 'TipoContenido.php' ) ) {
					continue; // la propia definición del enum, no una ruta de uso.
				}

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lectura de archivos locales del propio repo, no de una URL remota.
				$contenido = file_get_contents( $archivo );
				self::assertIsString( $contenido );
				self::assertStringNotContainsString(
					'TipoContenido::Patrocinada',
					$contenido,
					"{$archivo} referencia TipoContenido::Patrocinada — la muralla Y.1 se rompió."
				);
			}
		}
	}
}
