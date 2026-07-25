<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Único punto de contacto con modelos de IA (CLAUDE.md § Contrato del
 * Proveedor de Lenguaje). La lógica editorial NO conoce qué proveedor hay
 * detrás; cambiar de proveedor no toca `Pluma\Redaccion`.
 */
interface LenguajeInterface {

	/**
	 * @throws ProveedorLenguajeException fallo de red/HTTP/formato, respuesta
	 *                                    truncada o presupuesto agotado.
	 */
	public function completar( PeticionLenguaje $peticion ): RespuestaLenguaje;

	/**
	 * Familia de modelo (Nivel Tres J.2): "la independencia de proveedor
	 * comercial no es lo mismo que independencia de familia de modelo — el
	 * contrato debe distinguir ambas cosas explícitamente". Pura, sin red:
	 * deriva la familia del slug de modelo ya conocido, para que el test de
	 * arquitectura de GOVERNANCE §2.8 pueda verificarse en tiempo de
	 * configuración, no de ejecución.
	 */
	public function familiaDe( string $modelo ): string;
}
