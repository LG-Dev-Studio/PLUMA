<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Publicacion\DerivadoSocial;
use Pluma\Publicacion\EstadoDerivadoSocial;

/**
 * Contrato del repositorio de derivados sociales (Nivel Cuatro W.2).
 */
interface RepositorioDerivadosSocialesInterface {

	public function crear( int $piezaId, string $extractoSocial, string $titularDiscover, DateTimeImmutable $ahora ): int;

	public function obtenerPorId( int $id ): ?DerivadoSocial;

	/**
	 * @return list<DerivadoSocial>
	 */
	public function obtenerPorEstado( EstadoDerivadoSocial $estado, int $limite ): array;

	public function actualizarEstado( int $id, EstadoDerivadoSocial $estado ): bool;
}
