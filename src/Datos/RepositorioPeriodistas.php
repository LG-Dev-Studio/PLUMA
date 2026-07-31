<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\Especialidad;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use wpdb;

/**
 * Único punto del plugin con `$wpdb` para `pluma_periodistas` y
 * `pluma_periodistas_conducta_versiones` (CLAUDE.md § Ley de Arquitectura).
 *
 * Nota sobre los `argument.type` ignorados: ver `RepositorioPiezas` — el
 * nombre de tabla interpolado en `$wpdb->prepare()` viene siempre de
 * `$this->tablaPeriodistas()`/`$this->tablaVersiones()`, nunca de entrada de
 * usuario.
 */
final class RepositorioPeriodistas implements RepositorioPeriodistasInterface {

	public function __construct( private readonly wpdb $wpdb ) {
	}

	private function tablaPeriodistas(): string {
		return $this->wpdb->prefix . 'pluma_periodistas';
	}

	private function tablaVersiones(): string {
		return $this->wpdb->prefix . 'pluma_periodistas_conducta_versiones';
	}

	public function crear(
		string $nombre,
		?string $avatarUrl,
		string $biografia,
		RolPeriodista $rol,
		array $especialidades,
		EstadoPeriodista $estado,
		Diales $diales,
		ReglasConducta $reglas,
		MatrizTonos $matrizTonos,
		DateTimeImmutable $ahora,
		string $localeEditorial = 'es-ES',
		bool $creadoAutomaticamente = false
	): int {
		$this->wpdb->insert(
			$this->tablaPeriodistas(),
			array(
				'nombre'                     => $nombre,
				'avatar_url'                 => $avatarUrl,
				'biografia'                  => $biografia,
				'rol'                        => $rol->value,
				'especialidades'             => wp_json_encode( array_map( static fn ( Especialidad $e ): array => $e->aArray(), $especialidades ) ),
				'estado'                     => $estado->value,
				'version_conducta_actual_id' => 0,
				'locale_editorial'           => $localeEditorial,
				'creado_automaticamente'     => $creadoAutomaticamente ? 1 : 0,
				'creado_en'                  => $ahora->format( 'Y-m-d H:i:s' ),
				'actualizado_en'             => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		$periodistaId = (int) $this->wpdb->insert_id;
		$versionId    = $this->insertarVersion( $periodistaId, $diales, $reglas, $matrizTonos, $ahora );

		$this->wpdb->update(
			$this->tablaPeriodistas(),
			array( 'version_conducta_actual_id' => $versionId ),
			array( 'id' => $periodistaId ),
			array( '%d' ),
			array( '%d' )
		);

		return $periodistaId;
	}

	public function obtenerPorId( int $id ): ?Periodista {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tablaPeriodistas()} WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$fila = $this->wpdb->get_row( $sql, ARRAY_A );

		return null !== $fila ? $this->filaAPeriodista( $fila ) : null;
	}

	public function contarAutomaticosActivos(): int {
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->tablaPeriodistas()} WHERE creado_automaticamente = 1 AND estado != %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			EstadoPeriodista::Jubilado->value
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		return (int) $this->wpdb->get_var( $sql );
	}

