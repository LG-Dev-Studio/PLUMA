<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\Serializers\RBX;
use RuntimeException;

/**
 * Rol NLI pure-PHP (`ADR 0024`): clasificador entrenado offline
 * (`tools/entrenamiento-nli/entrenar.php`) sobre InferES (Kovatchev &
 * Taulé, 2022, CC-BY-4.0), persistido con Rubix ML. Sin red, sin FFI,
 * siempre disponible — reemplaza al NLI vía cerebro remoto (T3, `ADR 0020`,
 * retirado en esta misma reorientación).
 *
 * Carga perezosa del modelo + vocabulario (una vez por instancia, cacheado
 * en memoria de request) desde `recursos/modelos/` en la raíz del plugin —
 * el mismo artefacto que produjo el entrenamiento, sin descarga ni
 * configuración.
 */
final class ProveedorNliEntrenado implements NliInterface {

	private const RUTA_MODELO = 'recursos/modelos/nli-es.rbx';
	private const RUTA_VOCAB  = 'recursos/modelos/nli-es-vocab.json';

	private ?PersistentModel $modelo = null;

	/** @var array<string, int>|null */
	private ?array $vocabulario = null;

	public function __construct(
		private readonly CaracteristicasNli $caracteristicas,
	) {
	}

	/**
	 * @return list<ResultadoNli> ordenado descendente por puntuación.
	 *
	 * @throws ProveedorLenguajeException si el artefacto entrenado falta o está corrupto.
	 */
	public function inferir( string $premisa, string $hipotesis ): array {
		try {
			$modelo      = $this->modeloCargado();
			$vocabulario = $this->vocabularioCargado();
		} catch ( RuntimeException $excepcion ) {
			throw new ProveedorLenguajeException( 'No se pudo cargar el clasificador NLI entrenado: ' . $excepcion->getMessage() );
		}

		$vector    = $this->caracteristicas->vector( $premisa, $hipotesis, $vocabulario );
		$dataset   = Unlabeled::build( array( $vector ) );
		$distribucion = $modelo->proba( $dataset )[0] ?? array();

		if ( array() === $distribucion ) {
			throw new ProveedorLenguajeException( 'El clasificador NLI entrenado no devolvió ninguna distribución de probabilidad.' );
		}

		$resultados = array();

		foreach ( $distribucion as $etiquetaCruda => $puntuacion ) {
			$etiqueta = EtiquetaNli::tryFrom( (string) $etiquetaCruda );

			if ( null === $etiqueta ) {
				throw new ProveedorLenguajeException( "El clasificador NLI entrenado devolvió una etiqueta desconocida: «{$etiquetaCruda}»." );
			}

			$resultados[] = new ResultadoNli( $etiqueta, (float) $puntuacion );
		}

		usort( $resultados, static fn ( ResultadoNli $a, ResultadoNli $b ): int => $b->puntuacion <=> $a->puntuacion );

		return $resultados;
	}

	private function modeloCargado(): PersistentModel {
		if ( null === $this->modelo ) {
			$ruta = PLUMA_ENGINE_DIR . self::RUTA_MODELO;

			if ( ! is_file( $ruta ) ) {
				throw new RuntimeException( "Artefacto de modelo no encontrado en {$ruta}." );
			}

			$this->modelo = PersistentModel::load( new Filesystem( $ruta ), new RBX() );
		}

		return $this->modelo;
	}

	/**
	 * @return array<string, int>
	 */
	private function vocabularioCargado(): array {
		if ( null === $this->vocabulario ) {
			$ruta = PLUMA_ENGINE_DIR . self::RUTA_VOCAB;

			if ( ! is_file( $ruta ) ) {
				throw new RuntimeException( "Vocabulario no encontrado en {$ruta}." );
			}

			$contenido = file_get_contents( $ruta );
			$datos     = false !== $contenido ? json_decode( $contenido, true ) : null;

			if ( ! is_array( $datos ) || ! isset( $datos['vocabulario'] ) || ! is_array( $datos['vocabulario'] ) ) {
				throw new RuntimeException( "Vocabulario en {$ruta} tiene un formato inesperado." );
			}

			$this->vocabulario = array_flip( array_map( 'strval', $datos['vocabulario'] ) );
		}

		return $this->vocabulario;
	}
}
