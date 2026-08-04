<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Dobles;

use Pluma\Proveedores\EtiquetaNli;
use Pluma\Proveedores\NliInterface;
use Pluma\Proveedores\ResultadoNli;

/**
 * Doble de `NliInterface` para tests Unit: por defecto devuelve `Neutral`
 * con puntuación 1.0 para cualquier par (ningún test que no se ocupe de
 * contradicción ve una alerta inesperada). Acepta una función de mapeo
 * propia para los tests que sí necesitan un veredicto concreto.
 */
final class NliFalso implements NliInterface {

	/**
	 * @param (callable(string, string): list<ResultadoNli>)|null $mapeo
	 */
	public function __construct( private readonly mixed $mapeo = null ) {
	}

	public function inferir( string $premisa, string $hipotesis ): array {
		if ( null !== $this->mapeo ) {
			return ( $this->mapeo )( $premisa, $hipotesis );
		}

		return array( new ResultadoNli( EtiquetaNli::Neutral, 1.0 ) );
	}
}
