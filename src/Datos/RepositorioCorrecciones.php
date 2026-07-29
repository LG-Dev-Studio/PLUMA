<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Publicacion\Correccion;
use Pluma\Publicacion\EstadoCorreccion;
use wpdb;

/**
 * Único punto del plugin con `$wpdb` para `pluma_correcciones` (CLAUDE.md §
 * Ley de Arquitectura).
 */
final class RepositorioCorrecciones implements RepositorioCorreccionesInterface {

	public function __construct( private readonly wpdb $wpdb ) {
	}

	private function tabla(): string {
		return $this->wpdb->prefix . 'pluma_correcciones';
	}

	public function crear( int $piezaId, string $afirmacionReportada, string $evidenciaAportada, ?string $emailReportante, ?string $nombreCredito, bool $creditoOptIn, DateTimeImmutable $ahora ): int {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'pieza_id'             => $piezaId,
				'afirmacion_reportada' => $afirmacionReportada,
				'evidencia_aportada'   => $evidenciaAportada,
				'email_reportante'     => $emailReportante,
				'nombre_credito'       => $nombreCredito,
				'credito_opt_in'       => $creditoOptIn ? 1 : 0,
				'estado'               => EstadoCorreccion::Pendiente->value,
				'creado_en'            => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	public function obtenerPorId( int $id ): ?Correccion {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tabla()} WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$fila = $this->wpdb->get_row( $sql, ARRAY_A );

		return null !== $fila ? $this->filaACorreccion( $fila ) : null;
	}

	public function obtenerPorEstado( EstadoCorreccion $estado, int $limite ): array {
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

		return array_map( array( $this, 'filaACorreccion' ), $filas );
	}

	public function resolver( int $id, EstadoCorreccion $estado, ?string $notaEditor, DateTimeImmutable $ahora ): bool {
		return false !== $this->wpdb->update(
			$this->tabla(),
			array(
				'estado'      => $estado->value,
				'nota_editor' => $notaEditor,
				'resuelto_en' => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	public function obtenerVerificadasRecientes( int $limite ): array {
		return $this->obtenerPorEstado( EstadoCorreccion::Verificada, $limite );
	}

	/**
	 * @param array<string, mixed> $fila
	 */
	private function filaACorreccion( array $fila ): Correccion {
		return new Correccion(
			(int) $fila['id'],
			(int) $fila['pieza_id'],
			(string) $fila['afirmacion_reportada'],
			(string) $fila['evidencia_aportada'],
			null !== $fila['email_reportante'] ? (string) $fila['email_reportante'] : null,
			null !== $fila['nombre_credito'] ? (string) $fila['nombre_credito'] : null,
			(bool) (int) $fila['credito_opt_in'],
			EstadoCorreccion::from( (string) $fila['estado'] ),
			null !== $fila['nota_editor'] ? (string) $fila['nota_editor'] : null,
			new DateTimeImmutable( (string) $fila['creado_en'] ),
			null !== ( $fila['resuelto_en'] ?? null ) ? new DateTimeImmutable( (string) $fila['resuelto_en'] ) : null
		);
	}
}
