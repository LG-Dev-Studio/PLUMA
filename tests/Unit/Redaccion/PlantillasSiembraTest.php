<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Pluma\Redaccion\Especialidad;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\PlantillaPeriodista;
use Pluma\Redaccion\PlantillasSiembra;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * Libro Cap. 5.8: banco inicial recomendado de 3 periodistas complementarios,
 * más `cronistaFactual()` — plantilla nueva que le da representación real al
 * cuarto caso de `RolPeriodista` (`Cronista`), antes inalcanzable porque
 * `cronistaSatirico()` en realidad usa `RolPeriodista::Satirico`.
 *
 * @covers \Pluma\Redaccion\PlantillasSiembra
 */
final class PlantillasSiembraTest extends CasoDePruebaUnitario {

	public function test_todas_devuelve_las_cuatro_plantillas_de_siembra(): void {
		$plantillas = PlantillasSiembra::todas();

		self::assertCount( 4, $plantillas );

		foreach ( $plantillas as $plantilla ) {
			self::assertInstanceOf( PlantillaPeriodista::class, $plantilla );
			self::assertSame( EstadoPeriodista::Activo, $plantilla->estado );
			// Ninguna plantilla puede colar una fila de Tragedia que permita sátira:
			// la regla de sistema se verifica también en la materia prima de siembra.
			self::assertSame(
				NivelSatiraPermitida::Bloqueada,
				$plantilla->matrizTonos->paraTipo( TipoNoticia::Tragedia )->nivelSatira
			);
		}
	}

	public function test_las_cuatro_plantillas_tienen_nombres_y_diales_distintos(): void {
		$analista   = PlantillasSiembra::analistaDeDatosSobrio();
		$columnista = PlantillasSiembra::columnistaCriticaVehemente();
		$cronista   = PlantillasSiembra::cronistaSatirico();
		$factual    = PlantillasSiembra::cronistaFactual();

		$nombres = array( $analista->nombre, $columnista->nombre, $cronista->nombre, $factual->nombre );
		self::assertSame( $nombres, array_unique( $nombres ) );

		self::assertGreaterThan( $columnista->diales->satira, $cronista->diales->satira );
		self::assertGreaterThan( $cronista->diales->densidadDatos, $analista->diales->densidadDatos );
	}

	/**
	 * Bug real corregido en esta porción: `RolPeriodista::Cronista` no tenía
	 * ninguna plantilla que lo usara — quedaba inalcanzable vía cualquier
	 * plantilla de siembra existente.
	 */
	public function test_las_cuatro_opciones_del_enum_rol_periodista_estan_cubiertas_por_al_menos_una_plantilla(): void {
		$roles = array_map( static fn ( PlantillaPeriodista $p ): RolPeriodista => $p->rol, PlantillasSiembra::todas() );

		foreach ( RolPeriodista::cases() as $caso ) {
			self::assertContains( $caso, $roles, "Ninguna plantilla de siembra usa RolPeriodista::{$caso->name}." );
		}
	}

	/**
	 * `cronistaFactual()` responde literalmente al pedido de "un periodista
	 * que cubra todas las publicaciones": declara el comodín en vez de
	 * verticales concretos.
	 */
	public function test_cronista_factual_declara_el_comodin_de_cobertura_total(): void {
		$factual = PlantillasSiembra::cronistaFactual();

		self::assertCount( 1, $factual->especialidades );
		self::assertSame( Especialidad::VERTICAL_COMODIN, $factual->especialidades[0]->vertical );
		self::assertSame( RolPeriodista::Cronista, $factual->rol );
	}

	public function test_la_columnista_replica_los_diales_de_valentina_del_libro(): void {
		$columnista = PlantillasSiembra::columnistaCriticaVehemente();

		self::assertSame( 80, $columnista->diales->agudezaCritica );
		self::assertSame( 55, $columnista->diales->humor );
		self::assertSame( 40, $columnista->diales->satira );
	}
}
