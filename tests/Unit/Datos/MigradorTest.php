<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Datos;

use Brain\Monkey\Functions;
use Pluma\Datos\Migrador;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use wpdb;

/**
 * @covers \Pluma\Datos\Migrador
 */
final class MigradorTest extends CasoDePruebaUnitario {

	public function test_version_instalada_por_defecto_es_0_0_0(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Migrador::OPCION_VERSION, '0.0.0' )
			->andReturn( '0.0.0' );

		$migrador = new Migrador( new wpdb() );

		self::assertSame( '0.0.0', $migrador->versionInstalada() );
	}

	public function test_migrar_sin_sentencias_sube_la_version_si_cambio(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Migrador::OPCION_VERSION, '0.0.0' )
			->andReturn( '0.0.0' );

		Functions\expect( 'update_option' )
			->once()
			->with( Migrador::OPCION_VERSION, '0.1.0', false )
			->andReturn( true );

		( new Migrador( new wpdb() ) )->migrar( '0.1.0' );

		$this->expectNotToPerformAssertions();
	}

	public function test_migrar_es_idempotente_no_reescribe_si_la_version_no_cambio(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Migrador::OPCION_VERSION, '0.0.0' )
			->andReturn( '0.1.0' );

		Functions\expect( 'update_option' )->never();

		( new Migrador( new wpdb() ) )->migrar( '0.1.0' );

		$this->expectNotToPerformAssertions();
	}

	public function test_migrar_con_sentencias_invoca_dbdelta_por_cada_una_y_luego_actualiza_version(): void {
		$sentencia = 'CREATE TABLE wp_pluma_ejemplo (id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (id));';
		Functions\expect( 'dbDelta' )->once()->with( $sentencia )->andReturn( array() );

		Functions\expect( 'get_option' )
			->once()
			->with( Migrador::OPCION_VERSION, '0.0.0' )
			->andReturn( '0.0.0' );

		Functions\expect( 'update_option' )
			->once()
			->with( Migrador::OPCION_VERSION, '0.1.0', false )
			->andReturn( true );

		( new Migrador( new wpdb() ) )->migrar( '0.1.0', array( $sentencia ) );

		$this->expectNotToPerformAssertions();
	}

	public function test_ejecutar_dos_veces_el_mismo_lote_no_duplica_cambios(): void {
		Functions\when( 'dbDelta' )->justReturn( array() );

		Functions\expect( 'get_option' )
			->twice()
			->with( Migrador::OPCION_VERSION, '0.0.0' )
			->andReturn( '0.0.0', '0.1.0' );

		Functions\expect( 'update_option' )
			->once()
			->with( Migrador::OPCION_VERSION, '0.1.0', false )
			->andReturn( true );

		$sentencias = array( 'CREATE TABLE wp_pluma_ejemplo (id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (id));' );
		$migrador   = new Migrador( new wpdb() );

		$migrador->migrar( '0.1.0', $sentencias );
		$migrador->migrar( '0.1.0', $sentencias );

		$this->expectNotToPerformAssertions();
	}

	public function test_prefijo_tablas_usa_el_prefijo_del_wpdb_inyectado(): void {
		$wpdb         = new wpdb();
		$wpdb->prefix = 'wp_multisitio_3_';

		self::assertSame( 'wp_multisitio_3_pluma_', ( new Migrador( $wpdb ) )->prefijoTablas() );
	}

	public function test_revertir_a_ejecuta_las_sentencias_y_actualiza_la_version(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Migrador::OPCION_VERSION, '0.0.0' )
			->andReturn( '0.12.0' );

		Functions\expect( 'update_option' )
			->once()
			->with( Migrador::OPCION_VERSION, '0.11.0', false )
			->andReturn( true );

		( new Migrador( new wpdb() ) )->revertirA(
			'0.11.0',
			array( 'ALTER TABLE wp_pluma_ejemplo DROP COLUMN columna_nueva;' )
		);

		$this->expectNotToPerformAssertions();
	}

	public function test_revertir_a_es_idempotente_no_reescribe_si_la_version_ya_es_la_objetivo(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( Migrador::OPCION_VERSION, '0.0.0' )
			->andReturn( '0.11.0' );

		Functions\expect( 'update_option' )->never();

		( new Migrador( new wpdb() ) )->revertirA( '0.11.0', array() );

		$this->expectNotToPerformAssertions();
	}
}
