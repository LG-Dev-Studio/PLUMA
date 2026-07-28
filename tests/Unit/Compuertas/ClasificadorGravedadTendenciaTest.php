<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Compuertas;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Compuertas\ClasificadorGravedadTendencia;
use Pluma\Compuertas\CompuertaException;
use Pluma\Compuertas\GravedadTendenciaException;
use Pluma\Sensores\PuntuacionOportunidad;
use Pluma\Sensores\TendenciaDetectada;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;

/**
 * Nivel Dos F.1-F.2: clasifica gravedad/campo temático/campo geográfico de
 * una tendencia recién detectada — el eje de gravedad que el Radar nunca
 * calculó hasta ahora.
 *
 * @covers \Pluma\Compuertas\ClasificadorGravedadTendencia
 */
final class ClasificadorGravedadTendenciaTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( false );
	}

	private function tendencia(): TendenciaDetectada {
		return new TendenciaDetectada(
			'atentado en la capital',
			PuntuacionOportunidad::calcular( 80.0, 90.0 ),
			new DateTimeImmutable( '2026-07-27T12:00:00+00:00' ),
			array(
				array(
					'titulo' => 'Ataque deja varios heridos',
					'url'    => 'https://example.com',
					'fuente' => 'Agencia X',
				),
			),
			'google_trends'
		);
	}

	public function test_clasifica_gravedad_y_campos(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"gravedad": 95, "campoTematico": "atentado", "campoGeografico": "Francia"}' );

		$resultado = ( new ClasificadorGravedadTendencia( $proveedor ) )->clasificar( $this->tendencia() );

		self::assertSame( 95, $resultado->gravedad );
		self::assertSame( 'atentado', $resultado->campoTematico );
		self::assertSame( 'Francia', $resultado->campoGeografico );
	}

	public function test_acepta_campo_geografico_nulo(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"gravedad": 10, "campoTematico": "cultura_viral", "campoGeografico": null}' );

		$resultado = ( new ClasificadorGravedadTendencia( $proveedor ) )->clasificar( $this->tendencia() );

		self::assertNull( $resultado->campoGeografico );
	}

	public function test_recorta_la_gravedad_al_rango_0_100(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"gravedad": 150, "campoTematico": "x", "campoGeografico": null}' );

		$resultado = ( new ClasificadorGravedadTendencia( $proveedor ) )->clasificar( $this->tendencia() );

		self::assertSame( 100, $resultado->gravedad );
	}

	public function test_lanza_excepcion_si_falta_un_campo(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"gravedad": 50, "campoGeografico": null}' );

		$this->expectException( GravedadTendenciaException::class );

		( new ClasificadorGravedadTendencia( $proveedor ) )->clasificar( $this->tendencia() );
	}

	public function test_lanza_excepcion_si_la_respuesta_llego_truncada(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"gravedad": 50, "campoTematico": "x", "campoGeografico": null}', truncada: true );

		$this->expectException( CompuertaException::class );

		( new ClasificadorGravedadTendencia( $proveedor ) )->clasificar( $this->tendencia() );
	}
}
