<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

/**
 * Estado de una ranura en `pluma_cola_publicacion` (Libro Cap. 9.3):
 * "expirada" cubre la perecibilidad — "mejor no publicar que publicar tarde".
 * "pausada" (Nivel Dos F.3, modo respeto) es lateral y temporal: nunca se
 * descarta una ranura pausada, solo espera a que el modo respeto se
 * desactive para volver a "programada" con el jitter recalculado.
 */
enum EstadoColaPublicacion: string {

	case Programada = 'programada';
	case Publicada  = 'publicada';
	case Expirada   = 'expirada';
	case Pausada    = 'pausada';
}
