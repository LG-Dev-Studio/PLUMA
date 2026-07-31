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
 * GOVERNANCE §5.1 / `ADR 0011`: retiro de la función de comentarios —
 * primera migración FORWARD (no reversa) que borra esquema del proyecto.
 * A diferencia de todas las transiciones anteriores (que solo AÑADEN
 * columnas/tablas hacia adelante y solo DROPean en una reversa manual
 * explícita), aquí el `DROP` real ocurre automáticamente al actualizar,
 * vía {@see \Pluma\Datos\Migrador::ejecutarRetiro()} +
 * {@see Esquema::sentenciasRetiroHasta()} — probado aquí sobre una copia de
 * datos reales, igual que cualquier otra migración.
 *
 * Mismo patrón que `MigracionA0150ConDatosRealesTest` (crea/borra una tabla
 * completa, no solo columnas): `CREATE`/`DROP TABLE` SÍ son interceptados
 * por los filtros de tablas TEMPORARY que `WP_UnitTestCase::set_up()`
 * instala dentro de cada método de test normal — la simulación del shape
 * 0.25.0 ocurre en `set_up_before_class()`, y los tests que hacen el DROP/
 * CREATE real quitan los filtros explícitamente alrededor de la llamada.
 *
 * @covers \Pluma\Datos\Migrador
 * @covers \Pluma\Datos\Esquema
 */
final class MigracionA0260ConDatosRealesTest extends WP_UnitTestCase {

	private static int $periodistaIdSembrado;

	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		global $wpdb;

		// Simula el shape 0.25.0 (antes del retiro): exactamente las mismas
		// sentencias que la reversa 0.26.0->0.25.0 usaría para restaurar —
		// una sola fuente de verdad para "cómo era el shape anterior".
		foreach ( Esquema::sentenciasReversaDesde( $wpdb, '0.26.0', '0.25.0' ) as $sentencia ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- sentencias fijas resueltas internamente por Esquema::sentenciasReversaDesde(), sin entrada de usuario.
			$wpdb->query( $sentencia );
		}
		( new Migrador( $wpdb ) )->migrar( '0.25.0', array() );

		// Periodista real sembrado en el shape anterior, con la columna
		// respuestas_habilitadas todavía viva.
		$repo                       = new RepositorioPeriodistas( $wpdb );
		self::$periodistaIdSembrado = $repo->crear(
			'Periodista para el retiro de comentarios',
			null,
			'bio',
			RolPeriodista::Analista,
			array(),
			EstadoPeriodista::Activo,
			new Diales( 60, 40, 20, 60, 50, 50, 60, 50 ),
			new ReglasConducta( 'línea', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' ),
			MatrizTonos::desdeFilas( array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) ) ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);

