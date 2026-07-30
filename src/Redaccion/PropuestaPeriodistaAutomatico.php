<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

/**
 * Trabajo posterior a la Etapa 9 (creación automática de periodistas):
 * salida de `AgrupadorTemasSinCobertura::evaluar()` cuando el proveedor de
 * lenguaje decide que un grupo de temas sin cobertura sí forma UN tema
 * coherente. `vertical` es siempre una especialidad EXACTA (nunca
 * {@see Especialidad::VERTICAL_COMODIN}) — un periodista auto-creado nace
 * acotado a su clúster, precisamente para no acaparar asignaciones futuras
 * no relacionadas con el hueco real detectado.
 */
final readonly class PropuestaPeriodistaAutomatico {

	public function __construct(
		public string $vertical,
		public string $nombre,
		public string $biografia,
		public RolPeriodista $rol,
		public int $nivelDominio,
	) {
	}
}
