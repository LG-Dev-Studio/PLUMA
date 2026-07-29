<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use Pluma\Datos\RepositorioSuscriptoresInterface;
use Pluma\Proveedores\PushWebInterface;

/**
 * Nivel Cuatro W.3 — despacho a suscriptores confirmados de un objetivo
 * (periodista/historia/vertical/alerta urgente), por los dos canales:
 * email transaccional (`wp_mail()` directo, mismo patrón que
 * `Pluma\Admin\NotificadorRevision`) y push web (`PushWebInterface`, mejor
 * esfuerzo — un fallo de un suscriptor push nunca detiene el resto del
 * envío). Las suscripciones push que el navegador ya dio de baja
 * (`suscripcionExpirada`) se eliminan aquí mismo, en vez de seguir
 * intentando enviarles para siempre.
 */
final class NotificadorSuscripciones {

	public function __construct(
		private readonly RepositorioSuscriptoresInterface $suscriptores,
		private readonly PushWebInterface $push,
	) {
	}

	public function enviarConfirmacion( string $email, string $token ): void {
		$enlace = rest_url( 'pluma/v1/suscripciones/confirmar/' . $token );

		$asunto = __( 'Confirma tu suscripción', 'pluma-engine' );
		// translators: %s es el enlace de confirmación de un solo uso.
		$cuerpo = sprintf( __( 'Confirma tu suscripción haciendo clic en este enlace: %s', 'pluma-engine' ), $enlace );

		wp_mail( $email, $asunto, $cuerpo );
	}

	/**
	 * @return array{email: int, push: int}
	 */
	public function notificarObjetivo( TipoSuscripcion $tipo, ?int $referenciaId, ?string $vertical, string $titulo, string $cuerpo, ?string $url ): array {
		$enviadosEmail = 0;

		foreach ( $this->suscriptores->obtenerConfirmadosPorObjetivo( CanalSuscripcion::Email, $tipo, $referenciaId, $vertical ) as $suscriptor ) {
			if ( null === $suscriptor->email ) {
				continue;
			}

			$cuerpoConBaja = $cuerpo . "\n\n" . sprintf(
				// translators: %s es el enlace de baja de un clic.
				__( 'Darte de baja: %s', 'pluma-engine' ),
				rest_url( 'pluma/v1/suscripciones/baja/' . $suscriptor->token )
			);

			if ( wp_mail( $suscriptor->email, $titulo, $cuerpoConBaja ) ) {
				++$enviadosEmail;
			}
		}

		$enviadosPush = 0;

		foreach ( $this->suscriptores->obtenerConfirmadosPorObjetivo( CanalSuscripcion::Push, $tipo, $referenciaId, $vertical ) as $suscriptor ) {
			$resultado = $this->push->enviar( $suscriptor, $titulo, $cuerpo, $url );

			if ( $resultado->exito ) {
				++$enviadosPush;
			}

			if ( $resultado->suscripcionExpirada ) {
				$this->suscriptores->eliminar( $suscriptor->id );
			}
		}

		return array(
			'email' => $enviadosEmail,
			'push'  => $enviadosPush,
		);
	}
}
