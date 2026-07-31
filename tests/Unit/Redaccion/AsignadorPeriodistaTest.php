<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Redaccion\AsignadorPeriodista;
use Pluma\Redaccion\ClasificacionNoticia;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\Especialidad;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\NovedadNoticia;
use Pluma\Redaccion\DecisionEditorialException;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\NingunPeriodistaIdoneoException;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\AzarFijo;

/**
 * Paso 2 del Algoritmo de Decisión Editorial (Libro Cap. 5.5).
 *
 * @covers \Pluma\Redaccion\AsignadorPeriodista
 */
final class AsignadorPeriodistaTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( false );
	}

	private function periodista( int $id, string $vertical, int $dominio, string $lineaEditorial ): Periodista {
		$diales   = new Diales( 50, 50, 50, 50, 50, 50, 50, 50 );
		$reglas   = new ReglasConducta( $lineaEditorial, array(), array(), array(), TratamientoLector::Tu, '¿Y tú qué opinas?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( $id, $id, $diales, $reglas, $matriz, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

		return new Periodista(
			$id,
			"Periodista {$id}",
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array( new Especialidad( $vertical, $dominio ) ),
			EstadoPeriodista::Activo,
			$conducta,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);
	}

	private function clasificacion( string $tema, string $polaridad = '' ): ClasificacionNoticia {
		return new ClasificacionNoticia( $tema, 30, $polaridad, NovedadNoticia::Primicia, 50, TipoNoticia::DatoEconomico );
	}

	public function test_lanza_excepcion_si_no_hay_candidatos(): void {
		$this->expectException( DecisionEditorialException::class );

		( new AsignadorPeriodista( new AzarFijo( 0 ) ) )->asignar( array(), $this->clasificacion( 'economia' ), array(), array() );
	}

	public function test_elige_al_periodista_con_mayor_dominio_del_vertical(): void {
		$experto = $this->periodista( 1, 'economia', 5, 'neutral' );
		$novato  = $this->periodista( 2, 'economia', 1, 'neutral' );

		$elegido = ( new AsignadorPeriodista( new AzarFijo( 0 ) ) )->asignar( array( $novato, $experto ), $this->clasificacion( 'economia' ), array(), array() );

		self::assertSame( $experto->id, $elegido->id );
	}

	public function test_un_periodista_sin_la_especialidad_no_gana_frente_a_uno_con_dominio(): void {
		$sinEspecialidad = $this->periodista( 1, 'cultura', 5, 'neutral' );
		$conEspecialidad = $this->periodista( 2, 'economia', 3, 'neutral' );

		$elegido = ( new AsignadorPeriodista( new AzarFijo( 0 ) ) )->asignar(
			array( $sinEspecialidad, $conEspecialidad ),
			$this->clasificacion( 'economia' ),
			array(),
			array()
		);

		self::assertSame( $conEspecialidad->id, $elegido->id );
	}

	public function test_el_balance_de_carga_penaliza_a_quien_ya_tiene_piezas_asignadas_hoy(): void {
		$sobrecargado = $this->periodista( 1, 'economia', 3, 'neutral' );
		$disponible   = $this->periodista( 2, 'economia', 3, 'neutral' );

		$elegido = ( new AsignadorPeriodista( new AzarFijo( 0 ) ) )->asignar(
			array( $sobrecargado, $disponible ),
			$this->clasificacion( 'economia' ),
			array(
				$sobrecargado->id => 5,
				$disponible->id   => 0,
			),
			array()
		);

		self::assertSame( $disponible->id, $elegido->id );
	}

	public function test_el_historial_de_cobertura_favorece_a_quien_ya_siguio_el_tema(): void {
		$conHistorial = $this->periodista( 1, 'economia', 3, 'neutral' );
		$sinHistorial = $this->periodista( 2, 'economia', 3, 'neutral' );

		$elegido = ( new AsignadorPeriodista( new AzarFijo( 0 ) ) )->asignar(
			array( $conHistorial, $sinHistorial ),
			$this->clasificacion( 'economia' ),
			array(),
			array(
				$conHistorial->id => true,
				$sinHistorial->id => false,
			)
		);

		self::assertSame( $conHistorial->id, $elegido->id );
	}

	public function test_la_afinidad_de_linea_editorial_favorece_al_periodista_mas_alineado(): void {
		$alineado    = $this->periodista( 1, 'economia', 3, 'Escéptica del poder corporativo y la inflacion' );
		$desalineado = $this->periodista( 2, 'economia', 3, 'Optimista de la cultura pop y los videojuegos' );

		$elegido = ( new AsignadorPeriodista( new AzarFijo( 0 ) ) )->asignar(
			array( $desalineado, $alineado ),
			$this->clasificacion( 'economia', 'gobierno vs. corporaciones por la inflacion' ),
			array(),
			array()
		);

		self::assertSame( $alineado->id, $elegido->id );
	}

	/**
	 * Nivel Dos C.3: "gana el de mayor puntuación" no distingue entre "gana
	 * porque es bueno" y "gana porque es el menos malo" — sin ningún
	 * candidato sobre el umbral de dominio, no se asigna a nadie.
	 */
	public function test_ningun_candidato_sobre_el_umbral_de_dominio_lanza_excepcion(): void {
		$flojo = $this->periodista( 1, 'cultura', 1, 'neutral' );

		$this->expectException( NingunPeriodistaIdoneoException::class );

		( new AsignadorPeriodista( new AzarFijo( 0 ) ) )->asignar( array( $flojo ), $this->clasificacion( 'economia' ), array(), array() );
	}

	/**
	 * Nivel Dos C.3: aunque uno de los candidatos tenga MÁS dominio relativo
	 * que el resto, si ninguno supera el umbral absoluto, sigue sin haber
	 * periodista idóneo — "gana el menos malo" queda prohibido.
	 */
	public function test_ningun_candidato_sobre_el_umbral_aunque_uno_tenga_mas_dominio_relativo_que_el_resto(): void {
		$elMenosMalo = $this->periodista( 1, 'economia', 1, 'neutral' );
		$peorAun     = $this->periodista( 2, 'economia', 0, 'neutral' );

		try {
			( new AsignadorPeriodista( new AzarFijo( 0 ) ) )->asignar( array( $peorAun, $elMenosMalo ), $this->clasificacion( 'economia' ), array(), array() );
			self::fail( 'Se esperaba NingunPeriodistaIdoneoException.' );
		} catch ( NingunPeriodistaIdoneoException $e ) {
			self::assertSame( 1, $e->mejorDominioEncontrado );
		}
	}

	/**
	 * Nivel Dos C.2, paso 1: dentro del margen de casi-empate, gana el
	 * balance de carga aunque su puntuación cruda sea menor — no el de
	 * mayor puntuación total.
	 */
	public function test_casi_empate_promueve_el_balance_de_carga_sobre_la_puntuacion_cruda(): void {
		$sobrecargado  = $this->periodista( 1, 'economia', 3, 'neutral' ); // dominio 60 → 24; sin piezas hoy → balance 20. Total 44.
		$conMasDominio = $this->periodista( 2, 'economia', 4, 'neutral' ); // dominio 80 → 32; 1 pieza hoy → balance 15. Total 47.

		$elegido = ( new AsignadorPeriodista( new AzarFijo( 0 ) ) )->asignar(
			array( $sobrecargado, $conMasDominio ),
			$this->clasificacion( 'economia' ),
			array(
				$sobrecargado->id  => 0,
				$conMasDominio->id => 1,
			),
			array()
		);

		self::assertSame( $sobrecargado->id, $elegido->id, 'Dentro del margen de casi-empate (diferencia de 3 puntos), debe ganar quien tiene menos carga hoy, no quien tiene la puntuación cruda más alta.' );
	}

	/**
	 * Nivel Dos C.2, paso 2: si el balance de carga también empata, gana
	 * quien ya cubre la historia específica — no el primero del array.
	 */
	public function test_empate_total_lo_resuelve_la_historia_especifica(): void {
		$primero = $this->periodista( 1, 'economia', 3, 'neutral' );
		$segundo = $this->periodista( 2, 'economia', 3, 'neutral' );

		$elegido = ( new AsignadorPeriodista( new AzarFijo( 0 ) ) )->asignar(
			array( $primero, $segundo ),
			$this->clasificacion( 'economia' ),
			array(),
			array(),
			$segundo->id
		);

		self::assertSame( $segundo->id, $elegido->id, 'La historia específica debe ganar aunque no sea el primero del array ni tenga mejor puntuación.' );
	}

	/**
	 * Nivel Dos C.2, paso 3: último recurso, azar con semilla inyectable —
	 * nunca "el primero del array" por defecto (el bug más común).
	 */
	public function test_empate_total_sin_historia_especifica_lo_resuelve_el_azar(): void {
		$uno = $this->periodista( 1, 'economia', 3, 'neutral' );
		$dos = $this->periodista( 2, 'economia', 3, 'neutral' );

		$elegidoConAzarEnUno = ( new AsignadorPeriodista( new AzarFijo( 0 ) ) )->asignar(
			array( $uno, $dos ),
			$this->clasificacion( 'economia' ),
			array(),
			array()
		);

		$elegidoConAzarEnDos = ( new AsignadorPeriodista( new AzarFijo( 1 ) ) )->asignar(
			array( $uno, $dos ),
			$this->clasificacion( 'economia' ),
			array(),
			array()
		);

		self::assertNotSame(
			$elegidoConAzarEnUno->id,
			$elegidoConAzarEnDos->id,
			'El resultado debe depender del azar inyectado, no ser siempre "el primero del array" sin importar la semilla.'
		);
	}
}
