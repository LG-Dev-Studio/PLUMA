<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Kernel;

use Pluma\Kernel\SensorCapacidades;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * Desde `ADR 0024` `SensorCapacidades` ya no depende de ningún transporte
 * remoto — NLI y RRK son pure-PHP, siempre disponibles, nada que sondear.
 *
 * @covers \Pluma\Kernel\SensorCapacidades
 */
final class SensorCapacidadesTest extends CasoDePruebaUnitario {

	private function proveedorLenguaje( bool $tieneCredenciales ): LenguajeInterface {
		$doble = $this->createMock( LenguajeInterface::class );
		$doble->method( 'tieneCredenciales' )->willReturn( $tieneCredenciales );

		return $doble;
	}

	public function test_ffi_disponible_coincide_con_el_runtime_real_de_prueba(): void {
		$sensor = new SensorCapacidades( $this->proveedorLenguaje( false ) );

		self::assertSame( extension_loaded( 'FFI' ), $sensor->medir()->ffiDisponible );
	}

	public function test_api_pago_configurada_delega_en_el_proveedor_de_lenguaje(): void {
		$sensor = new SensorCapacidades( $this->proveedorLenguaje( true ) );

		self::assertTrue( $sensor->medir()->apiPagoConfigurada );
	}

	public function test_tiempo_maximo_de_ejecucion_es_un_entero_no_negativo(): void {
		$sensor = new SensorCapacidades( $this->proveedorLenguaje( false ) );

		self::assertGreaterThanOrEqual( 0, $sensor->medir()->tiempoMaximoEjecucionSegundos );
	}

	public function test_memoria_limite_mb_es_positiva(): void {
		$sensor = new SensorCapacidades( $this->proveedorLenguaje( false ) );

		self::assertGreaterThan( 0, $sensor->medir()->memoriaLimiteMb );
	}
}
