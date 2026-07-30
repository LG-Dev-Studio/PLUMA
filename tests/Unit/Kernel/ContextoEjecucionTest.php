<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Kernel;

use Pluma\Kernel\ContextoEjecucion;
use Pluma\Proveedores\OrigenLlamada;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Kernel\ContextoEjecucion
 */
final class ContextoEjecucionTest extends CasoDePruebaUnitario {

	public function test_el_origen_por_defecto_es_visitante_si_nadie_declaro_nada(): void {
		// NCP-1 (`ADR 0010`): un camino de ejecución que nadie declaró se
		// cuenta como el peor caso — nunca se subestima la exposición.
		self::assertSame( OrigenLlamada::Visitante, ( new ContextoEjecucion() )->obtener() );
	}

	public function test_declarar_cambia_el_origen_devuelto(): void {
		$contexto = new ContextoEjecucion();
		$contexto->declarar( OrigenLlamada::Cron );

		self::assertSame( OrigenLlamada::Cron, $contexto->obtener() );
	}

	public function test_una_segunda_declaracion_sobrescribe_la_anterior(): void {
		$contexto = new ContextoEjecucion();
		$contexto->declarar( OrigenLlamada::Panel );
		$contexto->declarar( OrigenLlamada::Cron );

		self::assertSame( OrigenLlamada::Cron, $contexto->obtener() );
	}
}
