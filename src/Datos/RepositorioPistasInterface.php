<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Publicacion\EstadoPista;
use Pluma\Publicacion\Pista;

/**
 * Contrato del repositorio del buzón de pistas (Nivel Cuatro X.3).
 */
interface RepositorioPistasInterface {

	public function crear( int $historiaId, string $contenido, ?string $contactoEmail, DateTimeImmutable $ahora ): int;

	public function obtenerPorId( int $id ): ?Pista;

	/**
	 * @return list<Pista>
	 */
	public function obtenerPorEstado( EstadoPista $estado, int $limite ): array;

	public function actualizarEstado( int $id, EstadoPista $estado ): bool;
}
