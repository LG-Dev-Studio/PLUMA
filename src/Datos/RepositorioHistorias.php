<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Pipeline\EstadoHistoria;
use Pluma\Pipeline\Historia;
use wpdb;

/**
 * Único punto del plugin con `$wpdb` para `pluma_historias` (CLAUDE.md §
 * Ley de Arquitectura). No toca `pluma_piezas` — por eso `Historia::$piezaIds`
 * siempre llega vacío desde aquí; `Pluma\Pipeline\GestorHistorias` es quien
 * compone la Historia completa combinando este repositorio con
 * `RepositorioPiezasInterface::obtenerPorHistoria()`.
 */
final class RepositorioHistorias implements RepositorioHistoriasInterface {

	public function __construct( private readonly wpdb $wpdb ) {
	}

	private function tabla(): string {
		return $this->wpdb->prefix . 'pluma_historias';
	}

	public function crear( string $titulo, DateTimeImmutable $ahora ): int {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'titulo'         => $titulo,
				'estado'         => EstadoHistoria::Abierta->value,
				'creada_en'      => $ahora->format( 'Y-m-d H:i:s' ),
				'actualizada_en' => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	public function obtenerPorId( int $id ): ?Historia {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tabla()} WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$fila = $this->wpdb->get_row( $sql, ARRAY_A );

		return null !== $fila ? $this->filaAHistoria( $fila ) : null;
	}

	public function actualizarEstado( int $id, EstadoHistoria $estado, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array(
				'estado'         => $estado->value,
				'actualizada_en' => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	public function asignarPeriodistaTitular( int $id, int $periodistaId, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array(
				'periodista_titular_id' => $periodistaId,
				'actualizada_en'        => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	public function tocar( int $id, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array( 'actualizada_en' => $ahora->format( 'Y-m-d H:i:s' ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	public function obtenerAbiertasSinActividadDesde( DateTimeImmutable $limite ): array {
		$sql = $this->wpdb->prepare(
			"SELECT id FROM {$this->tabla()} WHERE estado IN (%s, %s) AND actualizada_en < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			EstadoHistoria::Abierta->value,
			EstadoHistoria::EnSeguimiento->value,
			$limite->format( 'Y-m-d H:i:s' )
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_col( $sql );

		return array_map( static fn ( string $id ): int => (int) $id, $filas );
	}

	/**
	 * @param array<string, mixed> $fila
	 */
	private function filaAHistoria( array $fila ): Historia {
		return new Historia(
			(int) $fila['id'],
			(string) $fila['titulo'],
			EstadoHistoria::from( (string) $fila['estado'] ),
			null !== ( $fila['periodista_titular_id'] ?? null ) ? (int) $fila['periodista_titular_id'] : null,
			array(),
			new DateTimeImmutable( (string) $fila['creada_en'] ),
			new DateTimeImmutable( (string) $fila['actualizada_en'] )
		);
	}
}
