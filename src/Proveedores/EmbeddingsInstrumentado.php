<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

use Pluma\Datos\RepositorioLlamadasModeloInterface;
use Pluma\Kernel\ContextoEjecucion;
use Pluma\Kernel\RelojInterface;
use Throwable;

/**
 * NCP-1, `ADR 0010`: decorador de instrumentación sobre `EmbeddingsInterface`
 * — mismo criterio que {@see LenguajeInstrumentado}, cableado en el único
 * punto de registro del contenedor.
 *
 * Dos limitaciones declaradas (no omisiones — `docs/deuda.md`), impuestas
 * por el contrato de `embed(): list<float>`, que no expone coste ni uso:
 * - `coste_usd` es siempre `NULL`.
 * - `tokens_entrada`/`tokens_salida` son siempre `0`.
 *
 * `derivarFamiliaDeModelo()` duplica deliberadamente la regla de dos líneas de
 * `ProveedorOpenRouter::familiaDe()` (partir el slug `proveedor/modelo` por
 * `/`): `EmbeddingsInterface` no declara ese método, y romperla para
 * añadirlo afectaría a los 2 consumidores reales y a todos sus dobles de
 * test — un coste desproporcionado para reutilizar dos líneas puras.
 */
final class EmbeddingsInstrumentado implements EmbeddingsInterface {

	private bool $degradacionNotificada = false;

	public function __construct(
		private readonly EmbeddingsInterface $interno,
		private readonly EnrutadorModelos $enrutador,
		private readonly RepositorioLlamadasModeloInterface $repositorio,
		private readonly ContextoEjecucion $contextoEjecucion,
		private readonly RelojInterface $reloj,
		private readonly string $nombreProveedor,
	) {
	}

	public function embed( string $texto ): array {
		$modelo = $this->enrutador->modeloEmbeddings();
		$inicio = microtime( true );

		try {
			$vector = $this->interno->embed( $texto );
		} catch ( ProveedorLenguajeException $excepcion ) {
			$this->registrar( $modelo, ResultadoLlamada::desdeExcepcion( $excepcion ), $this->latenciaMsDesde( $inicio ) );

			throw $excepcion;
		}

		$this->registrar( $modelo, ResultadoLlamada::Ok, $this->latenciaMsDesde( $inicio ) );

		return $vector;
	}

	private function registrar( string $modelo, ResultadoLlamada $resultado, int $latenciaMs ): void {
		try {
			$this->repositorio->registrar(
				new RegistroLlamada(
					RegistroLlamada::PROPOSITO_EMBEDDINGS,
					$this->nombreProveedor,
					$modelo,
					self::derivarFamiliaDeModelo( $modelo ),
					$this->contextoEjecucion->obtener(),
					$resultado,
					0,
					0,
					null,
					$latenciaMs,
					false
				),
				$this->reloj->ahora()
			);
		} catch ( Throwable $errorRegistro ) {
			// El instrumento nunca rompe el pipeline (§5.1.6): degradación
			// declarada vía evento, una sola vez por instancia, no silenciosa.
			$this->degradar();
		}
	}

	private static function derivarFamiliaDeModelo( string $modelo ): string {
		$partes = explode( '/', $modelo, 2 );

		return $partes[0];
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
