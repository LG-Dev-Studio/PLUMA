<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Investigacion;

use DateTimeImmutable;
use Pluma\Investigacion\EstadoCorroboracionAudiovisual;
use Pluma\Investigacion\EstadoProcedenciaDeclaracion;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Investigacion\HechoFuente
 */
final class HechoFuenteTest extends CasoDePruebaUnitario {

	public function test_los_nuevos_campos_por_defecto_son_no_aplica(): void {
		$hecho = new HechoFuente( 'un hecho', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido );

		self::assertSame( EstadoProcedenciaDeclaracion::NoAplica, $hecho->procedenciaDeclaracion );
		self::assertSame( EstadoCorroboracionAudiovisual::NoAplica, $hecho->corroboracionAudiovisual );
	}

	public function test_aarray_y_desdearray_conservan_los_nuevos_campos(): void {
		$original = new HechoFuente(
			'una declaración citada',
			'https://example.com',
			new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ),
			NivelVerificacion::Verificado,
			EstadoProcedenciaDeclaracion::VerificadaCanalOficial,
			EstadoCorroboracionAudiovisual::CorroboradaIndependientemente
		);

		$reconstruido = HechoFuente::desdeArray( $original->aArray() );

		self::assertSame( EstadoProcedenciaDeclaracion::VerificadaCanalOficial, $reconstruido->procedenciaDeclaracion );
		self::assertSame( EstadoCorroboracionAudiovisual::CorroboradaIndependientemente, $reconstruido->corroboracionAudiovisual );
	}

	/**
	 * Compatibilidad retroactiva: un hecho persistido ANTES de esta porción
	 * no tiene estas dos claves en su JSON — `desdeArray()` debe asumir
	 * `NoAplica`, nunca romper la lectura de expedientes ya guardados.
	 */
	public function test_desdearray_sin_las_claves_nuevas_usa_no_aplica(): void {
		$hecho = HechoFuente::desdeArray(
			array(
				'extracto' => 'un hecho antiguo',
				'url'      => 'https://example.com',
				'fecha'    => '2026-07-22T12:00:00+00:00',
				'nivel'    => 'atribuido',
			)
		);

		self::assertSame( EstadoProcedenciaDeclaracion::NoAplica, $hecho->procedenciaDeclaracion );
		self::assertSame( EstadoCorroboracionAudiovisual::NoAplica, $hecho->corroboracionAudiovisual );
	}
}
