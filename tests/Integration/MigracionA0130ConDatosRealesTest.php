<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\Esquema;
use Pluma\Datos\Migrador;
use WP_UnitTestCase;

/**
 * GOVERNANCE §5.1: "migración de esquema probada sobre copia de datos reales
 * de la versión anterior (N-1 → N verificado siempre)". Transición real
 * 0.12.0→0.13.0 (Etapa 6, porción 4c: `tipo_aprobacion` en `pluma_auditoria`,
 * `aprobacion_activa` en `pluma_cola_publicacion`) — ver
 * `Esquema::sentenciasReversaDesde()`.
 *
 * Solo `ALTER TABLE ... DROP COLUMN` en ambos sentidos (ningún `DROP TABLE`),
 * así que — a diferencia de `MigracionConDatosRealesTest` — no hace falta
 * quitar los filtros de tabla temporal de `WP_UnitTestCase`: ese filtro solo
 * reescribe `CREATE`/`DROP TABLE`, nunca `ALTER TABLE`.
 *
 * @covers \Pluma\Datos\Migrador
 * @covers \Pluma\Datos\Esquema
 */
final class MigracionA0130ConDatosRealesTest extends WP_UnitTestCase {

	public function test_migrar_hacia_0_13_0_preserva_datos_reales_sembrados_en_el_shape_anterior(): void {
		global $wpdb;
		$prefijo  = $wpdb->prefix . 'pluma_';
		$migrador = new Migrador( $wpdb );

		// Simula el shape previo a 0.13.0.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$wpdb->query( "ALTER TABLE {$prefijo}auditoria DROP COLUMN tipo_aprobacion;" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$wpdb->query( "ALTER TABLE {$prefijo}cola_publicacion DROP COLUMN aprobacion_activa;" );
		$migrador->revertirA( '0.12.0', array() );

		$wpdb->insert(
			$prefijo . 'auditoria',
			array(
				'pieza_id'     => 999999,
				'estado_nuevo' => 'publicada',
				'actor'        => 'sistema',
				'motivo'       => 'dato real sembrado antes de migrar a 0.13.0',
				'ocurrida_en'  => '2026-01-01 00:00:00',
			)
		);
		$auditoriaIdSembrada = (int) $wpdb->insert_id;

		$wpdb->insert(
			$prefijo . 'cola_publicacion',
			array(
				'pieza_id'        => 999999,
				'vertical'        => 'economia',
				'hora_programada' => '2026-01-01 00:00:00',
				'estado'          => 'publicada',
				'creada_en'       => '2026-01-01 00:00:00',
			)
		);
		$colaIdSembrada = (int) $wpdb->insert_id;

		// Migra hacia adelante con los datos reales ya sembrados en el shape anterior.
		$migrador->migrar( '0.13.0', Esquema::sentenciasCreateTable( $wpdb ) );

		try {
			self::assertSame( '0.13.0', $migrador->versionInstalada() );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$columnasAuditoria = $wpdb->get_col( "SHOW COLUMNS FROM {$prefijo}auditoria" );
			self::assertContains( 'tipo_aprobacion', $columnasAuditoria );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$columnasCola = $wpdb->get_col( "SHOW COLUMNS FROM {$prefijo}cola_publicacion" );
			self::assertContains( 'aprobacion_activa', $columnasCola );

			// Los datos reales sembrados en el shape anterior sobreviven, y la
			// columna nueva llega con su valor por defecto (NULL / 0) — nunca
			// se inventa un valor para una fila que no pasó por esta porción.
			$motivo = $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
				$wpdb->prepare( "SELECT motivo FROM {$prefijo}auditoria WHERE id = %d", $auditoriaIdSembrada )
			);
			self::assertSame( 'dato real sembrado antes de migrar a 0.13.0', $motivo );

			$tipoAprobacion = $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
				$wpdb->prepare( "SELECT tipo_aprobacion FROM {$prefijo}auditoria WHERE id = %d", $auditoriaIdSembrada )
			);
			self::assertNull( $tipoAprobacion );

			$aprobacionActiva = $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
				$wpdb->prepare( "SELECT aprobacion_activa FROM {$prefijo}cola_publicacion WHERE id = %d", $colaIdSembrada )
			);
			self::assertSame( '0', $aprobacionActiva );
		} finally {
			$wpdb->delete( $prefijo . 'auditoria', array( 'id' => $auditoriaIdSembrada ) );
			$wpdb->delete( $prefijo . 'cola_publicacion', array( 'id' => $colaIdSembrada ) );
		}
	}

	public function test_revertir_a_0_12_0_deshace_solo_lo_que_esa_transicion_anadio(): void {
		global $wpdb;
		$prefijo  = $wpdb->prefix . 'pluma_';
		$migrador = new Migrador( $wpdb );

		$migrador->migrar( '0.13.0', Esquema::sentenciasCreateTable( $wpdb ) );

		$wpdb->insert(
			$prefijo . 'auditoria',
			array(
				'pieza_id'     => 999998,
				'estado_nuevo' => 'publicada',
				'actor'        => 'sistema',
				'motivo'       => 'dato real que sobrevive a la reversa',
				'ocurrida_en'  => '2026-01-01 00:00:00',
			)
		);
		$auditoriaIdSembrada = (int) $wpdb->insert_id;

		try {
			$migrador->revertirA( '0.12.0', Esquema::sentenciasReversaDesde( $wpdb, '0.13.0', '0.12.0' ) );

			self::assertSame( '0.12.0', $migrador->versionInstalada() );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$columnasAuditoria = $wpdb->get_col( "SHOW COLUMNS FROM {$prefijo}auditoria" );
			self::assertNotContains( 'tipo_aprobacion', $columnasAuditoria );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$columnasCola = $wpdb->get_col( "SHOW COLUMNS FROM {$prefijo}cola_publicacion" );
			self::assertNotContains( 'aprobacion_activa', $columnasCola );

			$motivo = $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
				$wpdb->prepare( "SELECT motivo FROM {$prefijo}auditoria WHERE id = %d", $auditoriaIdSembrada )
			);
			self::assertSame( 'dato real que sobrevive a la reversa', $motivo );
		} finally {
			// Deja el esquema real de vuelta a la versión objetivo para el
			// resto de la suite, pase lo que pase con las aserciones.
			$migrador->migrar( PLUMA_ENGINE_DB_VERSION_OBJETIVO, Esquema::sentenciasCreateTable( $wpdb ) );
			$wpdb->delete( $prefijo . 'auditoria', array( 'id' => $auditoriaIdSembrada ) );
		}
	}
}
