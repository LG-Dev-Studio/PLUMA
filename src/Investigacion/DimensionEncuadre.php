<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

/**
 * Nivel Dos B.4: vocabulario fijo y corto de dimensiones de encuadre —
 * "matriz de encuadres cubiertos" por las coberturas recolectadas.
 */
enum DimensionEncuadre: string {

	case Economica = 'economica';
	case Humana    = 'humana';
	case Politica  = 'politica';
	case Tecnica   = 'tecnica';
	case Historica = 'historica';
	case Legal     = 'legal';
}
