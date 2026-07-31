<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Publicacion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Mockery;
use Pluma\Datos\RepositorioDerivadosSocialesInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Datos\RepositorioSuscriptoresInterface;
use Pluma\Datos\RepositorioTendenciasInterface;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\Pieza;
use Pluma\Proveedores\PushWebInterface;
use Pluma\Publicacion\CanalSuscripcion;
use Pluma\Publicacion\GestorDerivadosSociales;
use Pluma\Publicacion\NotificadorSuscripciones;
use Pluma\Publicacion\TipoSuscripcion;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\GeneradorDerivadoSocial;
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
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Nivel Cuatro W.2 (derivados) + W.3 (alertas de última hora).
 *
 * @covers \Pluma\Publicacion\GestorDerivadosSociales
 */
final class GestorDerivadosSocialesTest extends CasoDePruebaUnitario {

	private function periodista(): Periodista {
		$diales   = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas   = new ReglasConducta( 'linea editorial escéptica', array( 'nunca inventar cifras' ), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 1, 1, $diales, $reglas, $matriz, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

		return new Periodista( 7, 'Valentina Ruiz', null, 'Bio.', RolPeriodista::Columnista, array(), EstadoPeriodista::Activo, $conducta, new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ), new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ) );
	}

	private function pieza( int $tendenciaId = 100 ): Pieza {
		$reloj = new DateTimeImmutable( '2026-07-22T12:00:00+00:00' );

		return new Pieza( 1, $tendenciaId, EstadoPieza::Publicada, null, 50, $reloj, $reloj, 7 );
	}

	public function test_pieza_sin_post_id_se_ignora(): void {
		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$reloj  = new DateTimeImmutable( '2026-07-22T12:00:00+00:00' );
		$piezas->expects( 'obtenerPorId' )->with( 1 )->andReturn( new Pieza( 1, 100, EstadoPieza::Publicada, null, null, $reloj, $reloj, 7 ) );

		$periodistas = Mockery::mock( RepositorioPeriodistasInterface::class );
		$periodistas->expects( 'obtenerPorId' )->never();

		$gestor = $this->construir( $piezas, $periodistas, Mockery::mock( RepositorioTendenciasInterface::class ), Mockery::mock( RepositorioDerivadosSocialesInterface::class ), '{}' );

		$gestor->procesarPublicacion( 1 );

		self::assertTrue( true );
	}

	public function test_genera_y_persiste_el_derivado_social(): void {
		Functions\when( 'get_post' )->justReturn(
			(object) array(
				'post_title'   => 'La inflación vuelve a subir',
				'post_content' => 'contenido de la pieza sobre inflación',
			)
		);
		Functions\when( 'wp_strip_all_tags' )->returnArg();
		Functions\when( 'wp_trim_words' )->returnArg();
		Functions\when( 'get_permalink' )->justReturn( 'https://ejemplo.test/piezas/1' );
		Functions\when( 'get_option' )->justReturn( false );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorId' )->with( 1 )->andReturn( $this->pieza() );

		$periodistas = Mockery::mock( RepositorioPeriodistasInterface::class );
		$periodistas->expects( 'obtenerPorId' )->with( 7 )->andReturn( $this->periodista() );

		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'obtenerPorId' )->with( 100 )->andReturn( array( 'gravedad' => 20 ) );

		$derivados = Mockery::mock( RepositorioDerivadosSocialesInterface::class );
		$derivados->expects( 'crear' )->with( 1, 'Extracto social', 'Titular Discover', Mockery::any() )->andReturn( 5 );

		$gestor = $this->construir( $piezas, $periodistas, $tendencias, $derivados, '{"extractoSocial": "Extracto social", "titularDiscover": "Titular Discover"}' );

		$gestor->procesarPublicacion( 1 );

		self::assertTrue( true );
	}

	public function test_gravedad_alta_dispara_alerta_urgente(): void {
		Functions\when( 'get_post' )->justReturn(
			(object) array(
				'post_title'   => 'Terremoto de gran magnitud',
				'post_content' => 'contenido',
			)
		);
		Functions\when( 'wp_strip_all_tags' )->returnArg();
		Functions\when( 'wp_trim_words' )->returnArg();
		Functions\when( 'get_permalink' )->justReturn( 'https://ejemplo.test/piezas/1' );
		Functions\when( 'get_option' )->justReturn( false );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorId' )->with( 1 )->andReturn( $this->pieza() );

		$periodistas = Mockery::mock( RepositorioPeriodistasInterface::class );
		$periodistas->expects( 'obtenerPorId' )->with( 7 )->andReturn( $this->periodista() );

		$tendencias = Mockery::mock( RepositorioTendenciasInterface::class );
		$tendencias->expects( 'obtenerPorId' )->with( 100 )->andReturn( array( 'gravedad' => 95 ) );

		$derivados = Mockery::mock( RepositorioDerivadosSocialesInterface::class );
		$derivados->allows( 'crear' )->andReturn( 5 );

		$suscriptores = Mockery::mock( RepositorioSuscriptoresInterface::class );
		$suscriptores->expects( 'obtenerConfirmadosPorObjetivo' )->with( CanalSuscripcion::Email, TipoSuscripcion::AlertaUrgente, null, null )->andReturn( array() );
		$suscriptores->expects( 'obtenerConfirmadosPorObjetivo' )->with( CanalSuscripcion::Push, TipoSuscripcion::AlertaUrgente, null, null )->andReturn( array() );

		$notificador = new NotificadorSuscripciones( $suscriptores, Mockery::mock( PushWebInterface::class ) );
		$generador   = new GeneradorDerivadoSocial( new ProveedorLenguajeFalso( '{"extractoSocial": "Extracto", "titularDiscover": "Titular"}' ) );

		$gestor = new GestorDerivadosSociales( $piezas, $periodistas, $tendencias, $derivados, $generador, $notificador, new RelojFijo() );

		$gestor->procesarPublicacion( 1 );

		self::assertTrue( true );
	}

	private function construir(
		RepositorioPiezasInterface $piezas,
		RepositorioPeriodistasInterface $periodistas,
		RepositorioTendenciasInterface $tendencias,
		RepositorioDerivadosSocialesInterface $derivados,
		string $jsonRespuesta
	): GestorDerivadosSociales {
		$suscriptores = Mockery::mock( RepositorioSuscriptoresInterface::class );
		$suscriptores->allows( 'obtenerConfirmadosPorObjetivo' )->andReturn( array() );
		$notificador = new NotificadorSuscripciones( $suscriptores, Mockery::mock( PushWebInterface::class ) );
		$generador   = new GeneradorDerivadoSocial( new ProveedorLenguajeFalso( $jsonRespuesta ) );

		return new GestorDerivadosSociales( $piezas, $periodistas, $tendencias, $derivados, $generador, $notificador, new RelojFijo() );
	}
}
