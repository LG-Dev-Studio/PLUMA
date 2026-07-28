<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

/**
 * Nivel Cuatro U.1 — ciclo de vida propio de una Historia (saga), distinto
 * e independiente del `EstadoPieza` de cada Pieza que la compone:
 *
 * - ABIERTA: recién creada, con al menos una Pieza; sigue recibiendo
 *   cobertura activa.
 * - EN_SEGUIMIENTO: 2+ Piezas — cruza el umbral de U.2 para generar el hub
 *   público.
 * - INACTIVA: sin Piezas nuevas en un tiempo (calculado, nunca declarado
 *   por el sistema como "cerrada" — solo el editor cierra explícitamente).
 * - CERRADA: cierre editorial explícito ("esta historia concluyó"), con
 *   Pieza de cierre opcional (`TipoPieza::Cierre`). Terminal — reabrir una
 *   historia cerrada crea una Historia nueva, nunca reutiliza la cerrada.
 */
enum EstadoHistoria: string {

	case Abierta       = 'abierta';
	case EnSeguimiento = 'en_seguimiento';
	case Inactiva      = 'inactiva';
	case Cerrada       = 'cerrada';
}
