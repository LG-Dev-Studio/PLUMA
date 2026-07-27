<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Proveedores\PropositoLenguaje;

/**
 * Traduce la Conducta de un periodista (diales, reglas, matriz de tonos) a
 * las `directrices` de estilo que viajan en la `PeticionLenguaje` (Libro
 * Cap. 5.6: "con la voz del periodista — sus diales, muletillas y
 * prohibiciones inyectados como directrices de estilo").
 *
 * La lógica editorial (`RedactorSintetico`) no conoce la forma interna de la
 * Conducta: solo consume el texto que este compilador produce.
 *
 * Nivel Dos A.2: el prompt se ensambla como una {@see PlantillaPrompt}
 * versionada, separando lo que ningún dial puede tocar (`seccionesFijas`) de
 * la traducción dial→directriz (`seccionesParametrizadas`).
 */
final class CompiladorDirectrices {

	private const VERSION_PLANTILLA = 1;

	/**
	 * @var array<string, array{etiqueta: string, bajo: array{directriz: string, parrafoAncla: string}, medio: array{directriz: string, parrafoAncla: string}, alto: array{directriz: string, parrafoAncla: string}}>|null
	 */
	private static ?array $anclasCongeladas = null;

	/**
	 * Nivel Dos A.3: anclas de 3 tramos (`[0,33) / [33,67) / [67,100]`) por
	 * dial continuo — cada tramo con su directriz y un párrafo ancla real
	 * (ejemplo de prosa en ese registro), congeladas en `references/`.
	 *
	 * @return array{etiqueta: string, bajo: array{directriz: string, parrafoAncla: string}, medio: array{directriz: string, parrafoAncla: string}, alto: array{directriz: string, parrafoAncla: string}}
	 */
	private function ancla( string $dial ): array {
		if ( null === self::$anclasCongeladas ) {
			self::$anclasCongeladas = require __DIR__ . '/references/anclas-diales.php';
		}

		return self::$anclasCongeladas[ $dial ] ?? array(
			'etiqueta' => $dial,
			'bajo'     => array(
				'directriz'    => '',
				'parrafoAncla' => '',
			),
			'medio'    => array(
				'directriz'    => '',
				'parrafoAncla' => '',
			),
			'alto'     => array(
				'directriz'    => '',
				'parrafoAncla' => '',
			),
		);
	}

	/**
	 * @return 'bajo'|'medio'|'alto'
	 */
	private function tramoDe( int $valor ): string {
		return match ( true ) {
			$valor < 33 => 'bajo',
			$valor < 67 => 'medio',
			default     => 'alto',
		};
	}

	private function lineaDial( string $dial, int $valor ): string {
		$ancla = $this->ancla( $dial );
		$tramo = $ancla[ $this->tramoDe( $valor ) ];

		return sprintf(
			"%s: %d/100. %s.\nEjemplo de registro (calibración de tono, no copiar literalmente): \"%s\"",
			$ancla['etiqueta'],
			$valor,
			ucfirst( $tramo['directriz'] ),
			$tramo['parrafoAncla']
		);
	}

	public function compilar(
		Periodista $periodista,
		Tono $tonoDominante,
		Tono $tonoApoyo,
		NivelSatiraPermitida $nivelSatiraPermitida
	): string {
		return $this->compilarPlantilla( $periodista, $tonoDominante, $tonoApoyo, $nivelSatiraPermitida )->ensamblar();
	}

