<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use Pluma\Datos\RepositorioSuscriptoresInterface;
use Pluma\Kernel\RelojInterface;

/**
 * Nivel Cuatro W.3 — suscripciones de precisión: "un periodista, una
 * Historia, un vertical, o alertas de última hora... todo opt-in explícito,
 * exportable, del cliente". Mecánica mínima de RGPD (`PLUMA-EV-2`): doble
 * opt-in por email (confirmación antes de recibir nada), baja de un clic,
 * exportación y borrado a petición — la revisión legal completa del
 * tratamiento de datos sigue pendiente y registrada aparte, esto solo cubre
 * lo técnicamente exigible sin inventar un dictamen legal.
 *
 * Las suscripciones de canal `push` se confirman al crearse: el propio
 * permiso del navegador (Push API) ya es el opt-in explícito — no hay un
 * segundo correo de confirmación que enviar para ese canal.
 */
final class GestorSuscripciones {

	public function __construct(
		private readonly RepositorioSuscriptoresInterface $suscriptores,
		private readonly RelojInterface $reloj,
	) {
	}

	/**
	 * @return array{id: int, token: string}
	 */
	public function suscribirEmail( TipoSuscripcion $tipo, ?int $referenciaId, ?string $vertical, string $email ): array {
		$token = $this->generarToken();
		$id    = $this->suscriptores->crearEmail( $tipo, $referenciaId, $vertical, $email, $token, $this->reloj->ahora() );

		return array(
			'id'    => $id,
			'token' => $token,
		);
	}

	public function suscribirPush( TipoSuscripcion $tipo, ?int $referenciaId, ?string $vertical, string $endpoint, string $claveP256dh, string $claveAuth ): int {
		$token = $this->generarToken();
		$ahora = $this->reloj->ahora();
		$id    = $this->suscriptores->crearPush( $tipo, $referenciaId, $vertical, $endpoint, $claveP256dh, $claveAuth, $token, $ahora );

		$this->suscriptores->confirmar( $id, $ahora );

		return $id;
	}

	/**
	 * @throws SuscripcionNoEncontradaException
	 */
	public function confirmar( string $token ): void {
		$suscriptor = $this->suscriptores->obtenerPorToken( $token );

		if ( null === $suscriptor || $suscriptor->confirmado ) {
			throw new SuscripcionNoEncontradaException();
		}

		$this->suscriptores->confirmar( $suscriptor->id, $this->reloj->ahora() );
	}

	/**
	 * @throws SuscripcionNoEncontradaException
	 */
	public function darDeBaja( string $token ): void {
		$suscriptor = $this->suscriptores->obtenerPorToken( $token );

		if ( null === $suscriptor ) {
			throw new SuscripcionNoEncontradaException();
		}

		$this->suscriptores->eliminar( $suscriptor->id );
	}

	/**
	 * @return list<Suscriptor>
	 */
	public function listar( int $limite = 100 ): array {
		return $this->suscriptores->listar( $limite );
	}

	/**
	 * RGPD, autoservicio (`PLUMA-EV-2`): todas las suscripciones de un email.
	 *
	 * @return list<Suscriptor>
	 */
	public function exportarPorEmail( string $email ): array {
		return $this->suscriptores->obtenerPorEmail( $email );
	}

	/**
	 * RGPD, autoservicio (`PLUMA-EV-2`): borra todas las suscripciones de un
	 * email.
	 */
	public function borrarPorEmail( string $email ): int {
		return $this->suscriptores->eliminarPorEmail( $email );
	}

	private function generarToken(): string {
		return bin2hex( random_bytes( 32 ) );
	}
}
