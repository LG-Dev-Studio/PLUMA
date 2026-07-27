<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Infraestructura de embeddings (Nivel Dos A.5 + Nivel Tres J.3): compartida
 * entre la deriva semántica del corpus de regresión de voz y la capa
 * determinista de verificación de trazabilidad — su coste marginal es
 * trivial una vez construida para cualquiera de los dos usos.
 */
interface EmbeddingsInterface {

	/**
	 * @return list<float>
	 *
	 * @throws ProveedorLenguajeException fallo de red/HTTP/formato o presupuesto agotado.
	 */
	public function embed( string $texto ): array;
}
