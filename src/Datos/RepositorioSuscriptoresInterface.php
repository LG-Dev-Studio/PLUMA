<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Publicacion\CanalSuscripcion;
use Pluma\Publicacion\Suscriptor;
use Pluma\Publicacion\TipoSuscripcion;

/**
 * Contrato del repositorio de suscriptores (Nivel Cuatro W.3).
 */
interface RepositorioSuscriptoresInterface {

	public function crearEmail( TipoSuscripcion $tipo, ?int $referenciaId, ?string $vertical, string $email, string $token, DateTimeImmutable $ahora ): int;

	public function crearPush( TipoSuscripcion $tipo, ?int $referenciaId, ?string $vertical, string $endpoint, string $claveP256dh, string $claveAuth, string $token, DateTimeImmutable $ahora ): int;

	public function obtenerPorToken( string $token ): ?Suscriptor;

	public function confirmar( int $id, DateTimeImmutable $ahora ): bool;

	public function eliminar( int $id ): bool;

	/**
	 * @return list<Suscriptor>
	 */
	public function obtenerConfirmadosPorObjetivo( CanalSuscripcion $canal, TipoSuscripcion $tipo, ?int $referenciaId, ?string $vertical ): array;

	/**
	 * @return list<Suscriptor>
	 */
	public function listar( int $limite ): array;

	/**
	 * RGPD (`PLUMA-EV-2`): todas las filas de un email, para exportación o
	 * borrado a petición del lector.
	 *
	 * @return list<Suscriptor>
	 */
	public function obtenerPorEmail( string $email ): array;

	public function eliminarPorEmail( string $email ): int;
}
