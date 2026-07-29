<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Kernel\Activador;
use Pluma\Kernel\RelojSistema;
use Pluma\Proveedores\ClavesVapid;
use WP_UnitTestCase;

/**
 * Nivel Cuatro W.3 — el par de claves VAPID se genera una sola vez en
 * activación y la privada se descifra con las salts reales de wp-config.php
 * (`Pluma\Kernel\Cifrado`, solo disponibles contra WordPress real).
 *
 * @covers \Pluma\Proveedores\ClavesVapid
 */
final class ClavesVapidTest extends WP_UnitTestCase {

	public function test_activar_genera_un_par_de_claves_que_se_descifra_correctamente(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$publica = ClavesVapid::publica();
		$privada = ClavesVapid::privada();

		self::assertNotNull( $publica );
		self::assertNotNull( $privada );
		self::assertNotSame( $publica, $privada );
	}

	public function test_activar_dos_veces_no_regenera_las_claves(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		$primeraPublica = ClavesVapid::publica();

		Activador::activar( new RelojSistema(), '0.9.0' );
		$segundaPublica = ClavesVapid::publica();

		self::assertSame( $primeraPublica, $segundaPublica );
	}
}
