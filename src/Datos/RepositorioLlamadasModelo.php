<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Proveedores\RegistroLlamada;
use wpdb;

final class RepositorioLlamadasModelo implements RepositorioLlamadasModeloInterface {

	public function __construct( private readonly wpdb $wpdb ) {
	}

	private function tabla(): string {
		return $this->wpdb->prefix . 'pluma_llamadas_modelo';
	}

	public function registrar( RegistroLlamada $registro, DateTimeImmutable $ahora ): void {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'proposito'      => $registro->proposito,
				'proveedor'      => $registro->proveedor,
				'modelo'         => $registro->modelo,
				'familia'        => $registro->familia,
				'origen'         => $registro->origen->value,
				'resultado'      => $registro->resultado->value,
				'tokens_entrada' => $registro->tokensEntrada,
				'tokens_salida'  => $registro->tokensSalida,
				'coste_usd'      => $registro->costeUsd,
				'latencia_ms'    => $registro->latenciaMs,
				'truncada'       => $registro->truncada ? 1 : 0,
				'creada_en'      => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%d', '%d', '%s' )
		);
	}

	public function resumirEntre( DateTimeImmutable $desde, DateTimeImmutable $hasta ): array {
		$sql = $this->wpdb->prepare(
			'SELECT proposito, origen, resultado, COUNT(*) AS llamadas, COALESCE(SUM(coste_usd), 0) AS coste_usd, COALESCE(SUM(tokens_entrada), 0) AS tokens_entrada, COALESCE(SUM(tokens_salida), 0) AS tokens_salida FROM ' . $this->tabla() . ' WHERE creada_en BETWEEN %s AND %s GROUP BY proposito, origen, resultado ORDER BY llamadas DESC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- tabla interna, generada internamente sin entrada de usuario. @phpstan-ignore-line argument.type
			$desde->format( 'Y-m-d H:i:s' ),
			$hasta->format( 'Y-m-d H:i:s' )
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		return array_map(
			/** @param array<string, mixed> $fila */
			static fn ( array $fila ): array => array(
				'proposito'     => (string) $fila['proposito'],
				'origen'        => (string) $fila['origen'],
				'resultado'     => (string) $fila['resultado'],
				'llamadas'      => (int) $fila['llamadas'],
				'costeUsd'      => (float) $fila['coste_usd'],
				'tokensEntrada' => (int) $fila['tokens_entrada'],
				'tokensSalida'  => (int) $fila['tokens_salida'],
			),
			$filas ?? array()
		);
	}

	public function purgarAnterioresA( DateTimeImmutable $limite ): int {
		$sql = $this->wpdb->prepare(
			'DELETE FROM ' . $this->tabla() . ' WHERE creada_en < %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- tabla interna, generada internamente sin entrada de usuario. @phpstan-ignore-line argument.type
			$limite->format( 'Y-m-d H:i:s' )
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery -- $sql ya se construyó con $wpdb->prepare() arriba; DELETE no tiene equivalente en $wpdb->delete() para condiciones de rango.
		$filasAfectadas = $this->wpdb->query( $sql );

		return is_int( $filasAfectadas ) ? $filasAfectadas : 0;
	}
}
