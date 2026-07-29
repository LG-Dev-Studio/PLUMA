<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

use DateTimeImmutable;
use Pluma\Kernel\RelojInterface;
use WP_Error;

/**
 * Implementación real sobre la API de Google Search Console, verificada
 * contra la documentación oficial (developers.google.com/identity/protocols/
 * oauth2/web-server y developers.google.com/webmaster-tools/v1):
 * autorización `https://accounts.google.com/o/oauth2/v2/auth`, token
 * `https://oauth2.googleapis.com/token`, `sites.list` y
 * `searchAnalytics.query` sobre `https://www.googleapis.com/webmasters/v3`.
 *
 * Resiliencia (pl-proveedor-ia §4): mismo patrón de circuit breaker que
 * `ProveedorGoogleTrends`/`ProveedorOpenRouter` — timeout explícito, sin
 * `sleep()` síncrono, el siguiente tick del motor es el reintento natural.
 *
 * Solo lectura: el scope pedido es siempre `webmasters.readonly` — PLUMA
 * nunca escribe en Search Console.
 */
final class ProveedorSearchConsole implements ProveedorSearchConsoleInterface {

	private const URL_AUTORIZACION      = 'https://accounts.google.com/o/oauth2/v2/auth';
	private const URL_TOKEN             = 'https://oauth2.googleapis.com/token';
	private const URL_SITIOS            = 'https://www.googleapis.com/webmasters/v3/sites';
	private const URL_BASE_ANALITICA    = 'https://www.googleapis.com/webmasters/v3/sites/%s/searchAnalytics/query';
	private const SCOPE                 = 'https://www.googleapis.com/auth/webmasters.readonly';
	private const TIMEOUT_SEGUNDOS      = 15;
	private const OPCION_FALLOS         = 'pluma_proveedor_search_console_fallos';
	private const OPCION_ABIERTO_HASTA  = 'pluma_proveedor_search_console_abierto_hasta';
	private const UMBRAL_FALLOS         = 3;
	private const ENFRIAMIENTO_SEGUNDOS = 900;
	private const FILAS_MAXIMAS         = 5000;

	public function __construct( private readonly RelojInterface $reloj ) {
	}

	public function urlAutorizacion( string $clientId, string $redirectUri, string $state ): string {
		return add_query_arg(
			array(
				'client_id'     => rawurlencode( $clientId ),
				'redirect_uri'  => rawurlencode( $redirectUri ),
				'response_type' => 'code',
				'scope'         => rawurlencode( self::SCOPE ),
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'state'         => rawurlencode( $state ),
			),
			self::URL_AUTORIZACION
		);
	}

	public function intercambiarCodigo( string $clientId, string $clientSecret, string $code, string $redirectUri ): TokensOAuth {
		return $this->pedirTokens(
			array(
				'client_id'     => $clientId,
				'client_secret' => $clientSecret,
				'code'          => $code,
				'redirect_uri'  => $redirectUri,
				'grant_type'    => 'authorization_code',
			)
		);
	}

	public function refrescarAccessToken( string $clientId, string $clientSecret, string $refreshToken ): TokensOAuth {
		return $this->pedirTokens(
			array(
				'client_id'     => $clientId,
				'client_secret' => $clientSecret,
				'refresh_token' => $refreshToken,
				'grant_type'    => 'refresh_token',
			)
		);
	}

	public function listarSitios( string $accessToken ): array {
		$this->verificarCircuitoCerrado();

		$respuesta = wp_remote_get(
			self::URL_SITIOS,
			array(
				'timeout' => self::TIMEOUT_SEGUNDOS,
				'headers' => array( 'Authorization' => 'Bearer ' . $accessToken ),
			)
		);

		$datos = $this->interpretarRespuestaJson( $respuesta );

		if ( ! isset( $datos['siteEntry'] ) || ! is_array( $datos['siteEntry'] ) ) {
			return array();
		}

		return array_map(
			static fn ( array $entrada ): array => array(
				'siteUrl'         => (string) ( $entrada['siteUrl'] ?? '' ),
				'permissionLevel' => (string) ( $entrada['permissionLevel'] ?? '' ),
			),
			$datos['siteEntry']
		);
	}

