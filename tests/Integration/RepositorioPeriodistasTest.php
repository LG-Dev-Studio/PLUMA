<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use DateTimeImmutable;
use Pluma\Datos\RepositorioPeriodistas;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\Especialidad;
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

	public function test_actualizar_identidad_cambia_nombre_rol_y_especialidades_sin_tocar_la_conducta(): void {
		global $wpdb;
		$repo  = new RepositorioPeriodistas( $wpdb );
		$reloj = new DateTimeImmutable();

		$id = $repo->crear(
			'Nombre original ' . uniqid(),
			null,
			'Bio original.',
			RolPeriodista::Analista,
			array( new Especialidad( 'economia', 3 ) ),
			EstadoPeriodista::Activo,
			$this->diales(),
			$this->reglas(),
			$this->matriz(),
			$reloj
		);

		$antes = $repo->obtenerPorId( $id );
		self::assertNotNull( $antes );
		$versionConductaAntes = $antes->conductaActual->id;

		$resultado = $repo->actualizarIdentidad(
			$id,
			'Nombre editado',
			'https://example.com/avatar.png',
			'Bio editada.',
			RolPeriodista::Cronista,
			array( new Especialidad( Especialidad::VERTICAL_COMODIN, 4 ) ),
			new DateTimeImmutable( '+1 minute' )
		);

		self::assertTrue( $resultado );

		$despues = $repo->obtenerPorId( $id );
		self::assertNotNull( $despues );
		self::assertSame( 'Nombre editado', $despues->nombre );
		self::assertSame( 'https://example.com/avatar.png', $despues->avatarUrl );
		self::assertSame( 'Bio editada.', $despues->biografia );
		self::assertSame( RolPeriodista::Cronista, $despues->rol );
		self::assertCount( 1, $despues->especialidades );
		self::assertSame( Especialidad::VERTICAL_COMODIN, $despues->especialidades[0]->vertical );
		self::assertSame( 4, $despues->especialidades[0]->nivelDominio );

		// Aislamiento de capas: Identidad se sobrescribió, Conducta no se tocó.
		self::assertSame( $versionConductaAntes, $despues->conductaActual->id );
	}

	public function test_actualizar_identidad_de_un_periodista_ya_en_su_estado_actual_no_se_confunde_con_no_encontrado(): void {
		global $wpdb;
		$repo  = new RepositorioPeriodistas( $wpdb );
		$reloj = new DateTimeImmutable();

		$id = $repo->crear(
			'Sin cambios ' . uniqid(),
			null,
			'Bio.',
			RolPeriodista::Analista,
			array( new Especialidad( 'economia', 3 ) ),
			EstadoPeriodista::Activo,
			$this->diales(),
			$this->reglas(),
			$this->matriz(),
			$reloj
		);

		$periodista = $repo->obtenerPorId( $id );
		self::assertNotNull( $periodista );

		// Mismos valores exactos: un UPDATE que no cambia nada reporta 0 filas
		// afectadas en MySQL — no debe confundirse con "no encontrado" (mismo
		// defecto real ya corregido en RepositorioTendencias esta sesión).
		$resultado = $repo->actualizarIdentidad(
			$id,
			$periodista->nombre,
			$periodista->avatarUrl,
			$periodista->biografia,
			$periodista->rol,
			$periodista->especialidades,
			new DateTimeImmutable( '+1 minute' )
		);

		self::assertTrue( $resultado );
	}

	public function test_actualizar_identidad_de_un_periodista_inexistente_devuelve_false(): void {
		global $wpdb;
		$repo = new RepositorioPeriodistas( $wpdb );

		$resultado = $repo->actualizarIdentidad(
			999999,
			'No existe',
			null,
			'Bio.',
			RolPeriodista::Analista,
			array(),
			new DateTimeImmutable()
		);

		self::assertFalse( $resultado );
	}
}
