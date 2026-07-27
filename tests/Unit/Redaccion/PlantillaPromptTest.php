<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Pluma\Proveedores\PropositoLenguaje;
use Pluma\Redaccion\PlantillaPrompt;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Redaccion\PlantillaPrompt
 */
final class PlantillaPromptTest extends CasoDePruebaUnitario {

	public function test_ensamblar_concatena_fijas_y_parametrizadas_en_ese_orden(): void {
		$plantilla = new PlantillaPrompt(
			PropositoLenguaje::Redactar,
			1,
			array( 'fija uno', 'fija dos' ),
			array( 'parametrizada uno' )
		);

		self::assertSame( "fija uno\n\nfija dos\n\nparametrizada uno", $plantilla->ensamblar() );
	}

	public function test_expone_proposito_y_version(): void {
		$plantilla = new PlantillaPrompt( PropositoLenguaje::Redactar, 1, array(), array() );

		self::assertSame( PropositoLenguaje::Redactar, $plantilla->proposito );
		self::assertSame( 1, $plantilla->version );
	}
}