	public function consultarAnalitica( string $accessToken, string $siteUrl, DateTimeImmutable $desde, DateTimeImmutable $hasta ): array {
		$this->verificarCircuitoCerrado();

		$cuerpo = wp_json_encode(
			array(
				'startDate'  => $desde->format( 'Y-m-d' ),
				'endDate'    => $hasta->format( 'Y-m-d' ),
				'dimensions' => array( 'page', 'query' ),
				'rowLimit'   => self::FILAS_MAXIMAS,
			)
		);

		if ( false === $cuerpo ) {
			throw new ProveedorSearchConsoleException( 'No se pudo codificar la petición a Search Console.' );
		}

		$url = sprintf( self::URL_BASE_ANALITICA, rawurlencode( $siteUrl ) );

		$respuesta = wp_remote_post(
			$url,
			array(
				'timeout' => self::TIMEOUT_SEGUNDOS,
				'headers' => array(
					'Authorization' => 'Bearer ' . $accessToken,
					'Content-Type'  => 'application/json',
				),
				'body'    => $cuerpo,
			)
		);

		$datos = $this->interpretarRespuestaJson( $respuesta );

		if ( ! isset( $datos['rows'] ) || ! is_array( $datos['rows'] ) ) {
			return array();
		}

		return array_map(
			static function ( array $fila ): FilaAnaliticaSearchConsole {
				$claves = is_array( $fila['keys'] ?? null ) ? $fila['keys'] : array( '', '' );

				return new FilaAnaliticaSearchConsole(
					(string) ( $claves[0] ?? '' ),
					(string) ( $claves[1] ?? '' ),
					(int) round( (float) ( $fila['clicks'] ?? 0 ) ),
					(int) round( (float) ( $fila['impressions'] ?? 0 ) ),
					(float) ( $fila['ctr'] ?? 0.0 ),
					(float) ( $fila['position'] ?? 0.0 )
				);
			},
			$datos['rows']
		);
	}

	public function circuitoAbierto(): bool {
		$abiertoHasta = (int) get_option( self::OPCION_ABIERTO_HASTA, 0 );

		return $abiertoHasta > $this->reloj->ahora()->getTimestamp();
	}

	/**
	 * @param array<string, string> $parametros
	 */
	private function pedirTokens( array $parametros ): TokensOAuth {
		$this->verificarCircuitoCerrado();

		$respuesta = wp_remote_post(
			self::URL_TOKEN,
			array(
				'timeout' => self::TIMEOUT_SEGUNDOS,
				'body'    => $parametros,
			)
		);

		$datos = $this->interpretarRespuestaJson( $respuesta );

		if ( ! isset( $datos['access_token'] ) ) {
			throw new ProveedorSearchConsoleException( 'Google no devolvió un access_token.' );
		}

		return new TokensOAuth(
			(string) $datos['access_token'],
			isset( $datos['refresh_token'] ) ? (string) $datos['refresh_token'] : null,
			(int) ( $datos['expires_in'] ?? 3600 )
		);
	}

	/**
	 * @param array<string, mixed>|WP_Error $respuesta
	 *
	 * @return array<string, mixed>
	 *
	 * @throws ProveedorSearchConsoleException
	 */
	private function interpretarRespuestaJson( array|WP_Error $respuesta ): array {
		if ( is_wp_error( $respuesta ) ) {
			$this->registrarFallo();

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new ProveedorSearchConsoleException( 'No se pudo contactar Google: ' . $respuesta->get_error_message() );
		}

		$codigo = wp_remote_retrieve_response_code( $respuesta );

		if ( $codigo < 200 || $codigo >= 300 ) {
			$this->registrarFallo();

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new ProveedorSearchConsoleException( "Google respondió HTTP {$codigo}." );
		}

		$datos = json_decode( wp_remote_retrieve_body( $respuesta ), true );

		if ( ! is_array( $datos ) ) {
			$this->registrarFallo();

			throw new ProveedorSearchConsoleException( 'Google devolvió una respuesta con formato inesperado.' );
		}

		update_option( self::OPCION_FALLOS, 0, false );

		return $datos;
	}

	/**
	 * @throws ProveedorSearchConsoleException si el circuito está abierto
	 */
	private function verificarCircuitoCerrado(): void {
		if ( $this->circuitoAbierto() ) {
			throw new ProveedorSearchConsoleException( 'Circuito abierto: Search Console falló repetidamente; en enfriamiento.' );
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
				do_action( 'pluma/proveedor_circuito_abierto', 'search_console' );
			}
		}
	}
}
