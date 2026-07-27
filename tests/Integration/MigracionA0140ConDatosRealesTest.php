<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\Esquema;
use Pluma\Datos\Migrador;
use WP_UnitTestCase;

/**
 * GOVERNANCE §5.1: "migración de esquema probada sobre copia de datos reales
 * de la versión anterior (N-1 → N verificado siempre)". Transición real
 * 0.13.0→0.14.0 (Etapa 8, porción 6, Nivel Dos E.2): nuevo índice `tema` en
 * solitario sobre `pluma_memoria_editorial`, para la consulta agregada de
 * memoria colectiva del sitio (across periodistas) — ver
 * `Esquema::sentenciasReversaDesde()`.
 *
 * Solo `CREATE INDEX`/`DROP INDEX` en ambos sentidos (ninguna columna nueva,
 * ningún `DROP TABLE`), así que — igual que `MigracionA0130ConDatosRealesTest`
 * — no hace falta quitar los filtros de tabla temporal de `WP_UnitTestCase`.
 *
 * @covers \Pluma\Datos\Migrador
 * @covers \Pluma\Datos\Esquema
 */
final class MigracionA0140ConDatosRealesTest extends WP_UnitTestCase {

	public function test_migrar_hacia_0_14_0_preserva_datos_reales_y_anade_el_indice_de_tema(): void {
		global $wpdb;
		$prefijo  = $wpdb->prefix . 'pluma_';
		$migrador = new Migrador( $wpdb );

		// Simula el shape previo a 0.14.0.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$wpdb->query( "DROP INDEX tema ON {$prefijo}memoria_editorial;" );
		$migrador->migrar( '0.13.0', array() );

		$wpdb->insert(
			$prefijo . 'memoria_editorial',
			array(
				'periodista_id' => 999999,
				'tipo'          => 'postura',
				'tema'          => 'dato real sembrado antes de migrar a 0.14.0',
				'contenido'     => '{"postura":"x"}',
				'creada_en'     => '2026-01-01 00:00:00',
			)
		);
		$entradaIdSembrada = (int) $wpdb->insert_id;

		// Migra hacia adelante con los datos reales ya sembrados en el shape anterior.
		$migrador->migrar( '0.14.0', Esquema::sentenciasCreateTable( $wpdb ) );

		try {
			self::assertSame( '0.14.0', $migrador->versionInstalada() );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$indices        = $wpdb->get_results( "SHOW INDEX FROM {$prefijo}memoria_editorial", ARRAY_A );
			$nombresIndices = array_map( static fn ( array $fila ): string => (string) $fila['Key_name'], $indices ?? array() );
			self::assertContains( 'tema', $nombresIndices );

			// El dato real sembrado en el shape anterior sobrevive intacto.
			$tema = $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
				$wpdb->prepare( "SELECT tema FROM {$prefijo}memoria_editorial WHERE id = %d", $entradaIdSembrada )
			);
			self::assertSame( 'dato real sembrado antes de migrar a 0.14.0', $tema );
		} finally {
			$wpdb->delete( $prefijo . 'memoria_editorial', array( 'id' => $entradaIdSembrada ) );
		}
	}

	public function test_revertir_a_0_13_0_elimina_solo_el_indice_de_tema(): void {
		global $wpdb;
		$prefijo  = $wpdb->prefix . 'pluma_';
		$migrador = new Migrador( $wpdb );

		$migrador->migrar( '0.14.0', Esquema::sentenciasCreateTable( $wpdb ) );

		$wpdb->insert(
			$prefijo . 'memoria_editorial',
			array(
				'periodista_id' => 999998,
				'tipo'          => 'postura',
				'tema'          => 'dato real que sobrevive a la reversa',
				'contenido'     => '{"postura":"x"}',
				'creada_en'     => '2026-01-01 00:00:00',
			)
		);
		$entradaIdSembrada = (int) $wpdb->insert_id;

		try {
			$migrador->revertirA( '0.13.0', Esquema::sentenciasReversaDesde( $wpdb, '0.14.0', '0.13.0' ) );

			self::assertSame( '0.13.0', $migrador->versionInstalada() );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$indices        = $wpdb->get_results( "SHOW INDEX FROM {$prefijo}memoria_editorial", ARRAY_A );
			$nombresIndices = array_map( static fn ( array $fila ): string => (string) $fila['Key_name'], $indices ?? array() );
			self::assertNotContains( 'tema', $nombresIndices );

			$tema = $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
				$wpdb->prepare( "SELECT tema FROM {$prefijo}memoria_editorial WHERE id = %d", $entradaIdSembrada )
			);
			self::assertSame( 'dato real que sobrevive a la reversa', $tema );
		} finally {
			// Deja el esquema real de vuelta a la versión objetivo para el
			// resto de la suite, pase lo que pase con las aserciones.
			$migrador->migrar( PLUMA_ENGINE_DB_VERSION_OBJETIVO, Esquema::sentenciasCreateTable( $wpdb ) );
			$wpdb->delete( $prefijo . 'memoria_editorial', array( 'id' => $entradaIdSembrada ) );
		}
	}
}
