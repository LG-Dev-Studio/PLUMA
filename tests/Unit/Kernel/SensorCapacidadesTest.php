<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Kernel;

use Brain\Monkey\Functions;
use Pluma\Kernel\Cifrado;
use Pluma\Kernel\SensorCapacidades;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\ProveedorCerebroRemoto;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * `ProveedorCerebroRemoto` es `final` (no mockeable) — se construye real
 * aquí y se controla vía `get_option`, mismo patrón que
 * `ProveedorCerebroRemotoTest`.
 *
 * @covers \Pluma\Kernel\SensorCapacidades
 */
final class SensorCapacidadesTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'AUTH_KEY' ) ) {
			define( 'AUTH_KEY', 'clave-app-de-prueba' );
			define( 'SECURE_AUTH_KEY', 'clave-secure-de-prueba' );
		}
	}

	private function proveedorLenguaje( bool $tieneCredenciales ): LenguajeInterface {
		$doble = $this->createMock( LenguajeInterface::class );
		$doble->method( 'tieneCredenciales' )->willReturn( $tieneCredenciales );

		return $doble;
	}

	private function cerebroRemoto( bool $configurado, bool $ultimaPruebaOk ): ProveedorCerebroRemoto {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) use ( $configurado, $ultimaPruebaOk ) {
				return match ( $opcion ) {
					ProveedorCerebroRemoto::OPCION_URL => $configurado ? 'https://cerebro.example/salud' : false,
					ProveedorCerebroRemoto::OPCION_TOKEN_CIFRADO => $configurado ? Cifrado::cifrar( 'token' ) : false,
					ProveedorCerebroRemoto::OPCION_ULTIMA_PRUEBA_OK => $ultimaPruebaOk,
					default => $defecto,
				};
			}
		);

		return new ProveedorCerebroRemoto();
	}

	public function test_ffi_disponible_coincide_con_el_runtime_real_de_prueba(): void {
		$sensor = new SensorCapacidades( $this->proveedorLenguaje( false ), $this->cerebroRemoto( false, false ) );

		self::assertSame( extension_loaded( 'FFI' ), $sensor->medir()->ffiDisponible );
	}

	public function test_api_pago_configurada_delega_en_el_proveedor_de_lenguaje(): void {
		$sensor = new SensorCapacidades( $this->proveedorLenguaje( true ), $this->cerebroRemoto( false, false ) );

		self::assertTrue( $sensor->medir()->apiPagoConfigurada );
	}

	public function test_cerebro_remoto_configurado_solo_si_esta_configurado_y_la_ultima_prueba_fue_ok(): void {
		$sensor = new SensorCapacidades( $this->proveedorLenguaje( false ), $this->cerebroRemoto( true, true ) );

		self::assertTrue( $sensor->medir()->cerebroRemotoConfigurado );
	}

	public function test_cerebro_remoto_no_configurado_si_la_ultima_prueba_no_fue_ok(): void {
		$sensor = new SensorCapacidades( $this->proveedorLenguaje( false ), $this->cerebroRemoto( true, false ) );

		self::assertFalse( $sensor->medir()->cerebroRemotoConfigurado );
	}

	public function test_cerebro_remoto_no_configurado_sin_url_ni_token(): void {
		$sensor = new SensorCapacidades( $this->proveedorLenguaje( false ), $this->cerebroRemoto( false, true ) );

		self::assertFalse( $sensor->medir()->cerebroRemotoConfigurado );
	}

	public function test_tiempo_maximo_de_ejecucion_es_un_entero_no_negativo(): void {
		$sensor = new SensorCapacidades( $this->proveedorLenguaje( false ), $this->cerebroRemoto( false, false ) );

		self::assertGreaterThanOrEqual( 0, $sensor->medir()->tiempoMaximoEjecucionSegundos );
	}

	public function test_memoria_limite_mb_es_positiva(): void {
		$sensor = new SensorCapacidades( $this->proveedorLenguaje( false ), $this->cerebroRemoto( false, false ) );

		self::assertGreaterThan( 0, $sensor->medir()->memoriaLimiteMb );
	}
}
