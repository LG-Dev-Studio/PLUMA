<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * `EmbeddingsInterface` sobre el cerebro remoto (T3, `ADR 0016`) — protocolo
 * `POST {url}/embed`, cuerpo `{"inputs": "texto"}`, respuesta `[[float,...]]`
 * (un vector por texto de entrada), verificado contra un servicio real
 * Hugging Face Text Embeddings Inference sirviendo `intfloat/multilingual-e5-small`
 * (MIT, `ADR 0014`). Fuente del protocolo: `github.com/huggingface/text-embeddings-inference`
 * (README + `docs/openapi.json`, schema `EmbedResponse`).
 *
 * Deliberadamente NO añade el prefijo `"query: "`/`"passage: "` que exige
 * e5 — eso es responsabilidad de quien construye el texto de entrada, no de
 * este transporte (esta clase no sabe qué modelo hay detrás del cerebro
 * remoto, "ninguna capa editorial sabe qué plano la atendió",
 * `CLAUDE.md` § Contrato del Proveedor de Lenguaje).
 *
 * NO está vinculada a `EmbeddingsInterface::class` en el contenedor de DI
 * — los 2 consumidores reales (`VerificadorRegresionVoz`,
 * `VerificadorTrazabilidadDeterminista`) siguen usando `ProveedorOpenRouter`;
 * sus umbrales están calibrados contra esa distribución de similitud, no
 * contra la de este modelo. Sustituir el proveedor por defecto es una
 * decisión futura explícita, no un efecto colateral de esta clase.
 */
final class ProveedorEmbeddingsCerebroRemoto implements EmbeddingsInterface {

	/** Modelo de referencia verificado en `ADR 0016` — metadato informativo, no un artefacto descargado por PLUMA (vive en el servicio remoto, fuera de su control directo). */
	public const MODELO_REFERENCIA = 'intfloat/multilingual-e5-small';

	private const TIMEOUT_SEGUNDOS = 15;

	public function __construct(
		private readonly ProveedorCerebroRemoto $cerebroRemoto,
	) {
	}

	/**
	 * @throws ProveedorLenguajeException fallo de red/HTTP/formato o sin credenciales.
	 */
	public function embed( string $texto ): array {
		$credenciales = $this->cerebroRemoto->credenciales();

		if ( null === $credenciales ) {
			throw new ProveedorLenguajeException(
				'No hay cerebro remoto configurado (o las salts de wp-config.php cambiaron).',
				sinCredenciales: true
			);
		}

		$cuerpoJson = wp_json_encode( array( 'inputs' => $texto ) );

		if ( false === $cuerpoJson ) {
			throw new ProveedorLenguajeException( 'No se pudo codificar el cuerpo de la petición de embeddings al cerebro remoto.' );
		}

		$respuesta = wp_remote_post(
			rtrim( $credenciales['url'], '/' ) . '/embed',
			array(
				'timeout' => self::TIMEOUT_SEGUNDOS,
				'headers' => array(
					'Authorization' => 'Bearer ' . $credenciales['token'],
					'Content-Type'  => 'application/json',
				),
				'body'    => $cuerpoJson,
			)
		);

		if ( is_wp_error( $respuesta ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new ProveedorLenguajeException( 'No se pudo contactar el cerebro remoto: ' . $respuesta->get_error_message() );
		}

		$codigo = wp_remote_retrieve_response_code( $respuesta );

		if ( 200 !== $codigo ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new ProveedorLenguajeException( "El cerebro remoto respondió HTTP {$codigo} en /embed." );
		}

		$datos = json_decode( wp_remote_retrieve_body( $respuesta ), true );

		if ( ! is_array( $datos ) || ! isset( $datos[0] ) || ! is_array( $datos[0] ) ) {
			throw new ProveedorLenguajeException( 'El cerebro remoto devolvió una respuesta de embeddings con formato inesperado.' );
		}

		return array_map( static fn ( $valor ): float => (float) $valor, $datos[0] );
	}
}
