<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use DateTimeImmutable;
use Pluma\Datos\Esquema;
use Pluma\Datos\Migrador;
use Pluma\Datos\RepositorioLlamadasModelo;
use Pluma\Proveedores\OrigenLlamada;
use Pluma\Proveedores\PropositoLenguaje;
use Pluma\Proveedores\RegistroLlamada;
use Pluma\Proveedores\ResultadoLlamada;
use WP_UnitTestCase;
use wpdb;

/**
 * GOVERNANCE §5.1: "migración de esquema probada sobre copia de datos reales
 * de la versión anterior (N-1 → N verificado siempre)". Transición real
 * 0.24.0→0.25.0 (NCP-1, `ADR 0010`): tabla nueva `pluma_llamadas_modelo`.
 *
 * Igual que `MigracionA0150ConDatosRealesTest` (que también crea/borra una
 * tabla completa, no solo columnas): `CREATE TABLE`/`DROP TABLE` SÍ son
 * interceptados por los filtros `_create_temporary_tables`/
 * `_drop_temporary_tables` que `WP_UnitTestCase::set_up()` instala dentro de
 * cada método de test normal (los reescribe a variantes `TEMPORARY` de
 * sesión — no tocan la tabla real, y rompen el aislamiento transaccional del
 * resto de la suite). Por eso la simulación del shape anterior y la
 * reconstrucción final ocurren en `set_up_before_class()`/
 * `tear_down_after_class()`, fuera de la ventana de esos filtros; el test de
 * reversa quita los filtros explícitamente alrededor de la llamada real.
 *
 * @covers \Pluma\Datos\Migrador
 * @covers \Pluma\Datos\Esquema
 * @covers \Pluma\Datos\RepositorioLlamadasModelo
 */
final class MigracionA0250ConDatosRealesTest extends WP_UnitTestCase {

	private static int $tendenciaIdSembrada;

	/**
	 * @return list<string>
	 */
	private static function sentenciasCreateTableRelevantes( wpdb $wpdb ): array {
		$prefijo = $wpdb->prefix . 'pluma_';

		return array_values(
			array_filter(
				Esquema::sentenciasCreateTable( $wpdb ),
				static fn ( string $sentencia ): bool => str_contains( $sentencia, "{$prefijo}llamadas_modelo (" )
			)
		);
	}

	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		global $wpdb;
		$prefijo = $wpdb->prefix . 'pluma_';

		// Simula el shape previo a 0.25.0: la tabla nueva aún no existe.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$wpdb->query( "DROP TABLE IF EXISTS {$prefijo}llamadas_modelo;" );
		( new Migrador( $wpdb ) )->migrar( '0.24.0', array() );

		// Dato real sembrado antes de simular el shape anterior a 0.25.0.
		$wpdb->insert(
			$prefijo . 'tendencias',
			array(
				'termino'                => 'tendencia real sembrada antes de migrar a 0.25.0',
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
		// anterior — el CREATE TABLE que crea `pluma_llamadas_modelo` ocurre
		// aquí, antes de que el filtro de tablas TEMPORARY exista.
		( new Migrador( $wpdb ) )->migrar( '0.25.0', self::sentenciasCreateTableRelevantes( $wpdb ) );
	}

	public static function tear_down_after_class(): void {
		global $wpdb;
		$prefijo = $wpdb->prefix . 'pluma_';

		// Restaura siempre el esquema completo real, pase lo que pase con
		// las aserciones — el test de reversa deja el esquema en shape 0.24.0.
		( new Migrador( $wpdb ) )->migrar( PLUMA_ENGINE_DB_VERSION_OBJETIVO, self::sentenciasCreateTableRelevantes( $wpdb ) );
		$wpdb->delete( $prefijo . 'tendencias', array( 'id' => self::$tendenciaIdSembrada ) );

		parent::tear_down_after_class();
	}

	public function test_migrar_hacia_0_25_0_crea_la_tabla_de_llamadas_modelo_y_preserva_datos_existentes(): void {
		global $wpdb;
		$prefijo = $wpdb->prefix . 'pluma_';

		self::assertSame( '0.25.0', ( new Migrador( $wpdb ) )->versionInstalada() );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$tablas = $wpdb->get_col( "SHOW TABLES LIKE '{$prefijo}llamadas_modelo'" );
		self::assertNotSame( array(), $tablas );

		// El dato real sembrado en el shape anterior sobrevive intacto.
		$termino = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$wpdb->prepare( "SELECT termino FROM {$prefijo}tendencias WHERE id = %d", self::$tendenciaIdSembrada )
		);
		self::assertSame( 'tendencia real sembrada antes de migrar a 0.25.0', $termino );
	}

