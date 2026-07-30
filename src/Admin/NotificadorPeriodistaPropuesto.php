<?php

declare(strict_types=1);

namespace Pluma\Admin;

/**
 * Trabajo posterior a la Etapa 9 (creación automática de periodistas):
 * alerta por correo cuando el sistema propone un periodista nuevo — "cero
 * sorpresas", el propietario se entera en el momento en que arranca la
 * ventana de veto, no solo si entra al panel a mirar. Se engancha al evento
 * `pluma/periodista_propuesto_automaticamente`, mismo patrón que
 * `NotificadorProveedorCaido`/`NotificadorRevision`.
 */
final class NotificadorPeriodistaPropuesto {

	public function registrar(): void {
		add_action( 'pluma/periodista_propuesto_automaticamente', array( $this, 'notificar' ), 10, 2 );
	}

	public function notificar( int $periodistaId, string $vertical ): void {
		$destinatario = get_option( 'admin_email' );

		if ( ! is_string( $destinatario ) || '' === $destinatario ) {
			return;
		}

		$asunto = __( 'PLUMA: se propuso un periodista nuevo', 'pluma-engine' );

		$cuerpo = sprintf(
			/* translators: 1: vertical que cubriría el nuevo periodista, 2: id del periodista propuesto */
			__( "El motor detectó suficientes noticias sin cobertura sobre \"%1\$s\" y propuso un periodista nuevo (#%2\$d) para cubrirlas.\n\nRevisa la propuesta en el Banco de Periodistas antes de que expire su ventana de veto — puedes aprobarla ahora, editarla, o descartarla.", 'pluma-engine' ),
			$vertical,
			$periodistaId
		);

		wp_mail( $destinatario, $asunto, $cuerpo );
	}
}
