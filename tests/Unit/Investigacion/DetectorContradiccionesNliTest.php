<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Investigacion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Investigacion\DetectorContradiccionesNli;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Kernel\Cifrado;
use Pluma\Proveedores\ProveedorCerebroRemoto;
use Pluma\Proveedores\ProveedorNliCerebroRemoto;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * `ProveedorNliCerebroRemoto` es `final` (no mockeable) — se construye real
 * y se controla vía `Brain\Monkey`, mismo patrón que
 * `Pluma\Tests\Unit\Redaccion\VerificadorContradiccionNliTest`.
 *
 * @covers \Pluma\Investigacion\DetectorContradiccionesNli
 */
final class DetectorContradiccionesNliTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'AUTH_KEY' ) ) {
			define( 'AUTH_KEY', 'clave-app-de-prueba' );
			define( 'SECURE_AUTH_KEY', 'clave-secure-de-prueba' );
		}
	}

	/**
	 * @param array<string, mixed> $opciones adicionales devueltas por `get_option`
	 */
	private function detector( string $cuerpoRespuestaNli, array $opciones = array() ): DetectorContradiccionesNli {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) use ( $opciones ) {
				if ( array_key_exists( $opcion, $opciones ) ) {
					return $opciones[ $opcion ];
				}

				return match ( $opcion ) {
					ProveedorCerebroRemoto::OPCION_URL => 'https://cerebro.example',
					ProveedorCerebroRemoto::OPCION_TOKEN_CIFRADO => Cifrado::cifrar( 'token' ),
					default => $defecto,
				};
			}
		);

		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $cuerpoRespuestaNli );

		$nli = new ProveedorNliCerebroRemoto( new ProveedorCerebroRemoto() );

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
		Functions\when( 'get_option' )->justReturn( false );

		$nli = new ProveedorNliCerebroRemoto( new ProveedorCerebroRemoto() );

		self::assertSame( array(), ( new DetectorContradiccionesNli( $nli ) )->paresQueContradicen( $this->expediente( 1 ) ) );
	}

	public function test_un_par_que_contradice_se_marca(): void {
		$detector = $this->detector( '[{"score":0.99,"label":"contradiction"},{"score":0.005,"label":"entailment"},{"score":0.005,"label":"neutral"}]' );

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
		$detector = $this->detector( '[{"score":0.9,"label":"neutral"},{"score":0.08,"label":"entailment"},{"score":0.02,"label":"contradiction"}]' );

		self::assertSame( array(), $detector->paresQueContradicen( $this->expediente( 2 ) ) );
	}

	public function test_tres_hechos_comparan_exactamente_los_tres_pares_sin_duplicados(): void {
		$detector = $this->detector( '[{"score":0.99,"label":"contradiction"},{"score":0.005,"label":"entailment"},{"score":0.005,"label":"neutral"}]' );

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
			'[{"score":0.99,"label":"contradiction"},{"score":0.005,"label":"entailment"},{"score":0.005,"label":"neutral"}]',
			array( DetectorContradiccionesNli::OPCION_UMBRAL_CONTRADICCION_FUENTES => 1.5 )
		);

		self::assertSame( array(), $detector->paresQueContradicen( $this->expediente( 2 ) ) );
	}
}
