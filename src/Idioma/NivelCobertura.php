<?php

declare(strict_types=1);

namespace Pluma\Idioma;

/**
 * Cuánto soporte real tiene PLUMA para un locale.
 *
 * `Completo` exige los órganos semánticos del Plano 1 (segmentador,
 * tokenizador, normalizador vía ONNX — NCP-2/NCP-3, no construidos
 * todavía): ningún resolutor de Plano 0 debe producir este caso. Existe
 * para que el contrato no se rompa cuando esos órganos lleguen, no como
 * promesa de capacidad actual.
 */
enum NivelCobertura: string {

	case Completo    = 'completo';
	case Parcial     = 'parcial';
	case NoSoportado = 'no_soportado';
}
