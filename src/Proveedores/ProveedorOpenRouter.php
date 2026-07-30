<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

use Pluma\Kernel\Cifrado;
use Pluma\Kernel\RelojInterface;

/**
 * Implementación real de `LenguajeInterface` Y `EmbeddingsInterface` sobre
 * OpenRouter (`https://openrouter.ai/api/v1/chat/completions` y
 * `.../v1/embeddings`, ambos verificados contra la documentación oficial):
 * la misma llave, el mismo circuit breaker — un fallo de red de OpenRouter
 * afecta a ambas capacidades por igual, así que comparten el mismo estado en
 * vez de duplicar la infraestructura de resiliencia en una segunda clase
 * (Nivel Dos A.5 + Nivel Tres J.3: "coste marginal trivial" comparte
 * infraestructura, no solo modelo de facturación).
 *
 * Resiliencia (pl-proveedor-ia §4): timeout explícito, circuit breaker por
 * fallos consecutivos entre ejecuciones (mismo patrón que
 * `ProveedorGoogleTrends` — sin `sleep()` síncrono, el presupuesto de tiempo
 * del orquestador manda). Presupuesto de coste verificado ANTES de la
 * llamada (GOVERNANCE, CLAUDE.md § Contrato del Proveedor de Lenguaje).
 */
final class ProveedorOpenRouter implements LenguajeInterface, EmbeddingsInterface {

	private const URL = 'https://openrouter.ai/api/v1/chat/completions';
	// Verificado contra la documentación oficial de OpenRouter
	// (openrouter.ai/docs/api-reference/embeddings).
	private const URL_EMBEDDINGS = 'https://openrouter.ai/api/v1/embeddings';
	// Verificado contra la documentación oficial de OpenRouter
	// (openrouter.ai/docs/api-reference/limits): "para consultar el límite o
	// los créditos restantes de una llave, haz un GET a esta URL" — sin
	// coste de generación, el endpoint correcto para "prueba en vivo".
	private const URL_VERIFICACION_LLAVE        = 'https://openrouter.ai/api/v1/key';
	private const TIMEOUT_SEGUNDOS              = 60;
	private const TIMEOUT_VERIFICACION_SEGUNDOS = 15;
	public const OPCION_LLAVE_CIFRADA           = 'pluma_openrouter_llave_cifrada';
	private const OPCION_FALLOS                 = 'pluma_proveedor_lenguaje_fallos';
	private const OPCION_ABIERTO_HASTA          = 'pluma_proveedor_lenguaje_abierto_hasta';
	private const UMBRAL_FALLOS                 = 3;
	private const ENFRIAMIENTO_SEGUNDOS         = 300;

	public function __construct(
		private readonly EnrutadorModelos $enrutador,
		private readonly PresupuestoLenguaje $presupuesto,
		private readonly RelojInterface $reloj,
	) {
	}

