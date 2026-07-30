<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Una fila de `pluma_llamadas_modelo` (NCP-1, `ADR 0010`). Inmutable: se
 * construye una vez con lo que el decorador observó de la llamada real y se
 * pasa tal cual al repositorio.
 *
 * `proposito` es `string`, no {@see PropositoLenguaje}: las llamadas de
 * `EmbeddingsInterface::embed()` se registran bajo el bucket literal
 * `"embeddings"`, que no es (ni debe forzarse a ser) un caso de ese enum —
 * `PropositoLenguaje` tiene matches exhaustivos con semántica de coste
 * premium/temperatura que no aplican a un embedding.
 */
final readonly class RegistroLlamada {

	public const PROPOSITO_EMBEDDINGS = 'embeddings';

	public function __construct(
		public string $proposito,
		public string $proveedor,
		public string $modelo,
		public string $familia,
		public OrigenLlamada $origen,
		public ResultadoLlamada $resultado,
		public int $tokensEntrada,
		public int $tokensSalida,
		public ?float $costeUsd,
		public int $latenciaMs,
		public bool $truncada,
	) {
	}
}
