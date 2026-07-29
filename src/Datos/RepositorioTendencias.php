<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Sensores\DiagnosticoLegitimidadInsumo;
use Pluma\Sensores\EstadoTendencia;
use Pluma\Sensores\TendenciaDetectada;
use wpdb;

final class RepositorioTendencias implements RepositorioTendenciasInterface {

	public function __construct( private readonly wpdb $wpdb ) {
	}

	private function tabla(): string {
		return $this->wpdb->prefix . 'pluma_tendencias';
	}

	public function existePorTermino( string $termino, string $fuenteSenal ): bool {
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->tabla()} WHERE termino = %s AND fuente_senal = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			mb_strtolower( trim( $termino ) ),
			$fuenteSenal
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$total = $this->wpdb->get_var( $sql );

		return null !== $total && (int) $total > 0;
	}

	public function guardar( TendenciaDetectada $tendencia, DateTimeImmutable $ahora ): int {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'termino'                => mb_strtolower( trim( $tendencia->termino ) ),
				'fuente_senal'           => $tendencia->fuenteSenal,
				'puntuacion_velocidad'   => $tendencia->puntuacion->velocidad,
				'puntuacion_afinidad'    => $tendencia->puntuacion->afinidad,
				'puntuacion_total'       => $tendencia->puntuacion->total,
				'articulos_relacionados' => wp_json_encode( $tendencia->articulosRelacionados ),
				'detectada_en'           => $tendencia->detectadaEn->format( 'Y-m-d H:i:s' ),
				'creada_en'              => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	public function guardarComoPosibleActualizacion( TendenciaDetectada $tendencia, int $tendenciaOriginalId, DateTimeImmutable $ahora ): int {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'termino'                => mb_strtolower( trim( $tendencia->termino ) ),
				'fuente_senal'           => $tendencia->fuenteSenal,
				'puntuacion_velocidad'   => $tendencia->puntuacion->velocidad,
				'puntuacion_afinidad'    => $tendencia->puntuacion->afinidad,
				'puntuacion_total'       => $tendencia->puntuacion->total,
				'articulos_relacionados' => wp_json_encode( $tendencia->articulosRelacionados ),
				'estado'                 => EstadoTendencia::PosibleActualizacion->value,
				'tendencia_original_id'  => $tendenciaOriginalId,
				'detectada_en'           => $tendencia->detectadaEn->format( 'Y-m-d H:i:s' ),
				'creada_en'              => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%d', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	public function guardarConSospechaLegitimidad( TendenciaDetectada $tendencia, DiagnosticoLegitimidadInsumo $diagnostico, DateTimeImmutable $ahora ): int {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'termino'                => mb_strtolower( trim( $tendencia->termino ) ),
				'fuente_senal'           => $tendencia->fuenteSenal,
				'puntuacion_velocidad'   => $tendencia->puntuacion->velocidad,
				'puntuacion_afinidad'    => $tendencia->puntuacion->afinidad,
				'puntuacion_total'       => $tendencia->puntuacion->total,
				'articulos_relacionados' => wp_json_encode( $tendencia->articulosRelacionados ),
				'estado'                 => EstadoTendencia::SospechaDeManipulacion->value,
				'diversidad_fuente'      => $diagnostico->diversidadFuente,
				'motivo_legitimidad'     => $diagnostico->motivo,
				'detectada_en'           => $tendencia->detectadaEn->format( 'Y-m-d H:i:s' ),
				'creada_en'              => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%f', '%s', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	public function obtenerRecientesConPiezaViva( int $diasVentana, int $limite, DateTimeImmutable $ahora ): array {
		$sql = $this->wpdb->prepare(
			"SELECT t.id, t.termino, t.articulos_relacionados FROM {$this->tabla()} t" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
				. " INNER JOIN {$this->wpdb->prefix}pluma_piezas p ON p.tendencia_id = t.id" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna.
				. ' WHERE t.detectada_en >= DATE_SUB(%s, INTERVAL %d DAY)'
				. " AND p.estado NOT IN ('descartada', 'fallida')"
				. ' ORDER BY t.detectada_en DESC LIMIT %d',
			$ahora->format( 'Y-m-d H:i:s' ),
			$diasVentana,
			$limite
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $filas ) ) {
			return array();
		}

		return array_map(
			static function ( array $fila ): array {
				/** @var list<array{titulo: string, url: string, fuente: string}> $articulos */
				$articulos = json_decode( (string) $fila['articulos_relacionados'], true ) ?? array();

				return array(
					'id'                    => (int) $fila['id'],
					'termino'               => (string) $fila['termino'],
					'articulosRelacionados' => $articulos,
				);
			},
			$filas
		);
	}

	public function obtenerPorId( int $id ): ?array {
		$sql = $this->wpdb->prepare( "SELECT termino, articulos_relacionados FROM {$this->tabla()} WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$fila = $this->wpdb->get_row( $sql, ARRAY_A );

		if ( null === $fila ) {
			return null;
		}

		/** @var list<array{titulo: string, url: string, fuente: string}> $articulos */
		$articulos = json_decode( (string) $fila['articulos_relacionados'], true ) ?? array();

		return array(
			'termino'               => (string) $fila['termino'],
			'articulosRelacionados' => $articulos,
		);
	}

	public function obtenerRecientes( int $limite ): array {
		$sql = $this->wpdb->prepare(
			"SELECT id, termino, puntuacion_total, detectada_en FROM {$this->tabla()} ORDER BY puntuacion_total DESC, detectada_en DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$limite
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $filas ) ) {
			return array();
		}

		return array_map(
			static fn ( array $fila ): array => array(
				'id'              => (int) $fila['id'],
				'termino'         => (string) $fila['termino'],
				'puntuacionTotal' => (float) $fila['puntuacion_total'],
				'detectadaEn'     => (string) $fila['detectada_en'],
			),
			$filas
		);
	}

	public function obtenerParaSala( int $limite ): array {
		$sql = $this->wpdb->prepare(
			"SELECT id, termino, fuente_senal, puntuacion_velocidad, puntuacion_afinidad, puntuacion_total, estado, articulos_relacionados, detectada_en, tendencia_original_id FROM {$this->tabla()} WHERE estado != %s ORDER BY puntuacion_total DESC, detectada_en DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			EstadoTendencia::Ignorada->value,
			$limite
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $filas ) ) {
			return array();
		}

		return array_map(
			static function ( array $fila ): array {
				/** @var list<array{titulo: string, url: string, fuente: string}> $articulos */
				$articulos = json_decode( (string) $fila['articulos_relacionados'], true ) ?? array();

				return array(
					'id'                    => (int) $fila['id'],
					'termino'               => (string) $fila['termino'],
					'fuenteSenal'           => (string) $fila['fuente_senal'],
					'velocidad'             => (float) $fila['puntuacion_velocidad'],
					'afinidad'              => (float) $fila['puntuacion_afinidad'],
					'puntuacionTotal'       => (float) $fila['puntuacion_total'],
					'estado'                => EstadoTendencia::tryFrom( (string) $fila['estado'] ) ?? EstadoTendencia::EnPipeline,
					'articulosRelacionados' => $articulos,
					'detectadaEn'           => (string) $fila['detectada_en'],
					'tendenciaOriginalId'   => null !== $fila['tendencia_original_id'] ? (int) $fila['tendencia_original_id'] : null,
				);
			},
			$filas
		);
	}

	public function obtenerTendenciaOriginal( int $id ): ?int {
		$sql = $this->wpdb->prepare( "SELECT tendencia_original_id FROM {$this->tabla()} WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$valor = $this->wpdb->get_var( $sql );

		return null !== $valor ? (int) $valor : null;
	}

	public function contarPorEstadoEntre( EstadoTendencia $estado, DateTimeImmutable $desde, DateTimeImmutable $hasta ): int {
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->tabla()} WHERE estado = %s AND detectada_en BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$estado->value,
			$desde->format( 'Y-m-d H:i:s' ),
			$hasta->format( 'Y-m-d H:i:s' )
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		return (int) $this->wpdb->get_var( $sql );
	}

	public function actualizarGravedad( int $id, int $gravedad, string $campoTematico, ?string $campoGeografico ): void {
		$this->wpdb->update(
			$this->tabla(),
			array(
				'gravedad'         => $gravedad,
				'campo_tematico'   => $campoTematico,
				'campo_geografico' => $campoGeografico,
			),
			array( 'id' => $id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	public function obtenerGravedadMaximaRecientes( int $umbralGravedad, DateTimeImmutable $desde ): array {
		$sql = $this->wpdb->prepare(
			"SELECT id, campo_tematico, campo_geografico FROM {$this->tabla()} WHERE gravedad >= %d AND detectada_en >= %s ORDER BY detectada_en ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$umbralGravedad,
			$desde->format( 'Y-m-d H:i:s' )
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $filas ) ) {
			return array();
		}

		return array_map(
			static fn ( array $fila ): array => array(
				'id'              => (int) $fila['id'],
				'campoTematico'   => (string) $fila['campo_tematico'],
				'campoGeografico' => null !== $fila['campo_geografico'] ? (string) $fila['campo_geografico'] : null,
			),
			$filas
		);
	}

	public function actualizarEstadoTendencia( int $id, EstadoTendencia $estado ): bool {
		$actualizadas = $this->wpdb->update(
			$this->tabla(),
			array( 'estado' => $estado->value ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $actualizadas ) {
			return false;
		}

		if ( $actualizadas > 0 ) {
			return true;
		}

		// $wpdb->update() (sin CLIENT_FOUND_ROWS) reporta 0 filas afectadas
		// tanto si la fila no existe como si existe pero el UPDATE no cambió
		// ningún valor (ej. "Cubrir ahora" sobre una tendencia que ya está
		// EN_PIPELINE) — sin esta verificación, ese caso se confundía con
		// "tendencia no encontrada" y la acción fallaba en el panel real.
		return null !== $this->obtenerPorId( $id );
	}
}