	public function completar( PeticionLenguaje $peticion ): RespuestaLenguaje {
		if ( ! $this->presupuesto->disponible() ) {
			throw new ProveedorLenguajeException(
				'Presupuesto diario de lenguaje agotado.',
				presupuestoAgotado: true
			);
		}

		$llave = $this->obtenerLlave();

		if ( null === $llave ) {
			throw new ProveedorLenguajeException(
				'No hay llave de OpenRouter configurada (o las salts de wp-config.php cambiaron).',
				sinCredenciales: true
			);
		}

		$this->verificarCircuitoCerrado();

		$modelo = $this->enrutador->modeloPara( $peticion->proposito );
		$bloque = NeutralizadorMaterial::delimitar( $peticion->material );

		$cuerpo = array(
			'model'       => $modelo,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $peticion->directrices . "\n\n" . $bloque['directriz'],
				),
				array(
					'role'    => 'user',
					'content' => $bloque['material'],
				),
			),
			'max_tokens'  => $peticion->maxTokens,
			'temperature' => $peticion->proposito->temperatura(),
		);

		$cuerpoJson = wp_json_encode( $cuerpo );

		if ( false === $cuerpoJson ) {
			throw new ProveedorLenguajeException( 'No se pudo codificar el cuerpo de la petición a OpenRouter.' );
		}

		$inicio = microtime( true );

		$respuesta = wp_remote_post(
			self::URL,
			array(
				'timeout' => self::TIMEOUT_SEGUNDOS,
				'headers' => array(
					'Authorization' => 'Bearer ' . $llave,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url(),
					'X-Title'       => 'PLUMA Engine',
				),
				'body'    => $cuerpoJson,
			)
		);

		$latenciaMs = (int) round( ( microtime( true ) - $inicio ) * 1000 );

		if ( is_wp_error( $respuesta ) ) {
			$this->registrarFallo();

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new ProveedorLenguajeException( 'No se pudo contactar OpenRouter: ' . $respuesta->get_error_message() );
		}

		$codigo = wp_remote_retrieve_response_code( $respuesta );

		if ( 200 !== $codigo ) {
			$this->registrarFallo();

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new ProveedorLenguajeException( "OpenRouter respondió HTTP {$codigo}." );
		}

		update_option( self::OPCION_FALLOS, 0, false );

		$datos = json_decode( wp_remote_retrieve_body( $respuesta ), true );

		if ( ! is_array( $datos ) || ! isset( $datos['choices'][0]['message']['content'] ) ) {
			throw new ProveedorLenguajeException( 'OpenRouter devolvió una respuesta con formato inesperado.' );
		}

		$costeUsd = (float) ( $datos['usage']['cost'] ?? 0.0 );
		$this->presupuesto->registrarGasto( $costeUsd );

		$razonDeCorte = $datos['choices'][0]['finish_reason'] ?? null;

		return new RespuestaLenguaje(
			(string) $datos['choices'][0]['message']['content'],
			(int) ( $datos['usage']['prompt_tokens'] ?? 0 ),
			(int) ( $datos['usage']['completion_tokens'] ?? 0 ),
			$costeUsd,
			'openrouter',
			(string) ( $datos['model'] ?? $modelo ),
			$latenciaMs,
			'length' === $razonDeCorte
		);
	}

	/**
	 * `EmbeddingsInterface` (Nivel Dos A.5 + Nivel Tres J.3): mismo circuito,
	 * misma llave, mismo presupuesto que `completar()` — no es un proveedor
	 * de red distinto, es la misma cuenta de OpenRouter con otro endpoint.
	 *
	 * @return list<float>
	 */
	public function embed( string $texto ): array {
		if ( ! $this->presupuesto->disponible() ) {
			throw new ProveedorLenguajeException(
				'Presupuesto diario de lenguaje agotado.',
				presupuestoAgotado: true
			);
		}

		$llave = $this->obtenerLlave();

		if ( null === $llave ) {
			throw new ProveedorLenguajeException(
				'No hay llave de OpenRouter configurada (o las salts de wp-config.php cambiaron).',
				sinCredenciales: true
			);
		}

		$this->verificarCircuitoCerrado();

		$modelo     = $this->enrutador->modeloEmbeddings();
		$cuerpoJson = wp_json_encode(
			array(
				'model' => $modelo,
				'input' => $texto,
			)
		);

		if ( false === $cuerpoJson ) {
			throw new ProveedorLenguajeException( 'No se pudo codificar el cuerpo de la petición de embeddings a OpenRouter.' );
		}

		$respuesta = wp_remote_post(
			self::URL_EMBEDDINGS,
			array(
				'timeout' => self::TIMEOUT_SEGUNDOS,
				'headers' => array(
					'Authorization' => 'Bearer ' . $llave,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url(),
					'X-Title'       => 'PLUMA Engine',
				),
				'body'    => $cuerpoJson,
			)
		);

		if ( is_wp_error( $respuesta ) ) {
			$this->registrarFallo();

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new ProveedorLenguajeException( 'No se pudo contactar OpenRouter: ' . $respuesta->get_error_message() );
		}

		$codigo = wp_remote_retrieve_response_code( $respuesta );

		if ( 200 !== $codigo ) {
			$this->registrarFallo();

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new ProveedorLenguajeException( "OpenRouter respondió HTTP {$codigo} en /embeddings." );
		}

		update_option( self::OPCION_FALLOS, 0, false );

		$datos = json_decode( wp_remote_retrieve_body( $respuesta ), true );

		if ( ! is_array( $datos ) || ! isset( $datos['data'][0]['embedding'] ) || ! is_array( $datos['data'][0]['embedding'] ) ) {
			throw new ProveedorLenguajeException( 'OpenRouter devolvió una respuesta de embeddings con formato inesperado.' );
		}

		$this->presupuesto->registrarGasto( (float) ( $datos['usage']['cost'] ?? 0.0 ) );

		return array_map( static fn ( $valor ): float => (float) $valor, $datos['data'][0]['embedding'] );
	}

	/**
	 * "Prueba en vivo" de una llave (Libro Cap. 10.3, onboarding acto 2; y
	 * Sala de Máquinas, Cap. 10.2): valida contra la API real de OpenRouter
	 * SIN generar coste — nunca invoca `completar()` para esto.
	 */
	public function probarLlave( string $llaveEnTextoPlano ): bool {
		$respuesta = wp_remote_get(
			self::URL_VERIFICACION_LLAVE,
			array(
				'timeout' => self::TIMEOUT_VERIFICACION_SEGUNDOS,
				'headers' => array(
					'Authorization' => 'Bearer ' . $llaveEnTextoPlano,
				),
			)
		);

		if ( is_wp_error( $respuesta ) ) {
			return false;
		}

		return 200 === wp_remote_retrieve_response_code( $respuesta );
	}

	/**
	 * OpenRouter nombra sus modelos con la convención `{proveedor}/{modelo}`
	 * (ej. `anthropic/claude-sonnet-5`) — el prefijo ya es la familia de
	 * entrenamiento real, no una taxonomía inventada (Nivel Tres J.2). Sin
	 * `/`, el propio slug es la familia (fallback defensivo).
	 */
	public function familiaDe( string $modelo ): string {
		$partes = explode( '/', $modelo, 2 );

		return $partes[0];
	}

	/**
	 * Sin red y sin coste: solo comprueba que exista una llave descifrable.
	 * `obtenerLlave()` devuelve `null` tanto si nunca se configuró como si
	 * las salts de `wp-config.php` cambiaron y el sobre ya no se puede abrir
	 * — ambos casos son "no hay credenciales utilizables" para el editor.
	 */
	public function tieneCredenciales(): bool {
		return null !== $this->obtenerLlave();
	}

	/**
	 * Estado del circuit breaker para la Sala de Máquinas (Cap. 10.2:
	 * "estado de cada API conectada") — el mismo estado que ya usa
	 * `verificarCircuitoCerrado()`, expuesto en solo lectura.
	 */
	public function circuitoAbierto(): bool {
		$abiertoHasta = (int) get_option( self::OPCION_ABIERTO_HASTA, 0 );

		return $abiertoHasta > $this->reloj->ahora()->getTimestamp();
	}

	private function obtenerLlave(): ?string {
		$sobre = get_option( self::OPCION_LLAVE_CIFRADA );

		if ( ! is_string( $sobre ) || '' === $sobre ) {
			return null;
		}

		return Cifrado::descifrar( $sobre );
	}

	/**
	 * @throws ProveedorLenguajeException si el circuito está abierto
	 */
	private function verificarCircuitoCerrado(): void {
		if ( $this->circuitoAbierto() ) {
			throw new ProveedorLenguajeException(
				'Circuito abierto: OpenRouter falló repetidamente; en enfriamiento.',
				circuitoAbierto: true
			);
		}
	}

	private function registrarFallo(): void {
		$fallos = ( (int) get_option( self::OPCION_FALLOS, 0 ) ) + 1;
		update_option( self::OPCION_FALLOS, $fallos, false );

		if ( $fallos >= self::UMBRAL_FALLOS ) {
			$yaAbierto = $this->circuitoAbierto();

			update_option( self::OPCION_ABIERTO_HASTA, $this->reloj->ahora()->getTimestamp() + self::ENFRIAMIENTO_SEGUNDOS, false );

			if ( ! $yaAbierto ) {
				// PLUMA-E3-7/E8-8: alerta una sola vez por transición cerrado→abierto.
				do_action( 'pluma/proveedor_circuito_abierto', 'openrouter' );
			}
		}
	}
}
