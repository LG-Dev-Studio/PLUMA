<?php

declare(strict_types=1);

namespace Pluma\Kernel;

use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\ProveedorCerebroRemoto;

/**
 * Sensor de infraestructura real (`docs/CEREBRO_PLUMA_v2.md` Parte 1.6):
 * lee hechos crudos del entorno, cero derivación — mismo peso/patrón que
 * `DetectorEntorno`/`DetectorConflictos`. La derivación vive en
 * `ResolutorPerfilEntorno`.
 */
final class SensorCapacidades {

	public function __construct(
		private readonly LenguajeInterface $proveedorLenguaje,
		private readonly ProveedorCerebroRemoto $cerebroRemoto,
	) {
	}

	public function medir(): HechosEntorno {
		return new HechosEntorno(
			extension_loaded( 'FFI' ),
			$this->memoriaLimiteMb(),
			$this->tiempoMaximoEjecucionSegundos(),
			$this->procesoHijoDisponible(),
			$this->cerebroRemoto->configurado() && $this->cerebroRemoto->ultimaPruebaOk(),
			$this->proveedorLenguaje->tieneCredenciales()
		);
	}

	private function memoriaLimiteMb(): int {
		$limite = trim( (string) ini_get( 'memory_limit' ) );

		if ( '-1' === $limite ) {
			return PHP_INT_MAX;
		}

		$unidad        = strtolower( substr( $limite, -1 ) );
		$numero        = (int) $limite;
		$multiplicador = match ( $unidad ) {
			'g' => 1024,
			'k' => 1 / 1024,
			default => 1,
		};

		return (int) ( $numero * $multiplicador );
	}

	private function tiempoMaximoEjecucionSegundos(): int {
		return (int) ini_get( 'max_execution_time' );
	}

	private function procesoHijoDisponible(): bool {
		if ( ! function_exists( 'proc_open' ) ) {
			return false;
		}

		$deshabilitadas = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );

		return ! in_array( 'proc_open', $deshabilitadas, true );
	}
}
