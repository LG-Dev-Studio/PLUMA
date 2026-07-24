<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Contrato del proveedor de telemetría (GOVERNANCE §5.5: "opt-in explícito,
 * anónima, documentada. Jamás contenido del cliente ni llaves").
 *
 * **Deliberadamente sin método de envío todavía.** Enviar telemetría por HTTP
 * requiere un servidor receptor propio que no existe (mismo motivo raíz que
 * `PLUMA-E6-1`, licenciamiento) — registrado como deuda `PLUMA-E6-2`. Esta
 * interfaz solo cubre la construcción local del payload: qué se compartiría
 * si el envío existiera, para que el editor lo vea antes de decidir activar
 * el interruptor opt-in.
 */
interface TelemetriaInterface {

	/**
	 * @return array{
	 *     versionPlugin: string,
	 *     versionEsquema: string,
	 *     versionPhp: string,
	 *     versionWordPress: string,
	 *     versionBaseDatos: string,
	 *     esMultisitio: bool,
	 *     modoOperacion: string,
	 *     periodistasActivos: int,
	 *     piezasPublicadas: int,
	 *     generadoEn: string
	 * }
	 */
	public function construirPayload(): array;
}
