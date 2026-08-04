<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Proveedores;

use Pluma\Proveedores\ModeloRegistrado;
use Pluma\Proveedores\RegistroModelos;
use Pluma\Proveedores\RolModelo;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Proveedores\RegistroModelos
 */
final class RegistroModelosTest extends CasoDePruebaUnitario {

	public function test_todos_devuelve_exactamente_las_dos_entradas_reales_hoy(): void {
		$entradas = ( new RegistroModelos() )->todos();

		self::assertCount( 2, $entradas );
		self::assertSame( RolModelo::Nli, $entradas[0]->rol );
		self::assertSame( RolModelo::Rrk, $entradas[1]->rol );
	}

	public function test_por_rol_enc_devuelve_vacio(): void {
		// ADR 0024: ENC nunca tuvo consumidor real vía T3 (los 2 consumidores
		// reales de EmbeddingsInterface siempre estuvieron ligados a
		// ProveedorOpenRouter) — se retira sin reemplazo.
		self::assertSame( array(), ( new RegistroModelos() )->porRol( RolModelo::Enc ) );
	}

	public function test_por_rol_nli_devuelve_la_entrada_con_checksum_real(): void {
		$entradas = ( new RegistroModelos() )->porRol( RolModelo::Nli );

		self::assertCount( 1, $entradas );
		self::assertSame( RolModelo::Nli, $entradas[0]->rol );
		self::assertNotNull( $entradas[0]->checksum );
		self::assertSame( 64, strlen( $entradas[0]->checksum ) );
	}

	public function test_por_rol_rrk_devuelve_la_entrada(): void {
		$entradas = ( new RegistroModelos() )->porRol( RolModelo::Rrk );

		self::assertCount( 1, $entradas );
		self::assertSame( RolModelo::Rrk, $entradas[0]->rol );
		self::assertNull( $entradas[0]->checksum );
		self::assertNotNull( $entradas[0]->motivoSinChecksum );
	}

	public function test_por_rol_sin_entradas_reales_devuelve_vacio(): void {
		self::assertSame( array(), ( new RegistroModelos() )->porRol( RolModelo::Ner ) );
		self::assertSame( array(), ( new RegistroModelos() )->porRol( RolModelo::Seg ) );
		self::assertSame( array(), ( new RegistroModelos() )->porRol( RolModelo::Lid ) );
		self::assertSame( array(), ( new RegistroModelos() )->porRol( RolModelo::Cls ) );
		self::assertSame( array(), ( new RegistroModelos() )->porRol( RolModelo::Tox ) );
	}

	public function test_toda_entrada_sin_checksum_declara_un_motivo(): void {
		foreach ( ( new RegistroModelos() )->todos() as $entrada ) {
			if ( null === $entrada->checksum ) {
				self::assertNotNull(
					$entrada->motivoSinChecksum,
					sprintf( 'La entrada del rol «%s» no tiene checksum pero tampoco declara un motivo — viola el canon (rol, versión, licencia, idioma, checksum, procedencia).', $entrada->rol->value )
				);
				self::assertNotSame( '', trim( $entrada->motivoSinChecksum ) );
			}
		}
	}

	public function test_toda_entrada_tiene_los_campos_obligatorios_no_vacios(): void {
		foreach ( ( new RegistroModelos() )->todos() as $entrada ) {
			self::assertNotSame( '', trim( $entrada->artefacto ) );
			self::assertNotSame( '', trim( $entrada->version ) );
			self::assertNotSame( '', trim( $entrada->licencia ) );
			self::assertNotSame( '', trim( $entrada->idiomas ) );
			self::assertNotSame( '', trim( $entrada->procedencia ) );
		}
	}

	public function test_construir_una_entrada_directamente_conserva_sus_valores(): void {
		$entrada = new ModeloRegistrado(
			RolModelo::Seg,
			'artefacto-de-prueba',
			'1.0',
			'MIT',
			'es-ES',
			'sha256:abc',
			null,
			'https://example.com'
		);

		self::assertSame( RolModelo::Seg, $entrada->rol );
		self::assertSame( 'sha256:abc', $entrada->checksum );
		self::assertNull( $entrada->motivoSinChecksum );
	}
}
