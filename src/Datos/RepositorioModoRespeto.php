<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Compuertas\ActivadorModoRespeto;
use Pluma\Compuertas\EstadoModoRespeto;
use wpdb;

/**
 * Único punto del plugin con `$wpdb` para `pluma_modo_respeto`
 * (CLAUDE.md § Ley de Arquitectura).
 */
final class RepositorioModoRespeto implements RepositorioModoRespetoInterface {

	public function __construct( private readonly wpdb $wpdb ) {
	}

	private function tabla(): string {
		return $this->wpdb->prefix . 'pluma_modo_respeto';
	}

	public function estadoActual(): EstadoModoRespeto {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- tabla interna, sin entrada de usuario en esta consulta.
		$fila = $this->wpdb->get_row( "SELECT * FROM {$this->tabla()} WHERE desactivado_en IS NULL ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna.

		if ( null === $fila ) {
			return EstadoModoRespeto::inactivo();
		}

		$activadoEn = new DateTimeImmutable( (string) $fila['activado_en'] );

		return new EstadoModoRespeto(
			true,
			$activadoEn,
			ActivadorModoRespeto::from( (string) $fila['activado_por'] ),
			(string) $fila['motivo'],
			$activadoEn->modify( '+' . ( (float) $fila['duracion_minima_horas'] ) . ' hours' )
		);
	}

	public function activar( ActivadorModoRespeto $activadoPor, string $motivo, float $duracionMinimaHoras, DateTimeImmutable $ahora ): int {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'activado_en'           => $ahora->format( 'Y-m-d H:i:s' ),
				'activado_por'          => $activadoPor->value,
				'motivo'                => $motivo,
				'duracion_minima_horas' => $duracionMinimaHoras,
			),
			array( '%s', '%s', '%s', '%f' )
		);

		return (int) $this->wpdb->insert_id;
	}

	public function desactivar( DateTimeImmutable $ahora ): bool {
		$sql = $this->wpdb->prepare(
			"UPDATE {$this->tabla()} SET desactivado_en = %s WHERE desactivado_en IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$ahora->format( 'Y-m-d H:i:s' )
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$actualizadas = $this->wpdb->query( $sql );

		return false !== $actualizadas && $actualizadas > 0;
	}
}
