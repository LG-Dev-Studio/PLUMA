<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Publicacion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Mockery;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Datos\RepositorioSuscriptoresInterface;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\Pieza;
use Pluma\Proveedores\PushWebInterface;
use Pluma\Publicacion\GestorBoletines;
use Pluma\Publicacion\NotificadorSuscripciones;
use Pluma\Publicacion\PeriodistaNoEncontradoParaBoletinException;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\GeneradorBoletin;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;

/**
 * Nivel Cuatro W.1 — el boletín como producto del periodista.
 *
 * @covers \Pluma\Publicacion\GestorBoletines
 */
final class GestorBoletinesTest extends CasoDePruebaUnitario {

	private function periodista(): Periodista {
		$diales   = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas   = new ReglasConducta( 'linea editorial escéptica', array( 'nunca inventar cifras' ), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 1, 1, $diales, $reglas, $matriz, true, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

		return new Periodista(
			7,
			'Valentina Ruiz',
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array(),
			EstadoPeriodista::Activo,
			$conducta,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);
	}

	private function pieza( int $id, int $postId ): Pieza {
		$reloj = new DateTimeImmutable( '2026-07-22T12:00:00+00:00' );

		return new Pieza( $id, 100, EstadoPieza::Publicada, null, $postId, $reloj, $reloj, 7 );
	}

	private function construir( RepositorioPeriodistasInterface $periodistas, RepositorioPiezasInterface $piezas, string $jsonRespuesta ): GestorBoletines {
		$generador    = new GeneradorBoletin( new ProveedorLenguajeFalso( $jsonRespuesta ) );
		$suscriptores = Mockery::mock( RepositorioSuscriptoresInterface::class );
		$suscriptores->allows( 'obtenerConfirmadosPorObjetivo' )->andReturn( array() );
		$notificador = new NotificadorSuscripciones( $suscriptores, Mockery::mock( PushWebInterface::class ) );

		return new GestorBoletines( $periodistas, $piezas, $generador, $notificador );
	}

	public function test_enviar_lanza_si_el_periodista_no_existe(): void {
		$periodistas = Mockery::mock( RepositorioPeriodistasInterface::class );
		$periodistas->expects( 'obtenerPorId' )->with( 999 )->andReturn( null );

		$this->expectException( PeriodistaNoEncontradoParaBoletinException::class );

		$this->construir( $periodistas, Mockery::mock( RepositorioPiezasInterface::class ), '{}' )->enviar( 999 );
	}

	public function test_enviar_sin_piezas_recientes_no_hace_nada(): void {
		$periodistas = Mockery::mock( RepositorioPeriodistasInterface::class );
		$periodistas->expects( 'obtenerPorId' )->with( 7 )->andReturn( $this->periodista() );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPublicadasRecientesPorPeriodista' )->with( 7, Mockery::any() )->andReturn( array() );

		$resultado = $this->construir( $periodistas, $piezas, '{}' )->enviar( 7 );

		self::assertSame(
			array(
				'piezas' => 0,
				'email'  => 0,
				'push'   => 0,
			),
			$resultado
		);
	}

	public function test_enviar_compone_y_despacha_el_boletin(): void {
		Functions\when( 'get_post' )->justReturn( (object) array( 'post_title' => 'Los precios suben otra vez' ) );
		Functions\when( 'get_permalink' )->justReturn( 'https://ejemplo.test/piezas/1' );
		Functions\when( '__' )->returnArg();

		$periodistas = Mockery::mock( RepositorioPeriodistasInterface::class );
		$periodistas->expects( 'obtenerPorId' )->with( 7 )->andReturn( $this->periodista() );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPublicadasRecientesPorPeriodista' )->with( 7, Mockery::any() )->andReturn( array( $this->pieza( 1, 50 ) ) );

		$resultado = $this->construir( $periodistas, $piezas, '{"apertura": "Esta semana volví sobre la inflación."}' )->enviar( 7 );

		self::assertSame( 1, $resultado['piezas'] );
	}
}
