<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

use DateTimeImmutable;
use Pluma\Kernel\RelojInterface;
use SimpleXMLElement;

/**
 * Feed público de tendencias diarias de Google (sin autenticación, verificado
 * en `https://trends.google.com/trending/rss?geo=US`).
 *
 * Resiliencia (pl-proveedor-ia §4): timeout explícito y circuit breaker por
 * fallos consecutivos entre ejecuciones del motor. Sin reintento síncrono
 * con `sleep()` dentro de la misma petición — el presupuesto de tiempo del
 * orquestador (Libro Cap. 9.4) prefiere ejecuciones cortas; el siguiente
 * disparo del cron real es el reintento natural.
 */
final class ProveedorGoogleTrends implements ProveedorTendenciasInterface {

	private const URL_BASE              = 'https://trends.google.com/trending/rss';
	private const TIMEOUT_SEGUNDOS      = 8;
	private const OPCION_FALLOS         = 'pluma_proveedor_trends_fallos';
	private const OPCION_ABIERTO_HASTA  = 'pluma_proveedor_trends_abierto_hasta';
	private const UMBRAL_FALLOS         = 3;
	private const ENFRIAMIENTO_SEGUNDOS = 900;
	private const NAMESPACE_HT          = 'https://trends.google.com/trending/rss';

	public function __construct(
		private readonly RelojInterface $reloj,
		private readonly string $geo = 'US',
	) {
	}

	public function obtenerTendenciasCrudas(): array {
		$this->verificarCircuitoCerrado();

		$url = add_query_arg( 'geo', $this->geo, self::URL_BASE );

		if ( ! ValidadorUrl::esSegura( $url ) ) {
			$this->registrarFallo();

			throw new ProveedorTendenciasException( 'URL del proveedor de tendencias no superó la validación SSRF.' );
		}

		$respuesta = wp_remote_get(
			$url,
			array(
				'timeout'    => self::TIMEOUT_SEGUNDOS,
				'user-agent' => 'PLUMA Engine/' . PLUMA_ENGINE_VERSION . ' (+https://github.com/jhonnfrank1995/PLUMA)',
			)
		);

		if ( is_wp_error( $respuesta ) ) {
			$this->registrarFallo();

			throw new ProveedorTendenciasException(
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
				'No se pudo contactar el proveedor de tendencias: ' . $respuesta->get_error_message()
			);
		}

		$codigo = wp_remote_retrieve_response_code( $respuesta );

		if ( 200 !== $codigo ) {
			$this->registrarFallo();

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new ProveedorTendenciasException( "El proveedor de tendencias respondió HTTP {$codigo}." );
		}

		$cuerpo     = wp_remote_retrieve_body( $respuesta );
		$tendencias = $this->interpretarFeed( $cuerpo );

		update_option( self::OPCION_FALLOS, 0, false );

		return $tendencias;
	}

	/**
	 * @throws ProveedorTendenciasException si el circuito está abierto
	 */
	private function verificarCircuitoCerrado(): void {
		if ( $this->circuitoAbierto() ) {
			throw new ProveedorTendenciasException(
				'Circuito abierto: el proveedor de tendencias falló repetidamente; en enfriamiento.'
			);
		}
	}

	/**
	 * Estado del circuit breaker para la Sala de Máquinas (Libro Cap. 10.2:
	 * "estado de cada API conectada") — el mismo estado que ya usa
	 * `verificarCircuitoCerrado()`, expuesto en solo lectura.
	 */
	public function circuitoAbierto(): bool {
		$abiertoHasta = (int) get_option( self::OPCION_ABIERTO_HASTA, 0 );

		return $abiertoHasta > $this->reloj->ahora()->getTimestamp();
	}

	private function registrarFallo(): void {
		$fallos = ( (int) get_option( self::OPCION_FALLOS, 0 ) ) + 1;
		update_option( self::OPCION_FALLOS, $fallos, false );

		if ( $fallos >= self::UMBRAL_FALLOS ) {
			$yaAbierto = $this->circuitoAbierto();

			update_option(
				self::OPCION_ABIERTO_HASTA,
				$this->reloj->ahora()->getTimestamp() + self::ENFRIAMIENTO_SEGUNDOS,
				false
			);

			if ( ! $yaAbierto ) {
				// PLUMA-E3-7/E8-8: alerta una sola vez por transición cerrado→abierto.
				do_action( 'pluma/proveedor_circuito_abierto', 'google_trends' );
			}
		}
	}

	/**
	 * @return list<TendenciaCruda>
	 *
	 * @throws ProveedorTendenciasException si el XML no puede interpretarse
	 */
	private function interpretarFeed( string $xmlCrudo ): array {
		$usoPrevio = libxml_use_internal_errors( true );
		$xml       = simplexml_load_string( $xmlCrudo );
		libxml_use_internal_errors( $usoPrevio );

		if ( false === $xml ) {
			throw new ProveedorTendenciasException( 'El feed de tendencias no es XML válido.' );
		}

		$tendencias = array();

		foreach ( $xml->channel->item as $item ) {
			$ht = $item->children( self::NAMESPACE_HT );

			$articulos = array();
			foreach ( $ht->news_item as $noticia ) {
				$articulos[] = array(
					'titulo' => (string) $noticia->news_item_title,
					'url'    => (string) $noticia->news_item_url,
					'fuente' => (string) $noticia->news_item_source,
				);
			}

			$tendencias[] = new TendenciaCruda(
				(string) $item->title,
				(string) $ht->approx_traffic,
				$this->parsearFecha( (string) $item->pubDate ),
				$articulos
			);
		}

		return $tendencias;
	}

	private function parsearFecha( string $rfc2822 ): DateTimeImmutable {
		$fecha = DateTimeImmutable::createFromFormat( DateTimeImmutable::RFC2822, $rfc2822 );

		return false !== $fecha ? $fecha : $this->reloj->ahora();
	}
}
