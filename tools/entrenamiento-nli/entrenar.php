<?php

declare(strict_types=1);

/**
 * Entrenamiento del clasificador NLI pure-PHP (`ADR 0024`) — Rubix ML sobre
 * InferES (Kovatchev & Taulé, 2022; CC-BY-4.0; https://huggingface.co/datasets/venelin/inferes),
 * 8.056 ejemplos en español peninsular (6.444 entrenamiento / 1.612 prueba),
 * etiquetados entailment/neutral/contradiction, con foco en ejemplos basados
 * en negación.
 *
 * Reemplaza al clasificador NLI vía cerebro remoto (T3, `ADR 0020`, retirado
 * en esta misma reorientación): sin red, sin FFI, siempre disponible.
 *
 * Herramienta de desarrollo: NO forma parte del producto, no se empaqueta en
 * el ZIP del plugin, no la orquesta `@wordpress/env`. El ARTEFACTO que
 * produce (`recursos/modelos/nli-es.rbx` + `nli-es-vocab.json`) sí es
 * producto real, cargado en runtime por `Pluma\Proveedores\ProveedorNliEntrenado`.
 *
 * PHP puro, sin WordPress: `CaracteristicasNli` y Rubix ML no dependen de
 * `wp_*`, así que este script corre vía `php` directo sobre el autoload de
 * Composer, sin arrancar el `Nucleo`/contenedor de DI (evita el problema del
 * huevo y la gallina: el contenedor exige `NliInterface` ya vinculado a un
 * modelo que este script todavía no ha producido la primera vez).
 *
 * Uso:
 *   npx wp-env run cli --env-cwd=wp-content/plugins/PLUMA -- php tools/entrenamiento-nli/entrenar.php
 */

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use Pluma\Proveedores\CaracteristicasNli;
use Rubix\ML\Classifiers\ClassificationTree;
use Rubix\ML\Classifiers\GaussianNB;
use Rubix\ML\Classifiers\RandomForest;
use Rubix\ML\CrossValidation\Metrics\Accuracy;
use Rubix\ML\CrossValidation\Reports\MulticlassBreakdown;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\Serializers\RBX;

const VOCABULARIO_MAXIMO       = 800;
const FRECUENCIA_DOCUMENTAL_MINIMA = 3;

/**
 * Mapea las etiquetas crudas de InferES a los valores exactos de
 * `Pluma\Proveedores\EtiquetaNli` — así el modelo persistido usa las mismas
 * cadenas que el resto del contrato NLI, sin tabla de traducción en runtime.
 *
 * @return array<string, string>
 */
function mapa_etiquetas(): array {
	return array(
		'cnt'     => 'contradiction',
		'neutral' => 'neutral',
		'ent'     => 'entailment',
	);
}

/**
 * @return list<array{premisa: string, hipotesis: string, etiqueta: string}>
 */
function cargar_csv( string $ruta ): array {
	$manejador = fopen( $ruta, 'r' );

	if ( false === $manejador ) {
		fwrite( STDERR, "No se pudo abrir {$ruta}\n" );
		exit( 1 );
	}

	$cabecera = fgetcsv( $manejador );
	$filas    = array();
	$etiquetas = mapa_etiquetas();

	while ( ( $fila = fgetcsv( $manejador ) ) !== false ) {
		if ( count( $fila ) !== count( $cabecera ) ) {
			continue;
		}

		$registro = array_combine( $cabecera, $fila );

		if ( ! isset( $etiquetas[ $registro['Label'] ] ) ) {
			continue;
		}

		$filas[] = array(
			'premisa'   => $registro['Premise'],
			'hipotesis' => $registro['Hypothesis'],
			'etiqueta'  => $etiquetas[ $registro['Label'] ],
		);
	}

	fclose( $manejador );

	return $filas;
}

/**
 * @param list<array{premisa: string, hipotesis: string, etiqueta: string}> $filas
 *
 * @return array<string, int> palabra => índice
 */
function construir_vocabulario( array $filas, CaracteristicasNli $caracteristicas ): array {
	$frecuenciaDocumental = array();

	foreach ( $filas as $fila ) {
		$tokensUnicos = array_unique(
			array_merge(
				$caracteristicas->tokenizar( $fila['premisa'] ),
				$caracteristicas->tokenizar( $fila['hipotesis'] )
			)
		);

		foreach ( $tokensUnicos as $token ) {
			if ( mb_strlen( $token, 'UTF-8' ) < 2 ) {
				continue;
			}

			$frecuenciaDocumental[ $token ] = ( $frecuenciaDocumental[ $token ] ?? 0 ) + 1;
		}
	}

	$frecuenciaDocumental = array_filter(
		$frecuenciaDocumental,
		static fn ( int $frecuencia ): bool => $frecuencia >= FRECUENCIA_DOCUMENTAL_MINIMA
	);

	arsort( $frecuenciaDocumental );

	$masFrecuentes = array_slice( array_keys( $frecuenciaDocumental ), 0, VOCABULARIO_MAXIMO, true );

	return array_flip( array_values( $masFrecuentes ) );
}

/**
 * @param list<array{premisa: string, hipotesis: string, etiqueta: string}> $filas
 * @param array<string, int> $vocabulario
 *
 * @return array{samples: list<list<float>>, labels: list<string>}
 */
