<?php

declare(strict_types=1);

namespace Pluma\Kernel;

use Pluma\Datos\Migrador;
use Pluma\Datos\RepositorioBitacoraInterface;

/**
 * Modo diagnóstico exportable (GOVERNANCE §5.6: "bitácora del motor +
 * versiones + conflictos detectados, sin secretos, para tickets de
 * soporte"). Mismo molde que `Pluma\Redaccion\ExportadorBancoPeriodistas`:
 * un array puro, servido por REST (`Pluma\Admin\RestSalaMaquinas`) como
 * `WP_REST_Response` — vive en `Pluma\Kernel` junto a `DetectorEntorno`/
 * `DetectorConflictos` porque resume hechos de infraestructura, no de un
 * dominio editorial concreto.
 *
 * **Nunca incluye**: contenido de Piezas, llaves (ni siquiera cifradas), ni
 * ningún dato del cliente más allá de lo que WordPress ya expone
 * públicamente (versiones, configuración técnica del motor).
 */
final class ExportadorDiagnostico {

	public const VERSION_FORMATO = '1.0';

	public function __construct(
		private readonly DetectorEntorno $detectorEntorno,
		private readonly DetectorConflictos $detectorConflictos,
		private readonly RepositorioBitacoraInterface $bitacora,
		private readonly RelojInterface $reloj,
		private readonly AlmacenPerfilEntornoInterface $almacenPerfilEntorno,
	) {
	}

	/**
	 * @return array{
	 *     version: string,
	 *     generadoEn: string,
	 *     entorno: array{versionPlugin: string, versionEsquema: string, versionPhp: string, versionWordPress: string, versionBaseDatos: string, cronRealConfigurado: bool, esMultisitio: bool},
	 *     conflictos: list<string>,
	 *     bitacoraReciente: list<array{iniciadaEn: string, finalizadaEn: ?string, lotesProcesados: int, errores: list<string>}>,
	 *     sondaCapacidades: array{transportePrioritario: string, medidoEn: string, hechos: array<string, bool|int>}
	 * }
	 */
	public function exportar(): array {
		// NCP-1 · Sonda de Capacidades (`ADR 0013`): lee el snapshot cacheado
		// (`leer()`), nunca lo refresca aquí — el export de diagnóstico debe
		// seguir siendo barato y sin red.
		$perfilEntorno = $this->almacenPerfilEntorno->leer();

		return array(
			'version'          => self::VERSION_FORMATO,
			'generadoEn'       => $this->reloj->ahora()->format( DATE_ATOM ),
			'entorno'          => array(
				'versionPlugin'       => PLUMA_ENGINE_VERSION,
				'versionEsquema'      => $this->versionEsquema(),
				'versionPhp'          => $this->detectorEntorno->versionPhp(),
				'versionWordPress'    => $this->detectorEntorno->versionWordPress(),
				'versionBaseDatos'    => $this->detectorEntorno->versionBaseDatos(),
				'cronRealConfigurado' => $this->detectorEntorno->cronRealConfigurado(),
				'esMultisitio'        => $this->detectorEntorno->esMultisitio(),
			),
			'conflictos'       => $this->detectorConflictos->detectar(),
			'bitacoraReciente' => $this->bitacora->obtenerRecientes( 20 ),
			'sondaCapacidades' => array(
				'transportePrioritario' => $perfilEntorno->transportePrioritario->value,
				'medidoEn'              => $perfilEntorno->medidoEn->format( DATE_ATOM ),
				'hechos'                => array(
					'ffiDisponible'                 => $perfilEntorno->hechos->ffiDisponible,
					'memoriaLimiteMb'               => $perfilEntorno->hechos->memoriaLimiteMb,
					'tiempoMaximoEjecucionSegundos' => $perfilEntorno->hechos->tiempoMaximoEjecucionSegundos,
					'procesoHijoDisponible'         => $perfilEntorno->hechos->procesoHijoDisponible,
					'cerebroRemotoConfigurado'      => $perfilEntorno->hechos->cerebroRemotoConfigurado,
					'apiPagoConfigurada'            => $perfilEntorno->hechos->apiPagoConfigurada,
				),
			),
		);
	}

	private function versionEsquema(): string {
		$version = get_option( Migrador::OPCION_VERSION, '0.0.0' );

		return is_string( $version ) ? $version : '0.0.0';
	}
}
