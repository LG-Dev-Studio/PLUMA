<?php

declare(strict_types=1);

namespace Pluma\Datos;

use wpdb;

/**
 * Migrador de esquema de PLUMA (sub-agente ESQUEMA, GOVERNANCE §1.4, pl-wp-core §3).
 *
 * Único punto del plugin que invoca `dbDelta`. La Etapa 0 no define tablas
 * todavía — la Pieza y su máquina de estados nacen en la Etapa 1 — pero la
 * infraestructura de versión y migración debe existir desde el primer commit
 * para que ninguna tabla futura se cree jamás fuera de este contrato.
 */
final class Migrador {

	public const OPCION_VERSION = 'pluma_db_version';

	public function __construct( private readonly wpdb $wpdb ) {
	}

	public function versionInstalada(): string {
		$version = get_option( self::OPCION_VERSION, '0.0.0' );

		return is_string( $version ) ? $version : '0.0.0';
	}

	/**
	 * Ejecuta las sentencias `CREATE TABLE` (formato dbDelta) necesarias
	 * para alcanzar `$versionObjetivo` y deja la versión registrada.
	 *
	 * Idempotente por construcción: `dbDelta` no reaplica cambios ya
	 * presentes y `update_option` con el mismo valor no escribe de más.
	 *
	 * Tras `dbDelta`, reconstruye cada tabla tocada (`ALTER TABLE ... FORCE`)
	 * — barato en el camino real (una migración de esquema es un evento raro,
	 * nunca una ruta caliente: {@see \Pluma\Kernel\Activador::actualizarEsquemaSiHaceFalta()}
	 * solo llega aquí cuando la versión instalada cambió), pero indispensable
	 * para no acumular indefinidamente los "restos" internos de columnas que
	 * MySQL/MariaDB conservan tras un `ALTER TABLE` con algoritmo `INSTANT`
	 * hasta la siguiente reconstrucción real de la tabla — verificado que,
	 * sin este paso, ciclos repetidos de `ADD`/`DROP COLUMN` sobre la misma
	 * tabla eventualmente disparan `ERROR 1118 (Row size too large)` aunque
	 * las columnas vivas de la tabla estén muy por debajo del límite real.
	 *
	 * @param list<string> $sentenciasCreateTable sentencias en formato dbDelta, una tabla por elemento
	 */
	public function migrar( string $versionObjetivo, array $sentenciasCreateTable = array() ): void {
		if ( array() !== $sentenciasCreateTable ) {
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			foreach ( $sentenciasCreateTable as $sentencia ) {
				$resultado = dbDelta( $sentencia );

				// Reconstruir solo cuando dbDelta reporta un cambio real
				// (columna añadida/modificada) — la inmensa mayoría de las
				// llamadas son no-op (tabla ya al día) y no necesitan pagar
				// el costo de la reconstrucción.
				if ( array() !== $resultado && 1 === preg_match( '/CREATE TABLE\s+(\S+)/i', $sentencia, $coincidencias ) ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- nombre de tabla extraído de la propia sentencia interna de dbDelta, sin entrada de usuario.
					$this->wpdb->query( "ALTER TABLE {$coincidencias[1]} FORCE" );
				}
			}
		}

		if ( $this->versionInstalada() !== $versionObjetivo ) {
			update_option( self::OPCION_VERSION, $versionObjetivo, false );
		}
	}

	public function prefijoTablas(): string {
		return $this->wpdb->prefix . 'pluma_';
	}

	/**
	 * Ejecuta una reversa de esquema ya resuelta (GOVERNANCE §5.1) y deja la
	 * versión registrada en `$versionObjetivo`. El llamador es responsable de
	 * obtener `$sentenciasReversa` de {@see Esquema::sentenciasReversaDesde()}
	 * — que lanza {@see ReversaNoDisponibleException} si la transición
	 * solicitada no está registrada — este método nunca reversa "a ciegas".
	 *
	 * @param list<string> $sentenciasReversa sentencias `ALTER`/`DROP` en orden de ejecución
	 */
	public function revertirA( string $versionObjetivo, array $sentenciasReversa ): void {
		foreach ( $sentenciasReversa as $sentencia ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- sentencias de reversa fijas, resueltas internamente por Esquema::sentenciasReversaDesde(), sin entrada de usuario.
			$this->wpdb->query( $sentencia );
		}

		if ( $this->versionInstalada() !== $versionObjetivo ) {
			update_option( self::OPCION_VERSION, $versionObjetivo, false );
		}
	}
}
