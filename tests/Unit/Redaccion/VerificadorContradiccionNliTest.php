<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Proveedores\EtiquetaNli;
use Pluma\Proveedores\ResultadoNli;
use Pluma\Redaccion\SegmentadorUnidadesFactuales;
use Pluma\Redaccion\VerificadorContradiccionNli;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\NliFalso;

/**
 * @covers \Pluma\Redaccion\VerificadorContradiccionNli
 */
final class VerificadorContradiccionNliTest extends CasoDePruebaUnitario {

	/**
	 * @param list<array{score: float, label: string}> $filas
	 */
	private function nliQueResponde( array $filas ): NliFalso {
		$resultados = array_map(
			static fn ( array $fila ): ResultadoNli => new ResultadoNli( EtiquetaNli::from( $fila['label'] ), $fila['score'] ),
			$filas
		);

		return new NliFalso( static fn ( string $premisa, string $hipotesis ): array => $resultados );
	}

	/**
	 * @param array<string, mixed> $opciones adicionales devueltas por `get_option` (p.ej. el umbral)
	 */
	private function verificador( NliFalso $nli, array $opciones = array() ): VerificadorContradiccionNli {
		Functions\when( 'get_option' )->alias(
			static fn ( string $opcion, $defecto = false ) => $opciones[ $opcion ] ?? $defecto
		);

		return new VerificadorContradiccionNli( $nli, new SegmentadorUnidadesFactuales() );
	}

	private function expediente(): Expediente {
		return new Expediente(
			'una tendencia',
			array( new HechoFuente( 'El alcalde renunció el lunes.', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ) )
		);
	}

	public function test_una_unidad_que_contradice_se_marca(): void {
		$verificador = $this->verificador(
			$this->nliQueResponde(
				array(
					array(
						'score' => 0.99,
						'label' => 'contradiction',
					),
					array(
						'score' => 0.005,
						'label' => 'entailment',
					),
					array(
						'score' => 0.005,
						'label' => 'neutral',
					),
				)
			)
		);

		$contradicciones = $verificador->unidadesQueContradicenElExpediente( $this->expediente(), 'El alcalde no renunció.' );

		self::assertCount( 1, $contradicciones );
		self::assertStringContainsString( 'no renunció', $contradicciones[0] );
	}

	public function test_una_unidad_neutral_no_se_marca(): void {
		$verificador = $this->verificador(
			$this->nliQueResponde(
				array(
					array(
						'score' => 0.9,
						'label' => 'neutral',
					),
					array(
						'score' => 0.08,
						'label' => 'entailment',
					),
					array(
						'score' => 0.02,
						'label' => 'contradiction',
					),
				)
			)
		);

		$contradicciones = $verificador->unidadesQueContradicenElExpediente( $this->expediente(), 'La ciudad celebró un festival.' );

		self::assertSame( array(), $contradicciones );
	}

	public function test_expediente_sin_hechos_no_marca_ninguna_unidad(): void {
		$verificador = $this->verificador( new NliFalso() );
		$expediente  = new Expediente( 'una tendencia', array() );

		self::assertSame( array(), $verificador->unidadesQueContradicenElExpediente( $expediente, 'Cualquier texto.' ) );
	}

	public function test_umbral_configurado_por_opcion_se_respeta(): void {
		// Umbral imposible de alcanzar (por encima de 1.0) — ni una puntuación
		// de contradicción de 0.99 basta para marcar la unidad, confirmando que
		// el umbral se lee de `get_option()` y no del valor de fábrica (0.5).
		$verificador = $this->verificador(
			$this->nliQueResponde(
				array(
					array(
						'score' => 0.99,
						'label' => 'contradiction',
					),
				)
			),
			array( VerificadorContradiccionNli::OPCION_UMBRAL_CONTRADICCION => 1.5 )
		);

		$contradicciones = $verificador->unidadesQueContradicenElExpediente( $this->expediente(), 'El alcalde no renunció.' );

		self::assertSame( array(), $contradicciones );
	}
}
