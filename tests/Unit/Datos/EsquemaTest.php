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

		Esquema::sentenciasReversaDesde( new wpdb(), '0.14.0', '0.13.0' );
	}
}
