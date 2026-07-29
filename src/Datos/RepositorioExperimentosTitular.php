<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Seo\ExperimentoTitular;
use wpdb;

/**
 * Único punto del plugin con `$wpdb` para `pluma_experimentos_titular`
 * (CLAUDE.md § Ley de Arquitectura).
 */
final class RepositorioExperimentosTitular implements RepositorioExperimentosTitularInterface {

	public function __construct( private readonly wpdb $wpdb ) {
	}

	private function tabla(): string {
		return $this->wpdb->prefix . 'pluma_experimentos_titular';
	}

	public function crear( int $piezaId, int $postId, string $tituloA, string $tituloB, DateTimeImmutable $ahora ): int {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'pieza_id'  => $piezaId,
				'post_id'   => $postId,
				'titulo_a'  => $tituloA,
				'titulo_b'  => $tituloB,
				'creado_en' => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	public function obtenerPorPostId( int $postId ): ?ExperimentoTitular {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tabla()} WHERE post_id = %d AND consolidado_en IS NULL", $postId ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$fila = $this->wpdb->get_row( $sql, ARRAY_A );

		return null !== $fila ? $this->filaAExperimento( $fila ) : null;
	}

	public function incrementarImpresion( int $id, string $variante ): void {
		$sql = 'a' === $variante
			? $this->wpdb->prepare( "UPDATE {$this->tabla()} SET impresiones_a = impresiones_a + 1 WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			: $this->wpdb->prepare( "UPDATE {$this->tabla()} SET impresiones_b = impresiones_b + 1 WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$this->wpdb->query( $sql );
	}

	public function incrementarClic( int $id, string $variante ): void {
		$sql = 'a' === $variante
			? $this->wpdb->prepare( "UPDATE {$this->tabla()} SET clics_a = clics_a + 1 WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			: $this->wpdb->prepare( "UPDATE {$this->tabla()} SET clics_b = clics_b + 1 WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$this->wpdb->query( $sql );
	}

	public function obtenerListosParaConsolidar( DateTimeImmutable $limiteCreacion, int $limite ): array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->tabla()} WHERE consolidado_en IS NULL AND creado_en <= %s LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$limiteCreacion->format( 'Y-m-d H:i:s' ),
			$limite
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $filas ) ) {
			return array();
		}

		return array_map( array( $this, 'filaAExperimento' ), $filas );
	}

	public function consolidar( int $id, string $tituloGanador, DateTimeImmutable $ahora ): bool {
		return false !== $this->wpdb->update(
			$this->tabla(),
			array(
				'titulo_ganador' => $tituloGanador,
				'consolidado_en' => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @param array<string, mixed> $fila
	 */
	private function filaAExperimento( array $fila ): ExperimentoTitular {
		return new ExperimentoTitular(
			(int) $fila['id'],
			(int) $fila['pieza_id'],
			(int) $fila['post_id'],
			(string) $fila['titulo_a'],
			(string) $fila['titulo_b'],
			(int) $fila['impresiones_a'],
			(int) $fila['clics_a'],
			(int) $fila['impresiones_b'],
			(int) $fila['clics_b'],
			null !== $fila['titulo_ganador'] ? (string) $fila['titulo_ganador'] : null,
			null !== $fila['consolidado_en'] ? new DateTimeImmutable( (string) $fila['consolidado_en'] ) : null,
			new DateTimeImmutable( (string) $fila['creado_en'] )
		);
	}
}
