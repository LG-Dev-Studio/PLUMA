<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use DateTimeImmutable;
use Pluma\Datos\Esquema;
use Pluma\Datos\Migrador;
use Pluma\Datos\RepositorioPeriodistas;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use WP_UnitTestCase;

/**
 * GOVERNANCE §5.1: "migración de esquema probada sobre copia de datos reales
 * de la versión anterior (N-1 → N verificado siempre)". Transición real
 * 0.23.0→0.24.0 (trabajo posterior a la Etapa 9, creación automática de
 * periodistas): `tema_sin_cubrir` en `pluma_piezas` y
 * `creado_automaticamente` en `pluma_periodistas` — ver
 * `Esquema::sentenciasReversaDesde()`.
 *
 * Solo `ALTER TABLE ... ADD COLUMN` en ambos sentidos (ninguna tabla nueva),
 * así que — igual que `MigracionA0140ConDatosRealesTest` — no hace falta
 * quitar los filtros de tabla temporal de `WP_UnitTestCase`.
 *
 * @covers \Pluma\Datos\Migrador
 * @covers \Pluma\Datos\Esquema
 */
final class MigracionA0240ConDatosRealesTest extends WP_UnitTestCase {

	public function test_migrar_hacia_0_24_0_preserva_datos_reales_y_anade_las_columnas_nuevas(): void {
		global $wpdb;
		$prefijo  = $wpdb->prefix . 'pluma_';
		$migrador = new Migrador( $wpdb );

		// Simula el shape previo a 0.24.0.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$wpdb->query( "ALTER TABLE {$prefijo}piezas DROP COLUMN tema_sin_cubrir;" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$wpdb->query( "ALTER TABLE {$prefijo}periodistas DROP COLUMN creado_automaticamente;" );
		$migrador->migrar( '0.23.0', array() );

		$wpdb->insert(
			$prefijo . 'tendencias',
			array(
				'termino'                => 'tendencia real sembrada antes de migrar a 0.24.0',
				'fuente_senal'           => 'google_trends',
				'puntuacion_velocidad'   => 50,
				'puntuacion_afinidad'    => 50,
				'puntuacion_total'       => 50,
				'articulos_relacionados' => '[]',
				'detectada_en'           => '2026-01-01 00:00:00',
				'creada_en'              => '2026-01-01 00:00:00',
			)
		);
		$tendenciaIdSembrada = (int) $wpdb->insert_id;

		$wpdb->insert(
			$prefijo . 'piezas',
			array(
				'tendencia_id'   => $tendenciaIdSembrada,
				'estado'         => 'sin_periodista_idoneo',
				'creada_en'      => '2026-01-01 00:00:00',
				'actualizada_en' => '2026-01-01 00:00:00',
			)
		);
		$piezaIdSembrada = (int) $wpdb->insert_id;

		// Migra hacia adelante con los datos reales ya sembrados en el shape anterior.
		$migrador->migrar( '0.24.0', Esquema::sentenciasCreateTable( $wpdb ) );

		try {
			self::assertSame( '0.24.0', $migrador->versionInstalada() );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$columnasPiezas = $wpdb->get_col( "SHOW COLUMNS FROM {$prefijo}piezas" );
			self::assertContains( 'tema_sin_cubrir', $columnasPiezas );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$columnasPeriodistas = $wpdb->get_col( "SHOW COLUMNS FROM {$prefijo}periodistas" );
			self::assertContains( 'creado_automaticamente', $columnasPeriodistas );

			// El dato real sembrado en el shape anterior sobrevive intacto.
			$estado = $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
				$wpdb->prepare( "SELECT estado FROM {$prefijo}piezas WHERE id = %d", $piezaIdSembrada )
			);
			self::assertSame( 'sin_periodista_idoneo', $estado );

			$temaSinCubrir = $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
				$wpdb->prepare( "SELECT tema_sin_cubrir FROM {$prefijo}piezas WHERE id = %d", $piezaIdSembrada )
			);
			self::assertNull( $temaSinCubrir );
		} finally {
			$wpdb->delete( $prefijo . 'piezas', array( 'id' => $piezaIdSembrada ) );
			$wpdb->delete( $prefijo . 'tendencias', array( 'id' => $tendenciaIdSembrada ) );
			// Ver el comentario detallado en el segundo test de esta clase:
			// el `ALTER TABLE` de la simulación ya rompió la transacción
			// por-test, así que este borrado necesita un `COMMIT` explícito
			// para no desaparecer con el `ROLLBACK` de `tear_down()`.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- control de transacción, no una consulta de datos.
			$wpdb->query( 'COMMIT' );
		}
	}

	public function test_revertir_a_0_23_0_elimina_solo_las_columnas_nuevas(): void {
		global $wpdb;
		$prefijo  = $wpdb->prefix . 'pluma_';
		$migrador = new Migrador( $wpdb );

		$migrador->migrar( '0.24.0', Esquema::sentenciasCreateTable( $wpdb ) );

		// Creado vía el repositorio real (no un $wpdb->insert() a mano): así
		// la fila de periodista siempre referencia una versión de conducta
		// real y válida en `pluma_periodistas_conducta_versiones` — un
		// `version_conducta_actual_id` inventado dejaría una fila corrupta
		// que sobrevive al `ALTER TABLE` de la reversa (DDL: commit
		// implícito, rompe el aislamiento transaccional de PHPUnit para el
		// resto de la suite) y rompe cualquier test posterior que liste
		// periodistas activos.
		$repoPeriodistas      = new RepositorioPeriodistas( $wpdb );
		$periodistaIdSembrado = $repoPeriodistas->crear(
			'Periodista que sobrevive a la reversa',
			null,
			'bio',
			RolPeriodista::Analista,
			array(),
			EstadoPeriodista::Activo,
			new Diales( 60, 40, 20, 60, 50, 50, 60, 50 ),
			new ReglasConducta( 'línea', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' ),
			MatrizTonos::desdeFilas( array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) ) ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			creadoAutomaticamente: true
		);

		try {
			$migrador->revertirA( '0.23.0', Esquema::sentenciasReversaDesde( $wpdb, '0.24.0', '0.23.0' ) );

			self::assertSame( '0.23.0', $migrador->versionInstalada() );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$columnasPiezas = $wpdb->get_col( "SHOW COLUMNS FROM {$prefijo}piezas" );
			self::assertNotContains( 'tema_sin_cubrir', $columnasPiezas );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$columnasPeriodistas = $wpdb->get_col( "SHOW COLUMNS FROM {$prefijo}periodistas" );
			self::assertNotContains( 'creado_automaticamente', $columnasPeriodistas );

			$nombre = $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
				$wpdb->prepare( "SELECT nombre FROM {$prefijo}periodistas WHERE id = %d", $periodistaIdSembrado )
			);
			self::assertSame( 'Periodista que sobrevive a la reversa', $nombre );
		} finally {
			// Borra el dato sembrado ANTES de restaurar el esquema: si
			// `migrar()` lanzara por cualquier motivo, un `finally` con la
			// restauración primero dejaría este borrado sin ejecutar nunca,
			// filtrando una fila de periodista al resto de la suite
			// (exactamente el defecto que este comentario documenta).
			$wpdb->delete( $prefijo . 'periodistas_conducta_versiones', array( 'periodista_id' => $periodistaIdSembrado ) );
			$wpdb->delete( $prefijo . 'periodistas', array( 'id' => $periodistaIdSembrado ) );
			// Deja el esquema real de vuelta a la versión objetivo para el
			// resto de la suite, pase lo que pase con las aserciones.
			$migrador->migrar( PLUMA_ENGINE_DB_VERSION_OBJETIVO, Esquema::sentenciasCreateTable( $wpdb ) );
			// El `ALTER TABLE` de la reversa ya rompió (commit implícito) la
			// transacción por-test de `WP_UnitTestCase` — MySQL autoarranca
			// una transacción nueva para las sentencias posteriores a esa
			// ruptura (autocommit sigue desactivado dentro de la suite), y
			// esa transacción nueva NUNCA se confirma por sí sola: el
			// `ROLLBACK` que `tear_down()` dispara al cerrar el test se la
			// llevaría entera, incluidos el borrado y la restauración de
			// arriba. `COMMIT` explícito para que sobrevivan de verdad.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- control de transacción, no una consulta de datos.
			$wpdb->query( 'COMMIT' );
		}
	}
}
