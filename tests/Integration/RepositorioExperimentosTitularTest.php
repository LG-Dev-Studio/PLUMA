<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\RepositorioExperimentosTitular;
use Pluma\Kernel\RelojSistema;
use WP_UnitTestCase;

/**
 * Repositorio `pluma_experimentos_titular` contra tablas reales — Nivel
 * Cuatro Y.2 (Etapa 9, Porción 5).
 *
 * @covers \Pluma\Datos\RepositorioExperimentosTitular
 */
final class RepositorioExperimentosTitularTest extends WP_UnitTestCase {

	public function test_crear_persiste_y_obtener_por_post_id_lo_recupera(): void {
		global $wpdb;
		$repo  = new RepositorioExperimentosTitular( $wpdb );
		$reloj = new RelojSistema();

		$repo->crear( 1, 501, 'Titular A', 'Titular B', $reloj->ahora() );

		$experimento = $repo->obtenerPorPostId( 501 );

		self::assertNotNull( $experimento );
		self::assertSame( 'Titular A', $experimento->tituloA );
		self::assertSame( 'Titular B', $experimento->tituloB );
		self::assertSame( 0, $experimento->impresionesA );
		self::assertNull( $experimento->tituloGanador );
	}

	public function test_incrementar_impresion_y_clic_por_variante(): void {
		global $wpdb;
		$repo  = new RepositorioExperimentosTitular( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->crear( 2, 502, 'Titular A', 'Titular B', $reloj->ahora() );

		$repo->incrementarImpresion( $id, 'a' );
		$repo->incrementarImpresion( $id, 'a' );
		$repo->incrementarImpresion( $id, 'b' );
		$repo->incrementarClic( $id, 'a' );
		$repo->incrementarClic( $id, 'b' );
		$repo->incrementarClic( $id, 'b' );

		$experimento = $repo->obtenerPorPostId( 502 );

		self::assertSame( 2, $experimento->impresionesA );
		self::assertSame( 1, $experimento->impresionesB );
		self::assertSame( 1, $experimento->clicsA );
		self::assertSame( 2, $experimento->clicsB );
	}

	public function test_obtener_por_post_id_no_devuelve_experimentos_ya_consolidados(): void {
		global $wpdb;
		$repo  = new RepositorioExperimentosTitular( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->crear( 3, 503, 'Titular A', 'Titular B', $reloj->ahora() );
		$repo->consolidar( $id, 'b', $reloj->ahora() );

		self::assertNull( $repo->obtenerPorPostId( 503 ) );
	}

	public function test_obtener_listos_para_consolidar_respeta_el_limite_de_creacion(): void {
		global $wpdb;
		$repo  = new RepositorioExperimentosTitular( $wpdb );
		$reloj = new RelojSistema();

		$antiguoId  = $repo->crear( 4, 504, 'Titular A', 'Titular B', $reloj->ahora()->modify( '-2 days' ) );
		$recienteId = $repo->crear( 5, 505, 'Titular A', 'Titular B', $reloj->ahora() );

		$listos = $repo->obtenerListosParaConsolidar( $reloj->ahora()->modify( '-1 day' ), 50 );
		$ids    = array_map( static fn ( $e ): int => $e->id, $listos );

		self::assertContains( $antiguoId, $ids );
		self::assertNotContains( $recienteId, $ids );
	}

	public function test_consolidar_marca_ganador_y_fecha(): void {
		global $wpdb;
		$repo  = new RepositorioExperimentosTitular( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->crear( 6, 506, 'Titular A', 'Titular B', $reloj->ahora() );

		self::assertTrue( $repo->consolidar( $id, 'b', $reloj->ahora() ) );

		$listos = $repo->obtenerListosParaConsolidar( $reloj->ahora(), 50 );
		self::assertNotContains( $id, array_map( static fn ( $e ): int => $e->id, $listos ) );
	}
}
