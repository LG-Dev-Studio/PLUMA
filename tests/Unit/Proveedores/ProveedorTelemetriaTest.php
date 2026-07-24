<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Proveedores;

use Brain\Monkey\Functions;
use Mockery;
use Pluma\Datos\Migrador;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Kernel\DetectorEntorno;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\Orquestador;
use Pluma\Proveedores\ProveedorTelemetria;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;
use wpdb;

/**
 * GOVERNANCE §5.5: el payload de telemetría nunca incluye contenido de
 * Piezas ni llaves — solo los campos documentados en `TelemetriaInterface`.
 *
 * @covers \Pluma\Proveedores\ProveedorTelemetria
 */
final class ProveedorTelemetriaTest extends CasoDePruebaUnitario {

	protected function tearDown(): void {
		unset( $GLOBALS['wp_version'] );
		parent::tearDown();
	}

	public function test_construir_payload_incluye_solo_los_campos_documentados_nunca_contenido_ni_llaves(): void {
		$GLOBALS['wp_version'] = '6.7.1';

		Functions\expect( 'is_multisite' )->once()->andReturn( false );

		Functions\expect( 'get_option' )
			->once()
			->with( Migrador::OPCION_VERSION, '0.0.0' )
			->andReturn( '0.12.0' );

		Functions\expect( 'get_option' )
			->once()
			->with( Orquestador::OPCION_MODO_OPERACION, 'copiloto' )
			->andReturn( 'autonomo' );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'contarPorEstado' )->once()->with( EstadoPieza::Publicada )->andReturn( 42 );

		$periodistas = Mockery::mock( RepositorioPeriodistasInterface::class );
		// Solo se usa count(): dos objetos cualesquiera bastan para probar la agregación.
		$periodistas->expects( 'obtenerActivos' )->once()->andReturn( array( new \stdClass(), new \stdClass() ) );

		$proveedor = new ProveedorTelemetria( new DetectorEntorno( new wpdb() ), $piezas, $periodistas, new RelojFijo() );

		$payload = $proveedor->construirPayload();

		self::assertSame(
			array(
				'versionPlugin'      => '0.0.0-test',
				'versionEsquema'     => '0.12.0',
				'versionPhp'         => PHP_VERSION,
				'versionWordPress'   => '6.7.1',
				'versionBaseDatos'   => '8.0.36',
				'esMultisitio'       => false,
				'modoOperacion'      => 'autonomo',
				'periodistasActivos' => 2,
				'piezasPublicadas'   => 42,
				'generadoEn'         => ( new RelojFijo() )->ahora()->format( DATE_ATOM ),
			),
			$payload
		);

		self::assertArrayNotHasKey( 'contenido', $payload );
		self::assertArrayNotHasKey( 'llave', $payload );
		self::assertArrayNotHasKey( 'llaveOpenRouter', $payload );
	}

	public function test_modo_operacion_desconocido_cae_a_copiloto(): void {
		Functions\expect( 'is_multisite' )->once()->andReturn( false );

		Functions\expect( 'get_option' )
			->once()
			->with( Migrador::OPCION_VERSION, '0.0.0' )
			->andReturn( '0.12.0' );

		Functions\expect( 'get_option' )
			->once()
			->with( Orquestador::OPCION_MODO_OPERACION, 'copiloto' )
			->andReturn( 'algo-invalido' );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->allows( 'contarPorEstado' )->andReturn( 0 );

		$periodistas = Mockery::mock( RepositorioPeriodistasInterface::class );
		$periodistas->allows( 'obtenerActivos' )->andReturn( array() );

		$proveedor = new ProveedorTelemetria( new DetectorEntorno( new wpdb() ), $piezas, $periodistas, new RelojFijo() );

		self::assertSame( 'copiloto', $proveedor->construirPayload()['modoOperacion'] );
	}
}
