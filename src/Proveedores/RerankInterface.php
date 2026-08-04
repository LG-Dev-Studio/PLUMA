<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Rol RRK (`docs/CEREBRO_PLUMA_v2.md` Parte 1.3): relevancia fina de un
 * conjunto de textos frente a una consulta — selección/orden de extractos
 * del expediente. Desacopla a los consumidores del proveedor concreto —
 * mismo patrón que `EmbeddingsInterface`.
 */
interface RerankInterface {

	/**
	 * @param list<string> $textos
	 *
	 * @return list<ResultadoRerank> ordenado descendente por puntuación.
	 *
	 * @throws ProveedorLenguajeException fallo del proveedor o formato inesperado.
	 */
	public function reordenar( string $consulta, array $textos ): array;
}
