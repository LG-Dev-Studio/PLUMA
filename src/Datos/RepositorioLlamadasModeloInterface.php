<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Proveedores\RegistroLlamada;

/**
 * NCP-1, `ADR 0010`: bitácora de gasto real del proveedor de lenguaje, una
 * fila por llamada. Único lugar con `$wpdb` para `pluma_llamadas_modelo`
 * (CLAUDE.md — Ley de Arquitectura).
 */
interface RepositorioLlamadasModeloInterface {

	public function registrar( RegistroLlamada $registro, DateTimeImmutable $ahora ): void;

	/**
	 * Conteo y coste agregados por propósito, dentro de `[$desde, $hasta]` —
	 * el dato crudo que necesita la porción 2 (auditoría) para calcular
	 * "% de llamadas eliminadas sobre bitácora real".
	 *
	 * @return list<array{proposito: string, origen: string, resultado: string, llamadas: int, costeUsd: float, tokensEntrada: int, tokensSalida: int}>
	 */
	public function resumirEntre( DateTimeImmutable $desde, DateTimeImmutable $hasta ): array;

	/**
	 * Elimina filas con `creada_en` anterior a `$limite` — mantenimiento
	 * periódico, mismo criterio de retención que `pluma_bitacora_motor`.
	 *
	 * @return int número de filas eliminadas
	 */
	public function purgarAnterioresA( DateTimeImmutable $limite ): int;
}
