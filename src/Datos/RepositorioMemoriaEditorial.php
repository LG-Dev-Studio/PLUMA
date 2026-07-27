<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Redaccion\EntradaMemoria;
use Pluma\Redaccion\TipoMemoria;
use wpdb;

/**
 * Único punto del plugin con `$wpdb` para `pluma_memoria_editorial`
 * (CLAUDE.md § Ley de Arquitectura). Ver `RepositorioPiezas` para la nota
 * sobre los `argument.type` ignorados en `$wpdb->prepare()`.
 */
final class RepositorioMemoriaEditorial implements RepositorioMemoriaEditorialInterface {

	public function __construct( private readonly wpdb $wpdb ) {
	}

	private function tabla(): string {
		return $this->wpdb->prefix . 'pluma_memoria_editorial';
	}

	public function registrar(
		int $periodistaId,
		TipoMemoria $tipo,
		string $tema,
		array $contenido,
		?int $piezaId,
		DateTimeImmutable $ahora
	): int {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'periodista_id' => $periodistaId,
				'tipo'          => $tipo->value,
				'tema'          => $tema,
				'contenido'     => wp_json_encode( $contenido ),
				'pieza_id'      => $piezaId,
				'creada_en'     => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	public function obtenerPosturasPorTema( int $periodistaId, string $tema ): array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->tabla()} WHERE periodista_id = %d AND tipo = %s AND tema = %s ORDER BY creada_en DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$periodistaId,
			TipoMemoria::Postura->value,
			$tema
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		return array_map( fn ( array $fila ): EntradaMemoria => $this->filaAEntrada( $fila ), $filas ?? array() );
	}

	public function obtenerPosturasColectivasPorTema( string $tema, int $limite = 20 ): array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->tabla()} WHERE tipo = %s AND tema = %s ORDER BY creada_en DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			TipoMemoria::Postura->value,
			$tema,
			$limite
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		return array_map( fn ( array $fila ): EntradaMemoria => $this->filaAEntrada( $fila ), $filas ?? array() );
	}

	public function obtenerPorPeriodista( int $periodistaId, ?TipoMemoria $tipo = null, int $limite = 50 ): array {
		if ( null !== $tipo ) {
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->tabla()} WHERE periodista_id = %d AND tipo = %s ORDER BY creada_en DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
				$periodistaId,
				$tipo->value,
				$limite
			);
		} else {
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->tabla()} WHERE periodista_id = %d ORDER BY creada_en DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
				$periodistaId,
				$limite
			);
		}
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		return array_map( fn ( array $fila ): EntradaMemoria => $this->filaAEntrada( $fila ), $filas ?? array() );
	}

	public function obtenerTodoPorPeriodista( int $periodistaId ): array {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tabla()} WHERE periodista_id = %d ORDER BY creada_en ASC", $periodistaId ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		return array_map( fn ( array $fila ): EntradaMemoria => $this->filaAEntrada( $fila ), $filas ?? array() );
	}

	public function existeCoberturaDelTema( int $periodistaId, string $tema ): bool {
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->tabla()} WHERE periodista_id = %d AND tipo = %s AND tema = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$periodistaId,
			TipoMemoria::Cobertura->value,
			$tema
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		return ( (int) $this->wpdb->get_var( $sql ) ) > 0;
	}

	/**
	 * @param array<string, mixed> $fila
	 */
	private function filaAEntrada( array $fila ): EntradaMemoria {
		/** @var array<string, mixed> $contenido */
		$contenido = json_decode( (string) $fila['contenido'], true );

		return new EntradaMemoria(
			(int) $fila['id'],
			(int) $fila['periodista_id'],
			TipoMemoria::from( (string) $fila['tipo'] ),
			(string) $fila['tema'],
			$contenido,
			null !== $fila['pieza_id'] ? (int) $fila['pieza_id'] : null,
			new DateTimeImmutable( (string) $fila['creada_en'] )
		);
	}
}
