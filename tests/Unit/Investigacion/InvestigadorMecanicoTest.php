<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Investigacion;

use Brain\Monkey\Functions;
use Pluma\Investigacion\EstadoProcedenciaDeclaracion;
use Pluma\Investigacion\InvestigadorMecanico;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Investigacion\VerificadorProcedenciaDeclaracion;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * @covers \Pluma\Investigacion\InvestigadorMecanico
 */
final class InvestigadorMecanicoTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'get_option' )->justReturn( array() );
	}

	private function investigador(): InvestigadorMecanico {
		return new InvestigadorMecanico( new RelojFijo(), new VerificadorProcedenciaDeclaracion() );
	}

	public function test_cada_articulo_se_convierte_en_un_hecho_atribuido(): void {
		$expediente = $this->investigador()->investigar(
			'una tendencia',
			array(
				array(
					'titulo' => 'El ayuntamiento aprueba la partida de 4 millones',
					'url'    => 'https://example.com/nota-1',
					'fuente' => 'example.com',
				),
			)
		);

		self::assertSame( 'una tendencia', $expediente->tendenciaOrigen );
		self::assertCount( 1, $expediente->hechos );
		self::assertSame( 'El ayuntamiento aprueba la partida de 4 millones', $expediente->hechos[0]->extracto );
		self::assertSame( NivelVerificacion::Atribuido, $expediente->hechos[0]->nivel );
	}

	/**
	 * Nivel Tres L.1: un título con una declaración textual atribuida sin
	 * canal oficial configurado se marca `NoVerificada`.
	 */
	public function test_un_articulo_con_declaracion_atribuida_activa_la_verificacion_de_procedencia(): void {
		$expediente = $this->investigador()->investigar(
			'una tendencia',
			array(
				array(
					'titulo' => 'El ministro afirmó que la reforma entrará en vigor en enero',
					'url'    => 'https://example.com/nota-1',
					'fuente' => 'example.com',
				),
			)
		);

		self::assertSame( EstadoProcedenciaDeclaracion::NoVerificada, $expediente->hechos[0]->procedenciaDeclaracion );
	}

	public function test_un_articulo_sin_declaracion_atribuida_no_aplica(): void {
		$expediente = $this->investigador()->investigar(
			'una tendencia',
			array(
				array(
					'titulo' => 'La producción industrial creció un 4% en el trimestre',
					'url'    => 'https://example.com/nota-1',
					'fuente' => 'example.com',
				),
			)
		);

		self::assertSame( EstadoProcedenciaDeclaracion::NoAplica, $expediente->hechos[0]->procedenciaDeclaracion );
	}
}
