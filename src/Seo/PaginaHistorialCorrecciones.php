<?php

declare(strict_types=1);

namespace Pluma\Seo;

use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Kernel\Activador;
use Pluma\Publicacion\GestorCorrecciones;

/**
 * Nivel Cuatro Z — historial público de correcciones: "las correcciones
 * verificadas quedan también en un historial público". Página virtual
 * (`ADR 0009`), mismo patrón que `PaginaAutorPeriodista`/`HistoriaHub`/
 * `PaginaMetodologia`.
 */
final class PaginaHistorialCorrecciones {

	public const QUERY_VAR = 'pluma_pagina_historial_correcciones';

	private const LIMITE = 50;

	/** @var list<array{tituloPieza: string, urlPieza: string, corregidaEn: \DateTimeImmutable, notaEditor: ?string, creditoLector: ?string}>|null */
	private static ?array $entradasActuales = null;

	public function __construct(
		private readonly GestorCorrecciones $gestorCorrecciones,
		private readonly RepositorioPiezasInterface $piezas,
	) {
	}

	public function registrar(): void {
		add_action( 'init', array( self::class, 'registrarReglaReescritura' ) );
		add_action( 'init', array( self::class, 'flushSiHaceFalta' ), 20 );
		add_filter( 'query_vars', array( $this, 'registrarQueryVar' ) );
		add_action( 'template_redirect', array( $this, 'resolverPeticion' ) );
		add_filter( 'template_include', array( $this, 'servirPlantilla' ) );
	}

	public static function registrarReglaReescritura(): void {
		add_rewrite_rule( '^correcciones/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	public static function flushSiHaceFalta(): void {
		if ( ! get_option( Activador::OPCION_FLUSH_REESCRITURA_PENDIENTE ) ) {
			return;
		}

		flush_rewrite_rules();
		delete_option( Activador::OPCION_FLUSH_REESCRITURA_PENDIENTE );
	}

	/**
	 * @param list<string> $vars
	 * @return list<string>
	 */
	public function registrarQueryVar( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	public static function url(): string {
		return home_url( '/correcciones/' );
	}

	public function resolverPeticion(): void {
		self::$entradasActuales = null;

		if ( '1' !== (string) get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$entradas = array();

		foreach ( $this->gestorCorrecciones->historialPublico( self::LIMITE ) as $correccion ) {
			if ( null === $correccion->resueltoEn ) {
				continue;
			}

			$pieza = $this->piezas->obtenerPorId( $correccion->piezaId );

			if ( null === $pieza || null === $pieza->postId ) {
				continue;
			}

			$titulo = get_the_title( $pieza->postId );
			$url    = get_permalink( $pieza->postId );

			if ( '' === $titulo || false === $url ) {
				continue;
			}

			$entradas[] = array(
				'tituloPieza'   => $titulo,
				'urlPieza'      => $url,
				'corregidaEn'   => $correccion->resueltoEn,
				'notaEditor'    => $correccion->notaEditor,
				'creditoLector' => $correccion->creditoOptIn ? $correccion->nombreCredito : null,
			);
		}

		self::$entradasActuales = $entradas;
	}

	public function servirPlantilla( string $plantilla ): string {
		if ( null === self::$entradasActuales ) {
			return $plantilla;
		}

		return PLUMA_ENGINE_DIR . 'src/Seo/templates/pagina-historial-correcciones.php';
	}

	/**
	 * @return list<array{tituloPieza: string, urlPieza: string, corregidaEn: \DateTimeImmutable, notaEditor: ?string, creditoLector: ?string}>|null
	 */
	public static function datosParaPlantilla(): ?array {
		return self::$entradasActuales;
	}
}
