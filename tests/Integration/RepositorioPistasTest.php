<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\RepositorioPistas;
use Pluma\Kernel\RelojSistema;
use Pluma\Publicacion\EstadoPista;
use WP_UnitTestCase;

/**
 * Repositorio `pluma_pistas` contra tablas reales — Nivel Cuatro X.3
 * (Etapa 9, Porción 5).
 *
 * @covers \Pluma\Datos\RepositorioPistas
 */
final class RepositorioPistasTest extends WP_UnitTestCase {

	public function test_crear_persiste_pendiente_y_obtener_por_id_lo_recupera(): void {
		global $wpdb;
		$repo  = new RepositorioPistas( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->crear( 42, 'creo que hay más detrás de esta historia', 'lector@example.test', $reloj->ahora() );

		$pista = $repo->obtenerPorId( $id );

		self::assertNotNull( $pista );
		self::assertSame( 42, $pista->historiaId );
		self::assertSame( EstadoPista::Pendiente, $pista->estado );
	}

	public function test_actualizar_estado_persiste_la_transicion(): void {
		global $wpdb;
		$repo  = new RepositorioPistas( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->crear( 43, 'contenido', null, $reloj->ahora() );

		self::assertTrue( $repo->actualizarEstado( $id, EstadoPista::Revisada ) );
		self::assertSame( EstadoPista::Revisada, $repo->obtenerPorId( $id )->estado );
	}

	public function test_obtener_por_estado_solo_devuelve_ese_estado(): void {
		global $wpdb;
		$repo  = new RepositorioPistas( $wpdb );
		$reloj = new RelojSistema();

		$pendienteId  = $repo->crear( 44, 'pendiente', null, $reloj->ahora() );
		$descartadaId = $repo->crear( 45, 'descartada', null, $reloj->ahora() );
		$repo->actualizarEstado( $descartadaId, EstadoPista::Descartada );

		$pendientes = $repo->obtenerPorEstado( EstadoPista::Pendiente, 50 );
		$ids        = array_map( static fn ( $p ): int => $p->id, $pendientes );

		self::assertContains( $pendienteId, $ids );
		self::assertNotContains( $descartadaId, $ids );
	}
}
