<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\RepositorioEventosProgramados;
use Pluma\Kernel\RelojSistema;
use Pluma\Pipeline\EstadoEventoProgramado;
use WP_UnitTestCase;

/**
 * Repositorio `pluma_eventos_programados` contra tablas reales — Nivel
 * Cuatro V.1 (Etapa 9, Porción 3).
 *
 * @covers \Pluma\Datos\RepositorioEventosProgramados
 */
final class RepositorioEventosProgramadosTest extends WP_UnitTestCase {

	public function test_crear_persiste_y_obtener_por_id_recupera_el_evento(): void {
		global $wpdb;
		$repo  = new RepositorioEventosProgramados( $wpdb );
		$reloj = new RelojSistema();

		$fechaEsperada = $reloj->ahora()->modify( '+10 days' );
		$id            = $repo->crear( 'Evento de prueba ' . uniqid(), 'economia', $fechaEsperada, 7, null, $reloj->ahora() );

		$evento = $repo->obtenerPorId( $id );

		self::assertNotNull( $evento );
		self::assertSame( 'economia', $evento->vertical );
		self::assertSame( EstadoEventoProgramado::Previsto, $evento->estado );
		self::assertSame( 7, $evento->periodistaAsignadoId );
		self::assertNull( $evento->historiaId );
		self::assertNull( $evento->tendenciaId );
	}

	public function test_actualizar_estado_persiste_la_transicion(): void {
		global $wpdb;
		$repo  = new RepositorioEventosProgramados( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->crear( 'Evento en curso ' . uniqid(), 'deportes', $reloj->ahora(), null, null, $reloj->ahora() );

		self::assertTrue( $repo->actualizarEstado( $id, EstadoEventoProgramado::Preparado, $reloj->ahora() ) );
		self::assertSame( EstadoEventoProgramado::Preparado, $repo->obtenerPorId( $id )->estado );

		self::assertTrue( $repo->actualizarEstado( $id, EstadoEventoProgramado::Cubierto, $reloj->ahora() ) );
		self::assertSame( EstadoEventoProgramado::Cubierto, $repo->obtenerPorId( $id )->estado );
	}

	public function test_vincular_tendencia_y_vincular_historia_persisten_el_enlace(): void {
		global $wpdb;
		$repo  = new RepositorioEventosProgramados( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->crear( 'Evento con enlaces ' . uniqid(), 'electoral', $reloj->ahora(), null, null, $reloj->ahora() );

		self::assertTrue( $repo->vincularTendencia( $id, 555, $reloj->ahora() ) );
		self::assertTrue( $repo->vincularHistoria( $id, 777, $reloj->ahora() ) );

		$evento = $repo->obtenerPorId( $id );
		self::assertSame( 555, $evento->tendenciaId );
		self::assertSame( 777, $evento->historiaId );
	}

	public function test_listar_ordena_por_fecha_esperada_ascendente(): void {
		global $wpdb;
		$repo  = new RepositorioEventosProgramados( $wpdb );
		$reloj = new RelojSistema();

		$tardio   = $repo->crear( 'Evento tardío ' . uniqid(), 'tecnologia', $reloj->ahora()->modify( '+30 days' ), null, null, $reloj->ahora() );
		$temprano = $repo->crear( 'Evento temprano ' . uniqid(), 'tecnologia', $reloj->ahora()->modify( '+2 days' ), null, null, $reloj->ahora() );

		$eventos = $repo->listar( 50 );
		$ids     = array_map( static fn ( $evento ): int => $evento->id, $eventos );

		self::assertContains( $temprano, $ids );
		self::assertContains( $tardio, $ids );
		self::assertLessThan( array_search( $tardio, $ids, true ), array_search( $temprano, $ids, true ) );
	}

	public function test_obtener_por_id_de_un_evento_inexistente_devuelve_null(): void {
		global $wpdb;
		$repo = new RepositorioEventosProgramados( $wpdb );

		self::assertNull( $repo->obtenerPorId( 999999 ) );
	}
}
