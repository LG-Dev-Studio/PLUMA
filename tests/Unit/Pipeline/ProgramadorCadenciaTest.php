<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Pipeline;

use DateTimeImmutable;
use Pluma\Kernel\AzarInterface;
use Pluma\Pipeline\ConfiguracionCadencia;
use Pluma\Pipeline\ProgramadorCadencia;
use Pluma\Pipeline\VentanaPublicacion;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Pipeline\ProgramadorCadencia
 */
final class ProgramadorCadenciaTest extends CasoDePruebaUnitario {

	private function config( int $jitterMaximoMinutos = 15 ): ConfiguracionCadencia {
		return new ConfiguracionCadencia(
			6,
			3,
			8,
			array( new VentanaPublicacion( 7, 9, 3 ) ),
			45,
			$jitterMaximoMinutos,
			array(),
			array()
		);
	}

	/**
	 * Nivel Dos F.3: "jitter de horario recalculado desde cero" — conserva
	 * la franja horaria (misma hora, minutos a cero) y redibuja el jitter.
	 */
	public function test_rejitter_conserva_la_hora_y_redibuja_los_minutos(): void {
		$azar = $this->createMock( AzarInterface::class );
		$azar->expects( self::once() )->method( 'entero' )->with( 0, 15 )->willReturn( 7 );

		$horaProgramada = new DateTimeImmutable( '2026-07-27T19:42:00+00:00' );
		$nuevaHora      = ( new ProgramadorCadencia( $azar ) )->rejitter( $this->config(), $horaProgramada );

		self::assertSame( '2026-07-27 19:07:00', $nuevaHora->format( 'Y-m-d H:i:s' ) );
	}

	public function test_rejitter_sin_jitter_configurado_no_llama_al_azar(): void {
		$azar = $this->createMock( AzarInterface::class );
		$azar->expects( self::never() )->method( 'entero' );

		$horaProgramada = new DateTimeImmutable( '2026-07-27T19:42:00+00:00' );
		$nuevaHora      = ( new ProgramadorCadencia( $azar ) )->rejitter( $this->config( 0 ), $horaProgramada );

		self::assertSame( '2026-07-27 19:00:00', $nuevaHora->format( 'Y-m-d H:i:s' ) );
	}
}
