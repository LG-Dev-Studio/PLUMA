<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use Pluma\Datos\RepositorioDerivadosSocialesInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Datos\RepositorioTendenciasInterface;
use Pluma\Kernel\RelojInterface;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Redaccion\DecisionEditorialException;
use Pluma\Redaccion\GeneradorDerivadoSocial;

/**
 * Nivel Cuatro W.2 (derivados por canal) + parte de W.3 (alertas de última
 * hora): se engancha a `pluma/pieza_publicada` — el mismo evento que
 * `Transicionador` ya dispara en toda transición (CLAUDE.md § Ley de
 * Arquitectura), cero acoplamiento directo con el Orquestador. Mejor
 * esfuerzo: si el derivado falla (proveedor caído, respuesta sin texto),
 * la Pieza ya se publicó igual — nunca se revierte ni se bloquea nada por
 * esto.
 */
final class GestorDerivadosSociales {

	public const OPCION_UMBRAL_GRAVEDAD_ALERTA_URGENTE   = 'pluma_alerta_urgente_gravedad_minima';
	private const UMBRAL_GRAVEDAD_ALERTA_URGENTE_DEFECTO = 70;
	private const PALABRAS_EXTRACTO_FUENTE               = 60;

	public function __construct(
		private readonly RepositorioPiezasInterface $piezas,
		private readonly RepositorioPeriodistasInterface $periodistas,
		private readonly RepositorioTendenciasInterface $tendencias,
		private readonly RepositorioDerivadosSocialesInterface $derivados,
		private readonly GeneradorDerivadoSocial $generador,
		private readonly NotificadorSuscripciones $notificador,
		private readonly RelojInterface $reloj,
	) {
	}

	public function registrar(): void {
		add_action( 'pluma/pieza_publicada', array( $this, 'procesarPublicacion' ), 10, 1 );
	}

	public function procesarPublicacion( int $piezaId ): void {
		$pieza = $this->piezas->obtenerPorId( $piezaId );

		if ( null === $pieza || null === $pieza->postId || null === $pieza->periodistaId ) {
			return;
		}

		$periodista = $this->periodistas->obtenerPorId( $pieza->periodistaId );

		if ( null === $periodista ) {
			return;
		}

		$post = get_post( $pieza->postId );

		if ( null === $post ) {
			return;
		}

		$extractoFuente = wp_trim_words( wp_strip_all_tags( $post->post_content ), self::PALABRAS_EXTRACTO_FUENTE );

		try {
			$derivado = $this->generador->generar( $periodista, $post->post_title, $extractoFuente );
		} catch ( ProveedorLenguajeException | DecisionEditorialException ) {
			return;
		}

		$this->derivados->crear( $piezaId, $derivado['extractoSocial'], $derivado['titularDiscover'], $this->reloj->ahora() );

		$this->notificarSiEsAlertaUrgente( $pieza->tendenciaId, $post->post_title, $derivado['extractoSocial'], (string) get_permalink( $post ) );
	}

	private function notificarSiEsAlertaUrgente( int $tendenciaId, string $titulo, string $extracto, string $url ): void {
		$tendencia = $this->tendencias->obtenerPorId( $tendenciaId );
		$gravedad  = is_array( $tendencia ) ? ( $tendencia['gravedad'] ?? null ) : null;

		if ( ! is_numeric( $gravedad ) || (int) $gravedad < $this->umbralAlertaUrgente() ) {
			return;
		}

		$this->notificador->notificarObjetivo( TipoSuscripcion::AlertaUrgente, null, null, $titulo, $extracto, $url );
	}

	private function umbralAlertaUrgente(): int {
		$valor = get_option( self::OPCION_UMBRAL_GRAVEDAD_ALERTA_URGENTE, self::UMBRAL_GRAVEDAD_ALERTA_URGENTE_DEFECTO );

		return is_numeric( $valor ) ? (int) $valor : self::UMBRAL_GRAVEDAD_ALERTA_URGENTE_DEFECTO;
	}
}
