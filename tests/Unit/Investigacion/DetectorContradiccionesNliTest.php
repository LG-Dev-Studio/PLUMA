<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Investigacion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Investigacion\DetectorContradiccionesNli;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Proveedores\EtiquetaNli;
use Pluma\Proveedores\ResultadoNli;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\NliFalso;

/**
 * @covers \Pluma\Investigacion\DetectorContradiccionesNli
 */
final class DetectorContradiccionesNliTest extends CasoDePruebaUnitario {

	/**
	 * @param list<array{score: float, label: string}> $filas
	 */
	private function nliQueResponde( array $filas ): NliFalso {
		$resultados = array_map(
			static fn ( array $fila ): ResultadoNli => new ResultadoNli( EtiquetaNli::from( $fila['label'] ), $fila['score'] ),
			$filas
		);

		return new NliFalso( static fn (): array => $resultados );
	}

	/**
	 * @param array<string, mixed> $opciones adicionales devueltas por `get_option`
	 */
	private function detector( NliFalso $nli, array $opciones = array() ): DetectorContradiccionesNli {
		Functions\when( 'get_option' )->alias(
			static fn ( string $opcion, $defecto = false ) => $opciones[ $opcion ] ?? $defecto
		);

		return new DetectorContradiccionesNli( $nli );
	}

	private function expediente( int $cantidadHechos ): Expediente {
		$hechos = array();

		for ( $i = 0; $i < $cantidadHechos; $i++ ) {
			$hechos[] = new HechoFuente( "hecho {$i}", "https://example.com/{$i}", new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido );
		}

		return new Expediente( 'una tendencia', $hechos );
	}

	public function test_expediente_con_menos_de_dos_hechos_no_llama_a_nada(): void {
		self::assertSame( array(), ( new DetectorContradiccionesNli( new NliFalso() ) )->paresQueContradicen( $this->expediente( 1 ) ) );
	}

	public function test_un_par_que_contradice_se_marca(): void {
		$detector = $this->detector(
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

		$pares = $detector->paresQueContradicen( $this->expediente( 2 ) );

		self::assertCount( 1, $pares );
		self::assertSame(
			array(
				'indiceA' => 0,
				'indiceB' => 1,
			),
			$pares[0]
		);
	}

	public function test_un_par_neutral_no_se_marca(): void {
		$detector = $this->detector(
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

		self::assertSame( array(), $detector->paresQueContradicen( $this->expediente( 2 ) ) );
	}

	public function test_tres_hechos_comparan_exactamente_los_tres_pares_sin_duplicados(): void {
		$detector = $this->detector(
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

		$pares = $detector->paresQueContradicen( $this->expediente( 3 ) );

		self::assertCount( 3, $pares );
		self::assertSame(
			array(
				array(
					'indiceA' => 0,
					'indiceB' => 1,
				),
				array(
					'indiceA' => 0,
					'indiceB' => 2,
				),
				array(
					'indiceA' => 1,
					'indiceB' => 2,
				),
			),
			$pares
		);
	}

	public function test_umbral_configurado_por_opcion_se_respeta(): void {
		// Umbral imposible de alcanzar (por encima de 1.0) — ni una puntuación
		// de contradicción de 0.99 basta para marcar el par, confirmando que
		// el umbral se lee de `get_option()` y no del valor de fábrica (0.5).
		$detector = $this->detector(
			$this->nliQueResponde(
				array(
					array(
						'score' => 0.99,
						'label' => 'contradiction',
					),
				)
			),
			array( DetectorContradiccionesNli::OPCION_UMBRAL_CONTRADICCION_FUENTES => 1.5 )
		);

		self::assertSame( array(), $detector->paresQueContradicen( $this->expediente( 2 ) ) );
	}
}
