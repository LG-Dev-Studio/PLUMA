<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Proveedores\PropositoLenguaje;

/**
 * Versiona lo que `CompiladorDirectrices::compilar()` produce (Nivel Dos A.2):
 * separa las secciones que ningún dial de periodista puede tocar
 * (`seccionesFijas` — invariantes de sistema, líneas rojas globales) de las
 * que sí varían con la Conducta (`seccionesParametrizadas` — la traducción
 * dial→directriz). Antes de esta versión, el compilador generaba el prompt
 * ad-hoc sin registro de qué cambió entre una versión y otra.
 */
final readonly class PlantillaPrompt {

	/**
	 * @param list<string> $seccionesFijas
	 * @param list<string> $seccionesParametrizadas
	 */
	public function __construct(
		public PropositoLenguaje $proposito,
		public int $version,
		public array $seccionesFijas,
		public array $seccionesParametrizadas,
	) {
	}

	public function ensamblar(): string {
		return implode( "\n\n", array_merge( $this->seccionesFijas, $this->seccionesParametrizadas ) );
	}
}
