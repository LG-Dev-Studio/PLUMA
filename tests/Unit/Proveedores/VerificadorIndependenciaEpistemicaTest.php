<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Proveedores;

use Pluma\Compuertas\ModoOperacion;
use Pluma\Proveedores\IndependenciaEpistemicaException;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\RespuestaLenguaje;
use Pluma\Proveedores\VerificadorIndependenciaEpistemica;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * Test de arquitectura obligatorio de GOVERNANCE §2.8 (Nivel Tres J.1-J.2):
 * "intentar activar Autónomo con verificador_provider de la misma familia
 * que redactor_provider debe fallar de forma explícita, nunca degradar en
 * silencio". Alcance de Etapa 7: solo el contrato — nada invoca esta clase
 * todavía desde un flujo de ejecución real (ADR 0003).
 *
 * @covers \Pluma\Proveedores\VerificadorIndependenciaEpistemica
 */
final class VerificadorIndependenciaEpistemicaTest extends CasoDePruebaUnitario {

	private function proveedor(): LenguajeInterface {
		return new class() implements LenguajeInterface {
			public function completar( PeticionLenguaje $peticion ): RespuestaLenguaje {
				throw new \RuntimeException( 'no usado en este test' );
			}

			public function tieneCredenciales(): bool {
				return true;
			}

			public function familiaDe( string $modelo ): string {
				return explode( '/', $modelo, 2 )[0];
			}
		};
	}

	public function test_autonomo_con_la_misma_familia_falla_de_forma_explicita(): void {
		$this->expectException( IndependenciaEpistemicaException::class );

		( new VerificadorIndependenciaEpistemica() )->verificar(
			$this->proveedor(),
			'anthropic/claude-sonnet-5',
			'anthropic/claude-haiku-4.5',
			ModoOperacion::Autonomo
		);
	}

	public function test_autonomo_con_familia_distinta_no_falla(): void {
		( new VerificadorIndependenciaEpistemica() )->verificar(
			$this->proveedor(),
			'anthropic/claude-sonnet-5',
			'openai/gpt-5',
			ModoOperacion::Autonomo
		);

		$this->expectNotToPerformAssertions();
	}

	/**
	 * Nivel Tres J.2: fuera de Autónomo, la independencia es recomendada,
	 * no obligatoria — Piloto/Copiloto con la misma familia no fallan.
	 */
	public function test_piloto_con_la_misma_familia_no_falla(): void {
		( new VerificadorIndependenciaEpistemica() )->verificar(
			$this->proveedor(),
			'anthropic/claude-sonnet-5',
			'anthropic/claude-haiku-4.5',
			ModoOperacion::Piloto
		);

		$this->expectNotToPerformAssertions();
	}

	public function test_copiloto_con_la_misma_familia_no_falla(): void {
		( new VerificadorIndependenciaEpistemica() )->verificar(
			$this->proveedor(),
			'anthropic/claude-sonnet-5',
			'anthropic/claude-haiku-4.5',
			ModoOperacion::Copiloto
		);

		$this->expectNotToPerformAssertions();
	}
}
