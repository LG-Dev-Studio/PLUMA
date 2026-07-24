<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\Esquema;
use Pluma\Datos\Migrador;
use WP_UnitTestCase;

/**
 * GOVERNANCE §5.1: "migración de esquema probada sobre copia de datos reales
 * de la versión anterior (N-1 → N verificado siempre)". Usa la transición
 * real 0.11.0→0.12.0 (Etapa 5, porción 3) como caso de referencia — ver
 * `Esquema::sentenciasReversaDesde()`.
 *
 * El `DROP COLUMN`/`DROP TABLE` que simula el shape 0.11.0 y la reconstrucción
 * vía `Migrador::migrar()` se ejecutan en `set_up_before_class()`/
 * `tear_down_after_class()` — NO dentro de un método de test normal — porque
 * `WP_UnitTestCase::set_up()` instala dos filtros `query` simétricos:
 * `_create_temporary_tables` convierte cualquier `CREATE TABLE` posterior en
 * una tabla `TEMPORARY` de sesión, y `_drop_temporary_tables` hace lo mismo
 * con `DROP TABLE` (lo reescribe a `DROP TEMPORARY TABLE`, que no toca una
 * tabla real — descubierto de la forma difícil: la reversa parecía no borrar
 * nada). Ejecutar la reconstrucción/eliminación del esquema real fuera de esa
 * ventana es obligatorio para no dejar `pluma_respuestas_comentarios` como
 * tabla fantasma (o indestructible) para el resto de la suite (ver
 * `RepositorioVocabularioTest`, `RepositorioColaPublicacionTest`). Cuando un
 * `DROP TABLE` real SÍ debe ejecutarse dentro de un método de test normal
 * (la reversa que se está probando), el propio test quita ambos filtros
 * temporalmente — ver `test_revertir_a_0_11_0_deshace_solo_lo_que_esa_transicion_anadio()`.
 * `ALTER TABLE`/`DROP COLUMN` sí son seguros siempre — ningún filtro los
 * intercepta.
 *
 * @covers \Pluma\Datos\Migrador
 * @covers \Pluma\Datos\Esquema
 */
final class MigracionConDatosRealesTest extends WP_UnitTestCase {

	private static int $periodistaIdSembrado;

	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		global $wpdb;
		$prefijo = $wpdb->prefix . 'pluma_';

		// Dato real sembrado antes de simular el shape anterior a 0.12.0.
		$wpdb->insert(
			$prefijo . 'periodistas',
			array(
				'nombre'                     => 'Periodista de prueba de migración',
				'biografia'                  => 'Biografía de prueba para MigracionConDatosRealesTest.',
				'rol'                        => 'analista',
				'especialidades'             => '[]',
				'estado'                     => 'activo',
				'version_conducta_actual_id' => 1,
				'creado_en'                  => '2026-01-01 00:00:00',
				'actualizado_en'             => '2026-01-01 00:00:00',
			)
		);
		self::$periodistaIdSembrado = (int) $wpdb->insert_id;

		// Simula el shape previo a 0.12.0 (ALTER/DROP: seguros fuera de la ventana del filtro también).
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$wpdb->query( "ALTER TABLE {$prefijo}periodistas_conducta_versiones DROP COLUMN respuestas_habilitadas;" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$wpdb->query( "DROP TABLE IF EXISTS {$prefijo}respuestas_comentarios;" );

		// Migra hacia adelante con el dato real ya sembrado en el shape anterior —
		// esto es lo que GOVERNANCE §5.1 exige verificar. El CREATE TABLE que
		// reconstruye respuestas_comentarios ocurre aquí, antes de que el filtro
		// de tablas TEMPORARY exista.
		( new Migrador( $wpdb ) )->migrar( '0.12.0', Esquema::sentenciasCreateTable( $wpdb ) );
	}

	public static function tear_down_after_class(): void {
		global $wpdb;
		$prefijo = $wpdb->prefix . 'pluma_';

		// Restaura siempre el esquema completo real, pase lo que pase con las
		// aserciones — el test de reversa deja el esquema en shape 0.11.0.
		( new Migrador( $wpdb ) )->migrar( PLUMA_ENGINE_DB_VERSION_OBJETIVO, Esquema::sentenciasCreateTable( $wpdb ) );
		$wpdb->delete( $prefijo . 'periodistas', array( 'id' => self::$periodistaIdSembrado ) );

		parent::tear_down_after_class();
	}

	public function test_migrar_hacia_0_12_0_preserva_el_dato_sembrado_en_el_shape_anterior(): void {
		global $wpdb;
		$prefijo = $wpdb->prefix . 'pluma_';

		self::assertSame( '0.12.0', ( new Migrador( $wpdb ) )->versionInstalada() );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$columnas = $wpdb->get_col( "SHOW COLUMNS FROM {$prefijo}periodistas_conducta_versiones" );
		self::assertContains( 'respuestas_habilitadas', $columnas );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$tablaRespuestas = $wpdb->get_col( "SHOW TABLES LIKE '{$prefijo}respuestas_comentarios'" );
		self::assertNotSame( array(), $tablaRespuestas );

		$nombre = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$wpdb->prepare( "SELECT nombre FROM {$prefijo}periodistas WHERE id = %d", self::$periodistaIdSembrado )
		);
		self::assertSame( 'Periodista de prueba de migración', $nombre );
	}

	public function test_revertir_a_0_11_0_deshace_solo_lo_que_esa_transicion_anadio(): void {
		global $wpdb;
		$prefijo  = $wpdb->prefix . 'pluma_';
		$migrador = new Migrador( $wpdb );

		// WP_UnitTestCase::set_up() (activo dentro de cualquier método de test
		// normal) también intercepta DROP TABLE y lo reescribe a DROP TEMPORARY
		// TABLE — que no toca una tabla real, solo una de sesión. Fuera de esta
		// ventana no hace falta (ver el docblock de la clase): aquí sí, porque
		// la reversa real DEBE borrar la tabla permanente de verdad.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$migrador->revertirA( '0.11.0', Esquema::sentenciasReversaDesde( $wpdb, '0.12.0', '0.11.0' ) );

		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		self::assertSame( '0.11.0', $migrador->versionInstalada() );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$columnas = $wpdb->get_col( "SHOW COLUMNS FROM {$prefijo}periodistas_conducta_versiones" );
		self::assertNotContains( 'respuestas_habilitadas', $columnas );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$tablaRespuestas = $wpdb->get_col( "SHOW TABLES LIKE '{$prefijo}respuestas_comentarios'" );
		self::assertSame( array(), $tablaRespuestas );

		// La reversa solo tocó las dos piezas de la transición 0.12.0->0.11.0 —
		// una tabla no afectada (periodistas) conserva su dato real intacto.
		$nombre = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$wpdb->prepare( "SELECT nombre FROM {$prefijo}periodistas WHERE id = %d", self::$periodistaIdSembrado )
		);
		self::assertSame( 'Periodista de prueba de migración', $nombre );
	}
}
