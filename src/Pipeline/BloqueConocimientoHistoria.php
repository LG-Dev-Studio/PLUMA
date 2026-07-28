<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

/**
 * Nivel Cuatro U.1 — el bloque "Lo que sabemos / Lo que no sabemos":
 * mantenido por el sistema a partir de los estados del expediente de cada
 * Pieza de la Historia (hechos verificados/atribuidos vs. disputados),
 * nunca redactado a mano. `HechoFuente::$nivel` es la única señal que
 * decide el bucket — `Verificado`/`Atribuido` son sustento suficiente para
 * "sabemos", `Disputado` es "no sabemos aún" (Nivel Dos B.1-B.2).
 */
final readonly class BloqueConocimientoHistoria {

	/**
	 * @param list<string> $sabemos extractos con sustento (Verificado/Atribuido)
	 * @param list<string> $noSabemos extractos disputados, sin resolver entre fuentes
	 */
	public function __construct(
		public array $sabemos,
		public array $noSabemos,
	) {
	}
}