	public function obtenerActivos(): array {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tablaPeriodistas()} WHERE estado = %s ORDER BY nombre ASC", EstadoPeriodista::Activo->value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		return array_map( fn ( array $fila ): Periodista => $this->filaAPeriodista( $fila ), $filas ?? array() );
	}

	public function obtenerTodos(): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery -- tabla interna, sin entrada de usuario que parametrizar.
		$filas = $this->wpdb->get_results( "SELECT * FROM {$this->tablaPeriodistas()} ORDER BY nombre ASC", ARRAY_A );

		return array_map( fn ( array $fila ): Periodista => $this->filaAPeriodista( $fila ), $filas ?? array() );
	}

	public function obtenerHistorialVersiones( int $periodistaId ): array {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tablaVersiones()} WHERE periodista_id = %d ORDER BY id ASC", $periodistaId ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		return array_map( fn ( array $fila ): ConductaVersion => $this->filaAVersion( $fila ), $filas ?? array() );
	}

	public function nuevaVersionConducta(
		int $periodistaId,
		Diales $diales,
		ReglasConducta $reglas,
		MatrizTonos $matrizTonos,
		DateTimeImmutable $ahora
	): int {
		$versionId = $this->insertarVersion( $periodistaId, $diales, $reglas, $matrizTonos, $ahora );

		$this->wpdb->update(
			$this->tablaPeriodistas(),
			array(
				'version_conducta_actual_id' => $versionId,
				'actualizado_en'             => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $periodistaId ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return $versionId;
	}

	public function obtenerVersionConducta( int $versionId ): ?ConductaVersion {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tablaVersiones()} WHERE id = %d", $versionId ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$fila = $this->wpdb->get_row( $sql, ARRAY_A );

		return null !== $fila ? $this->filaAVersion( $fila ) : null;
	}

	public function actualizarIdentidad(
		int $periodistaId,
		string $nombre,
		?string $avatarUrl,
		string $biografia,
		RolPeriodista $rol,
		array $especialidades,
		DateTimeImmutable $ahora,
		string $localeEditorial = 'es-ES'
	): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tablaPeriodistas(),
			array(
				'nombre'           => $nombre,
				'avatar_url'       => $avatarUrl,
				'biografia'        => $biografia,
				'rol'              => $rol->value,
				'especialidades'   => wp_json_encode( array_map( static fn ( Especialidad $e ): array => $e->aArray(), $especialidades ) ),
				'locale_editorial' => $localeEditorial,
				'actualizado_en'   => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $periodistaId ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $filasAfectadas ) {
			return false;
		}

		// $wpdb->update() (sin CLIENT_FOUND_ROWS) reporta 0 filas afectadas
		// tanto si la fila no existe como si existe pero el UPDATE no cambió
		// ningún valor — mismo defecto real ya corregido en
		// RepositorioTendencias::actualizarEstadoTendencia() esta sesión.
		if ( $filasAfectadas > 0 ) {
			return true;
		}

		return null !== $this->obtenerPorId( $periodistaId );
	}

	public function jubilar( int $periodistaId, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tablaPeriodistas(),
			array(
				'estado'         => EstadoPeriodista::Jubilado->value,
				'actualizado_en' => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $periodistaId ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	public function obtenerPropuestos(): array {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tablaPeriodistas()} WHERE estado = %s ORDER BY creado_en ASC", EstadoPeriodista::Propuesto->value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		return array_map( fn ( array $fila ): Periodista => $this->filaAPeriodista( $fila ), $filas ?? array() );
	}

	public function activarPropuesta( int $periodistaId, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tablaPeriodistas(),
			array(
				'estado'         => EstadoPeriodista::Activo->value,
				'actualizado_en' => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array(
				'id'     => $periodistaId,
				'estado' => EstadoPeriodista::Propuesto->value,
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		if ( false === $filasAfectadas ) {
			return false;
		}

		if ( $filasAfectadas > 0 ) {
			return true;
		}

		// Mismo defecto de MySQL corregido antes en esta sesión
		// (RepositorioTendencias/RepositorioPeriodistas::actualizarIdentidad):
		// un UPDATE que no cambia nada reporta 0 filas afectadas — no debe
		// confundirse con "no encontrado o no estaba Propuesto".
		$periodista = $this->obtenerPorId( $periodistaId );

		return null !== $periodista && EstadoPeriodista::Activo === $periodista->estado;
	}

	public function descartarPropuesta( int $periodistaId ): bool {
		$periodista = $this->obtenerPorId( $periodistaId );

		if ( null === $periodista || EstadoPeriodista::Propuesto !== $periodista->estado ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- borrado real acotado por periodista_id, sin caché aplicable.
		$this->wpdb->delete( $this->tablaVersiones(), array( 'periodista_id' => $periodistaId ), array( '%d' ) );

		return false !== $this->wpdb->delete( $this->tablaPeriodistas(), array( 'id' => $periodistaId ), array( '%d' ) );
	}

	private function insertarVersion(
		int $periodistaId,
		Diales $diales,
		ReglasConducta $reglas,
		MatrizTonos $matrizTonos,
		DateTimeImmutable $ahora
	): int {
		$this->wpdb->insert(
			$this->tablaVersiones(),
			array(
				'periodista_id'   => $periodistaId,
				'diales'          => wp_json_encode( $diales->aArray() ),
				'reglas_conducta' => wp_json_encode( $reglas->aArray() ),
				'matriz_tonos'    => wp_json_encode( $matrizTonos->aArray() ),
				'creada_en'       => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * @param array<string, mixed> $fila
	 */
	private function filaAPeriodista( array $fila ): Periodista {
		$conductaActual = $this->obtenerVersionConducta( (int) $fila['version_conducta_actual_id'] );
		assert( null !== $conductaActual, 'Toda fila de periodistas debe tener una versión de conducta válida.' );

		/** @var list<array{vertical: string, nivelDominio: int}> $especialidadesJson */
		$especialidadesJson = json_decode( (string) $fila['especialidades'], true );

		return new Periodista(
			(int) $fila['id'],
			(string) $fila['nombre'],
			null !== $fila['avatar_url'] ? (string) $fila['avatar_url'] : null,
			(string) $fila['biografia'],
			RolPeriodista::from( (string) $fila['rol'] ),
			array_map( static fn ( array $e ): Especialidad => Especialidad::desdeArray( $e ), $especialidadesJson ),
			EstadoPeriodista::from( (string) $fila['estado'] ),
			$conductaActual,
			new DateTimeImmutable( (string) $fila['creado_en'] ),
			new DateTimeImmutable( (string) $fila['actualizado_en'] ),
			isset( $fila['locale_editorial'] ) && is_string( $fila['locale_editorial'] ) ? $fila['locale_editorial'] : 'es-ES',
			isset( $fila['creado_automaticamente'] ) && '1' === (string) $fila['creado_automaticamente']
		);
	}

	/**
	 * @param array<string, mixed> $fila
	 */
	private function filaAVersion( array $fila ): ConductaVersion {
		/** @var array{agudezaCritica: int, humor: int, satira: int, formalidad: int, vehemencia: int, empatia: int, densidadDatos: int, longitudPreferida: int} $dialesJson */
		$dialesJson = json_decode( (string) $fila['diales'], true );

		/** @var array{lineaEditorial: string, lineasRojas: list<string>, muletillas: list<string>, vocabularioProhibido: list<string>, tratamientoLector: string, estiloPreguntaFinal: string} $reglasJson */
		$reglasJson = json_decode( (string) $fila['reglas_conducta'], true );

		/** @var array<string, array{tipoNoticia: string, tonoDominante: string, tonoApoyo: string, nivelSatira: string}> $matrizJson */
		$matrizJson = json_decode( (string) $fila['matriz_tonos'], true );

		return new ConductaVersion(
			(int) $fila['id'],
			(int) $fila['periodista_id'],
			Diales::desdeArray( $dialesJson ),
			ReglasConducta::desdeArray( $reglasJson ),
			MatrizTonos::desdeArray( $matrizJson ),
			new DateTimeImmutable( (string) $fila['creada_en'] )
		);
	}
}
