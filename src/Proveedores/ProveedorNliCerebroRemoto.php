<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Rol NLI (`docs/CEREBRO_PLUMA_v2.md` Parte 1.3, `ADR 0020`) sobre el
 * cerebro remoto (T3) — protocolo `POST {url}/predict`, cuerpo
 * `{"inputs": "premisa</s></s>hipotesis"}` (separador de pares de secuencias
 * de XLM-RoBERTa), respuesta `[{"score": float, "label": string}, ...]`
 * ordenada descendente. Verificado contra un servicio real Hugging Face Text
 * Embeddings Inference sirviendo `MoritzLaurer/xlm-v-base-mnli-xnli`
 * (MIT, `ADR 0020`).
 *
 * Mismo principio que `ProveedorEmbeddingsCerebroRemoto` (`ADR 0016`): no
 * sabe qué modelo hay detrás, no está vinculado a ningún consumidor real
 * todavía — disponible en el contenedor de DI para cuando NCP-3
 * (`verificar_trazabilidad`) exista.
 */
final class ProveedorNliCerebroRemoto {

	private const TIMEOUT_SEGUNDOS = 15;

	public function __construct(
		private readonly ProveedorCerebroRemoto $cerebroRemoto,
	) {
	}

	/**
	 * @return list<ResultadoNli>
	 *
	 * @throws ProveedorLenguajeException fallo de red/HTTP/formato o sin credenciales.
	 */
	public function inferir( string $premisa, string $hipotesis ): array {
		$credenciales = $this->cerebroRemoto->credenciales();

		if ( null === $credenciales ) {
			throw new ProveedorLenguajeException(
				'No hay cerebro remoto configurado (o las salts de wp-config.php cambiaron).',
				sinCredenciales: true
			);
		}

		$cuerpoJson = wp_json_encode( array( 'inputs' => "{$premisa}</s></s>{$hipotesis}" ) );

		if ( false === $cuerpoJson ) {
			throw new ProveedorLenguajeException( 'No se pudo codificar el cuerpo de la petición de NLI al cerebro remoto.' );
		}

		$respuesta = wp_remote_post(
			rtrim( $credenciales['url'], '/' ) . '/predict',
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
			throw new ProveedorLenguajeException( "El cerebro remoto respondió HTTP {$codigo} en /predict." );
		}

		$datos = json_decode( wp_remote_retrieve_body( $respuesta ), true );

		if ( ! is_array( $datos ) || array() === $datos ) {
			throw new ProveedorLenguajeException( 'El cerebro remoto devolvió una respuesta de NLI con formato inesperado.' );
		}

		return array_map(
			function ( $fila ): ResultadoNli {
				if ( ! is_array( $fila ) || ! isset( $fila['label'], $fila['score'] ) || ! is_string( $fila['label'] ) ) {
					throw new ProveedorLenguajeException( 'El cerebro remoto devolvió una fila de NLI con formato inesperado.' );
				}

				$etiqueta = EtiquetaNli::tryFrom( $fila['label'] );

				if ( null === $etiqueta ) {
					throw new ProveedorLenguajeException( "El cerebro remoto devolvió una etiqueta de NLI desconocida: «{$fila['label']}»." );
				}

				return new ResultadoNli( $etiqueta, (float) $fila['score'] );
			},
			$datos
		);
	}
}
