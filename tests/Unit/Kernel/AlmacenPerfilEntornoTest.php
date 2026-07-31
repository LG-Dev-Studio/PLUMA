<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Kernel;

use Brain\Monkey\Functions;
use Mockery;
use Pluma\Kernel\AlmacenPerfilEntorno;
use Pluma\Kernel\PerfilEntorno;
use Pluma\Kernel\ResolutorPerfilEntorno;
use Pluma\Kernel\SensorCapacidades;
use Pluma\Kernel\TransportePlano1;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\ProveedorCerebroRemoto;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * `SensorCapacidades`/`ProveedorCerebroRemoto` son `final` (no mockeables) —
 * este test compone instancias reales sobre un `LenguajeInterface` mockeado
 * (interfaz, sí mockeable) y controla el resto vía `get_option`. El objetivo
 * de este test es la mecánica de persistencia/fallo-abierto, no valores
 * concretos de `HechosEntorno` (que dependen del runtime real de PHP).
 *
 * @covers \Pluma\Kernel\AlmacenPerfilEntorno
 */
final class AlmacenPerfilEntornoTest extends CasoDePruebaUnitario {

	private function almacen(): AlmacenPerfilEntorno {
		$proveedorLenguaje = $this->createMock( LenguajeInterface::class );
		$proveedorLenguaje->method( 'tieneCredenciales' )->willReturn( false );

		$sensor = new SensorCapacidades( $proveedorLenguaje, new ProveedorCerebroRemoto() );

		return new AlmacenPerfilEntorno( $sensor, new ResolutorPerfilEntorno(), new RelojFijo() );
	}

	public function test_refrescar_persiste_con_autoload_desactivado(): void {
		Functions\when( 'get_option' )->justReturn( false );
		Functions\expect( 'update_option' )
			->once()
			->with( AlmacenPerfilEntorno::OPCION, Mockery::type( 'array' ), false )
			->andReturn( true );

		$this->almacen()->refrescar();

		$this->expectNotToPerformAssertions();
	}

	public function test_leer_con_opcion_ausente_falla_abierto_y_refresca(): void {
		Functions\when( 'get_option' )->justReturn( false );
		Functions\expect( 'update_option' )->once()->andReturn( true );

		$perfil = $this->almacen()->leer();

		self::assertInstanceOf( PerfilEntorno::class, $perfil );
	}

	public function test_leer_con_json_corrupto_falla_abierto_y_refresca(): void {
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInTernaryCondition.FoundInTernaryCondition -- falso positivo: es una arrow function con `=>`, no una asignación.
			static fn ( string $opcion, $defecto = false ) => AlmacenPerfilEntorno::OPCION === $opcion
				? array( 'version' => '1.0' ) // faltan claves.
				: $defecto
		);
		Functions\expect( 'update_option' )->once()->andReturn( true );

		$this->almacen()->leer();

		$this->expectNotToPerformAssertions();
	}

	public function test_leer_con_version_no_coincidente_falla_abierto_y_refresca(): void {
		$snapshotObsoleto = array(
			'version'               => '0.9',
			'medidoEn'              => ( new RelojFijo() )->ahora()->format( DATE_ATOM ),
			'transportePrioritario' => 'ninguno',
			'hechos'                => array(
				'ffiDisponible'                 => false,
				'memoriaLimiteMb'               => 128,
				'tiempoMaximoEjecucionSegundos' => 90,
				'procesoHijoDisponible'         => false,
				'cerebroRemotoConfigurado'      => false,
				'apiPagoConfigurada'            => false,
			),
		);
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInTernaryCondition.FoundInTernaryCondition -- falso positivo: es una arrow function con `=>`, no una asignación.
			static fn ( string $opcion, $defecto = false ) => AlmacenPerfilEntorno::OPCION === $opcion ? $snapshotObsoleto : $defecto
		);
		Functions\expect( 'update_option' )->once()->andReturn( true );

		$this->almacen()->leer();

		$this->expectNotToPerformAssertions();
	}

	public function test_leer_con_snapshot_valido_no_refresca(): void {
		$snapshot = array(
			'version'               => '1.0',
			'medidoEn'              => ( new RelojFijo() )->ahora()->format( DATE_ATOM ),
			'transportePrioritario' => 'ninguno',
			'hechos'                => array(
				'ffiDisponible'                 => false,
				'memoriaLimiteMb'               => 128,
				'tiempoMaximoEjecucionSegundos' => 90,
				'procesoHijoDisponible'         => false,
				'cerebroRemotoConfigurado'      => false,
				'apiPagoConfigurada'            => false,
			),
		);
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInTernaryCondition.FoundInTernaryCondition -- falso positivo: es una arrow function con `=>`, no una asignación.
			static fn ( string $opcion, $defecto = false ) => AlmacenPerfilEntorno::OPCION === $opcion ? $snapshot : $defecto
		);
		Functions\expect( 'update_option' )->never();

		$perfil = $this->almacen()->leer();

		self::assertSame( TransportePlano1::Ninguno, $perfil->transportePrioritario );
		self::assertFalse( $perfil->hechos->ffiDisponible );
	}
}
