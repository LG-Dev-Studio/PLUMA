<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Brain\Monkey\Functions;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Redaccion\AnalizadorAudiencia;
use Pluma\Redaccion\SentimientoAudiencia;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Memoria de audiencia (Libro Cap. 5.7, Etapa 5).
 *
 * @covers \Pluma\Redaccion\AnalizadorAudiencia
 */
final class AnalizadorAudienciaTest extends CasoDePruebaUnitario {

	private function presupuestoDisponible(): PresupuestoLenguaje {
		Functions\when( 'get_option' )->justReturn( false );

		return new PresupuestoLenguaje( new RelojFijo() );
	}

	public function test_extrae_el_resumen_y_el_sentimiento_de_una_respuesta_valida(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"resumen": "Le importa el impacto en el bolsillo.", "sentimiento": "negativo"}' );

		$aprendizaje = ( new AnalizadorAudiencia( $proveedor, $this->presupuestoDisponible() ) )->analizar( 'economia', 'esto me va a afectar el bolsillo' );

		self::assertNotNull( $aprendizaje );
		self::assertSame( 'Le importa el impacto en el bolsillo.', $aprendizaje->resumen );
		self::assertSame( SentimientoAudiencia::Negativo, $aprendizaje->sentimiento );
	}

	public function test_sin_presupuesto_disponible_devuelve_null_sin_llamar_al_proveedor(): void {
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInTernaryCondition.FoundInTernaryCondition -- falso positivo: es una arrow function con `=>`, no una asignación.
			static fn ( string $opcion, $defecto = false ) => PresupuestoLenguaje::OPCION_LIMITE_DIARIO === $opcion ? 0.0 : $defecto
		);

		$proveedor = new ProveedorLenguajeFalso( '{"resumen": "x", "sentimiento": "neutral"}' );

		$aprendizaje = ( new AnalizadorAudiencia( $proveedor, new PresupuestoLenguaje( new RelojFijo() ) ) )->analizar( 'economia', 'comentario' );

		self::assertNull( $aprendizaje );
		self::assertNull( $proveedor->ultimaPeticion );
	}

	public function test_proveedor_caido_devuelve_null_en_vez_de_lanzar(): void {
		$proveedor = new class() implements \Pluma\Proveedores\LenguajeInterface {
			public function completar( \Pluma\Proveedores\PeticionLenguaje $peticion ): \Pluma\Proveedores\RespuestaLenguaje {
				throw new ProveedorLenguajeException( 'proveedor caído' );
			}

			public function familiaDe( string $modelo ): string {
				return $modelo;
			}
		};

		$aprendizaje = ( new AnalizadorAudiencia( $proveedor, $this->presupuestoDisponible() ) )->analizar( 'economia', 'comentario' );

		self::assertNull( $aprendizaje );
	}

	public function test_respuesta_no_interpretable_devuelve_null_en_vez_de_lanzar(): void {
		$proveedor = new ProveedorLenguajeFalso( 'no es JSON en absoluto' );

		$aprendizaje = ( new AnalizadorAudiencia( $proveedor, $this->presupuestoDisponible() ) )->analizar( 'economia', 'comentario' );

		self::assertNull( $aprendizaje );
	}

	public function test_respuesta_con_sentimiento_desconocido_devuelve_null(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"resumen": "x", "sentimiento": "furioso"}' );

		$aprendizaje = ( new AnalizadorAudiencia( $proveedor, $this->presupuestoDisponible() ) )->analizar( 'economia', 'comentario' );

		self::assertNull( $aprendizaje );
	}

	public function test_respuesta_truncada_devuelve_null_en_vez_de_lanzar(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"resumen": "x", "sentimiento": "neutral"}', truncada: true );

		$aprendizaje = ( new AnalizadorAudiencia( $proveedor, $this->presupuestoDisponible() ) )->analizar( 'economia', 'comentario' );

		self::assertNull( $aprendizaje );
	}

	public function test_el_material_enviado_incluye_el_tema_y_el_comentario(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"resumen": "x", "sentimiento": "neutral"}' );

		( new AnalizadorAudiencia( $proveedor, $this->presupuestoDisponible() ) )->analizar( 'economia nacional', 'un comentario muy sustantivo' );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringContainsString( 'economia nacional', $proveedor->ultimaPeticion->material );
		self::assertStringContainsString( 'un comentario muy sustantivo', $proveedor->ultimaPeticion->material );
	}
}
