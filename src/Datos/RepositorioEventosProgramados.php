<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Pipeline\EstadoEventoProgramado;
use Pluma\Pipeline\EventoProgramado;
use wpdb;

/**
 * Único punto del plugin con `$wpdb` para `pluma_eventos_programados`
 * (CLAUDE.md § Ley de Arquitectura).
 */
final class RepositorioEventosProgramados implements RepositorioEventosProgramadosInterface {

	public function __construct( private readonly wpdb $wpdb ) {
	}

	private function tabla(): string {
		return $this->wpdb->prefix . 'pluma_eventos_programados';
	}

	public function crear( string $titulo, string $vertical, DateTimeImmutable $fechaEsperada, ?int $periodistaAsignadoId, ?int $historiaId, DateTimeImmutable $ahora ): int {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'titulo'                 => $titulo,
				'vertical'               => $vertical,
				'fecha_esperada'         => $fechaEsperada->format( 'Y-m-d H:i:s' ),
				'estado'                 => EstadoEventoProgramado::Previsto->value,
				'periodista_asignado_id' => $periodistaAsignadoId,
				'historia_id'            => $historiaId,
				'tendencia_id'           => null,
				'creado_en'              => $ahora->format( 'Y-m-d H:i:s' ),
				'actualizado_en'         => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	public function obtenerPorId( int $id ): ?EventoProgramado {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tabla()} WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$fila = $this->wpdb->get_row( $sql, ARRAY_A );

		return null !== $fila ? $this->filaAEvento( $fila ) : null;
	}

	public function listar( int $limite ): array {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tabla()} ORDER BY fecha_esperada ASC LIMIT %d", $limite ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $filas ) ) {
			return array();
		}

		return array_map( array( $this, 'filaAEvento' ), $filas );
	}

	public function actualizarEstado( int $id, EstadoEventoProgramado $estado, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array(
				'estado'         => $estado->value,
				'actualizado_en' => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	public function vincularTendencia( int $id, int $tendenciaId, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array(
				'tendencia_id'   => $tendenciaId,
				'actualizado_en' => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	public function vincularHistoria( int $id, int $historiaId, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array(
				'historia_id'    => $historiaId,
				'actualizado_en' => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	/**
	 * @param array<string, mixed> $fila
	 */
	private function filaAEvento( array $fila ): EventoProgramado {
		return new EventoProgramado(
			(int) $fila['id'],
			(string) $fila['titulo'],
			(string) $fila['vertical'],
			new DateTimeImmutable( (string) $fila['fecha_esperada'] ),
			EstadoEventoProgramado::from( (string) $fila['estado'] ),
			null !== ( $fila['periodista_asignado_id'] ?? null ) ? (int) $fila['periodista_asignado_id'] : null,
			null !== ( $fila['historia_id'] ?? null ) ? (int) $fila['historia_id'] : null,
			null !== ( $fila['tendencia_id'] ?? null ) ? (int) $fila['tendencia_id'] : null,
			new DateTimeImmutable( (string) $fila['creado_en'] ),
			new DateTimeImmutable( (string) $fila['actualizado_en'] )
		);
	}
}