function construir_dataset( array $filas, array $vocabulario, CaracteristicasNli $caracteristicas ): array {
	$samples = array();
	$labels  = array();

	foreach ( $filas as $fila ) {
		$samples[] = $caracteristicas->vector( $fila['premisa'], $fila['hipotesis'], $vocabulario );
		$labels[]  = $fila['etiqueta'];
	}

	return array(
		'samples' => $samples,
		'labels'  => $labels,
	);
}

echo "=== Entrenamiento NLI pure-PHP (ADR 0024) ===\n\n";

$caracteristicas = new CaracteristicasNli();

$filasEntrenamiento = cargar_csv( __DIR__ . '/dataset/train.csv' );
$filasPrueba        = cargar_csv( __DIR__ . '/dataset/test.csv' );

echo 'Ejemplos de entrenamiento: ' . count( $filasEntrenamiento ) . "\n";
echo 'Ejemplos de prueba (held-out, nunca vistos en vocabulario/entrenamiento): ' . count( $filasPrueba ) . "\n\n";

echo "Construyendo vocabulario (solo desde entrenamiento)...\n";
$vocabulario = construir_vocabulario( $filasEntrenamiento, $caracteristicas );
echo 'Tamaño real del vocabulario: ' . count( $vocabulario ) . "\n\n";

echo "Extrayendo características...\n";
$datosEntrenamiento = construir_dataset( $filasEntrenamiento, $vocabulario, $caracteristicas );
$datosPrueba        = construir_dataset( $filasPrueba, $vocabulario, $caracteristicas );

$entrenamiento = Labeled::build( $datosEntrenamiento['samples'], $datosEntrenamiento['labels'] );
$prueba        = Labeled::build( $datosPrueba['samples'], $datosPrueba['labels'] );

$candidatos = array(
	'GaussianNB'                 => new GaussianNB(),
	'ClassificationTree'         => new ClassificationTree( 20, 3 ),
	'RandomForest(balanceado)'   => new RandomForest( new ClassificationTree( 20, 3 ), 50, 0.2, true ),
);

$metrica    = new Accuracy();
$resultados = array();

foreach ( $candidatos as $nombre => $estimador ) {
	echo "\n=== Candidato: {$nombre} ===\n";
	$inicio = microtime( true );

	$estimador->train( $entrenamiento );
	$predicciones = $estimador->predict( $prueba );

	$duracion = microtime( true ) - $inicio;
	$exactitud = $metrica->score( $predicciones, $datosPrueba['labels'] );

	echo 'Exactitud real sobre el split de prueba (' . count( $filasPrueba ) . " ejemplos): {$exactitud}\n";
	echo "Tiempo de entrenamiento + predicción: {$duracion}s\n";

	$reporte = ( new MulticlassBreakdown() )->generate( $predicciones, $datosPrueba['labels'] );
	echo "Desglose por clase:\n";
	foreach ( $reporte['classes'] as $clase => $metricas ) {
		printf(
			"  %-14s precision=%.4f recall=%.4f f1=%.4f (soporte=%d)\n",
			$clase,
			$metricas['precision'],
			$metricas['recall'],
			$metricas['f1 score'],
			$metricas['cardinality']
		);
	}

	$resultados[ $nombre ] = array(
		'estimador' => $estimador,
		'exactitud' => $exactitud,
	);
}

uasort( $resultados, static fn ( array $a, array $b ): int => $b['exactitud'] <=> $a['exactitud'] );

$nombreGanador = array_key_first( $resultados );
$ganador       = $resultados[ $nombreGanador ];

echo "\n=== Ganador real (mayor exactitud medida en el split de prueba): {$nombreGanador} ({$ganador['exactitud']}) ===\n";

$directorioModelos = dirname( __DIR__, 2 ) . '/recursos/modelos';

if ( ! is_dir( $directorioModelos ) && ! mkdir( $directorioModelos, 0755, true ) && ! is_dir( $directorioModelos ) ) {
	fwrite( STDERR, "No se pudo crear {$directorioModelos}\n" );
	exit( 1 );
}

$rutaModelo = $directorioModelos . '/nli-es.rbx';
$rutaVocab  = $directorioModelos . '/nli-es-vocab.json';

$modeloPersistente = new PersistentModel( $ganador['estimador'], new Filesystem( $rutaModelo ), new RBX() );
$modeloPersistente->save();

$vocabularioOrdenado = array();
foreach ( $vocabulario as $palabra => $indice ) {
	$vocabularioOrdenado[ $indice ] = $palabra;
}
ksort( $vocabularioOrdenado );

$metadatos = array(
	'algoritmo'          => $nombreGanador,
	'exactitudPrueba'    => $ganador['exactitud'],
	'ejemplosEntrenamiento' => count( $filasEntrenamiento ),
	'ejemplosPrueba'     => count( $filasPrueba ),
	'tamanoVocabulario'  => count( $vocabulario ),
	'entrenadoEn'        => gmdate( 'c' ),
	'dataset'            => 'venelin/inferes (CC-BY-4.0, Kovatchev & Taulé 2022)',
	'vocabulario'        => array_values( $vocabularioOrdenado ),
);

file_put_contents( $rutaVocab, json_encode( $metadatos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

echo "\nModelo persistido en: {$rutaModelo}\n";
echo "Vocabulario persistido en: {$rutaVocab}\n";
echo 'sha256 del modelo: ' . hash_file( 'sha256', $rutaModelo ) . "\n";
