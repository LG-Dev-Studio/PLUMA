<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Brain\Monkey\Functions;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Redaccion\AgrupadorTemasSinCobertura;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Trabajo posterior a la Etapa 9 (creación automática de periodistas).
 *
 * @covers \Pluma\Redaccion\AgrupadorTemasSinCobertura
 */
final class AgrupadorTemasSinCoberturaTest extends CasoDePruebaUnitario {

	/**
	 * @return list<array{tema: string, extracto: string}>
	 */
	private function muestras(): array {
		return array(
			array(
				'tema'     => 'deportes',
				'extracto' => 'Los Dodgers ganaron el partido de anoche.',
			),
			array(
				'tema'     => 'deportes',
				'extracto' => 'Shohei Ohtani conectó otro jonrón.',
			),
			array(
				'tema'     => 'deportes',
				'extracto' => 'Los Padres perdieron en casa.',
			),
		);
	}

	private function presupuestoDisponible(): PresupuestoLenguaje {
		Functions\when( 'get_option' )->justReturn( false );

		return new PresupuestoLenguaje( new RelojFijo() );
	}

	public function test_propone_periodista_cuando_el_proveedor_decide_que_el_grupo_es_coherente(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"crearPeriodista": true, "vertical": "Deportes", "nombre": "Renata Solís", "biografia": "Cubre deportes con rigor.", "rol": "cronista", "nivelDominio": 4}'
		);

		$propuesta = ( new AgrupadorTemasSinCobertura( $proveedor, $this->presupuestoDisponible() ) )->evaluar( $this->muestras() );

		self::assertNotNull( $propuesta );
		self::assertSame( 'deportes', $propuesta->vertical );
		self::assertSame( 'Renata Solís', $propuesta->nombre );
		self::assertSame( RolPeriodista::Cronista, $propuesta->rol );
		self::assertSame( 4, $propuesta->nivelDominio );
	}

	public function test_devuelve_null_cuando_el_proveedor_decide_que_no_hay_grupo_coherente(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"crearPeriodista": false}' );

		$propuesta = ( new AgrupadorTemasSinCobertura( $proveedor, $this->presupuestoDisponible() ) )->evaluar( $this->muestras() );

		self::assertNull( $propuesta );
	}

	public function test_sin_muestras_no_llama_al_proveedor(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"crearPeriodista": true, "vertical": "x", "nombre": "x", "biografia": "x", "rol": "cronista", "nivelDominio": 4}' );

		$propuesta = ( new AgrupadorTemasSinCobertura( $proveedor, $this->presupuestoDisponible() ) )->evaluar( array() );

		self::assertNull( $propuesta );
		self::assertNull( $proveedor->ultimaPeticion );
	}

	public function test_sin_presupuesto_disponible_devuelve_null_sin_llamar_al_proveedor(): void {
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInTernaryCondition.FoundInTernaryCondition -- falso positivo: es una arrow function con `=>`, no una asignación.
			static fn ( string $opcion, $defecto = false ) => PresupuestoLenguaje::OPCION_LIMITE_DIARIO === $opcion ? 0.0 : $defecto
		);

		$proveedor = new ProveedorLenguajeFalso( '{"crearPeriodista": true, "vertical": "x", "nombre": "x", "biografia": "x", "rol": "cronista", "nivelDominio": 4}' );

		$propuesta = ( new AgrupadorTemasSinCobertura( $proveedor, new PresupuestoLenguaje( new RelojFijo() ) ) )->evaluar( $this->muestras() );

		self::assertNull( $propuesta );
		self::assertNull( $proveedor->ultimaPeticion );
	}

	public function test_proveedor_caido_devuelve_null_en_vez_de_lanzar(): void {
		$proveedor = new class() implements \Pluma\Proveedores\LenguajeInterface {
			public function completar( \Pluma\Proveedores\PeticionLenguaje $peticion ): \Pluma\Proveedores\RespuestaLenguaje {
				throw new ProveedorLenguajeException( 'proveedor caído' );
			}

			public function tieneCredenciales(): bool {
				return true;
			}

			public function familiaDe( string $modelo ): string {
				return $modelo;
			}
		};

		$propuesta = ( new AgrupadorTemasSinCobertura( $proveedor, $this->presupuestoDisponible() ) )->evaluar( $this->muestras() );

		self::assertNull( $propuesta );
	}

	public function test_respuesta_no_interpretable_devuelve_null_en_vez_de_lanzar(): void {
		$proveedor = new ProveedorLenguajeFalso( 'no es JSON en absoluto' );

		$propuesta = ( new AgrupadorTemasSinCobertura( $proveedor, $this->presupuestoDisponible() ) )->evaluar( $this->muestras() );

		self::assertNull( $propuesta );
	}

	public function test_respuesta_truncada_devuelve_null_en_vez_de_lanzar(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"crearPeriodista": true, "vertical": "deportes", "nombre": "X", "biografia": "X", "rol": "cronista", "nivelDominio": 4}',
			truncada: true
		);

		$propuesta = ( new AgrupadorTemasSinCobertura( $proveedor, $this->presupuestoDisponible() ) )->evaluar( $this->muestras() );

		self::assertNull( $propuesta );
	}

	public function test_rol_desconocido_se_trata_como_no_crear(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"crearPeriodista": true, "vertical": "deportes", "nombre": "X", "biografia": "X", "rol": "inventor-de-roles", "nivelDominio": 4}'
		);

		$propuesta = ( new AgrupadorTemasSinCobertura( $proveedor, $this->presupuestoDisponible() ) )->evaluar( $this->muestras() );

		self::assertNull( $propuesta );
	}

	public function test_nivel_de_dominio_fuera_de_rango_se_trata_como_no_crear(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"crearPeriodista": true, "vertical": "deportes", "nombre": "X", "biografia": "X", "rol": "cronista", "nivelDominio": 9}'
		);

		$propuesta = ( new AgrupadorTemasSinCobertura( $proveedor, $this->presupuestoDisponible() ) )->evaluar( $this->muestras() );

		self::assertNull( $propuesta );
	}

	public function test_el_material_enviado_incluye_los_temas_y_extractos(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"crearPeriodista": false}' );

		( new AgrupadorTemasSinCobertura( $proveedor, $this->presupuestoDisponible() ) )->evaluar( $this->muestras() );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringContainsString( 'deportes', $proveedor->ultimaPeticion->material );
		self::assertStringContainsString( 'Shohei Ohtani', $proveedor->ultimaPeticion->material );
	}
}
