<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

use Pluma\Compuertas\ModoOperacion;
use Pluma\Datos\Migrador;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Kernel\DetectorEntorno;
use Pluma\Kernel\RelojInterface;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\Orquestador;

/**
 * {@see TelemetriaInterface} — ver ahí por qué este proveedor no envía nada
 * por red todavía.
 */
final class ProveedorTelemetria implements TelemetriaInterface {

	public function __construct(
		private readonly DetectorEntorno $detectorEntorno,
		private readonly RepositorioPiezasInterface $repoPiezas,
		private readonly RepositorioPeriodistasInterface $repoPeriodistas,
		private readonly RelojInterface $reloj,
	) {
	}

	public function construirPayload(): array {
		return array(
			'versionPlugin'      => PLUMA_ENGINE_VERSION,
			'versionEsquema'     => $this->versionEsquema(),
			'versionPhp'         => $this->detectorEntorno->versionPhp(),
			'versionWordPress'   => $this->detectorEntorno->versionWordPress(),
			'versionBaseDatos'   => $this->detectorEntorno->versionBaseDatos(),
			'esMultisitio'       => $this->detectorEntorno->esMultisitio(),
			'modoOperacion'      => $this->modoOperacion()->value,
			'periodistasActivos' => count( $this->repoPeriodistas->obtenerActivos() ),
			'piezasPublicadas'   => $this->repoPiezas->contarPorEstado( EstadoPieza::Publicada ),
			'generadoEn'         => $this->reloj->ahora()->format( DATE_ATOM ),
		);
	}

	private function versionEsquema(): string {
		$version = get_option( Migrador::OPCION_VERSION, '0.0.0' );

		return is_string( $version ) ? $version : '0.0.0';
	}

	private function modoOperacion(): ModoOperacion {
		$valor = get_option( Orquestador::OPCION_MODO_OPERACION, ModoOperacion::Copiloto->value );

		return ModoOperacion::tryFrom( is_string( $valor ) ? $valor : '' ) ?? ModoOperacion::Copiloto;
	}
}
