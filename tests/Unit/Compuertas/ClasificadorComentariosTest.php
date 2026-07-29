<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Compuertas;

use Brain\Monkey\Functions;
use Pluma\Compuertas\CategoriaComentario;
use Pluma\Compuertas\ClasificadorComentarios;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Proveedores\RespuestaLenguaje;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Nivel Cuatro X.1 — compuertas de comentarios, clasificación.
 *
 * @covers \Pluma\Compuertas\ClasificadorComentarios
 */
final class ClasificadorComentariosTest extends CasoDePruebaUnitario {

	private function presupuestoDisponible(): PresupuestoLenguaje {
		Functions\when( 'get_option' )->justReturn( false );

		return new PresupuestoLenguaje( new RelojFijo() );
	}

	public function test_clasifica_una_respuesta_valida(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"categoria": "aporte_informativo"}' );

		$categoria = ( new ClasificadorComentarios( $proveedor, $this->presupuestoDisponible() ) )->clasificar( 'según el INE, el dato real es otro' );

		self::assertSame( CategoriaComentario::AporteInformativo, $categoria );
	}

	public function test_sin_presupuesto_disponible_devuelve_null_sin_llamar_al_proveedor(): void {
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInTernaryCondition.FoundInTernaryCondition -- falso positivo: es una arrow function con `=>`, no una asignación.
			static fn ( string $opcion, $defecto = false ) => PresupuestoLenguaje::OPCION_LIMITE_DIARIO === $opcion ? 0.0 : $defecto
		);

		$proveedor = new ProveedorLenguajeFalso( '{"categoria": "spam"}' );

		$categoria = ( new ClasificadorComentarios( $proveedor, new PresupuestoLenguaje( new RelojFijo() ) ) )->clasificar( 'comentario' );

		self::assertNull( $categoria );
		self::assertNull( $proveedor->ultimaPeticion );
	}

	public function test_proveedor_caido_devuelve_null_en_vez_de_lanzar(): void {
		$proveedor = new class() implements LenguajeInterface {
			public function completar( PeticionLenguaje $peticion ): RespuestaLenguaje {
				throw new ProveedorLenguajeException( 'proveedor caído' );
			}

			public function tieneCredenciales(): bool {
				return true;
			}

			public function familiaDe( string $modelo ): string {
				return $modelo;
			}
		};

		$categoria = ( new ClasificadorComentarios( $proveedor, $this->presupuestoDisponible() ) )->clasificar( 'comentario' );

		self::assertNull( $categoria );
	}

	public function test_respuesta_no_interpretable_devuelve_null_en_vez_de_lanzar(): void {
		$proveedor = new ProveedorLenguajeFalso( 'no es JSON en absoluto' );

		$categoria = ( new ClasificadorComentarios( $proveedor, $this->presupuestoDisponible() ) )->clasificar( 'comentario' );

		self::assertNull( $categoria );
	}

	public function test_respuesta_con_categoria_desconocida_devuelve_null(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"categoria": "categoria_inventada"}' );

		$categoria = ( new ClasificadorComentarios( $proveedor, $this->presupuestoDisponible() ) )->clasificar( 'comentario' );

		self::assertNull( $categoria );
	}

	public function test_respuesta_truncada_devuelve_null_en_vez_de_lanzar(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"categoria": "spam"}', truncada: true );

		$categoria = ( new ClasificadorComentarios( $proveedor, $this->presupuestoDisponible() ) )->clasificar( 'comentario' );

		self::assertNull( $categoria );
	}

	public function test_el_material_enviado_es_el_texto_del_comentario(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"categoria": "critica_legitima"}' );

		( new ClasificadorComentarios( $proveedor, $this->presupuestoDisponible() ) )->clasificar( 'no estoy de acuerdo con este argumento' );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringContainsString( 'no estoy de acuerdo con este argumento', $proveedor->ultimaPeticion->material );
	}

	/**
	 * @return list<array{0: string}>
	 */
	public static function categoriasValidasProvider(): array {
		return array(
			array( 'spam' ),
			array( 'odio_ataque_personal' ),
			array( 'afirmacion_riesgosa' ),
			array( 'critica_legitima' ),
			array( 'aporte_informativo' ),
		);
	}

	/**
	 * @dataProvider categoriasValidasProvider
	 */
	public function test_reconoce_las_cinco_categorias_del_texto_fuente( string $valorCategoria ): void {
		$proveedor = new ProveedorLenguajeFalso( '{"categoria": "' . $valorCategoria . '"}' );

		$categoria = ( new ClasificadorComentarios( $proveedor, $this->presupuestoDisponible() ) )->clasificar( 'comentario' );

		self::assertNotNull( $categoria );
		self::assertSame( $valorCategoria, $categoria->value );
	}
}
