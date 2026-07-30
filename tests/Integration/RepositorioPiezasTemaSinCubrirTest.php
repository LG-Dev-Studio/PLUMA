<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use DateTimeImmutable;
use Pluma\Datos\RepositorioPiezas;
use Pluma\Pipeline\EstadoPieza;
use WP_UnitTestCase;

/**
 * Trabajo posterior a la Etapa 9 (creación automática de periodistas):
 * `actualizarTemaSinCubrir()` persiste el `$clasificacion->tema` que
 * `NingunPeriodistaIdoneoException` ya calculaba y descartaba — señal real
 * que `CreadorAutomaticoPeriodistas` consume.
 *
 * @covers \Pluma\Datos\RepositorioPiezas
 */
final class RepositorioPiezasTemaSinCubrirTest extends WP_UnitTestCase {

	public function test_actualizar_tema_sin_cubrir_lo_persiste_y_obtener_por_estado_entre_lo_devuelve(): void {
		global $wpdb;
		$repo    = new RepositorioPiezas( $wpdb );
		$prefijo = $wpdb->prefix . 'pluma_';

		$wpdb->insert(
			$prefijo . 'tendencias',
			array(
				'termino'                => 'tendencia real para tema sin cubrir ' . uniqid(),
				'fuente_senal'           => 'google_trends',
				'puntuacion_velocidad'   => 50,
				'puntuacion_afinidad'    => 50,
				'puntuacion_total'       => 50,
				'articulos_relacionados' => '[]',
				'detectada_en'           => '2026-01-01 00:00:00',
				'creada_en'              => '2026-01-01 00:00:00',
			)
		);
		$tendenciaId = (int) $wpdb->insert_id;

		$wpdb->insert(
			$prefijo . 'piezas',
			array(
				'tendencia_id'   => $tendenciaId,
				'estado'         => EstadoPieza::SinPeriodistaIdoneo->value,
				'creada_en'      => '2026-01-01 00:00:00',
				'actualizada_en' => '2026-01-01 00:00:00',
			)
		);
		$piezaId = (int) $wpdb->insert_id;

		try {
			$resultado = $repo->actualizarTemaSinCubrir( $piezaId, 'deportes', new DateTimeImmutable() );

			self::assertTrue( $resultado );

			$pieza = $repo->obtenerPorId( $piezaId );
			self::assertNotNull( $pieza );
			self::assertSame( 'deportes', $pieza->temaSinCubrir );

			$ahora     = new DateTimeImmutable( '+1 minute' );
			$desde     = new DateTimeImmutable( '-1 day' );
			$elegibles = $repo->obtenerPorEstadoEntre( EstadoPieza::SinPeriodistaIdoneo, $desde, $ahora, 50 );

			self::assertContains( $piezaId, array_map( static fn ( $p ) => $p->id, $elegibles ) );
		} finally {
			$wpdb->delete( $prefijo . 'piezas', array( 'id' => $piezaId ) );
			$wpdb->delete( $prefijo . 'tendencias', array( 'id' => $tendenciaId ) );
		}
	}
}
