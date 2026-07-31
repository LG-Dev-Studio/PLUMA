<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use DateTimeImmutable;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\Especialidad;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * `Periodista::dominioDe()` (Paso 2 del Algoritmo de Decisión Editorial):
 * match exacto normalizado, comodín "cubre todos los temas"
 * (`Especialidad::VERTICAL_COMODIN`), y la precedencia entre ambos.
 *
 * @covers \Pluma\Redaccion\Periodista
 */
final class PeriodistaTest extends CasoDePruebaUnitario {

	private function periodista( array $especialidades ): Periodista {
		$diales   = new Diales( 60, 40, 20, 60, 50, 50, 60, 50 );
		$reglas   = new ReglasConducta( 'línea', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 1, 5, $diales, $reglas, $matriz, new DateTimeImmutable( '2026-07-29T12:00:00+00:00' ) );

		return new Periodista(
			5,
			'Periodista de prueba',
			null,
			'biografía',
			RolPeriodista::Analista,
			$especialidades,
			EstadoPeriodista::Activo,
			$conducta,
			new DateTimeImmutable( '2026-07-29T12:00:00+00:00' ),
			new DateTimeImmutable( '2026-07-29T12:00:00+00:00' )
		);
	}

	public function test_sin_especialidades_devuelve_cero(): void {
		self::assertSame( 0, $this->periodista( array() )->dominioDe( 'economia' ) );
	}

	public function test_match_exacto_devuelve_el_nivel_declarado(): void {
		$periodista = $this->periodista( array( new Especialidad( 'economia', 5 ) ) );

		self::assertSame( 5, $periodista->dominioDe( 'economia' ) );
	}

	public function test_sin_match_ni_comodin_devuelve_cero(): void {
		$periodista = $this->periodista( array( new Especialidad( 'economia', 5 ) ) );

		self::assertSame( 0, $periodista->dominioDe( 'deportes' ) );
	}

	public function test_normaliza_mayusculas_minusculas_y_espacios_antes_de_comparar(): void {
		$periodista = $this->periodista( array( new Especialidad( '  Economía  ', 4 ) ) );

		self::assertSame( 4, $periodista->dominioDe( 'economía' ) );
	}

	/**
	 * `PLUMA-E9-21`: el LLM devuelve el tema con tildes ("Economía"), la
	 * especialidad declarada por el editor puede no llevarlas — el folding
	 * de diacríticos (`Pluma\Idioma\PlegadorDiacriticos`) debe hacerlos calzar.
	 */
	public function test_normaliza_diacriticos_antes_de_comparar(): void {
		$periodista = $this->periodista( array( new Especialidad( 'economia', 4 ) ) );

		self::assertSame( 4, $periodista->dominioDe( 'Economía' ) );
	}

	public function test_el_comodin_responde_cuando_no_hay_match_exacto(): void {
		$periodista = $this->periodista( array( new Especialidad( Especialidad::VERTICAL_COMODIN, 3 ) ) );

		self::assertSame( 3, $periodista->dominioDe( 'un tema nunca visto antes' ) );
	}

	/**
	 * Un periodista "generalista, pero especialmente fuerte en economía":
	 * el match exacto de un vertical declarado siempre gana sobre el
	 * comodín, aunque el comodín tenga un nivel de dominio más alto.
	 */
	public function test_el_match_exacto_gana_sobre_el_comodin_aunque_el_comodin_sea_mas_alto(): void {
		$periodista = $this->periodista(
			array(
				new Especialidad( 'economia', 2 ),
				new Especialidad( Especialidad::VERTICAL_COMODIN, 5 ),
			)
		);

		self::assertSame( 2, $periodista->dominioDe( 'economia' ) );
	}

	public function test_el_comodin_sigue_respondiendo_para_temas_no_declarados_junto_a_especialidades_reales(): void {
		$periodista = $this->periodista(
			array(
				new Especialidad( 'economia', 2 ),
				new Especialidad( Especialidad::VERTICAL_COMODIN, 5 ),
			)
		);

		self::assertSame( 5, $periodista->dominioDe( 'deportes' ) );
	}
}
