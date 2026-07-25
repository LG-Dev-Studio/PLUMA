<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\TipoAprobacion;

interface RepositorioAuditoriaInterface {

	public function registrar(
		int $piezaId,
		?EstadoPieza $estadoAnterior,
		EstadoPieza $estadoNuevo,
		string $actor,
		string $motivo,
		DateTimeImmutable $ahora,
		?TipoAprobacion $tipoAprobacion = null
	): void;
}
