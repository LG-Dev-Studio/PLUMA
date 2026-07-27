<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Pluma\Redaccion\PuntoCorrector;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Redaccion\PuntoCorrector
 */
final class PuntoCorrectorTest extends CasoDePruebaUnitario {

	/**
	 * Nivel Dos A.6: el orden de reparación es distinto del orden de
	 * declaración del enum (que es el orden de verificación del Corrector).
	 */
	public function test_orden_de_reparacion_difiere_del_orden_de_declaracion(): void {
		self::assertSame(
			array(
				PuntoCorrector::Hechos,
				PuntoCorrector::MatrizYLineasRojas,
				PuntoCorrector::SolapamientoNGrama,
				PuntoCorrector::ProporcionInterpretativa,
				PuntoCorrector::Voz,
				PuntoCorrector::TitularHonesto,
			),
			PuntoCorrector::ordenDeReparacion()
		);

		self::assertNotSame( PuntoCorrector::cases(), PuntoCorrector::ordenDeReparacion() );
	}
}
