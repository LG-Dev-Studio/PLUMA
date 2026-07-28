<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\RepositorioColaPublicacion;
use Pluma\Kernel\Activador;
use Pluma\Kernel\RelojSistema;
use Pluma\Pipeline\EstadoColaPublicacion;
use WP_UnitTestCase;

/**
 * Repositorio `pluma_cola_publicacion` (Libro Cap. 9.2-9.3) contra tabla
 * real (pl-testing: "Integración (wp-env): repositorios pluma_*").
 *
 * Tabla GENUINAMENTE NUEVA (igual que `pluma_vocabulario`, ver
 * `RepositorioVocabularioTest`): el esquema se activa en
 * `set_up_before_class()`, antes de que `WP_UnitTestCase` instale el filtro
 * `_create_temporary_tables` que convertiría el `CREATE TABLE` en una tabla
 * temporal que desaparecería con el `ROLLBACK` entre tests.
 *
 * @covers \Pluma\Datos\RepositorioColaPublicacion
 */
final class RepositorioColaPublicacionTest extends WP_UnitTestCase {

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Activador::activar( new RelojSistema(), '0.9.0' );
	}

	public function test_programar_y_obtener_entre_dos_fechas(): void {
		global $wpdb;
		$repo  = new RepositorioColaPublicacion( $wpdb );
		$reloj = new RelojSistema();

		$hoy    = $reloj->ahora()->setTime( 0, 0 );
		$manana = $hoy->modify( '+1 day' );

		$id = $repo->programar( 1001, 'economia', 5, $hoy->modify( '+10 hours' ), $reloj->ahora() );

		self::assertGreaterThan( 0, $id );

		$ranuras = $repo->obtenerEntre( $hoy, $manana );

		self::assertNotEmpty(
			array_filter( $ranuras, static fn ( $r ): bool => $r->id === $id )
		);
	}

	public function test_obtener_entre_excluye_ranuras_expiradas(): void {
		global $wpdb;
		$repo  = new RepositorioColaPublicacion( $wpdb );
		$reloj = new RelojSistema();

		$hoy    = $reloj->ahora()->setTime( 0, 0 );
		$manana = $hoy->modify( '+1 day' );

		$id = $repo->programar( 1002, 'cultura', null, $hoy->modify( '+11 hours' ), $reloj->ahora() );
		self::assertTrue( $repo->marcarExpirada( $id ) );

		$ranuras = $repo->obtenerEntre( $hoy, $manana );

		self::assertEmpty( array_filter( $ranuras, static fn ( $r ): bool => $r->id === $id ) );
	}

	public function test_obtener_vencidas_solo_devuelve_programadas_con_hora_ya_cumplida(): void {
		global $wpdb;
		$repo  = new RepositorioColaPublicacion( $wpdb );
		$reloj = new RelojSistema();

		$idPasada = $repo->programar( 1003, 'economia', 5, $reloj->ahora()->modify( '-1 hour' ), $reloj->ahora() );
		$idFutura = $repo->programar( 1004, 'economia', 5, $reloj->ahora()->modify( '+1 hour' ), $reloj->ahora() );

		$vencidas    = $repo->obtenerVencidas( $reloj->ahora() );
		$idsVencidos = array_map( static fn ( $r ) => $r->id, $vencidas );

		self::assertContains( $idPasada, $idsVencidos );
		self::assertNotContains( $idFutura, $idsVencidos );
	}

	public function test_marcar_publicada_saca_la_ranura_de_vencidas(): void {
		global $wpdb;
		$repo  = new RepositorioColaPublicacion( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->programar( 1005, 'economia', 5, $reloj->ahora()->modify( '-1 hour' ), $reloj->ahora() );

		self::assertTrue( $repo->marcarPublicada( $id ) );

		$vencidas = $repo->obtenerVencidas( $reloj->ahora() );

		self::assertEmpty( array_filter( $vencidas, static fn ( $r ): bool => $r->id === $id ) );
	}

	public function test_la_ranura_persistida_conserva_todos_sus_campos(): void {
		global $wpdb;
		$repo  = new RepositorioColaPublicacion( $wpdb );
		$reloj = new RelojSistema();

		$hoy    = $reloj->ahora()->setTime( 0, 0 );
		$manana = $hoy->modify( '+1 day' );
		$hora   = $hoy->modify( '+9 hours' );

		$id = $repo->programar( 1006, 'politica', 7, $hora, $reloj->ahora() );

		$ranura = current( array_filter( $repo->obtenerEntre( $hoy, $manana ), static fn ( $r ) => $r->id === $id ) );

		self::assertNotFalse( $ranura );
		self::assertSame( 1006, $ranura->piezaId );
		self::assertSame( 'politica', $ranura->vertical );
		self::assertSame( 7, $ranura->periodistaId );
		self::assertSame( EstadoColaPublicacion::Programada, $ranura->estado );
	}

	/**
	 * Nivel Dos F.3, modo respeto: pausar TODAS las ranuras `programada` —
	 * nunca las descarta — y que `obtenerVencidas()` deje de verlas
	 * mientras estén pausadas.
	 */
	public function test_pausar_programadas_las_saca_de_vencidas_y_las_deja_en_obtener_pausadas(): void {
		global $wpdb;
		$repo  = new RepositorioColaPublicacion( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->programar( 2001, 'economia', 5, $reloj->ahora()->modify( '-1 hour' ), $reloj->ahora() );
		self::assertContains( $id, array_map( static fn ( $r ) => $r->id, $repo->obtenerVencidas( $reloj->ahora() ) ) );

		$afectadas = $repo->pausarProgramadas();

		self::assertGreaterThanOrEqual( 1, $afectadas );
		self::assertNotContains( $id, array_map( static fn ( $r ) => $r->id, $repo->obtenerVencidas( $reloj->ahora() ) ) );
		self::assertContains( $id, array_map( static fn ( $r ) => $r->id, $repo->obtenerPausadas() ) );
	}

	/**
	 * Nivel Dos F.3: `reprogramar()` reactiva una ranura pausada con una
	 * hora nueva — vuelve a `programada` y desaparece de `obtenerPausadas()`.
	 */
	public function test_reprogramar_reactiva_una_ranura_pausada_con_nueva_hora(): void {
		global $wpdb;
		$repo  = new RepositorioColaPublicacion( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->programar( 2002, 'cultura', null, $reloj->ahora(), $reloj->ahora() );
		$repo->pausarProgramadas();
		self::assertContains( $id, array_map( static fn ( $r ) => $r->id, $repo->obtenerPausadas() ) );

		$nuevaHora = $reloj->ahora()->modify( '+2 hours' );
		self::assertTrue( $repo->reprogramar( $id, $nuevaHora ) );

		self::assertNotContains( $id, array_map( static fn ( $r ) => $r->id, $repo->obtenerPausadas() ) );
		$hoy    = $reloj->ahora()->setTime( 0, 0 );
		$manana = $hoy->modify( '+1 day' );
		$ranura = current( array_filter( $repo->obtenerEntre( $hoy, $manana ), static fn ( $r ) => $r->id === $id ) );
		self::assertNotFalse( $ranura );
		self::assertSame( EstadoColaPublicacion::Programada, $ranura->estado );
		self::assertEquals( $nuevaHora->format( 'Y-m-d H:i' ), $ranura->horaProgramada->format( 'Y-m-d H:i' ) );
	}
}
