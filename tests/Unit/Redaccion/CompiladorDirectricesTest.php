<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use DateTimeImmutable;
use Pluma\Redaccion\CompiladorDirectrices;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Redaccion\VocabularioProhibidoGlobal;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * @covers \Pluma\Redaccion\CompiladorDirectrices
 */
final class CompiladorDirectricesTest extends CasoDePruebaUnitario {

	private function periodista( int $satira ): Periodista {
		$diales   = new Diales( 80, 55, $satira, 55, 75, 60, 60, 65 );
		$reglas   = new ReglasConducta(
			'Escéptica del poder.',
			array( 'menores de edad' ),
			array( 'abre con una pregunta retórica', 'cierra con una cifra' ),
			array( 'muletilla propia prohibida' ),
			TratamientoLector::Tu,
			'¿A quién le crees aquí?'
		);
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 1, 1, $diales, $reglas, $matriz, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

		return new Periodista(
			1,
			'Valentina Ruiz',
			null,
			'Economista de formación.',
			RolPeriodista::Columnista,
			array(),
			EstadoPeriodista::Activo,
			$conducta,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);
	}

	public function test_la_satira_bloqueada_por_sistema_se_impone_aunque_el_dial_sea_alto(): void {
		$directrices = ( new CompiladorDirectrices() )->compilar(
			$this->periodista( 90 ),
			Tono::InformativoEmpatico,
			Tono::Analitico,
			NivelSatiraPermitida::Bloqueada
		);

		self::assertStringContainsString( 'SÁTIRA BLOQUEADA POR SISTEMA', $directrices );
	}

	public function test_un_nivel_de_satira_permitido_describe_el_permiso_concreto(): void {
		$directrices = ( new CompiladorDirectrices() )->compilar(
			$this->periodista( 40 ),
			Tono::Humoristico,
			Tono::Opinion,
			NivelSatiraPermitida::PiezaCompleta
		);

		self::assertStringContainsString( 'puedes construir la pieza entera con tono satírico', $directrices );
		self::assertStringNotContainsString( 'BLOQUEADA POR SISTEMA', $directrices );
	}

	public function test_incluye_el_vocabulario_prohibido_propio_y_el_global(): void {
		$directrices = ( new CompiladorDirectrices() )->compilar(
			$this->periodista( 40 ),
			Tono::Analitico,
			Tono::Critico,
			NivelSatiraPermitida::No
		);

		self::assertStringContainsString( 'muletilla propia prohibida', $directrices );
		self::assertStringContainsString( VocabularioProhibidoGlobal::muletillasDeTextoIa()[0], $directrices );
	}

	public function test_incluye_lineas_rojas_y_rasgos_de_voz_y_extension_objetivo(): void {
		$directrices = ( new CompiladorDirectrices() )->compilar(
			$this->periodista( 40 ),
			Tono::Analitico,
			Tono::Critico,
			NivelSatiraPermitida::No
		);

		self::assertStringContainsString( 'menores de edad', $directrices );
		self::assertStringContainsString( 'abre con una pregunta retórica', $directrices );
		self::assertStringContainsString( 'aproximadamente', $directrices );
	}

	/**
	 * Nivel Dos A.2: la regla anti-alucinación es una invariante de sistema,
	 * presente sea cual sea la configuración de diales del periodista.
	 */
	public function test_incluye_la_regla_de_oro_contra_la_alucinacion(): void {
		$directrices = ( new CompiladorDirectrices() )->compilar(
			$this->periodista( 40 ),
			Tono::Analitico,
			Tono::Critico,
			NivelSatiraPermitida::No
		);

		self::assertStringContainsString( 'REGLA DE ORO CONTRA LA ALUCINACIÓN', $directrices );
	}

	/**
	 * Nivel Dos A.3: dial en tramo bajo (< 33) usa la directriz + párrafo
	 * ancla de ese tramo, no el de un tramo alto.
	 */
	public function test_un_dial_en_tramo_bajo_usa_la_directriz_de_ese_tramo(): void {
		$diales     = new Diales( 10, 55, 20, 55, 30, 60, 60, 65 );
		$reglas     = new ReglasConducta( 'Escéptica del poder.', array(), array(), array(), TratamientoLector::Tu, '¿Y tú qué opinas?' );
		$matriz     = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta   = new ConductaVersion( 1, 1, $diales, $reglas, $matriz, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );
		$periodista = new Periodista(
			1,
			'Valentina Ruiz',
			null,
			'Economista de formación.',
			RolPeriodista::Columnista,
			array(),
			EstadoPeriodista::Activo,
			$conducta,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);

		$directrices = ( new CompiladorDirectrices() )->compilar( $periodista, Tono::Analitico, Tono::Critico, NivelSatiraPermitida::No );

		self::assertStringContainsString( 'Relata con neutralidad', $directrices );
		self::assertStringNotContainsString( 'interroga motivos', $directrices );
	}

