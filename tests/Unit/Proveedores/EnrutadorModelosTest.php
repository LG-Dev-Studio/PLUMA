<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Proveedores;

use Brain\Monkey\Functions;
use Pluma\Proveedores\EnrutadorModelos;
use Pluma\Proveedores\PropositoLenguaje;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Proveedores\EnrutadorModelos
 */
final class EnrutadorModelosTest extends CasoDePruebaUnitario {

	public function test_usa_el_modelo_economico_por_defecto_para_un_proposito_no_premium(): void {
		Functions\when( 'get_option' )->justReturn( 'anthropic/claude-haiku-4.5' );

		self::assertSame(
			'anthropic/claude-haiku-4.5',
			( new EnrutadorModelos() )->modeloPara( PropositoLenguaje::Clasificar )
		);
	}

	public function test_usa_el_modelo_premium_por_defecto_para_redactar(): void {
		Functions\when( 'get_option' )->justReturn( 'anthropic/claude-sonnet-5' );

		self::assertSame(
			'anthropic/claude-sonnet-5',
			( new EnrutadorModelos() )->modeloPara( PropositoLenguaje::Redactar )
		);
	}

	public function test_respeta_el_modelo_configurado_por_el_cliente(): void {
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInTernaryCondition.FoundInTernaryCondition -- falso positivo: es una arrow function con `=>`, no una asignación.
			static fn ( string $opcion, $defecto = false ) => EnrutadorModelos::OPCION_MODELO_PREMIUM === $opcion
				? 'openai/gpt-5.2'
				: $defecto
		);

		self::assertSame(
			'openai/gpt-5.2',
			( new EnrutadorModelos() )->modeloPara( PropositoLenguaje::Corregir )
		);
	}

	/**
	 * Nivel Tres J.1-J.2: sin configuración del cliente, el verificador
	 * comparte modelo (y por tanto familia) con el redactor — honesto: el
	 * estado de hoy, documentado en vez de escondido. Solo el contrato existe
	 * en esta porción.
	 */
	public function test_modelo_verificador_sin_configurar_usa_el_mismo_que_el_premium(): void {
		Functions\when( 'get_option' )->justReturn( 'anthropic/claude-sonnet-5' );

		self::assertSame( 'anthropic/claude-sonnet-5', ( new EnrutadorModelos() )->modeloVerificador() );
	}

	public function test_modelo_verificador_respeta_la_configuracion_del_cliente(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				if ( EnrutadorModelos::OPCION_MODELO_VERIFICADOR === $opcion ) {
					return 'openai/gpt-5';
				}

				return $defecto;
			}
		);

		self::assertSame( 'openai/gpt-5', ( new EnrutadorModelos() )->modeloVerificador() );
	}

	/**
	 * Nivel Dos A.5 + Nivel Tres J.3: modelo de embeddings compartido entre la
	 * deriva semántica del corpus de voz y la capa determinista de
	 * trazabilidad — default verificado contra la documentación oficial de
	 * OpenRouter (openrouter.ai/docs/api-reference/embeddings).
	 */
	public function test_modelo_embeddings_sin_configurar_usa_el_defecto_de_fabrica(): void {
		Functions\when( 'get_option' )->justReturn( false );

		self::assertSame( 'openai/text-embedding-3-small', ( new EnrutadorModelos() )->modeloEmbeddings() );
	}

	public function test_modelo_embeddings_respeta_la_configuracion_del_cliente(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				if ( EnrutadorModelos::OPCION_MODELO_EMBEDDINGS === $opcion ) {
					return 'openai/text-embedding-3-large';
				}

				return $defecto;
			}
		);

		self::assertSame( 'openai/text-embedding-3-large', ( new EnrutadorModelos() )->modeloEmbeddings() );
	}

	public function test_ignora_una_opcion_vacia_y_cae_al_defecto(): void {
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- la firma debe calzar con la de get_option(); el doble ignora ambos parámetros a propósito.
			static fn ( string $opcion, $defecto = false ) => ''
		);

		self::assertSame(
			'anthropic/claude-haiku-4.5',
			( new EnrutadorModelos() )->modeloPara( PropositoLenguaje::Titulares )
		);
	}
}
