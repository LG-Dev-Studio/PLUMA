<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Dobles;

use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\RespuestaLenguaje;

/**
 * Doble de `LenguajeInterface` que devuelve un contenido distinto en cada
 * llamada sucesiva (en el orden dado) — para tests de orquestadores como
 * `DecisionEditorial` que hacen varias llamadas encadenadas al proveedor con
 * formas de respuesta distintas cada vez.
 */
final class ProveedorLenguajeSecuencial implements LenguajeInterface {

	private int $indice = 0;

	/** @var list<PeticionLenguaje> */
	public array $peticiones = array();

	/**
	 * @param list<string> $contenidos
	 */
	public function __construct( private readonly array $contenidos ) {
	}

	public function completar( PeticionLenguaje $peticion ): RespuestaLenguaje {
		$this->peticiones[] = $peticion;

		$contenido = $this->contenidos[ $this->indice ] ?? end( $this->contenidos );
		++$this->indice;

		return new RespuestaLenguaje( (string) $contenido, 100, 50, 0.001, 'falso', 'modelo-falso', 10, false );
	}

	public function tieneCredenciales(): bool {
		return true;
	}

	public function familiaDe( string $modelo ): string {
		return explode( '/', $modelo, 2 )[0];
	}
}
