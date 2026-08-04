<?php

declare(strict_types=1);

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

define( 'PLUMA_ENGINE_DIR', dirname( __DIR__, 2 ) . '/' );

use Pluma\Proveedores\CaracteristicasNli;
use Pluma\Proveedores\ProveedorNliEntrenado;

$proveedor = new ProveedorNliEntrenado( new CaracteristicasNli() );

$casos = array(
	array( 'El equipo ganó el partido por goleada.', 'El equipo perdió el partido.' ),
	array( 'El alcalde renunció el lunes.', 'El alcalde no renunció.' ),
	array( 'El gobierno anunció nuevas medidas económicas.', 'Este texto habla de política y economía.' ),
	array( 'París es la capital de Francia.', 'París es una ciudad europea.' ),
);

foreach ( $casos as $caso ) {
	[$premisa, $hipotesis] = $caso;
	$resultados            = $proveedor->inferir( $premisa, $hipotesis );

	echo "Premisa:   {$premisa}\n";
	echo "Hipótesis: {$hipotesis}\n";
	foreach ( $resultados as $resultado ) {
		printf( "  %-14s %.4f\n", $resultado->etiqueta->value, $resultado->puntuacion );
	}
	echo "\n";
}
