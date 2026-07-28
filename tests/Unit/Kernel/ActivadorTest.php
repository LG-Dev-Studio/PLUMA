<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Kernel;

use Brain\Monkey\Functions;
use Mockery;
use Pluma\Kernel\Activador;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;
use wpdb;

/**
 * @covers \Pluma\Kernel\Activador
 */
final class ActivadorTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wpdb'] = new wpdb();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_activar_instala_capacidades_migra_el_esquema_y_registra_el_instante(): void {
		$rol = Mockery::mock( 'WP_Role' );
		$rol->expects( 'add_cap' )->times( 3 );
		Functions\expect( 'get_role' )->once()->with( 'administrator' )->andReturn( $rol );

		Functions\expect( 'dbDelta' )->times( 15 )->andReturn( array() );
		Functions\expect( 'get_option' )->once()->with( 'pluma_db_version', '0.0.0' )->andReturn( '0.0.0' );
		Functions\expect( 'update_option' )->once()->with( 'pluma_db_version', '0.1.0', false )->andReturn( true );

		Functions\expect( 'add_option' )
			->once()
			->with( Activador::OPCION_CONSERVAR_DATOS, true, '', false )
			->andReturn( true );

		Functions\expect( 'wp_generate_password' )->once()->with( 43, false, false )->andReturn( 'token-de-prueba' );
		Functions\expect( 'add_option' )
			->once()
			->with( Activador::OPCION_MOTOR_TOKEN, 'token-de-prueba', '', false )
			->andReturn( true );

		Functions\expect( 'add_option' )
			->once()
			->with( Activador::OPCION_TELEMETRIA_HABILITADA, false, '', false )
			->andReturn( true );

		Functions\expect( 'update_option' )
			->once()
			->with( Activador::OPCION_ACTIVADO_EN, '2026-07-22T12:00:00+00:00', false )
			->andReturn( true );

		Functions\expect( 'update_option' )
			->once()
			->with( Activador::OPCION_FLUSH_REESCRITURA_PENDIENTE, true, false )
			->andReturn( true );

		Activador::activar( new RelojFijo(), '0.1.0' );

		$this->expectNotToPerformAssertions();
	}

	public function test_activar_para_red_sin_multisitio_activa_una_sola_vez(): void {
		Functions\expect( 'is_multisite' )->once()->andReturn( false );

		$rol = Mockery::mock( 'WP_Role' );
		$rol->expects( 'add_cap' )->times( 3 );
		Functions\expect( 'get_role' )->once()->andReturn( $rol );
		Functions\expect( 'dbDelta' )->times( 15 )->andReturn( array() );
		Functions\expect( 'get_option' )->once()->andReturn( '0.1.0' );
		Functions\expect( 'wp_generate_password' )->once()->andReturn( 'token-de-prueba' );
		Functions\expect( 'add_option' )->times( 3 )->andReturn( true );
		Functions\expect( 'update_option' )->twice();

		Activador::activarParaRed( false, new RelojFijo(), '0.1.0' );

		$this->expectNotToPerformAssertions();
	}

	public function test_activar_para_red_completa_en_multisitio_activa_en_cada_sitio(): void {
		Functions\expect( 'is_multisite' )->once()->andReturn( true );
		Functions\expect( 'get_sites' )->once()->with( array( 'fields' => 'ids' ) )->andReturn( array( 1, 2 ) );
		Functions\expect( 'switch_to_blog' )->twice();
		Functions\expect( 'restore_current_blog' )->twice();

		$rol = Mockery::mock( 'WP_Role' );
		$rol->expects( 'add_cap' )->times( 6 ); // 3 capacidades × 2 sitios
		Functions\expect( 'get_role' )->twice()->andReturn( $rol );
		Functions\expect( 'dbDelta' )->times( 30 )->andReturn( array() ); // 15 tablas × 2 sitios
		Functions\expect( 'get_option' )->twice()->andReturn( '0.1.0' );
		Functions\expect( 'wp_generate_password' )->twice()->andReturn( 'token-de-prueba' );
		Functions\expect( 'add_option' )->times( 6 )->andReturn( true ); // 3 opciones × 2 sitios
		Functions\expect( 'update_option' )->times( 4 ); // (activado_en + flush pendiente) × 2 sitios

		Activador::activarParaRed( true, new RelojFijo(), '0.1.0' );

		$this->expectNotToPerformAssertions();
	}

	/**
	 * Regresión: una actualización normal de WordPress reemplaza los
	 * archivos del plugin SIN disparar `register_activation_hook` — sin este
	 * chequeo en `plugins_loaded`, el esquema de un cliente real se quedaría
	 * congelado en la versión con la que activó por última vez, mientras el
	 * código corre migraciones más nuevas (reproducido localmente: esquema
	 * atascado en 0.3.0 contra código en 0.9.0, con errores reales de SQL
	 * por columnas/tablas faltantes).
	 */
	public function test_actualizar_esquema_no_hace_nada_si_la_version_ya_coincide(): void {
		Functions\expect( 'get_option' )->once()->with( 'pluma_db_version', '0.0.0' )->andReturn( '0.9.0' );

		Activador::actualizarEsquemaSiHaceFalta( new RelojFijo(), '0.9.0' );

		$this->expectNotToPerformAssertions();
	}

	public function test_actualizar_esquema_migra_cuando_la_version_instalada_esta_desactualizada(): void {
		Functions\expect( 'get_option' )->twice()->with( 'pluma_db_version', '0.0.0' )->andReturn( '0.3.0' );

		$rol = Mockery::mock( 'WP_Role' );
		$rol->expects( 'add_cap' )->times( 3 );
		Functions\expect( 'get_role' )->once()->with( 'administrator' )->andReturn( $rol );

		Functions\expect( 'dbDelta' )->times( 15 )->andReturn( array() );
		Functions\expect( 'update_option' )->once()->with( 'pluma_db_version', '0.9.0', false )->andReturn( true );

		Functions\expect( 'add_option' )->times( 3 )->andReturn( true );
		Functions\expect( 'wp_generate_password' )->once()->andReturn( 'token-de-prueba' );
		Functions\expect( 'update_option' )
			->once()
			->with( Activador::OPCION_ACTIVADO_EN, '2026-07-22T12:00:00+00:00', false )
			->andReturn( true );
		Functions\expect( 'update_option' )
			->once()
			->with( Activador::OPCION_FLUSH_REESCRITURA_PENDIENTE, true, false )
			->andReturn( true );

		Activador::actualizarEsquemaSiHaceFalta( new RelojFijo(), '0.9.0' );

		$this->expectNotToPerformAssertions();
	}
}
