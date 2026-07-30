<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

use Pluma\Datos\RepositorioLlamadasModeloInterface;
use Pluma\Kernel\ContextoEjecucion;
use Pluma\Kernel\RelojInterface;
use Throwable;

/**
 * NCP-1, `ADR 0010`: decorador de instrumentación sobre `LenguajeInterface`,
 * cableado en el único punto de registro del contenedor
 * ({@see \Pluma\Kernel\Nucleo}) — los 21 consumidores reales de
 * `completar()` quedan medidos sin que ninguno sepa que lo está siendo
 * (§5.1.2). Contrato de esta porción: CERO cambio de comportamiento; si el
 * registro falla, la llamada real al modelo sigue su curso normal.
 *
 * `modelo` se resuelve con `EnrutadorModelos::modeloPara()` ANTES de
 * intentar la llamada real: es el mismo cálculo que `ProveedorOpenRouter`
 * hace internamente, así que está disponible también en el camino de
 * excepción (donde `RespuestaLenguaje` nunca llega a existir). La latencia
 * se mide aquí, no se copia de `RespuestaLenguaje->latenciaMs`, para que el
 * mismo reloj cubra también el tiempo hasta un fallo.
 */
final class LenguajeInstrumentado implements LenguajeInterface {

	private bool $degradacionNotificada = false;

	public function __construct(
		private readonly LenguajeInterface $interno,
		private readonly EnrutadorModelos $enrutador,
		private readonly RepositorioLlamadasModeloInterface $repositorio,
		private readonly ContextoEjecucion $contextoEjecucion,
		private readonly RelojInterface $reloj,
		private readonly string $nombreProveedor,
	) {
	}

	public function completar( PeticionLenguaje $peticion ): RespuestaLenguaje {
		$modelo = $this->enrutador->modeloPara( $peticion->proposito );
		$inicio = microtime( true );

		try {
			$respuesta = $this->interno->completar( $peticion );
		} catch ( ProveedorLenguajeException $excepcion ) {
			$this->registrar(
				$peticion->proposito->value,
				$modelo,
				ResultadoLlamada::desdeExcepcion( $excepcion ),
				0,
				0,
				null,
				$this->latenciaMsDesde( $inicio ),
				false
			);

			throw $excepcion;
		}

		$this->registrar(
			$peticion->proposito->value,
			$respuesta->modelo,
			ResultadoLlamada::Ok,
			$respuesta->tokensEntrada,
			$respuesta->tokensSalida,
			$respuesta->costeUsd,
			$this->latenciaMsDesde( $inicio ),
			$respuesta->truncada
		);

		return $respuesta;
	}

	public function tieneCredenciales(): bool {
		return $this->interno->tieneCredenciales();
	}

	public function familiaDe( string $modelo ): string {
		return $this->interno->familiaDe( $modelo );
	}

	private function registrar(
		string $proposito,
		string $modelo,
		ResultadoLlamada $resultado,
		int $tokensEntrada,
		int $tokensSalida,
		?float $costeUsd,
		int $latenciaMs,
		bool $truncada
	): void {
		try {
			$this->repositorio->registrar(
				new RegistroLlamada(
					$proposito,
					$this->nombreProveedor,
					$modelo,
					$this->interno->familiaDe( $modelo ),
					$this->contextoEjecucion->obtener(),
					$resultado,
					$tokensEntrada,
					$tokensSalida,
					$costeUsd,
					$latenciaMs,
					$truncada
				),
				$this->reloj->ahora()
			);
		} catch ( Throwable $errorRegistro ) {
			// El instrumento nunca rompe el pipeline (§5.1.6): degradación
			// declarada vía evento, una sola vez por instancia, no silenciosa.
			$this->degradar();
		}
	}

	private function latenciaMsDesde( float $inicio ): int {
		return (int) round( ( microtime( true ) - $inicio ) * 1000 );
	}

	private function degradar(): void {
		if ( $this->degradacionNotificada ) {
			return;
		}

		$this->degradacionNotificada = true;
		do_action( 'pluma/registro_llamadas_degradado' );
	}
}
