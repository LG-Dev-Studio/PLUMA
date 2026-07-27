<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

/**
 * Salida de `RedactorSintetico::redactar()`. Si `retenida` es `true`, el
 * Corrector Interno no aprobó la pieza tras el máximo de ciclos (Libro
 * Cap. 5.6): `titulo`/`cuerpoHtml` quedan vacíos y el llamador debe mover la
 * Pieza a `EstadoPieza::Retenida` para revisión humana, nunca publicar "lo
 * menos malo".
 *
 * Nivel Dos C.3: si `sinPeriodistaIdoneo` es `true`, ningún periodista del
 * banco superó el umbral de dominio mínimo — `titulo`/`cuerpoHtml` quedan
 * vacíos (nunca se llegó a redactar nada) y el llamador debe mover la Pieza
 * a `EstadoPieza::SinPeriodistaIdoneo`.
 */
final readonly class ResultadoRedaccion {

	public function __construct(
		public string $titulo,
		public string $cuerpoHtml,
		public bool $retenida,
		public ?string $motivoRetenida,
		public int $ciclosUsados,
		public bool $sinPeriodistaIdoneo = false,
		public ?string $motivoSinPeriodistaIdoneo = null,
	) {
	}
}
