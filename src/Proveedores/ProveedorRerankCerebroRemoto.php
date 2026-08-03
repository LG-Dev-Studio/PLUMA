<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Rol RRK (`docs/CEREBRO_PLUMA_v2.md` Parte 1.3, `ADR 0020`) sobre el
 * cerebro remoto (T3) — protocolo `POST {url}/rerank`, cuerpo
 * `{"query": string, "texts": [string,...], "raw_scores": bool}`, respuesta
 * `[{"index": int, "score": float}, ...]` ordenada descendente. Verificado
 * contra un servicio real Hugging Face Text Embeddings Inference sirviendo
 * `BAAI/bge-reranker-base` (MIT, `ADR 0020`).
 *
 * Mismo principio que `ProveedorEmbeddingsCerebroRemoto` (`ADR 0016`): no
 * sabe qué modelo hay detrás, no está vinculado a ningún consumidor real
 * todavía.
 */
final class ProveedorRerankCerebroRemoto {

	private const TIMEOUT_SEGUNDOS = 15;

	public function __construct(
		private readonly ProveedorCerebroRemoto $cerebroRemoto,
	) {
	}

	/**
	 * @param list<string> $textos
	 * @return list<ResultadoRerank>
	 *
	 * @throws ProveedorLenguajeException fallo de red/HTTP/formato o sin credenciales.
	 */
	public function reordenar( string $consulta, array $textos ): array {
		$credenciales = $this->cerebroRemoto->credenciales();

		if ( null === $credenciales ) {
			throw new ProveedorLenguajeException(
				'No hay cerebro remoto configurado (o las salts de wp-config.php cambiaron).',
				sinCredenciales: true
			);
		}

		$cuerpoJson = wp_json_encode(
			array(
				'query'      => $consulta,
				'texts'      => $textos,
				'raw_scores' => false,
			)
		);

		if ( false === $cuerpoJson ) {
			throw new ProveedorLenguajeException( 'No se pudo codificar el cuerpo de la petición de reranking al cerebro remoto.' );
		}

		$respuesta = wp_remote_post(
			rtrim( $credenciales['url'], '/' ) . '/rerank',
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
			throw new ProveedorLenguajeException( "El cerebro remoto respondió HTTP {$codigo} en /rerank." );
		}

		$datos = json_decode( wp_remote_retrieve_body( $respuesta ), true );

		if ( ! is_array( $datos ) ) {
			throw new ProveedorLenguajeException( 'El cerebro remoto devolvió una respuesta de reranking con formato inesperado.' );
		}

		return array_map(
			static function ( $fila ): ResultadoRerank {
				if ( ! is_array( $fila ) || ! isset( $fila['index'], $fila['score'] ) ) {
					throw new ProveedorLenguajeException( 'El cerebro remoto devolvió una fila de reranking con formato inesperado.' );
				}

				return new ResultadoRerank( (int) $fila['index'], (float) $fila['score'] );
			},
			$datos
		);
	}
}
