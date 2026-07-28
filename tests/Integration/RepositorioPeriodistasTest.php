<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use DateTimeImmutable;
use Pluma\Datos\RepositorioPeriodistas;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use WP_UnitTestCase;

/**
 * Repositorio `pluma_periodistas` contra la tabla real — Nivel Tres Q.1
 * (Etapa 8, Porción 10): `locale_editorial` persiste y se recupera
 * correctamente, con default `'es-ES'` cuando no se especifica.
 *
 * @covers \Pluma\Datos\RepositorioPeriodistas
 */
final class RepositorioPeriodistasTest extends WP_UnitTestCase {

	private function diales(): Diales {
		return new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
	}

	private function reglas(): ReglasConducta {
		return new ReglasConducta( 'linea', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
	}

	private function matriz(): MatrizTonos {
		return MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
	}

	public function test_crear_persiste_el_locale_editorial_explicito(): void {
		global $wpdb;
		$repo = new RepositorioPeriodistas( $wpdb );

		$id = $repo->crear(
			'Periodista con locale ' . uniqid(),
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array(),
			EstadoPeriodista::Activo,
			$this->diales(),
			$this->reglas(),
			$this->matriz(),
			new DateTimeImmutable(),
			'fr-FR'
		);

		$periodista = $repo->obtenerPorId( $id );

		self::assertNotNull( $periodista );
		self::assertSame( 'fr-FR', $periodista->localeEditorial );
	}

	public function test_crear_sin_locale_editorial_usa_es_es_por_defecto(): void {
		global $wpdb;
		$repo = new RepositorioPeriodistas( $wpdb );

		$id = $repo->crear(
			'Periodista sin locale ' . uniqid(),
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array(),
			EstadoPeriodista::Activo,
			$this->diales(),
			$this->reglas(),
			$this->matriz(),
			new DateTimeImmutable()
		);

		$periodista = $repo->obtenerPorId( $id );

		self::assertNotNull( $periodista );
		self::assertSame( 'es-ES', $periodista->localeEditorial );
	}
}
