<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Rol NLI (`docs/CEREBRO_PLUMA_v2.md` Parte 1.3): implicación textual entre
 * un par premisa/hipótesis. Desacopla a los consumidores (verificación de
 * trazabilidad, contradicciones entre fuentes) del proveedor concreto —
 * mismo patrón que `EmbeddingsInterface`.
 */
interface NliInterface {

	/**
	 * @return list<ResultadoNli> ordenado descendente por puntuación.
	 *
	 * @throws ProveedorLenguajeException fallo del proveedor o formato inesperado.
	 */
	public function inferir( string $premisa, string $hipotesis ): array;
}
