<?php

declare(strict_types=1);

namespace Pluma\Kernel;

/**
 * Conflictos reales y verificables entre PLUMA y el resto del sitio
 * (GOVERNANCE §5.6: "modo diagnóstico... conflictos detectados").
 *
 * Deliberadamente acotado: solo reporta condiciones que el propio código del
 * plugin ya verifica en otro punto (cero invención — no se inventa una lista
 * de plugins de terceros supuestamente incompatibles sin evidencia real de
 * que lo sean). Hoy solo cubre Yoast y Rank Math activos a la vez, la misma
 * detección que ya usa `Pluma\Seo\DetectorPluginSeo` para decidir prioridad
 * (Yoast gana) — aquí se convierte en una advertencia legible para soporte.
 * Crece con evidencia real, no con suposiciones.
 */
final class DetectorConflictos {

	/**
	 * @return list<string> advertencias legibles; vacío si no hay nada que reportar
	 */
	public function detectar(): array {
		$advertencias = array();

		if ( defined( 'WPSEO_VERSION' ) && defined( 'RANK_MATH_VERSION' ) ) {
			$advertencias[] = __(
				'Yoast SEO y Rank Math están activos a la vez. PLUMA escribe únicamente en los campos de Yoast (prioridad fija) — los de Rank Math quedan sin usar mientras ambos sigan activos.',
				'pluma-engine'
			);
		}

		return $advertencias;
	}
}
