<?php

declare(strict_types=1);

namespace Pluma\Kernel;

use DateTimeImmutable;

/**
 * Primer patrón de snapshot cacheado del proyecto (`ADR 0013`): persiste el
 * último `PerfilEntorno` calculado en un `wp_option` versionado, con fallo
 * abierto — si la opción falta o el JSON no decodifica de forma válida,
 * `leer()` nunca rompe una carga de página, siempre recalcula.
 *
 * `refrescar()` es el único punto de escritura; se llama explícitamente
 * desde el primer arranque tras activación (`Nucleo::arrancar()`) y desde
 * cada tick del Orquestador — nunca implícitamente desde el camino feliz de
 * `leer()`, para que quede claro cuándo se paga el coste de recalcular.
 */
final class AlmacenPerfilEntorno implements AlmacenPerfilEntornoInterface {

	public const OPCION = 'pluma_perfil_entorno';

	private const VERSION_FORMATO = '1.1';

	public function __construct(
		private readonly SensorCapacidades $sensor,
		private readonly ResolutorPerfilEntorno $resolutor,
		private readonly RelojInterface $reloj,
	) {
	}

	public function refrescar(): PerfilEntorno {
		$perfil = $this->resolutor->resolver( $this->sensor->medir(), $this->reloj->ahora() );

		update_option( self::OPCION, $this->aArray( $perfil ), false );

		return $perfil;
	}

	public function leer(): PerfilEntorno {
		$bruto  = get_option( self::OPCION, false );
		$perfil = is_array( $bruto ) ? $this->desdeArray( $bruto ) : null;

		// Fallo abierto: opción ausente, JSON corrupto o versión no
		// coincidente — nunca rompe la carga de página, siempre recalcula.
		return $perfil ?? $this->refrescar();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function aArray( PerfilEntorno $perfil ): array {
		return array(
			'version'               => self::VERSION_FORMATO,
			'medidoEn'              => $perfil->medidoEn->format( DATE_ATOM ),
			'transportePrioritario' => $perfil->transportePrioritario->value,
			'hechos'                => array(
				'ffiDisponible'                 => $perfil->hechos->ffiDisponible,
				'memoriaLimiteMb'               => $perfil->hechos->memoriaLimiteMb,
				'tiempoMaximoEjecucionSegundos' => $perfil->hechos->tiempoMaximoEjecucionSegundos,
				'procesoHijoDisponible'         => $perfil->hechos->procesoHijoDisponible,
				'apiPagoConfigurada'            => $perfil->hechos->apiPagoConfigurada,
			),
		);
	}

	/**
	 * @param array<string, mixed> $datos
	 */
	private function desdeArray( array $datos ): ?PerfilEntorno {
		if ( ! isset( $datos['version'] ) || self::VERSION_FORMATO !== $datos['version'] ) {
			return null;
		}

		if ( ! isset( $datos['medidoEn'], $datos['transportePrioritario'], $datos['hechos'] ) ) {
			return null;
		}

		if ( ! is_string( $datos['medidoEn'] ) || ! is_string( $datos['transportePrioritario'] ) || ! is_array( $datos['hechos'] ) ) {
			return null;
		}

		$transporte = TransportePlano1::tryFrom( $datos['transportePrioritario'] );
		$medidoEn   = DateTimeImmutable::createFromFormat( DATE_ATOM, $datos['medidoEn'] );
		$hechos     = $this->hechosDesdeArray( $datos['hechos'] );

		if ( null === $transporte || false === $medidoEn || null === $hechos ) {
			return null;
		}

		return new PerfilEntorno( $hechos, $transporte, $medidoEn );
	}

	/**
	 * @param array<string, mixed> $datos
	 */
	private function hechosDesdeArray( array $datos ): ?HechosEntorno {
		$claves = array(
			'ffiDisponible',
			'memoriaLimiteMb',
			'tiempoMaximoEjecucionSegundos',
			'procesoHijoDisponible',
			'apiPagoConfigurada',
		);

		foreach ( $claves as $clave ) {
			if ( ! isset( $datos[ $clave ] ) ) {
				return null;
			}
		}

		if (
			! is_bool( $datos['ffiDisponible'] )
			|| ! is_int( $datos['memoriaLimiteMb'] )
			|| ! is_int( $datos['tiempoMaximoEjecucionSegundos'] )
			|| ! is_bool( $datos['procesoHijoDisponible'] )
			|| ! is_bool( $datos['apiPagoConfigurada'] )
		) {
			return null;
		}

		return new HechosEntorno(
			$datos['ffiDisponible'],
			$datos['memoriaLimiteMb'],
			$datos['tiempoMaximoEjecucionSegundos'],
			$datos['procesoHijoDisponible'],
			$datos['apiPagoConfigurada']
		);
	}
}
