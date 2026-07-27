<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

use DateTimeImmutable;

/**
 * Un hecho del expediente con su procedencia (GOVERNANCE §2.5): extracto
 * acotado, url y fecha para citar y enlazar — jamás para reproducir.
 *
 * Nivel Tres L.1 + L.2: `procedenciaDeclaracion` y `corroboracionAudiovisual`
 * son ejes independientes del nivel de confianza del medio (`nivel`) —
 * `NoAplica` por defecto para la mayoría de los hechos, que no son
 * declaraciones textuales atribuidas ni de origen audiovisual.
 */
final readonly class HechoFuente {

	public function __construct(
		public string $extracto,
		public string $url,
		public DateTimeImmutable $fecha,
		public NivelVerificacion $nivel,
		public EstadoProcedenciaDeclaracion $procedenciaDeclaracion = EstadoProcedenciaDeclaracion::NoAplica,
		public EstadoCorroboracionAudiovisual $corroboracionAudiovisual = EstadoCorroboracionAudiovisual::NoAplica,
	) {
	}

	/**
	 * @return array{extracto: string, url: string, fecha: string, nivel: string, procedenciaDeclaracion: string, corroboracionAudiovisual: string}
	 */
	public function aArray(): array {
		return array(
			'extracto'                 => $this->extracto,
			'url'                      => $this->url,
			'fecha'                    => $this->fecha->format( DATE_ATOM ),
			'nivel'                    => $this->nivel->value,
			'procedenciaDeclaracion'   => $this->procedenciaDeclaracion->value,
			'corroboracionAudiovisual' => $this->corroboracionAudiovisual->value,
		);
	}

	/**
	 * @param array{extracto: string, url: string, fecha: string, nivel: string, procedenciaDeclaracion?: string, corroboracionAudiovisual?: string} $datos
	 */
	public static function desdeArray( array $datos ): self {
		return new self(
			$datos['extracto'],
			$datos['url'],
			new DateTimeImmutable( $datos['fecha'] ),
			NivelVerificacion::from( $datos['nivel'] ),
			isset( $datos['procedenciaDeclaracion'] ) ? EstadoProcedenciaDeclaracion::from( $datos['procedenciaDeclaracion'] ) : EstadoProcedenciaDeclaracion::NoAplica,
			isset( $datos['corroboracionAudiovisual'] ) ? EstadoCorroboracionAudiovisual::from( $datos['corroboracionAudiovisual'] ) : EstadoCorroboracionAudiovisual::NoAplica,
		);
	}
}