		$sqlActualizar = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			"UPDATE {$wpdb->prefix}pluma_periodistas_conducta_versiones SET respuestas_habilitadas = 1 WHERE periodista_id = %d",
			self::$periodistaIdSembrado
		);
		assert( null !== $sqlActualizar );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sqlActualizar ya se construyó con $wpdb->prepare() arriba.
		$wpdb->query( $sqlActualizar );
	}

	public static function tear_down_after_class(): void {
		global $wpdb;

		// Restaura siempre el esquema real de vuelta a la versión objetivo,
		// pase lo que pase con las aserciones.
		( new Migrador( $wpdb ) )->ejecutarRetiro( Esquema::sentenciasRetiroHasta( $wpdb, PLUMA_ENGINE_DB_VERSION_OBJETIVO ) );
		( new Migrador( $wpdb ) )->migrar( PLUMA_ENGINE_DB_VERSION_OBJETIVO, Esquema::sentenciasCreateTable( $wpdb ) );
		$wpdb->delete( $wpdb->prefix . 'pluma_periodistas_conducta_versiones', array( 'periodista_id' => self::$periodistaIdSembrado ) );
		$wpdb->delete( $wpdb->prefix . 'pluma_periodistas', array( 'id' => self::$periodistaIdSembrado ) );

		parent::tear_down_after_class();
	}

	public function test_sentencias_de_retiro_estan_vacias_por_debajo_de_la_version_0_26_0(): void {
		global $wpdb;

		self::assertSame( array(), Esquema::sentenciasRetiroHasta( $wpdb, '0.25.0' ) );
	}

	public function test_ejecutar_retiro_sobre_datos_reales_borra_tabla_y_columna_preservando_el_resto(): void {
		global $wpdb;
		$prefijo = $wpdb->prefix . 'pluma_';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$columnasAntes = $wpdb->get_col( "SHOW COLUMNS FROM {$prefijo}periodistas_conducta_versiones" );
		self::assertContains( 'respuestas_habilitadas', $columnasAntes );

		$respuestasHabilitadasAntes = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$wpdb->prepare( "SELECT respuestas_habilitadas FROM {$prefijo}periodistas_conducta_versiones WHERE periodista_id = %d", self::$periodistaIdSembrado )
		);
		self::assertSame( '1', $respuestasHabilitadasAntes, 'El dato real sembrado en el shape anterior debe existir antes del retiro.' );

		// WP_UnitTestCase::set_up() (activo dentro de cualquier método de test
		// normal) también intercepta CREATE/DROP TABLE — fuera de esta
		// ventana no hace falta (ver el docblock de la clase): aquí sí,
		// porque el retiro real DEBE borrar la tabla permanente de verdad.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$migrador = new Migrador( $wpdb );
		$migrador->ejecutarRetiro( Esquema::sentenciasRetiroHasta( $wpdb, '0.26.0' ) );

		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$tablas = $wpdb->get_col( "SHOW TABLES LIKE '{$prefijo}respuestas_comentarios'" );
		self::assertSame( array(), $tablas, 'pluma_respuestas_comentarios debe desaparecer tras el retiro.' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$columnasDespues = $wpdb->get_col( "SHOW COLUMNS FROM {$prefijo}periodistas_conducta_versiones" );
		self::assertNotContains( 'respuestas_habilitadas', $columnasDespues, 'respuestas_habilitadas debe desaparecer tras el retiro.' );

		// El resto de la fila real sembrada sobrevive intacta.
		$periodista = ( new RepositorioPeriodistas( $wpdb ) )->obtenerPorId( self::$periodistaIdSembrado );
		self::assertNotNull( $periodista );
		self::assertSame( 60, $periodista->conductaActual->diales->agudezaCritica );

		// Idempotencia real: reejecutar el retiro sobre el shape ya retirado
		// no debe fallar — DROP TABLE IF EXISTS es naturalmente idempotente,
		// y la columna ya no existe así que sentenciasRetiroHasta() no debe
		// proponer un DROP COLUMN que MySQL rechazaría.
		$sentenciasSegundaVez = Esquema::sentenciasRetiroHasta( $wpdb, '0.26.0' );
		self::assertSame(
			array( "DROP TABLE IF EXISTS {$prefijo}respuestas_comentarios;" ),
			$sentenciasSegundaVez,
			'Con la columna ya borrada, el retiro no debe volver a proponer el DROP COLUMN.'
		);
		$migrador->ejecutarRetiro( $sentenciasSegundaVez );
	}

	public function test_reversa_0_26_0_a_0_25_0_restaura_la_forma_del_esquema(): void {
		global $wpdb;
		$prefijo = $wpdb->prefix . 'pluma_';

		// Punto de partida de este test: el shape ya retirado (sin tabla ni
		// columna), igual que deja el test anterior.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$migrador = new Migrador( $wpdb );

		foreach ( Esquema::sentenciasReversaDesde( $wpdb, '0.26.0', '0.25.0' ) as $sentencia ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- sentencias fijas resueltas internamente por Esquema::sentenciasReversaDesde(), sin entrada de usuario.
			$wpdb->query( $sentencia );
		}

		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$tablas = $wpdb->get_col( "SHOW TABLES LIKE '{$prefijo}respuestas_comentarios'" );
		self::assertNotSame( array(), $tablas, 'La reversa debe recrear pluma_respuestas_comentarios (vacía).' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$columnas = $wpdb->get_col( "SHOW COLUMNS FROM {$prefijo}periodistas_conducta_versiones" );
		self::assertContains( 'respuestas_habilitadas', $columnas, 'La reversa debe restaurar la columna respuestas_habilitadas.' );

		// Deja el esquema real de vuelta a la versión objetivo para el resto
		// de esta clase (`tear_down_after_class()` la restaura otra vez de
		// todas formas, pero un método de test posterior no debería depender
		// de ese orden).
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$migrador->ejecutarRetiro( Esquema::sentenciasRetiroHasta( $wpdb, '0.26.0' ) );
		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );
	}
}
