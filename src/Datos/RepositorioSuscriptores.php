<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Publicacion\CanalSuscripcion;
use Pluma\Publicacion\Suscriptor;
use Pluma\Publicacion\TipoSuscripcion;
use wpdb;

/**
 * Único punto del plugin con `$wpdb` para `pluma_suscriptores` (CLAUDE.md §
 * Ley de Arquitectura).
 */
final class RepositorioSuscriptores implements RepositorioSuscriptoresInterface {

	public function __construct( private readonly wpdb $wpdb ) {
	}

	private function tabla(): string {
		return $this->wpdb->prefix . 'pluma_suscriptores';
	}

	public function crearEmail( TipoSuscripcion $tipo, ?int $referenciaId, ?string $vertical, string $email, string $token, DateTimeImmutable $ahora ): int {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'canal'         => CanalSuscripcion::Email->value,
				'tipo'          => $tipo->value,
				'referencia_id' => $referenciaId,
				'vertical'      => $vertical,
				'email'         => $email,
				'token'         => $token,
				'confirmado'    => 0,
				'creado_en'     => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	public function crearPush( TipoSuscripcion $tipo, ?int $referenciaId, ?string $vertical, string $endpoint, string $claveP256dh, string $claveAuth, string $token, DateTimeImmutable $ahora ): int {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'canal'             => CanalSuscripcion::Push->value,
				'tipo'              => $tipo->value,
				'referencia_id'     => $referenciaId,
				'vertical'          => $vertical,
				'push_endpoint'     => $endpoint,
				'push_clave_p256dh' => $claveP256dh,
				'push_clave_auth'   => $claveAuth,
				'token'             => $token,
				'confirmado'        => 0,
				'creado_en'         => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	public function obtenerPorToken( string $token ): ?Suscriptor {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tabla()} WHERE token = %s", $token ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$fila = $this->wpdb->get_row( $sql, ARRAY_A );

		return null !== $fila ? $this->filaASuscriptor( $fila ) : null;
	}

	public function confirmar( int $id, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array(
				'confirmado'    => 1,
				'confirmado_en' => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	public function eliminar( int $id ): bool {
		return false !== $this->wpdb->delete( $this->tabla(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Nunca hay a la vez `$referenciaId` y `$vertical` (`TipoSuscripcion`:
	 * Periodista/Historia usan `referenciaId`, Vertical usa `$vertical`,
	 * AlertaUrgente no usa ninguno de los dos) — tres consultas literales
	 * fijas en vez de construir el `WHERE` dinámicamente, para que
	 * `wpdb::prepare()` reciba siempre una cadena literal.
	 */
	public function obtenerConfirmadosPorObjetivo( CanalSuscripcion $canal, TipoSuscripcion $tipo, ?int $referenciaId, ?string $vertical ): array {
		if ( null !== $referenciaId ) {
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->tabla()} WHERE canal = %s AND tipo = %s AND confirmado = 1 AND referencia_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
				$canal->value,
				$tipo->value,
				$referenciaId
			);
		} elseif ( null !== $vertical ) {
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->tabla()} WHERE canal = %s AND tipo = %s AND confirmado = 1 AND vertical = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
				$canal->value,
				$tipo->value,
				$vertical
			);
		} else {
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->tabla()} WHERE canal = %s AND tipo = %s AND confirmado = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
				$canal->value,
				$tipo->value
			);
		}
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $filas ) ) {
			return array();
		}

		return array_map( array( $this, 'filaASuscriptor' ), $filas );
	}

	public function listar( int $limite ): array {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tabla()} ORDER BY creado_en DESC LIMIT %d", $limite ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $filas ) ) {
			return array();
		}

		return array_map( array( $this, 'filaASuscriptor' ), $filas );
	}

	public function obtenerPorEmail( string $email ): array {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tabla()} WHERE email = %s", $email ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $filas ) ) {
			return array();
		}

		return array_map( array( $this, 'filaASuscriptor' ), $filas );
	}

	public function eliminarPorEmail( string $email ): int {
		$filasAfectadas = $this->wpdb->delete( $this->tabla(), array( 'email' => $email ), array( '%s' ) );

		return false !== $filasAfectadas ? $filasAfectadas : 0;
	}

	/**
	 * @param array<string, mixed> $fila
	 */
	private function filaASuscriptor( array $fila ): Suscriptor {
		return new Suscriptor(
			(int) $fila['id'],
			CanalSuscripcion::from( (string) $fila['canal'] ),
			TipoSuscripcion::from( (string) $fila['tipo'] ),
			null !== ( $fila['referencia_id'] ?? null ) ? (int) $fila['referencia_id'] : null,
			null !== ( $fila['vertical'] ?? null ) ? (string) $fila['vertical'] : null,
			null !== ( $fila['email'] ?? null ) ? (string) $fila['email'] : null,
			null !== ( $fila['push_endpoint'] ?? null ) ? (string) $fila['push_endpoint'] : null,
			null !== ( $fila['push_clave_p256dh'] ?? null ) ? (string) $fila['push_clave_p256dh'] : null,
			null !== ( $fila['push_clave_auth'] ?? null ) ? (string) $fila['push_clave_auth'] : null,
			(string) $fila['token'],
			(bool) (int) $fila['confirmado'],
			new DateTimeImmutable( (string) $fila['creado_en'] ),
			null !== ( $fila['confirmado_en'] ?? null ) ? new DateTimeImmutable( (string) $fila['confirmado_en'] ) : null
		);
	}
}
