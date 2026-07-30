<?php

declare(strict_types=1);

namespace Pluma\Tests\Invariantes;

use FilesystemIterator;
use Pluma\Kernel\Nucleo;
use Pluma\Proveedores\EmbeddingsInstrumentado;
use Pluma\Proveedores\EmbeddingsInterface;
use Pluma\Proveedores\LenguajeInstrumentado;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use wpdb;

/**
 * NCP-1, `ADR 0010`: el instrumento de medición solo mide de verdad si NADA
 * puede sortearlo. Dos formas de sortearlo, ambas cubiertas aquí:
 *
 * 1. Un refactor futuro desenvuelve el decorador en el único punto de
 *    registro del contenedor ({@see \Pluma\Kernel\Nucleo}) — los 21
 *    consumidores reales de `completar()` dejarían de medirse en silencio.
 * 2. Una capa nueva inyecta `ProveedorOpenRouter` (el tipo concreto, no el
 *    contrato) y lo usa para `completar()`/`embed()` directamente — hoy ese
 *    registro crudo existe en `Nucleo.php` SOLO para `probarLlave()`/
 *    `circuitoAbierto()` (Sala de Máquinas), y es una puerta trasera sin
 *    instrumentar si algún día alguien más lo usa para hablar con el modelo.
 *
 * @covers \Pluma\Kernel\Nucleo
 */
final class LlamadasInstrumentadasInvarianteTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();

		// `RepositorioLlamadasModeloInterface` (NCP-1, ADR 0010) necesita
		// `$wpdb` para construirse, aunque este test nunca ejecuta ninguna
		// consulta real — mismo doble mínimo que ya usa `tests/bootstrap.php`
		// para la suite Unit.
		global $wpdb;
		$wpdb = new wpdb();
	}

	public function test_el_contenedor_resuelve_lenguajeinterface_a_una_instancia_instrumentada(): void {
		$lenguaje = ( new Nucleo() )->contenedor()->obtener( LenguajeInterface::class );

		self::assertInstanceOf(
			LenguajeInstrumentado::class,
			$lenguaje,
			'LenguajeInterface::class ya no resuelve a la variante instrumentada: un refactor desenvolvió el decorador en el único punto de registro del contenedor y los consumidores reales dejaron de medirse en silencio (NCP-1, ADR 0010).'
		);
	}

	public function test_el_contenedor_resuelve_embeddingsinterface_a_una_instancia_instrumentada(): void {
		$embeddings = ( new Nucleo() )->contenedor()->obtener( EmbeddingsInterface::class );

		self::assertInstanceOf(
			EmbeddingsInstrumentado::class,
			$embeddings,
			'EmbeddingsInterface::class ya no resuelve a la variante instrumentada (NCP-1, ADR 0010).'
		);
	}

	public function test_ningun_consumidor_llama_completar_o_embed_sobre_el_tipo_concreto_proveedoropenrouter(): void {
		$archivos = $this->archivosPhpRecursivo( dirname( __DIR__, 2 ) . '/src' );
		self::assertNotEmpty( $archivos, 'No se pudo listar ningún archivo de src/: el test se habría vuelto decorativo.' );

		$huboAlgunaPropiedadProveedorOpenRouter = false;

		foreach ( $archivos as $archivo ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lectura de un archivo del propio repo en un test de arquitectura, no una URL remota.
			$fuente = file_get_contents( $archivo );
			self::assertIsString( $fuente );

			preg_match_all( '/private readonly ProveedorOpenRouter \$(\w+)/', $fuente, $coincidencias );

			foreach ( $coincidencias[1] as $propiedad ) {
				$huboAlgunaPropiedadProveedorOpenRouter = true;

				self::assertStringNotContainsString(
					"\$this->{$propiedad}->completar(",
					$fuente,
					"{$archivo} tipa una propiedad como ProveedorOpenRouter (el tipo concreto, sin instrumentar) y la usa para completar() — puerta trasera que evade la bitácora de NCP-1. Inyecta LenguajeInterface en su lugar."
				);
				self::assertStringNotContainsString(
					"\$this->{$propiedad}->embed(",
					$fuente,
					"{$archivo} tipa una propiedad como ProveedorOpenRouter (el tipo concreto, sin instrumentar) y la usa para embed() — puerta trasera que evade la bitácora de NCP-1. Inyecta EmbeddingsInterface en su lugar."
				);
			}
		}

		self::assertTrue(
			$huboAlgunaPropiedadProveedorOpenRouter,
			'No se encontró ninguna propiedad tipada como ProveedorOpenRouter en src/ (se esperaba al menos la de RestSalaMaquinas, para probarLlave()/circuitoAbierto()): el test se habría vuelto decorativo.'
		);
	}

	/**
	 * `glob()` no es recursivo (ver el docblock de otros tests de este
	 * directorio) — aquí sí hace falta bajar a subdirectorios como
	 * `src/Kernel/Excepciones/`, así que se usa un iterador recursivo real.
	 *
	 * @return list<string>
	 */
	private function archivosPhpRecursivo( string $directorio ): array {
		$iterador = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directorio, FilesystemIterator::SKIP_DOTS )
		);

		$archivos = array();
		foreach ( $iterador as $archivo ) {
			assert( $archivo instanceof SplFileInfo );
			if ( 'php' === $archivo->getExtension() ) {
				$archivos[] = $archivo->getPathname();
			}
		}

		return $archivos;
	}
}
