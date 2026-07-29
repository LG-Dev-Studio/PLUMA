<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Publicacion\EstadoPista;
use Pluma\Publicacion\Pista;
use wpdb;

/**
 * Único punto del plugin con `$wpdb` para `pluma_pistas` (CLAUDE.md § Ley
 * de Arquitectura).
 */
final class RepositorioPistas implements RepositorioPistasInterface {

	public function __construct( private readonly wpdb $wpdb ) {
	}

	private function tabla(): string {
		return $this->wpdb->prefix . 'pluma_pistas';
	}

	public function crear( int $historiaId, string $contenido, ?string $contactoEmail, DateTimeImmutable $ahora ): int {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'historia_id'    => $historiaId,
				'contenido'      => $contenido,
				'contacto_email' => $contactoEmail,
				'estado'         => EstadoPista::Pendiente->value,
				'creado_en'      => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	public function obtenerPorId( int $id ): ?Pista {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tabla()} WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$fila = $this->wpdb->get_row( $sql, ARRAY_A );

		return null !== $fila ? $this->filaAPista( $fila ) : null;
	}

	public function obtenerPorEstado( EstadoPista $estado, int $limite ): array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->tabla()} WHERE estado = %s ORDER BY creado_en DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$estado->value,
			$limite
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $filas ) ) {
			return array();
		}

		return array_map( array( $this, 'filaAPista' ), $filas );
	}

	public function actualizarEstado( int $id, EstadoPista $estado ): bool {
		return false !== $this->wpdb->update(
			$this->tabla(),
			array( 'estado' => $estado->value ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @param array<string, mixed> $fila
	 */
	private function filaAPista( array $fila ): Pista {
		return new Pista(
			(int) $fila['id'],
			(int) $fila['historia_id'],
			(string) $fila['contenido'],
			null !== $fila['contacto_email'] ? (string) $fila['contacto_email'] : null,
			EstadoPista::from( (string) $fila['estado'] ),
			new DateTimeImmutable( (string) $fila['creado_en'] )
		);
	}
}
