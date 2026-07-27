<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Pipeline\EstadoPieza;

/**
 * Nivel Dos C.3: notificación por correo cuando ninguna periodista del
 * banco supera el umbral de dominio mínimo para un vertical — mismo patrón
 * que {@see NotificadorRevision}, enganchado al evento genérico que
 * `Transicionador` ya dispara en toda transición.
 */
final class NotificadorSinPeriodistaIdoneo {

	public function registrar(): void {
		add_action( 'pluma/pieza_sin_periodista_idoneo', array( $this, 'notificar' ), 10, 3 );
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- la firma debe calzar con la de `do_action('pluma/pieza_sin_periodista_idoneo', ...)`; el estado anterior no aporta nada al correo.
	public function notificar( int $piezaId, EstadoPieza $estadoAnterior, string $motivo ): void {
		$destinatario = get_option( 'admin_email' );

		if ( ! is_string( $destinatario ) || '' === $destinatario ) {
			return;
		}

		$asunto = sprintf(
			/* translators: %d: id de la Pieza */
			__( 'PLUMA: la pieza #%d no encontró un periodista idóneo', 'pluma-engine' ),
			$piezaId
		);

		$cuerpo = sprintf(
			/* translators: 1: id de la Pieza, 2: motivo */
			__( "La pieza #%1\$d quedó en espera: ningún periodista del banco supera el umbral de dominio para su vertical.\n\nMotivo: %2\$s\n\nSugerencia: crea o ajusta un periodista para este vertical, o clona uno existente con las especialidades ampliadas.", 'pluma-engine' ),
			$piezaId,
			$motivo
		);

		wp_mail( $destinatario, $asunto, $cuerpo );
	}
}
