<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Publicacion\DerivadoSocial;
use Pluma\Publicacion\EstadoDerivadoSocial;
use wpdb;

/**
 * Único punto del plugin con `$wpdb` para `pluma_derivados_sociales`
 * (CLAUDE.md § Ley de Arquitectura).
 */
final class RepositorioDerivadosSociales implements RepositorioDerivadosSocialesInterface {

	public function __construct( private readonly wpdb $wpdb ) {
	}

	private function tabla(): string {
		return $this->wpdb->prefix . 'pluma_derivados_sociales';
	}

	public function crear( int $piezaId, string $extractoSocial, string $titularDiscover, DateTimeImmutable $ahora ): int {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'pieza_id'         => $piezaId,
				'extracto_social'  => $extractoSocial,
				'titular_discover' => $titularDiscover,
				'estado'           => EstadoDerivadoSocial::Pendiente->value,
				'creado_en'        => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	public function obtenerPorId( int $id ): ?DerivadoSocial {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tabla()} WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$fila = $this->wpdb->get_row( $sql, ARRAY_A );

		return null !== $fila ? $this->filaADerivado( $fila ) : null;
	}

	public function obtenerPorEstado( EstadoDerivadoSocial $estado, int $limite ): array {
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

		return array_map( array( $this, 'filaADerivado' ), $filas );
	}

	public function actualizarEstado( int $id, EstadoDerivadoSocial $estado ): bool {
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
	private function filaADerivado( array $fila ): DerivadoSocial {
		return new DerivadoSocial(
			(int) $fila['id'],
			(int) $fila['pieza_id'],
			(string) $fila['extracto_social'],
			(string) $fila['titular_discover'],
			EstadoDerivadoSocial::from( (string) $fila['estado'] ),
			new DateTimeImmutable( (string) $fila['creado_en'] )
		);
	}
}
