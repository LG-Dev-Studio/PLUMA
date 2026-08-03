<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Una entrada real del registro de modelos exigido por el canon
 * (`docs/CEREBRO_PLUMA_v2.md` Parte 5.1, regla 5: "Ningún artefacto sin
 * registro: todo modelo cargado declara rol, versión, licencia, idioma,
 * checksum y procedencia"). Objeto inmutable, sin lógica — la invariante
 * "`checksum === null` exige `motivoSinChecksum` no nulo" se verifica en
 * `RegistroModelosTest`, no aquí.
 */
final readonly class ModeloRegistrado {

	public function __construct(
		public RolModelo $rol,
		public string $artefacto,
		public string $version,
		public string $licencia,
		public string $idiomas,
		public ?string $checksum,
		public ?string $motivoSinChecksum,
		public string $procedencia,
	) {
	}
}
