<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\TipoAprobacion;
use wpdb;

/**
 * `pluma_auditoria`: toda transición registra {de, a, actor, motivo,
 * timestamp} (pl-pipeline §1, `references/estados.md`).
 */
final class RepositorioAuditoria implements RepositorioAuditoriaInterface {

	public function __construct( private readonly wpdb $wpdb ) {
	}

	private function tabla(): string {
		return $this->wpdb->prefix . 'pluma_auditoria';
	}

	public function registrar(
		int $piezaId,
		?EstadoPieza $estadoAnterior,
		EstadoPieza $estadoNuevo,
		string $actor,
		string $motivo,
		DateTimeImmutable $ahora,
		?TipoAprobacion $tipoAprobacion = null
	): void {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'pieza_id'        => $piezaId,
				'estado_anterior' => $estadoAnterior?->value,
				'estado_nuevo'    => $estadoNuevo->value,
				'actor'           => $actor,
				'motivo'          => $motivo,
				'tipo_aprobacion' => $tipoAprobacion?->value,
				'ocurrida_en'     => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