	public function compilarPlantilla(
		Periodista $periodista,
		Tono $tonoDominante,
		Tono $tonoApoyo,
		NivelSatiraPermitida $nivelSatiraPermitida
	): PlantillaPrompt {
		$conducta = $periodista->conductaActual;
		$diales   = $conducta->diales;
		$reglas   = $conducta->reglas;

		$seccionesFijas = array(
			sprintf( 'Eres %s, %s de la redacción. %s', $periodista->nombre, $periodista->rol->value, $periodista->biografia ),
			'Línea editorial (filtro de toda tesis que defiendas): ' . $reglas->lineaEditorial,
			'REGLA DE ORO CONTRA LA ALUCINACIÓN (invariante de sistema, ningún dial la modifica): no puedes afirmar nada que no exista en el expediente adjunto.',
			'Vocabulario y frases PROHIBIDAS (nunca las uses, ni variaciones cercanas): '
				. implode( ', ', VocabularioProhibidoGlobal::combinarCon( $reglas->vocabularioProhibido ) ),
		);

		$seccionesParametrizadas = array(
			implode(
				"\n",
				array(
					'Diales de temperamento:',
					$this->lineaDial( 'agudezaCritica', $diales->agudezaCritica ),
					$this->lineaDial( 'humor', $diales->humor ),
					$this->lineaDial( 'formalidad', $diales->formalidad ),
					$this->lineaDial( 'vehemencia', $diales->vehemencia ),
					$this->lineaDial( 'empatia', $diales->empatia ),
					$this->lineaDial( 'densidadDatos', $diales->densidadDatos ),
				)
			),
		);

		$seccionesParametrizadas = array_merge(
			$seccionesParametrizadas,
			MatrizCombinacionDiales::directrices( $diales )
		);

		$directrizSatira = $this->directrizSatira( $diales->satira, $nivelSatiraPermitida );

		if ( NivelSatiraPermitida::Bloqueada === $nivelSatiraPermitida ) {
			$seccionesFijas[] = $directrizSatira;
		} else {
			$seccionesParametrizadas[] = $directrizSatira;
		}

		$seccionesParametrizadas[] = sprintf( 'Tono dominante de esta pieza: %s. Tono de apoyo: %s.', $tonoDominante->value, $tonoApoyo->value );
		$seccionesParametrizadas[] = sprintf( 'Extensión objetivo: aproximadamente %d palabras.', $diales->longitudPalabrasObjetivo() );

		if ( array() !== $reglas->muletillas ) {
			$seccionesParametrizadas[] = 'Rasgos de voz reconocibles (úsalos con moderación — como mucho uno por pieza, nunca todos juntos, jamás de forma paródica): '
				. implode( '; ', $reglas->muletillas );
		}

		if ( array() !== $reglas->lineasRojas ) {
			$seccionesParametrizadas[] = 'Líneas rojas personales — jamás bromees ni las cruces: ' . implode( '; ', $reglas->lineasRojas );
		}

		$seccionesParametrizadas[] = sprintf(
			'Te diriges al lector %s. Estilo de pregunta final: "%s".',
			TratamientoLector::Tu === $reglas->tratamientoLector ? 'de tú' : 'de usted',
			$reglas->estiloPreguntaFinal
		);

		return new PlantillaPrompt(
			PropositoLenguaje::Redactar,
			self::VERSION_PLANTILLA,
			$seccionesFijas,
			$seccionesParametrizadas
		);
	}

	private function directrizSatira( int $dialSatira, NivelSatiraPermitida $nivelPermitido ): string {
		if ( NivelSatiraPermitida::Bloqueada === $nivelPermitido ) {
			// Regla de sistema inviolable (Libro Cap. 5.3): se antepone al dial del periodista sin excepción.
			return 'SÁTIRA BLOQUEADA POR SISTEMA para esta pieza: bajo ninguna circunstancia uses exageración satírica, ironía cruel o humor a costa de víctimas o afectados.';
		}

		$permiso = match ( $nivelPermitido ) {
			NivelSatiraPermitida::No           => 'no uses sátira en esta pieza',
			NivelSatiraPermitida::ConModeracion => 'puedes usar sátira con moderación, en pasajes puntuales',
			NivelSatiraPermitida::EnRemate      => 'puedes usar sátira solo en el remate final',
			NivelSatiraPermitida::PiezaCompleta => 'puedes construir la pieza entera con tono satírico',
		};

		return sprintf( 'Sátira (dial %d/100 de este periodista): para este tipo de noticia, %s.', $dialSatira, $permiso );
	}
}
