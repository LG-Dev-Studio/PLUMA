<?php

declare(strict_types=1);

namespace Pluma\Kernel;

/**
 * Contrato de `AlmacenPerfilEntorno` — existe para que los consumidores
 * (`Orquestador`, `ExportadorDiagnostico`, `PantallaPanel`, `RestSalaMaquinas`)
 * puedan sustituirlo en tests, mismo patrón que `RepositorioXInterface`.
 */
interface AlmacenPerfilEntornoInterface {

	public function refrescar(): PerfilEntorno;

	public function leer(): PerfilEntorno;
}
