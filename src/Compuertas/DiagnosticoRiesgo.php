<?php

declare(strict_types=1);

namespace Pluma\Compuertas;

/**
 * Diagnóstico de la Compuerta de Riesgo (Libro Cap. 8.2, endurecido por
 * Nivel Tres M.1/N.1). `implicaTragedia` se hereda de la clasificación del
 * Paso 1 del Algoritmo de Decisión Editorial (Etapa 2,
 * `Pluma\Redaccion\ClasificacionNoticia`) — no se re-pregunta al proveedor
 * de lenguaje algo que el sistema ya sabe.
 */
final readonly class DiagnosticoRiesgo {

	public function __construct(
		public bool $implicaTragedia,
		public bool $implicaMenores,
		public bool $implicaSalud,
		public bool $implicaViolencia,
		public bool $riesgoDifamacion,
		public string $detalleDifamacion,
		public bool $hechosDisputadosSinSenalar,
		public ?TemaRegulado $temaRegulado,
		public bool $afirmacionNegativaSobrePersonaIdentificable = false,
		public bool $posturaSenaladoAusente = false,
		public bool $retencionObligatoriaPorRegimenPenal = false,
	) {
	}

	/**
	 * Sensibilidad temática (Libro Cap. 8.2/2.4): fuerza degradación de modo
	 * y bloqueo absoluto de sátira, por encima de cualquier configuración.
	 */
	public function requiereDegradacionPorSensibilidad(): bool {
		return $this->implicaTragedia || $this->implicaMenores || $this->implicaSalud || $this->implicaViolencia;
	}

	/**
	 * Riesgo legal/reputacional que el sistema nunca decide solo (Libro
	 * Cap. 8.2, Nivel Tres M.1/N.1): difamación, hechos disputados
	 * presentados como consenso, ausencia registrada de postura del
	 * señalado (derecho de réplica previa — motivo independiente de la
	 * difamación: un hecho puede estar perfectamente verificado por doble
	 * fuente y aun así publicarse sin que la parte señalada haya tenido
	 * nunca oportunidad de responder), o régimen de responsabilidad penal
	 * sobre una afirmación fáctica negativa de persona identificable (nunca
	 * Autónomo en ese caso, sin excepción configurable).
	 */
	public function requiereRetencionParaHumano(): bool {
		return $this->riesgoDifamacion
			|| $this->hechosDisputadosSinSenalar
			|| $this->posturaSenaladoAusente
			|| $this->retencionObligatoriaPorRegimenPenal;
	}

	/**
	 * @return array{implicaTragedia: bool, implicaMenores: bool, implicaSalud: bool, implicaViolencia: bool, riesgoDifamacion: bool, detalleDifamacion: string, hechosDisputadosSinSenalar: bool, temaRegulado: ?string, afirmacionNegativaSobrePersonaIdentificable: bool, posturaSenaladoAusente: bool, retencionObligatoriaPorRegimenPenal: bool}
	 */
	public function aArray(): array {
		return array(
			'implicaTragedia'                             => $this->implicaTragedia,
			'implicaMenores'                              => $this->implicaMenores,
			'implicaSalud'                                => $this->implicaSalud,
			'implicaViolencia'                            => $this->implicaViolencia,
			'riesgoDifamacion'                            => $this->riesgoDifamacion,
			'detalleDifamacion'                           => $this->detalleDifamacion,
			'hechosDisputadosSinSenalar'                  => $this->hechosDisputadosSinSenalar,
			'temaRegulado'                                => $this->temaRegulado?->value,
			'afirmacionNegativaSobrePersonaIdentificable' => $this->afirmacionNegativaSobrePersonaIdentificable,
			'posturaSenaladoAusente'                      => $this->posturaSenaladoAusente,
			'retencionObligatoriaPorRegimenPenal'         => $this->retencionObligatoriaPorRegimenPenal,
		);
	}

	/**
	 * Los 3 campos nuevos (Nivel Tres M.1/N.1) usan `?? false` al leer:
	 * diagnósticos persistidos antes de esta porción no traen esas claves, y
	 * esas Piezas ya atravesaron sus propias Compuertas en su momento — no se
	 * reevalúan retroactivamente, solo se leen para mostrar el historial.
	 *
	 * @param array{implicaTragedia: bool, implicaMenores: bool, implicaSalud: bool, implicaViolencia: bool, riesgoDifamacion: bool, detalleDifamacion: string, hechosDisputadosSinSenalar: bool, temaRegulado: ?string, afirmacionNegativaSobrePersonaIdentificable?: bool, posturaSenaladoAusente?: bool, retencionObligatoriaPorRegimenPenal?: bool} $datos
	 */
	public static function desdeArray( array $datos ): self {
		return new self(
			$datos['implicaTragedia'],
			$datos['implicaMenores'],
			$datos['implicaSalud'],
			$datos['implicaViolencia'],
			$datos['riesgoDifamacion'],
			$datos['detalleDifamacion'],
			$datos['hechosDisputadosSinSenalar'],
			null !== $datos['temaRegulado'] ? TemaRegulado::from( $datos['temaRegulado'] ) : null,
			$datos['afirmacionNegativaSobrePersonaIdentificable'] ?? false,
			$datos['posturaSenaladoAusente'] ?? false,
			$datos['retencionObligatoriaPorRegimenPenal'] ?? false
		);
	}
}
