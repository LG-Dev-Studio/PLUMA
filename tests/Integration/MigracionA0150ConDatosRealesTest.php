<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\Esquema;
use Pluma\Datos\Migrador;
use WP_UnitTestCase;
use wpdb;

/**
 * GOVERNANCE §5.1: "migración de esquema probada sobre copia de datos reales
 * de la versión anterior (N-1 → N verificado siempre)". Transición real
 * 0.14.0→0.15.0 (Etapa 8, porción 7a, Nivel Dos F.1-F.2): columnas
 * `gravedad`/`campo_tematico`/`campo_geografico` en `pluma_tendencias` +
 * tabla nueva `pluma_modo_respeto` — ver `Esquema::sentenciasReversaDesde()`.
 *
 * A diferencia de `MigracionA0130ConDatosRealesTest`/`MigracionA0140...`
 * (solo `ALTER TABLE`), esta transición crea y borra una tabla completa
 * (`pluma_modo_respeto`) — `CREATE TABLE`/`DROP TABLE` SÍ son interceptados
 * por los filtros `_create_temporary_tables`/`_drop_temporary_tables` que
 * `WP_UnitTestCase::set_up()` instala dentro de cada método de test normal
 * (los reescribe a variantes `TEMPORARY` de sesión — no tocan la tabla real,
 * y además rompen el aislamiento transaccional del resto de la suite: la
 * primera vez se descubrió exactamente así, con datos de otros tests
 * filtrándose entre sí). Por eso, igual que `MigracionConDatosRealesTest`,
 * toda la simulación del shape anterior y la reconstrucción final ocurren en
 * `set_up_before_class()`/`tear_down_after_class()` — fuera de la ventana de
 * esos filtros.
 *
 * Deliberadamente NO reejecuta `dbDelta` sobre las otras 12 tablas del
 * esquema (a diferencia de `MigracionConDatosRealesTest`, que sí corre el
 * `CREATE TABLE` completo): esta transición solo toca `pluma_tendencias`/
 * `pluma_modo_respeto`, y minimizar el churn de `dbDelta` sobre tablas no
 * relacionadas evita presionar el límite de tamaño de fila de InnoDB que
 * ciclos repetidos de `ALTER TABLE` acumulan a lo largo de una suite con
 * muchas clases de test de migración corriendo en la misma sesión de MySQL.
 *
 * @covers \Pluma\Datos\Migrador
 * @covers \Pluma\Datos\Esquema
 */
final class MigracionA0150ConDatosRealesTest extends WP_UnitTestCase {

	private static int $tendenciaIdSembrada;

	/**
	 * @return list<string>
	 */
	private static function sentenciasCreateTableRelevantes( wpdb $wpdb ): array {
		$prefijo = $wpdb->prefix . 'pluma_';

		return array_values(
			array_filter(
				Esquema::sentenciasCreateTable( $wpdb ),
				static fn ( string $sentencia ): bool => str_contains( $sentencia, "{$prefijo}tendencias (" ) || str_contains( $sentencia, "{$prefijo}modo_respeto (" )
			)
		);
	}

	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		global $wpdb;
		$prefijo = $wpdb->prefix . 'pluma_';

		// Simula el shape previo a 0.15.0.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$wpdb->query( "ALTER TABLE {$prefijo}tendencias DROP COLUMN gravedad, DROP COLUMN campo_tematico, DROP COLUMN campo_geografico;" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$wpdb->query( "DROP TABLE IF EXISTS {$prefijo}modo_respeto;" );
		( new Migrador( $wpdb ) )->migrar( '0.14.0', array() );

		// Dato real sembrado antes de simular el shape anterior a 0.15.0.
		$wpdb->insert(
			$prefijo . 'tendencias',
			array(
				'termino'                => 'dato real sembrado antes de migrar a 0.15.0',
				'fuente_senal'           => 'google_trends',
				'puntuacion_velocidad'   => 50,
				'puntuacion_afinidad'    => 50,
				'puntuacion_total'       => 50,
				'articulos_relacionados' => '[]',
				'detectada_en'           => '2026-01-01 00:00:00',
				'creada_en'              => '2026-01-01 00:00:00',
			)
		);
		self::$tendenciaIdSembrada = (int) $wpdb->insert_id;

