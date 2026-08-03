<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Kernel\Cifrado;
use Pluma\Proveedores\ProveedorCerebroRemoto;
use Pluma\Proveedores\ProveedorNliCerebroRemoto;
use Pluma\Redaccion\SegmentadorUnidadesFactuales;
use Pluma\Redaccion\VerificadorContradiccionNli;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * `ProveedorNliCerebroRemoto` es `final` (no mockeable) — se construye real
 * y se controla vía `Brain\Monkey` sobre `get_option`/`wp_remote_post`,
 * mismo patrón que `ProveedorNliCerebroRemotoTest`.
 *
 * @covers \Pluma\Redaccion\VerificadorContradiccionNli
 */
final class VerificadorContradiccionNliTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'AUTH_KEY' ) ) {
			define( 'AUTH_KEY', 'clave-app-de-prueba' );
			define( 'SECURE_AUTH_KEY', 'clave-secure-de-prueba' );
		}
	}

	/**
	 * @param array<string, mixed> $opciones adicionales devueltas por `get_option` (p.ej. el umbral)
	 */
	private function verificador( string $cuerpoRespuestaNli, array $opciones = array() ): VerificadorContradiccionNli {
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

		return new VerificadorContradiccionNli( $nli, new SegmentadorUnidadesFactuales() );
	}

	private function expediente(): Expediente {
		return new Expediente(
			'una tendencia',
			array( new HechoFuente( 'El alcalde renunció el lunes.', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ) )
		);
	}

	public function test_una_unidad_que_contradice_se_marca(): void {
		$verificador = $this->verificador( '[{"score":0.99,"label":"contradiction"},{"score":0.005,"label":"entailment"},{"score":0.005,"label":"neutral"}]' );

		$contradicciones = $verificador->unidadesQueContradicenElExpediente( $this->expediente(), 'El alcalde no renunció.' );

		self::assertCount( 1, $contradicciones );
		self::assertStringContainsString( 'no renunció', $contradicciones[0] );
	}

	public function test_una_unidad_neutral_no_se_marca(): void {
		$verificador = $this->verificador( '[{"score":0.9,"label":"neutral"},{"score":0.08,"label":"entailment"},{"score":0.02,"label":"contradiction"}]' );

		$contradicciones = $verificador->unidadesQueContradicenElExpediente( $this->expediente(), 'La ciudad celebró un festival.' );

		self::assertSame( array(), $contradicciones );
	}

	public function test_expediente_sin_hechos_no_marca_ninguna_unidad(): void {
		$verificador = $this->verificador( '[{"score":0.99,"label":"contradiction"}]' );
		$expediente  = new Expediente( 'una tendencia', array() );

		self::assertSame( array(), $verificador->unidadesQueContradicenElExpediente( $expediente, 'Cualquier texto.' ) );
	}

	public function test_umbral_configurado_por_opcion_se_respeta(): void {
		// Umbral imposible de alcanzar (por encima de 1.0) — ni una puntuación
		// de contradicción de 0.99 basta para marcar la unidad, confirmando que
		// el umbral se lee de `get_option()` y no del valor de fábrica (0.5).
		$verificador = $this->verificador(
			'[{"score":0.99,"label":"contradiction"},{"score":0.005,"label":"entailment"},{"score":0.005,"label":"neutral"}]',
			array( VerificadorContradiccionNli::OPCION_UMBRAL_CONTRADICCION => 1.5 )
		);

		$contradicciones = $verificador->unidadesQueContradicenElExpediente( $this->expediente(), 'El alcalde no renunció.' );

		self::assertSame( array(), $contradicciones );
	}
}
