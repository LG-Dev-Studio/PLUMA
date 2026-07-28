<?php

declare(strict_types=1);

namespace Pluma\Compuertas;

/**
 * Nivel Cuatro X.1 — "la misma filosofía del Capítulo 8, aplicada a la
 * entrada": clasificación automática de cada comentario. `Spam` y
 * `OdioAtaquePersonal` son pisos de fábrica (siempre filtrados, nunca
 * configurables); `AfirmacionRiesgosa` se retiene para revisión humana por
 * defecto (configurable, salvo bajo régimen de responsabilidad severo);
 * `CriticaLegitima`/`AporteInformativo` se publican y se destacan.
 */
enum CategoriaComentario: string {

	case Spam               = 'spam';
	case OdioAtaquePersonal = 'odio_ataque_personal';
	case AfirmacionRiesgosa = 'afirmacion_riesgosa';
	case CriticaLegitima    = 'critica_legitima';
	case AporteInformativo  = 'aporte_informativo';
}