		// Migra hacia adelante con el dato real ya sembrado en el shape
		// anterior — esto es lo que GOVERNANCE §5.1 exige verificar. El
		// CREATE TABLE que reconstruye `pluma_modo_respeto` ocurre aquí,
		// antes de que el filtro de tablas TEMPORARY exista.
		( new Migrador( $wpdb ) )->migrar( '0.15.0', self::sentenciasCreateTableRelevantes( $wpdb ) );
	}

	public static function tear_down_after_class(): void {
		global $wpdb;
		$prefijo = $wpdb->prefix . 'pluma_';

		// Restaura siempre el esquema completo real, pase lo que pase con
		// las aserciones — el test de reversa deja el esquema en shape 0.14.0.
		( new Migrador( $wpdb ) )->migrar( PLUMA_ENGINE_DB_VERSION_OBJETIVO, self::sentenciasCreateTableRelevantes( $wpdb ) );
		$wpdb->delete( $prefijo . 'tendencias', array( 'id' => self::$tendenciaIdSembrada ) );

		parent::tear_down_after_class();
	}

	public function test_migrar_hacia_0_15_0_preserva_datos_reales_y_anade_columnas_y_tabla(): void {
		global $wpdb;
		$prefijo = $wpdb->prefix . 'pluma_';

		self::assertSame( '0.15.0', ( new Migrador( $wpdb ) )->versionInstalada() );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$columnas = $wpdb->get_col( "SHOW COLUMNS FROM {$prefijo}tendencias" );
		self::assertContains( 'gravedad', $columnas );
		self::assertContains( 'campo_tematico', $columnas );
		self::assertContains( 'campo_geografico', $columnas );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$tablaModoRespeto = $wpdb->get_col( "SHOW TABLES LIKE '{$prefijo}modo_respeto'" );
		self::assertNotSame( array(), $tablaModoRespeto );

		$termino = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$wpdb->prepare( "SELECT termino FROM {$prefijo}tendencias WHERE id = %d", self::$tendenciaIdSembrada )
		);
		self::assertSame( 'dato real sembrado antes de migrar a 0.15.0', $termino );

		$gravedad = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$wpdb->prepare( "SELECT gravedad FROM {$prefijo}tendencias WHERE id = %d", self::$tendenciaIdSembrada )
		);
		self::assertNull( $gravedad );
	}

	public function test_revertir_a_0_14_0_elimina_columnas_y_tabla_de_modo_respeto(): void {
		global $wpdb;
		$prefijo  = $wpdb->prefix . 'pluma_';
		$migrador = new Migrador( $wpdb );

		// WP_UnitTestCase::set_up() (activo dentro de cualquier método de test
		// normal) también intercepta CREATE/DROP TABLE — fuera de esta
		// ventana no hace falta (ver el docblock de la clase): aquí sí,
		// porque la reversa real DEBE borrar la tabla permanente de verdad.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$migrador->revertirA( '0.14.0', Esquema::sentenciasReversaDesde( $wpdb, '0.15.0', '0.14.0' ) );

		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		self::assertSame( '0.14.0', $migrador->versionInstalada() );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$columnas = $wpdb->get_col( "SHOW COLUMNS FROM {$prefijo}tendencias" );
		self::assertNotContains( 'gravedad', $columnas );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$tablaModoRespeto = $wpdb->get_col( "SHOW TABLES LIKE '{$prefijo}modo_respeto'" );
		self::assertSame( array(), $tablaModoRespeto );

		// La reversa solo tocó tendencias/modo_respeto — el dato real sembrado
		// en la propia tabla tendencias (columnas no afectadas) sobrevive.
		$termino = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$wpdb->prepare( "SELECT termino FROM {$prefijo}tendencias WHERE id = %d", self::$tendenciaIdSembrada )
		);
		self::assertSame( 'dato real sembrado antes de migrar a 0.15.0', $termino );
	}
}