	/**
	 * Nivel Dos A.3: dial en tramo medio (33-66) usa la directriz intermedia,
	 * distinta de los dos extremos.
	 */
	public function test_un_dial_en_tramo_medio_usa_la_directriz_intermedia(): void {
		$directrices = ( new CompiladorDirectrices() )->compilar(
			$this->periodista( 40 ),
			Tono::Analitico,
			Tono::Critico,
			NivelSatiraPermitida::No
		);

		self::assertStringContainsString( 'Una nota de ironía puntual', $directrices );
	}

	/**
	 * Nivel Dos A.4: humor y agudeza crítica altos a la vez disparan la
	 * directriz de la matriz de combinación (la agudeza ataca argumentos, no
	 * a la persona).
	 */
	public function test_humor_y_agudeza_critica_altos_disparan_la_matriz_de_combinacion(): void {
		$diales     = new Diales( 90, 90, 10, 55, 40, 40, 40, 65 );
		$reglas     = new ReglasConducta( 'Escéptica del poder.', array(), array(), array(), TratamientoLector::Tu, '¿Y tú qué opinas?' );
		$matriz     = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta   = new ConductaVersion( 1, 1, $diales, $reglas, $matriz, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );
		$periodista = new Periodista(
			1,
			'Valentina Ruiz',
			null,
			'Economista de formación.',
			RolPeriodista::Columnista,
			array(),
			EstadoPeriodista::Activo,
			$conducta,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);

		$directrices = ( new CompiladorDirectrices() )->compilar( $periodista, Tono::Analitico, Tono::Critico, NivelSatiraPermitida::No );

		self::assertStringContainsString( 'la agudeza ataca argumentos e incentivos, jamás a la persona', $directrices );
	}

	/**
	 * Nivel Dos A.4: si ninguna combinación de diales cruza sus dos umbrales
	 * a la vez, no se añade ninguna directriz de la matriz.
	 */
	public function test_sin_combinacion_de_diales_activada_no_anade_directrices_de_matriz(): void {
		$directrices = ( new CompiladorDirectrices() )->compilar(
			$this->periodista( 10 ),
			Tono::Analitico,
			Tono::Critico,
			NivelSatiraPermitida::No
		);

		self::assertStringNotContainsString( 'Combinación', $directrices );
	}

	/**
	 * Nivel Tres Q.1: `localeEditorial` por defecto es `'es-ES'` — un
	 * periodista sin locale explícito produce exactamente el mismo texto
	 * que antes de esta porción (el catálogo movido bajo la clave `es-ES`
	 * no cambia una sola palabra).
	 */
	public function test_periodista_sin_locale_explicito_usa_es_es_por_defecto(): void {
		$directrices = ( new CompiladorDirectrices() )->compilar(
			$this->periodista( 40 ),
			Tono::Analitico,
			Tono::Critico,
			NivelSatiraPermitida::No
		);

		self::assertStringContainsString( 'Una nota de ironía puntual', $directrices );
	}

	/**
	 * Nivel Tres Q.1: un locale sin catálogo curado todavía cae al catálogo
	 * de `es-ES` — nunca a una traducción automática ni a un ancla vacía.
	 */
	public function test_locale_sin_catalogo_curado_cae_al_catalogo_es_es(): void {
		$diales     = new Diales( 80, 55, 55, 55, 75, 60, 60, 65 );
		$reglas     = new ReglasConducta( 'Escéptica del poder.', array(), array(), array(), TratamientoLector::Tu, '¿Y tú qué opinas?' );
		$matriz     = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta   = new ConductaVersion( 1, 1, $diales, $reglas, $matriz, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );
		$periodista = new Periodista(
			1,
			'Periodista sin catálogo curado',
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array(),
			EstadoPeriodista::Activo,
			$conducta,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			'fr-FR'
		);

		$directrices = ( new CompiladorDirectrices() )->compilar( $periodista, Tono::Analitico, Tono::Critico, NivelSatiraPermitida::No );

		self::assertStringContainsString( 'Una nota de ironía puntual', $directrices );
	}
}
