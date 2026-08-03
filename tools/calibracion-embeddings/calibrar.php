<?php
/**
 * Herramienta de calibración de embeddings (NCP-2) — mide la forma de la
 * distribución de similitud coseno de `Pluma\Proveedores\ProveedorEmbeddingsCerebroRemoto`
 * (T3, `ADR 0016`) contra los fixtures de desarrollo de voz y trazabilidad.
 *
 * NO ES una calibración de producción: los fixtures que usa
 * (`tests/Fixtures/corpus-voz.php`, `tests/Fixtures/corpus-trazabilidad.php`)
 * son corpus mínimos de desarrollo, no el corpus real de piezas/expedientes
 * del cliente que `docs/ncp-estado-y-continuidad.md` §5(d) exige para fijar
 * umbrales reales. Esta herramienta NO cambia `VerificadorRegresionVoz::UMBRAL_DEFECTO`
 * ni `VerificadorTrazabilidadDeterminista::UMBRAL_DEFECTO`, ni reconecta
 * `EmbeddingsInterface::class` en `Nucleo.php` — solo produce los números que
 * una decisión futura explícita necesitaría para hacerlo con datos reales.
 *
 * Herramienta de desarrollo: NO forma parte del producto, no se empaqueta en
 * el ZIP del plugin, no la orquesta `@wordpress/env`.
 *
 * Uso (ver `README.md` de este directorio):
 *   npx wp-env run cli --env-cwd=wp-content/plugins/PLUMA -- wp eval-file tools/calibracion-embeddings/calibrar.php
 *
 * Nota: sin `declare(strict_types=1)` a propósito — `wp eval-file` evalúa el
 * contenido del script como código ya en ejecución (no vía `include`), y esa
 * declaración solo es válida como la primera instrucción real de un archivo
 * incluido directamente por PHP.
 */

use Pluma\Proveedores\EmbeddingsInterface;
use Pluma\Proveedores\ProveedorCerebroRemoto;
use Pluma\Proveedores\ProveedorEmbeddingsCerebroRemoto;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Proveedores\SimilitudVectorial;
use Pluma\Redaccion\VerificadorRegresionVoz;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Este script debe ejecutarse vía `wp eval-file` dentro del contenedor cli de wp-env (WordPress debe estar cargado).\n" );
	exit( 1 );
}

/**
 * Envoltorio con caché en memoria — el corpus de desarrollo repite el mismo
 * texto en múltiples comparaciones (leave-one-out, inter-periodista); sin
 * memoización cada repetición sería una llamada HTTP real innecesaria al
 * cerebro remoto.
 */
final class EmbeddingsConCache implements EmbeddingsInterface {

	/** @var array<string, list<float>> */
	private array $cache = array();

	public function __construct( private readonly EmbeddingsInterface $interno ) {
	}

	public function embed( string $texto ): array {
		if ( ! isset( $this->cache[ $texto ] ) ) {
			$this->cache[ $texto ] = $this->interno->embed( $texto );
		}

		return $this->cache[ $texto ];
	}
}

/**
 * @param list<float> $valores
 * @return array{min: float, max: float, media: float, mediana: float, n: int}
 */
function estadisticas( array $valores ): array {
	sort( $valores );
	$n = count( $valores );

	if ( 0 === $n ) {
		return array(
			'min'     => 0.0,
			'max'     => 0.0,
			'media'   => 0.0,
			'mediana' => 0.0,
			'n'       => 0,
		);
	}

	$mitad = intdiv( $n, 2 );
	// phpcs:ignore Universal.Operators.DisallowShortTernary.Found -- expresión simple, más clara que un if/else de 3 líneas aquí.
	$mediana = ( $n % 2 === 0 ) ? ( $valores[ $mitad - 1 ] + $valores[ $mitad ] ) / 2 : $valores[ $mitad ];

	return array(
		'min'     => $valores[0],
		'max'     => $valores[ $n - 1 ],
		'media'   => array_sum( $valores ) / $n,
		'mediana' => $mediana,
		'n'       => $n,
	);
}

/**
 * @param array{min: float, max: float, media: float, mediana: float, n: int} $stats
 */
