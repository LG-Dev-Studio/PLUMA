<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Enrutamiento por coste (CLAUDE.md § Contrato del Proveedor de Lenguaje,
 * Libro Cap. 12.3): clasificar con modelo económico, redactar con el mejor.
 * Editable en configuración — jamás hardcodeado en la lógica editorial.
 *
 * Slugs verificados contra el catálogo real de OpenRouter
 * (`GET https://openrouter.ai/api/v1/models`) al escribir este archivo.
 */
final class EnrutadorModelos {

	public const OPCION_MODELO_ECONOMICO   = 'pluma_modelo_economico';
	public const OPCION_MODELO_PREMIUM     = 'pluma_modelo_premium';
	public const OPCION_MODELO_VERIFICADOR = 'pluma_modelo_verificador';
	public const OPCION_MODELO_EMBEDDINGS  = 'pluma_modelo_embeddings';

	private const MODELO_ECONOMICO_DEFECTO = 'anthropic/claude-haiku-4.5';
	private const MODELO_PREMIUM_DEFECTO   = 'anthropic/claude-sonnet-5';
	// Verificado contra la documentación oficial de OpenRouter
	// (openrouter.ai/docs/api-reference/embeddings): modelo de ejemplo citado
	// literalmente ahí para el campo "model" de una petición de embeddings.
	private const MODELO_EMBEDDINGS_DEFECTO = 'openai/text-embedding-3-small';

	public function modeloPara( PropositoLenguaje $proposito ): string {
		$opcion  = $proposito->esPremium() ? self::OPCION_MODELO_PREMIUM : self::OPCION_MODELO_ECONOMICO;
		$defecto = $proposito->esPremium() ? self::MODELO_PREMIUM_DEFECTO : self::MODELO_ECONOMICO_DEFECTO;

		$modelo = get_option( $opcion, $defecto );

		return is_string( $modelo ) && '' !== $modelo ? $modelo : $defecto;
	}

	/**
	 * Modelo del verificador de independencia epistémica (Nivel Tres J.1-J.2).
	 * Default = el mismo modelo premium (honesto: de fábrica, sin
	 * configuración del cliente, redactor y verificador comparten familia —
	 * exactamente el estado de hoy, documentado en vez de escondido). Solo el
	 * contrato existe en esta porción; la obligatoriedad dura de Autónomo
	 * espera validación empírica en Piloto (ADR 0003).
	 */
	public function modeloVerificador(): string {
		$defecto = $this->modeloPara( PropositoLenguaje::Corregir );
		$modelo  = get_option( self::OPCION_MODELO_VERIFICADOR, $defecto );

		return is_string( $modelo ) && '' !== $modelo ? $modelo : $defecto;
	}

	/**
	 * Modelo de embeddings (Nivel Dos A.5 + Nivel Tres J.3) — infraestructura
	 * compartida entre la deriva semántica del corpus de regresión de voz y
	 * la capa determinista de verificación de trazabilidad.
	 */
	public function modeloEmbeddings(): string {
		$modelo = get_option( self::OPCION_MODELO_EMBEDDINGS, self::MODELO_EMBEDDINGS_DEFECTO );

		return is_string( $modelo ) && '' !== $modelo ? $modelo : self::MODELO_EMBEDDINGS_DEFECTO;
	}
}
