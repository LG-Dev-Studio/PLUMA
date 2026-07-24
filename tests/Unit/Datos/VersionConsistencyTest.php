<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Datos;

use Pluma\Tests\Unit\CasoDePruebaUnitario;
use RuntimeException;

/**
 * GOVERNANCE §5.1: el número de versión del plugin vive en cuatro lugares
 * (comentario de cabecera y constante `PLUMA_ENGINE_VERSION` de
 * `pluma-engine.php`, `composer.json`, `package.json`) y deben coincidir
 * siempre. Ya hubo un desincronizado real entre el comentario y la
 * constante durante el cierre de la Etapa 5 — este test es la guardia que
 * lo hubiera detectado antes de llegar a CI.
 */
final class VersionConsistencyTest extends CasoDePruebaUnitario {

	private const RAIZ = __DIR__ . '/../../../';

	public function test_las_cuatro_fuentes_de_version_coinciden(): void {
		$archivoPrincipal = self::contenidoArchivo( 'pluma-engine.php' );

		$versionCabecera  = self::extraerConRegex( '/^\s*\*\s*Version:\s*([0-9.]+)\s*$/m', $archivoPrincipal, 'Version (cabecera del plugin)' );
		$versionConstante = self::extraerConRegex( "/define\\(\\s*'PLUMA_ENGINE_VERSION',\\s*'([0-9.]+)'\\s*\\)/", $archivoPrincipal, 'PLUMA_ENGINE_VERSION (constante)' );

		$composer = self::jsonArchivo( 'composer.json' );
		$paquete  = self::jsonArchivo( 'package.json' );

		self::assertArrayHasKey( 'version', $composer, 'composer.json debe declarar "version".' );
		self::assertArrayHasKey( 'version', $paquete, 'package.json debe declarar "version".' );

		self::assertSame( $versionCabecera, $versionConstante, 'El comentario de cabecera y la constante PLUMA_ENGINE_VERSION divergen.' );
		self::assertSame( $versionCabecera, $composer['version'], 'composer.json["version"] no coincide con la versión del plugin.' );
		self::assertSame( $versionCabecera, $paquete['version'], 'package.json["version"] no coincide con la versión del plugin.' );
	}

	private static function contenidoArchivo( string $relativo ): string {
		$ruta = self::RAIZ . $relativo;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lectura de archivo local del propio repo en un test, no una URL remota.
		$contenido = file_get_contents( $ruta );

		if ( false === $contenido ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new RuntimeException( "No se pudo leer {$relativo}." );
		}

		return $contenido;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function jsonArchivo( string $relativo ): array {
		$decodificado = json_decode( self::contenidoArchivo( $relativo ), true );

		if ( ! is_array( $decodificado ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new RuntimeException( "{$relativo} no contiene un objeto JSON válido." );
		}

		return $decodificado;
	}

	private static function extraerConRegex( string $patron, string $contenido, string $etiqueta ): string {
		if ( 1 !== preg_match( $patron, $contenido, $coincidencias ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new RuntimeException( "No se pudo extraer {$etiqueta} de pluma-engine.php." );
		}

		return $coincidencias[1];
	}
}
