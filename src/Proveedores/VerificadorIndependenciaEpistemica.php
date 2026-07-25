<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

use Pluma\Compuertas\ModoOperacion;

/**
 * Test de arquitectura obligatorio de GOVERNANCE §2.8 (Nivel Tres J.1-J.2):
 * el modo Autónomo exige que el verificador (punto 1 del Corrector Interno)
 * resuelva a una familia de modelo distinta de la del redactor. Fuera de
 * Autónomo, la independencia es recomendada pero no obligatoria (J.2).
 *
 * Alcance de esta porción (Etapa 7): solo el contrato — sin dependencias
 * inyectadas, no se registra en `Pluma\Kernel\Nucleo` porque nada la invoca
 * todavía desde un flujo de ejecución real. La capa determinista (J.3) y la
 * conexión a la activación real de Autónomo quedan en Etapa 8, gateadas por
 * validación empírica en Piloto (ADR 0003, `docs/deuda.md`).
 */
final class VerificadorIndependenciaEpistemica {

	/**
	 * @throws IndependenciaEpistemicaException si el modo es Autónomo y ambos modelos resuelven a la misma familia.
	 */
	public function verificar(
		LenguajeInterface $proveedor,
		string $modeloRedactor,
		string $modeloVerificador,
		ModoOperacion $modo
	): void {
		if ( ModoOperacion::Autonomo !== $modo ) {
			return;
		}

		$familiaRedactor    = $proveedor->familiaDe( $modeloRedactor );
		$familiaVerificador = $proveedor->familiaDe( $modeloVerificador );

		if ( $familiaRedactor === $familiaVerificador ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML; $familiaRedactor viene de slugs de modelo configurados por el propio administrador.
			throw new IndependenciaEpistemicaException( $familiaRedactor );
		}
	}
}
