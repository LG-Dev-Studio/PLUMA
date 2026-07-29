<?php

declare(strict_types=1);

namespace Pluma\Admin;

/**
 * Alerta por correo cuando el circuit breaker de un proveedor externo se
 * abre (`PLUMA-E3-7`/`PLUMA-E8-8`: "alertas tras 3 fallos"). Se engancha al
 * evento `pluma/proveedor_circuito_abierto`, disparado UNA sola vez por cada
 * transición cerrado→abierto (nunca en cada fallo repetido mientras el
 * circuito ya está abierto) — mismo patrón de eventos que `Transicionador`
 * ya usa para las Piezas, aplicado aquí a la resiliencia de `Pluma\Proveedores`.
 */
final class NotificadorProveedorCaido {

	public function registrar(): void {
		add_action( 'pluma/proveedor_circuito_abierto', array( $this, 'notificar' ), 10, 1 );
	}

	public function notificar( string $identificadorProveedor ): void {
		$destinatario = get_option( 'admin_email' );

		if ( ! is_string( $destinatario ) || '' === $destinatario ) {
			return;
		}

		$asunto = __( 'PLUMA: un proveedor externo dejó de responder', 'pluma-engine' );

		$cuerpo = sprintf(
			/* translators: %s: identificador del proveedor cuyo circuito se abrió */
			__( "El circuito de resiliencia de \"%s\" se abrió tras fallos consecutivos — PLUMA dejará de intentar contactarlo hasta que el enfriamiento expire.\n\nRevisa la Sala de Máquinas para ver el estado de las APIs conectadas.", 'pluma-engine' ),
			$identificadorProveedor
		);

		wp_mail( $destinatario, $asunto, $cuerpo );
	}
}
