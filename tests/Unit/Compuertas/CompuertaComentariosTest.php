<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Compuertas;

use Brain\Monkey\Functions;
use Pluma\Compuertas\CategoriaComentario;
use Pluma\Compuertas\ClasificadorComentarios;
use Pluma\Compuertas\CompuertaComentarios;
use Pluma\Compuertas\CompuertaRiesgo;
use Pluma\Compuertas\RegimenResponsabilidad;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Nivel Cuatro X.1 — compuertas de comentarios, moderación por IA.
 *
 * `ClasificadorComentarios` es `final` sin interfaz (mismo criterio que el
 * resto de esta base de código para clases de dominio puro): se construye
 * real, con un `ProveedorLenguajeFalso` en vez de mockearlo.
 *
 * @covers \Pluma\Compuertas\CompuertaComentarios
 */
final class CompuertaComentariosTest extends CasoDePruebaUnitario {

	/**
	 * Stub genérico de `get_option`: por defecto devuelve siempre el valor
	 * por defecto que cada llamador ya pasa (así `PresupuestoLenguaje`
	 * queda con presupuesto disponible y `CompuertaRiesgo` en régimen Civil
	 * sin necesidad de listar cada opción una por una). `$overrides` fija
	 * el valor de opciones concretas para los tests que necesitan otra cosa.
	 *
	 * @param array<string, mixed> $overrides
	 */
	private function stubOpciones( array $overrides = array() ): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) use ( $overrides ) {
				return array_key_exists( $opcion, $overrides ) ? $overrides[ $opcion ] : $defecto;
			}
		);
	}

	private function clasificadorReal( string $jsonRespuesta, bool $truncada = false ): ClasificadorComentarios {
		$proveedor = new ProveedorLenguajeFalso( $jsonRespuesta, $truncada );

		return new ClasificadorComentarios( $proveedor, new PresupuestoLenguaje( new RelojFijo() ) );
	}

	/**
	 * @return array<string, int|string>
	 */
	private function datosComentario( int $postId = 7, string $texto = 'comentario' ): array {
		return array(
			'comment_post_ID' => $postId,
			'comment_content' => $texto,
		);
	}

	public function test_evaluar_deja_pasar_valores_no_numericos_sin_clasificar(): void {
		$compuerta = new CompuertaComentarios( $this->clasificadorReal( '{"categoria": "spam"}' ) );

		$resultado = $compuerta->evaluar( new \WP_Error( 'comment_duplicate', 'duplicado' ), $this->datosComentario() );

		self::assertInstanceOf( \WP_Error::class, $resultado );
	}

	public function test_evaluar_deja_pasar_cuando_el_post_no_es_de_pluma(): void {
		Functions\when( 'get_post_meta' )->justReturn( '' );

		$compuerta = new CompuertaComentarios( $this->clasificadorReal( '{"categoria": "spam"}' ) );

		$resultado = $compuerta->evaluar( 1, $this->datosComentario() );

		self::assertSame( 1, $resultado );
	}

	public function test_evaluar_deja_pasar_cuando_el_clasificador_no_devuelve_categoria(): void {
		Functions\when( 'get_post_meta' )->justReturn( '42' );
		$this->stubOpciones();

		$compuerta = new CompuertaComentarios( $this->clasificadorReal( 'no es JSON en absoluto' ) );

		$resultado = $compuerta->evaluar( 1, $this->datosComentario() );

		self::assertSame( 1, $resultado );
	}

	/**
	 * @return list<array{0: string}>
	 */
	public static function categoriasDeSpamProvider(): array {
		return array(
			array( 'spam' ),
			array( 'odio_ataque_personal' ),
		);
	}

	/**
	 * @dataProvider categoriasDeSpamProvider
	 */
	public function test_evaluar_marca_spam_para_spam_y_odio( string $valorCategoria ): void {
		Functions\when( 'get_post_meta' )->justReturn( '42' );
		$this->stubOpciones();

		$compuerta = new CompuertaComentarios( $this->clasificadorReal( '{"categoria": "' . $valorCategoria . '"}' ) );

		$resultado = $compuerta->evaluar( 1, $this->datosComentario() );

		self::assertSame( 'spam', $resultado );
	}

	/**
	 * @return list<array{0: string}>
	 */
	public static function categoriasDestacablesProvider(): array {
		return array(
			array( 'critica_legitima' ),
			array( 'aporte_informativo' ),
		);
	}

	/**
	 * @dataProvider categoriasDestacablesProvider
	 */
	public function test_evaluar_aprueba_directo_critica_legitima_y_aporte_informativo( string $valorCategoria ): void {
		Functions\when( 'get_post_meta' )->justReturn( '42' );
		$this->stubOpciones();

		$compuerta = new CompuertaComentarios( $this->clasificadorReal( '{"categoria": "' . $valorCategoria . '"}' ) );

		$resultado = $compuerta->evaluar( 1, $this->datosComentario() );

		self::assertSame( 1, $resultado );
	}

	public function test_evaluar_retiene_afirmacion_riesgosa_por_defecto_bajo_regimen_civil(): void {
		Functions\when( 'get_post_meta' )->justReturn( '42' );
		$this->stubOpciones();

		$compuerta = new CompuertaComentarios( $this->clasificadorReal( '{"categoria": "afirmacion_riesgosa"}' ) );

		$resultado = $compuerta->evaluar( 1, $this->datosComentario() );

		self::assertSame( 0, $resultado );
	}

	public function test_evaluar_no_retiene_afirmacion_riesgosa_si_se_desactiva_la_opcion_bajo_regimen_civil(): void {
		Functions\when( 'get_post_meta' )->justReturn( '42' );
		$this->stubOpciones(
			array(
				CompuertaComentarios::OPCION_RETENER_AFIRMACION_RIESGOSA => false,
				CompuertaRiesgo::OPCION_REGIMEN_RESPONSABILIDAD           => RegimenResponsabilidad::Civil->value,
			)
		);

		$compuerta = new CompuertaComentarios( $this->clasificadorReal( '{"categoria": "afirmacion_riesgosa"}' ) );

		$resultado = $compuerta->evaluar( 1, $this->datosComentario() );

		self::assertSame( 1, $resultado );
	}

	public function test_evaluar_retiene_afirmacion_riesgosa_siempre_bajo_regimen_penal_sin_importar_la_opcion(): void {
		Functions\when( 'get_post_meta' )->justReturn( '42' );
		$this->stubOpciones(
			array(
				CompuertaComentarios::OPCION_RETENER_AFIRMACION_RIESGOSA => false,
				CompuertaRiesgo::OPCION_REGIMEN_RESPONSABILIDAD           => RegimenResponsabilidad::Penal->value,
			)
		);

		$compuerta = new CompuertaComentarios( $this->clasificadorReal( '{"categoria": "afirmacion_riesgosa"}' ) );

		$resultado = $compuerta->evaluar( 1, $this->datosComentario() );

		self::assertSame( 0, $resultado );
	}

	public function test_persistir_categoria_escribe_meta_solo_cuando_hubo_clasificacion(): void {
		Functions\when( 'get_post_meta' )->justReturn( '42' );
		$this->stubOpciones();

		$metaEscrita = array();
		Functions\when( 'add_comment_meta' )->alias(
			static function ( int $comentarioId, string $clave, $valor ) use ( &$metaEscrita ): int {
				$metaEscrita[ $clave ] = $valor;

				return 1;
			}
		);

		$compuerta = new CompuertaComentarios( $this->clasificadorReal( '{"categoria": "aporte_informativo"}' ) );

		$compuerta->evaluar( 1, $this->datosComentario() );
		$compuerta->persistirCategoria( 99 );

		self::assertArrayHasKey( 'pluma_categoria_comentario', $metaEscrita );
		self::assertSame( CategoriaComentario::AporteInformativo->value, $metaEscrita['pluma_categoria_comentario'] );
	}

	public function test_persistir_categoria_no_escribe_meta_sin_clasificacion_previa(): void {
		Functions\when( 'get_post_meta' )->justReturn( '' );

		$llamadas = 0;
		Functions\when( 'add_comment_meta' )->alias(
			static function () use ( &$llamadas ): int {
				++$llamadas;

				return 1;
			}
		);

		$compuerta = new CompuertaComentarios( $this->clasificadorReal( '{"categoria": "aporte_informativo"}' ) );

		$compuerta->evaluar( 1, $this->datosComentario() );
		$compuerta->persistirCategoria( 99 );

		self::assertSame( 0, $llamadas );
	}

	/**
	 * @return list<array{0: string, 1: bool}>
	 */
	public static function destacadoProvider(): array {
		return array(
			array( 'critica_legitima', true ),
			array( 'aporte_informativo', true ),
			array( 'spam', false ),
			array( 'odio_ataque_personal', false ),
			array( 'afirmacion_riesgosa', false ),
			array( '', false ),
		);
	}

	/**
	 * @dataProvider destacadoProvider
	 */
	public function test_destacar_en_marcado_solo_anade_clases_para_las_categorias_destacables( string $categoriaGuardada, bool $debeDestacar ): void {
		Functions\when( 'get_comment_meta' )->justReturn( $categoriaGuardada );

		$compuerta = new CompuertaComentarios( $this->clasificadorReal( '{"categoria": "spam"}' ) );

		$clases = $compuerta->destacarEnMarcado( array( 'comment' ), array(), '99' );

		if ( $debeDestacar ) {
			self::assertContains( 'pluma-comentario--destacado', $clases );
		} else {
			self::assertNotContains( 'pluma-comentario--destacado', $clases );
		}
	}
}