	public function test_repositorio_inserta_y_agrega_por_proposito_origen_y_resultado_y_purga_lo_antiguo(): void {
		global $wpdb;

		$repositorio = new RepositorioLlamadasModelo( $wpdb );
		$ahora       = new DateTimeImmutable( '2026-07-30T10:00:00+00:00' );

		$repositorio->registrar(
			new RegistroLlamada(
				PropositoLenguaje::Redactar->value,
				'openrouter',
				'anthropic/claude-sonnet-5',
				'anthropic',
				OrigenLlamada::Cron,
				ResultadoLlamada::Ok,
				1200,
				800,
				0.045,
				3400,
				false
			),
			$ahora
		);
		$repositorio->registrar(
			new RegistroLlamada(
				RegistroLlamada::PROPOSITO_EMBEDDINGS,
				'openrouter',
				'openai/text-embedding-3-small',
				'openai',
				OrigenLlamada::Visitante,
				ResultadoLlamada::PresupuestoAgotado,
				40,
				0,
				null,
				10,
				false
			),
			$ahora
		);

		try {
			$resumen = $repositorio->resumirEntre(
				new DateTimeImmutable( '2026-07-30T00:00:00+00:00' ),
				new DateTimeImmutable( '2026-07-30T23:59:59+00:00' )
			);

			self::assertCount( 2, $resumen );

			$porProposito = array();
			foreach ( $resumen as $fila ) {
				$porProposito[ $fila['proposito'] ] = $fila;
			}

			self::assertSame( 1, $porProposito['redactar']['llamadas'] );
			self::assertSame( 'cron', $porProposito['redactar']['origen'] );
			self::assertSame( 'ok', $porProposito['redactar']['resultado'] );
			self::assertEqualsWithDelta( 0.045, $porProposito['redactar']['costeUsd'], 0.0001 );

			self::assertSame( 1, $porProposito['embeddings']['llamadas'] );
			self::assertSame( 'visitante', $porProposito['embeddings']['origen'] );
			self::assertSame( 'presupuesto_agotado', $porProposito['embeddings']['resultado'] );
			self::assertSame( 0.0, $porProposito['embeddings']['costeUsd'] );

			// La purga respeta lo reciente y borra solo lo antiguo.
			$eliminadas = $repositorio->purgarAnterioresA( new DateTimeImmutable( '2026-07-31T00:00:00+00:00' ) );
			self::assertSame( 2, $eliminadas );

			$resumenTrasPurga = $repositorio->resumirEntre(
				new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
				new DateTimeImmutable( '2026-12-31T23:59:59+00:00' )
			);
			self::assertSame( array(), $resumenTrasPurga );
		} finally {
			// Por si alguna aserción falla antes de que la purga limpie las
			// filas: no dejar registros sembrados para los tests siguientes
			// de esta misma clase.
			$prefijo = $wpdb->prefix . 'pluma_';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$wpdb->query( "TRUNCATE TABLE {$prefijo}llamadas_modelo" );
		}
	}

	public function test_revertir_a_0_24_0_elimina_la_tabla_de_llamadas_modelo(): void {
		global $wpdb;
		$prefijo  = $wpdb->prefix . 'pluma_';
		$migrador = new Migrador( $wpdb );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$tablasAntes = $wpdb->get_col( "SHOW TABLES LIKE '{$prefijo}llamadas_modelo'" );
		self::assertNotSame( array(), $tablasAntes );

		// WP_UnitTestCase::set_up() (activo dentro de cualquier método de test
		// normal) también intercepta CREATE/DROP TABLE — fuera de esta
		// ventana no hace falta (ver el docblock de la clase): aquí sí,
		// porque la reversa real DEBE borrar la tabla permanente de verdad.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$migrador->revertirA( '0.24.0', Esquema::sentenciasReversaDesde( $wpdb, '0.25.0', '0.24.0' ) );

		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		self::assertSame( '0.24.0', $migrador->versionInstalada() );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
		$tablasDespues = $wpdb->get_col( "SHOW TABLES LIKE '{$prefijo}llamadas_modelo'" );
		self::assertSame( array(), $tablasDespues );

		// Deja el esquema real de vuelta a la versión objetivo para el resto
		// de esta clase (`tear_down_after_class()` la restaura otra vez de
		// todas formas, pero un método de test posterior en la misma clase
		// no debería depender de ese orden).
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$migrador->migrar( '0.25.0', self::sentenciasCreateTableRelevantes( $wpdb ) );
		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );
	}
}
