<?php

declare(strict_types=1);

namespace Pluma\Seo;

use Pluma\Compuertas\CompuertaRiesgo;
use Pluma\Compuertas\GestorModoRespeto;
use Pluma\Compuertas\ModoOperacion;
use Pluma\Compuertas\RegimenResponsabilidad;
use Pluma\Kernel\Activador;
use Pluma\Pipeline\Orquestador;

/**
 * Nivel Cuatro Z — página de metodología ("Cómo trabaja esta redacción"):
 * "generada desde la configuración real del sistema — nunca prosa de
 * marketing desincronizada de la operación". Página virtual (`ADR 0009`),
 * mismo patrón que `PaginaAutorPeriodista`/`HistoriaHub`.
 */
final class PaginaMetodologia {

	public const QUERY_VAR = 'pluma_pagina_metodologia';

	/** @var array{modoOperacion: ModoOperacion, regimenResponsabilidad: RegimenResponsabilidad, modoRespetoActivo: bool}|null */
	private static ?array $datosActuales = null;

	public function __construct( private readonly GestorModoRespeto $gestorModoRespeto ) {
	}

	public function registrar(): void {
		add_action( 'init', array( self::class, 'registrarReglaReescritura' ) );
		add_action( 'init', array( self::class, 'flushSiHaceFalta' ), 20 );
		add_filter( 'query_vars', array( $this, 'registrarQueryVar' ) );
		add_action( 'template_redirect', array( $this, 'resolverPeticion' ) );
		add_filter( 'template_include', array( $this, 'servirPlantilla' ) );
	}

	public static function registrarReglaReescritura(): void {
		add_rewrite_rule( '^metodologia/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
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
		return home_url( '/metodologia/' );
	}

	public function resolverPeticion(): void {
		self::$datosActuales = null;

		if ( '1' !== (string) get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$modo    = ModoOperacion::tryFrom( (string) get_option( Orquestador::OPCION_MODO_OPERACION, ModoOperacion::Piloto->value ) ) ?? ModoOperacion::Piloto;
		$regimen = RegimenResponsabilidad::tryFrom( (string) get_option( CompuertaRiesgo::OPCION_REGIMEN_RESPONSABILIDAD, RegimenResponsabilidad::Civil->value ) ) ?? RegimenResponsabilidad::Civil;
		$respeto = $this->gestorModoRespeto->estadoActual();

		self::$datosActuales = array(
			'modoOperacion'          => $modo,
			'regimenResponsabilidad' => $regimen,
			'modoRespetoActivo'      => $respeto->activo,
		);
	}

	public function servirPlantilla( string $plantilla ): string {
		if ( null === self::$datosActuales ) {
			return $plantilla;
		}

		return PLUMA_ENGINE_DIR . 'src/Seo/templates/pagina-metodologia.php';
	}

	/**
	 * @return array{modoOperacion: ModoOperacion, regimenResponsabilidad: RegimenResponsabilidad, modoRespetoActivo: bool}|null
	 */
	public static function datosParaPlantilla(): ?array {
		return self::$datosActuales;
	}
}
