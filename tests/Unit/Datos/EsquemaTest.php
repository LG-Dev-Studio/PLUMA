<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Datos;

use Pluma\Datos\Esquema;
use Pluma\Datos\ReversaNoDisponibleException;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use wpdb;

/**
 * @covers \Pluma\Datos\Esquema
 */
final class EsquemaTest extends CasoDePruebaUnitario {

	public function test_sentencias_reversa_desde_0_12_0_a_0_11_0_elimina_lo_que_esa_version_anadio(): void {
		$sentencias = Esquema::sentenciasReversaDesde( new wpdb(), '0.12.0', '0.11.0' );

		self::assertSame(
			array(
				'ALTER TABLE wp_pluma_periodistas_conducta_versiones DROP COLUMN respuestas_habilitadas;',
				'DROP TABLE IF EXISTS wp_pluma_respuestas_comentarios;',
			),
			$sentencias
		);
	}

	public function test_sentencias_reversa_desde_una_transicion_no_registrada_lanza_excepcion(): void {
		$this->expectException( ReversaNoDisponibleException::class );

		Esquema::sentenciasReversaDesde( new wpdb(), '99.0.0', '98.0.0' );
	}

	public function test_sentencias_reversa_desde_0_16_0_a_0_15_0_elimina_diversidad_fuente_y_motivo_legitimidad(): void {
		$sentencias = Esquema::sentenciasReversaDesde( new wpdb(), '0.16.0', '0.15.0' );

		self::assertSame(
			array(
				'ALTER TABLE wp_pluma_tendencias DROP COLUMN diversidad_fuente, DROP COLUMN motivo_legitimidad;',
			),
			$sentencias
		);
	}

	public function test_sentencias_reversa_desde_0_17_0_a_0_16_0_elimina_locale_editorial(): void {
		$sentencias = Esquema::sentenciasReversaDesde( new wpdb(), '0.17.0', '0.16.0' );

		self::assertSame(
			array(
				'ALTER TABLE wp_pluma_periodistas DROP COLUMN locale_editorial;',
			),
			$sentencias
		);
	}

	public function test_sentencias_reversa_desde_0_15_0_a_0_14_0_elimina_gravedad_y_modo_respeto(): void {
		$sentencias = Esquema::sentenciasReversaDesde( new wpdb(), '0.15.0', '0.14.0' );

		self::assertSame(
			array(
				'ALTER TABLE wp_pluma_tendencias DROP COLUMN gravedad, DROP COLUMN campo_tematico, DROP COLUMN campo_geografico;',
				'DROP TABLE IF EXISTS wp_pluma_modo_respeto;',
			),
			$sentencias
		);
	}

	public function test_sentencias_reversa_desde_0_14_0_a_0_13_0_elimina_el_indice_de_tema(): void {
		$sentencias = Esquema::sentenciasReversaDesde( new wpdb(), '0.14.0', '0.13.0' );

		self::assertSame(
			array( 'DROP INDEX tema ON wp_pluma_memoria_editorial;' ),
			$sentencias
		);
	}
}
