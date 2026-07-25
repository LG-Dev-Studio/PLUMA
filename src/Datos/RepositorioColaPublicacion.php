<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Pipeline\EstadoColaPublicacion;
use Pluma\Pipeline\RanuraPublicacion;
use wpdb;

/**
 * Único punto del plugin con `$wpdb` para `pluma_cola_publicacion`
 * (CLAUDE.md § Ley de Arquitectura). Ver nota sobre `argument.type`
 * ignorados en `RepositorioPiezas`: mismo motivo.
 */
final class RepositorioColaPublicacion implements RepositorioColaPublicacionInterface {

	public function __construct( private readonly wpdb $wpdb ) {
	}

	private function tabla(): string {
		return $this->wpdb->prefix . 'pluma_cola_publicacion';
	}

	public function programar(
		int $piezaId,
		string $vertical,
		?int $periodistaId,
		DateTimeImmutable $horaProgramada,
		DateTimeImmutable $ahora
	): int {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'pieza_id'        => $piezaId,
				'vertical'        => $vertical,
				'periodista_id'   => $periodistaId,
				'hora_programada' => $horaProgramada->format( 'Y-m-d H:i:s' ),
				'estado'          => EstadoColaPublicacion::Programada->value,
				'creada_en'       => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	public function obtenerEntre( DateTimeImmutable $inicio, DateTimeImmutable $fin ): array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->tabla()} WHERE hora_programada >= %s AND hora_programada < %s AND estado != %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$inicio->format( 'Y-m-d H:i:s' ),
			$fin->format( 'Y-m-d H:i:s' ),
			EstadoColaPublicacion::Expirada->value
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		return array_map( fn ( array $fila ): RanuraPublicacion => $this->filaARanura( $fila ), $filas ?? array() );
	}

	/**
	 * "Vencidas" incluye, además de las que ya cruzaron `hora_programada`, las
	 * ranuras con `aprobacion_activa` (Etapa 6, porción 4c: "aprobar ahora" en
	 * la cola de veto de Copiloto) sin importar si su hora aún no llegó — la
	 * aprobación humana activa adelanta la publicación al próximo tick.
	 */
	public function obtenerVencidas( DateTimeImmutable $ahora ): array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->tabla()} WHERE estado = %s AND (hora_programada <= %s OR aprobacion_activa = 1) ORDER BY hora_programada ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			EstadoColaPublicacion::Programada->value,
			$ahora->format( 'Y-m-d H:i:s' )
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		return array_map( fn ( array $fila ): RanuraPublicacion => $this->filaARanura( $fila ), $filas ?? array() );
	}

	public function obtenerProgramadaPorPieza( int $piezaId ): ?RanuraPublicacion {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->tabla()} WHERE pieza_id = %d AND estado = %s ORDER BY hora_programada DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$piezaId,
			EstadoColaPublicacion::Programada->value
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$fila = $this->wpdb->get_row( $sql, ARRAY_A );

		return null !== $fila ? $this->filaARanura( $fila ) : null;
	}

	public function marcarPublicada( int $id ): bool {
		return $this->actualizarEstado( $id, EstadoColaPublicacion::Publicada );
	}

	public function marcarExpirada( int $id ): bool {
		return $this->actualizarEstado( $id, EstadoColaPublicacion::Expirada );
	}

	public function marcarAprobacionActiva( int $id ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array( 'aprobacion_activa' => 1 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	private function actualizarEstado( int $id, EstadoColaPublicacion $estado ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array( 'estado' => $estado->value ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	/**
	 * @param array<string, mixed> $fila
	 */
	private function filaARanura( array $fila ): RanuraPublicacion {
		return new RanuraPublicacion(
			(int) $fila['id'],
			(int) $fila['pieza_id'],
			(string) $fila['vertical'],
			null !== $fila['periodista_id'] ? (int) $fila['periodista_id'] : null,
			new DateTimeImmutable( (string) $fila['hora_programada'] ),
			EstadoColaPublicacion::from( (string) $fila['estado'] ),
			(bool) $fila['aprobacion_activa'],
			new DateTimeImmutable( (string) $fila['creada_en'] )
		);
	}
}