function imprimirFila( string $etiqueta, array $stats ): void {
	printf(
		"%-32s n=%-3d min=%.4f  mediana=%.4f  media=%.4f  max=%.4f\n",
		$etiqueta,
		$stats['n'],
		$stats['min'],
		$stats['mediana'],
		$stats['media'],
		$stats['max']
	);
}

$cerebroRemoto = new ProveedorCerebroRemoto();

if ( ! $cerebroRemoto->configurado() ) {
	fwrite( STDERR, "No hay cerebro remoto configurado (pluma_cerebro_remoto_url / pluma_cerebro_remoto_token_cifrado). Configúralo primero contra el TEI local (ver tools/tei-local/README.md).\n" );
	exit( 1 );
}

$embeddings = new EmbeddingsConCache( new ProveedorEmbeddingsCerebroRemoto( $cerebroRemoto ) );

try {
	echo "== Calibración de embeddings — Pluma\\Proveedores\\ProveedorEmbeddingsCerebroRemoto ==\n";
	echo "Corpus de DESARROLLO (no producción) — ver docs/decisiones/0017-ncp2-herramienta-calibracion-embeddings.md\n\n";

	// --- Voz: intra-periodista (leave-one-out) vs inter-periodista ---
	$corpusVoz = require __DIR__ . '/../../tests/Fixtures/corpus-voz.php';
	$verificadorVoz = new VerificadorRegresionVoz( $embeddings );

	$intraVoz = array();
	$interVoz = array();

	foreach ( $corpusVoz as $nombre => $piezas ) {
		foreach ( $piezas as $indice => $pieza ) {
			$resto        = $piezas;
			unset( $resto[ $indice ] );
			$intraVoz[] = $verificadorVoz->similitudPromedioConCorpus( array_values( $resto ), $pieza );
		}
	}

	foreach ( $corpusVoz as $nombreA => $corpusA ) {
		foreach ( $corpusVoz as $nombreB => $corpusB ) {
			if ( $nombreA === $nombreB ) {
				continue;
			}

			foreach ( $corpusB as $piezaB ) {
				$interVoz[] = $verificadorVoz->similitudPromedioConCorpus( $corpusA, $piezaB );
			}
		}
	}

	echo "-- Voz (VerificadorRegresionVoz::similitudPromedioConCorpus, umbral de fábrica actual: 0.70) --\n";
	imprimirFila( 'Intra-periodista (voz genuina)', estadisticas( $intraVoz ) );
	imprimirFila( 'Inter-periodista (voz ajena)', estadisticas( $interVoz ) );
	echo "\n";

	// --- Trazabilidad: con respaldo vs sin respaldo ---
	$corpusTrazabilidad = require __DIR__ . '/../../tests/Fixtures/corpus-trazabilidad.php';

	$conRespaldo = array();
	$sinRespaldo = array();

	foreach ( $corpusTrazabilidad as $caso ) {
		$vectorHecho = $embeddings->embed( $caso['hecho'] );

		$conRespaldo[] = SimilitudVectorial::coseno( $vectorHecho, $embeddings->embed( $caso['unidad_respaldada'] ) );
		$sinRespaldo[] = SimilitudVectorial::coseno( $vectorHecho, $embeddings->embed( $caso['unidad_sin_respaldo'] ) );
	}

	echo "-- Trazabilidad (coseno hecho vs unidad, umbral de fábrica actual: 0.75) --\n";
	imprimirFila( 'Con respaldo real', estadisticas( $conRespaldo ) );
	imprimirFila( 'Sin respaldo real', estadisticas( $sinRespaldo ) );
	echo "\n";

	echo "Recordatorio: estos números miden un corpus de DESARROLLO. No fijan ni sugieren un umbral de producción — eso exige el corpus real de Piloto (docs/ncp-estado-y-continuidad.md §5(d)).\n";
} catch ( ProveedorLenguajeException $excepcion ) {
	fwrite( STDERR, 'Fallo del proveedor de embeddings: ' . $excepcion->getMessage() . "\n" );
	exit( 1 );
}
