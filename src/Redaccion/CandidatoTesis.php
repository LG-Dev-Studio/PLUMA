<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

/**
 * Un candidato de tesis del Paso 3 del Algoritmo de Decisión Editorial
 * (Libro Cap. 5.5), puntuado por originalidad, compatibilidad con la línea
 * editorial, sustento en hechos verificados, y potencial de conversación.
 */
final readonly class CandidatoTesis {

	public function __construct(
		public string $tesis,
		public float $puntuacionOriginalidad,
		public float $puntuacionCompatibilidadLinea,
		public float $puntuacionSustento,
		public float $puntuacionConversacional,
	) {
	}

	/**
	 * Media ponderada de los tres factores de PRIORIDAD (Nivel Tres K.1):
	 * originalidad 0.40, compatibilidad con la línea editorial 0.35,
	 * potencial de conversación 0.25. El sustento en hechos verificados es un
	 * piso ELIMINATORIO, no un contribuyente — ya se aplicó en
	 * `SelectorAngulo::generarCandidatos()` (`puntuacionSustento >= UMBRAL_SUSTENTO_MINIMO`)
	 * antes de que el candidato llegue aquí. Sumarlo también al promedio
	 * diluiría el piso exactamente como K.1 diagnostica: una tesis con
	 * sustento apenas por encima del umbral pero muy alta en potencial de
	 * conversación podría ganarle a una mejor sustentada.
	 */
	public function puntuacionTotal(): float {
		return 0.40 * $this->puntuacionOriginalidad
			+ 0.35 * $this->puntuacionCompatibilidadLinea
			+ 0.25 * $this->puntuacionConversacional;
	}

	/**
	 * @return array{tesis: string, puntuacionOriginalidad: float, puntuacionCompatibilidadLinea: float, puntuacionSustento: float, puntuacionConversacional: float}
	 */
	public function aArray(): array {
		return array(
			'tesis'                         => $this->tesis,
			'puntuacionOriginalidad'        => $this->puntuacionOriginalidad,
			'puntuacionCompatibilidadLinea' => $this->puntuacionCompatibilidadLinea,
			'puntuacionSustento'            => $this->puntuacionSustento,
			'puntuacionConversacional'      => $this->puntuacionConversacional,
		);
	}

	/**
	 * @param array{tesis: string, puntuacionOriginalidad: float, puntuacionCompatibilidadLinea: float, puntuacionSustento: float, puntuacionConversacional: float} $datos
	 */
	public static function desdeArray( array $datos ): self {
		return new self(
			$datos['tesis'],
			$datos['puntuacionOriginalidad'],
			$datos['puntuacionCompatibilidadLinea'],
			$datos['puntuacionSustento'],
			$datos['puntuacionConversacional']
		);
	}
}
