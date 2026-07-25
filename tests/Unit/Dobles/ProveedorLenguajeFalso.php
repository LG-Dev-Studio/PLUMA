<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Dobles;

use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\RespuestaLenguaje;

/**
 * Doble de `LenguajeInterface` para tests Unit de la Sala de Redacción: no
 * hace ninguna llamada real, solo devuelve `$contenido` prefijado y expone la
 * última `PeticionLenguaje` recibida para que el test verifique qué se envió
 * (p. ej. que el material incluye la memoria antes de tesis).
 */
final class ProveedorLenguajeFalso implements LenguajeInterface {

	public ?PeticionLenguaje $ultimaPeticion = null;

	public function __construct( private readonly string $contenido, private readonly bool $truncada = false ) {
	}

	public function completar( PeticionLenguaje $peticion ): RespuestaLenguaje {
		$this->ultimaPeticion = $peticion;

		return new RespuestaLenguaje( $this->contenido, 100, 50, 0.001, 'falso', 'modelo-falso', 10, $this->truncada );
	}

	public function familiaDe( string $modelo ): string {
		return explode( '/', $modelo, 2 )[0];
	}
}
