<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Proveedores\EtiquetaNli;
use Pluma\Proveedores\ResultadoNli;
use Pluma\Redaccion\CandidatoTesis;
use Pluma\Redaccion\ClasificacionNoticia;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\CorrectorInterno;
use Pluma\Redaccion\DecisionEditorialException;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EsqueletoPieza;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\FichaDecisionEditorial;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\NovedadNoticia;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\PuntoCorrector;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\SegmentadorUnidadesFactuales;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Redaccion\VerificadorContradiccionNli;
use Pluma\Redaccion\VerificadorNGramas;
use Pluma\Redaccion\VerificadorTrazabilidadDeterminista;
use Pluma\Redaccion\VerificadorVoz;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\EmbeddingsFalso;
use Pluma\Tests\Unit\Dobles\NliFalso;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;

/**
 * Lista de 6 puntos del Corrector Interno (Libro Cap. 5.6).
 *
 * @covers \Pluma\Redaccion\CorrectorInterno
 */
final class CorrectorInternoTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'AUTH_KEY' ) ) {
			define( 'AUTH_KEY', 'clave-app-de-prueba' );
			define( 'SECURE_AUTH_KEY', 'clave-secure-de-prueba' );
		}
	}

	/**
	 * Por defecto el doble de NLI no marca ninguna contradicción (etiqueta
	 * "neutral" como principal).
	 */
	private function verificadorContradiccion( string $etiquetaNliPrincipal = 'neutral' ): VerificadorContradiccionNli {
		$resultados = array( new ResultadoNli( EtiquetaNli::from( $etiquetaNliPrincipal ), 0.9 ) );
		$nli        = new NliFalso( static fn ( string $premisa, string $hipotesis ): array => $resultados );

		return new VerificadorContradiccionNli( $nli, new SegmentadorUnidadesFactuales() );
	}

	private function periodista(): Periodista {
		$diales   = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas   = new ReglasConducta( 'linea', array( 'menores de edad' ), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 1, 1, $diales, $reglas, $matriz, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

		return new Periodista(
			1,
			'Periodista',
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array(),
			EstadoPeriodista::Activo,
			$conducta,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);
	}

	private function expediente(): Expediente {
		return new Expediente(
			'una tendencia',
			array( new HechoFuente( 'un hecho verificado', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ) )
		);
	}

	private function ficha(): FichaDecisionEditorial {
		return new FichaDecisionEditorial(
			1,
			1,
			new ClasificacionNoticia( 'economia', 30, 'x', NovedadNoticia::Primicia, 50, TipoNoticia::DatoEconomico ),
			array( new CandidatoTesis( 'tesis', 80.0, 80.0, 80.0, 80.0 ) ),
			0,
			Tono::Analitico,
			Tono::Persuasivo,
			new EsqueletoPieza( 'g', 'h', array( 'm1', 'm2' ), 'c', 'r' ),
			new DateTimeImmutable( '2026-07-22T12:00:00+00:00' )
		);
	}

	private function corrector( ProveedorLenguajeFalso $proveedor ): CorrectorInterno {
		Functions\when( 'get_option' )->justReturn( false );

		return new CorrectorInterno(
			$proveedor,
			new VerificadorVoz(),
			new VerificadorNGramas(),
			new VerificadorTrazabilidadDeterminista( new EmbeddingsFalso(), new SegmentadorUnidadesFactuales() ),
			$this->verificadorContradiccion()
		);
	}

	public function test_revisar_devuelve_seis_anotaciones_en_el_orden_del_enum(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"hechos": {"aprobado": true, "detalle": "ok"}, "proporcion_interpretativa": {"aprobado": true, "detalle": "ok"}, '
				. '"titular_honesto": {"aprobado": true, "detalle": "ok"}, "matriz_y_lineas_rojas": {"aprobado": true, "detalle": "ok"}}'
		);

		$anotaciones = $this->corrector( $proveedor )->revisar( $this->periodista(), $this->expediente(), $this->ficha(), 'Título', 'Un cuerpo cualquiera sin copias.' );

		self::assertCount( 6, $anotaciones );
		self::assertSame(
			array(
				PuntoCorrector::Hechos,
				PuntoCorrector::ProporcionInterpretativa,
				PuntoCorrector::SolapamientoNGrama,
				PuntoCorrector::Voz,
				PuntoCorrector::TitularHonesto,
				PuntoCorrector::MatrizYLineasRojas,
			),
			array_map( static fn ( $a ) => $a->punto, $anotaciones )
		);
	}

	public function test_aprobado_es_falso_si_un_solo_punto_reprueba(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"hechos": {"aprobado": false, "detalle": "hay un dato inventado"}, "proporcion_interpretativa": {"aprobado": true, "detalle": "ok"}, '
				. '"titular_honesto": {"aprobado": true, "detalle": "ok"}, "matriz_y_lineas_rojas": {"aprobado": true, "detalle": "ok"}}'
		);

		$anotaciones = $this->corrector( $proveedor )->revisar( $this->periodista(), $this->expediente(), $this->ficha(), 'Título', 'Un cuerpo cualquiera sin copias.' );
		$corrector   = $this->corrector( $proveedor );

		self::assertFalse( $corrector->aprobado( $anotaciones ) );
	}

	public function test_aprobado_es_verdadero_solo_si_los_seis_puntos_aprueban(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"hechos": {"aprobado": true, "detalle": "ok"}, "proporcion_interpretativa": {"aprobado": true, "detalle": "ok"}, '
				. '"titular_honesto": {"aprobado": true, "detalle": "ok"}, "matriz_y_lineas_rojas": {"aprobado": true, "detalle": "ok"}}'
		);
		$corrector = $this->corrector( $proveedor );

		$anotaciones = $corrector->revisar( $this->periodista(), $this->expediente(), $this->ficha(), 'Título', 'Un cuerpo cualquiera sin copias.' );

		self::assertTrue( $corrector->aprobado( $anotaciones ) );
	}

	public function test_lanza_excepcion_si_falta_un_punto_en_la_respuesta(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"hechos": {"aprobado": true, "detalle": "ok"}}' );

		$this->expectException( DecisionEditorialException::class );

		$this->corrector( $proveedor )->revisar( $this->periodista(), $this->expediente(), $this->ficha(), 'Título', 'cuerpo' );
	}

	public function test_lanza_excepcion_si_la_respuesta_llego_truncada(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"hechos": {"aprobado": true, "detalle": "ok"}, "proporcion_interpretativa": {"aprobado": true, "detalle": "ok"}, '
				. '"titular_honesto": {"aprobado": true, "detalle": "ok"}, "matriz_y_lineas_rojas": {"aprobado": true, "detalle": "ok"}}',
			truncada: true
		);

		$this->expectException( DecisionEditorialException::class );

		$this->corrector( $proveedor )->revisar( $this->periodista(), $this->expediente(), $this->ficha(), 'Título', 'cuerpo' );
	}

	/**
	 * Nivel Tres J.3: cuando la capa determinista detecta una frase sin
	 * respaldo aparente en el expediente, `CorrectorInterno` la señala en la
	 * petición que envía al proveedor de lenguaje para el punto "hechos" —
	 * prioriza y abarata la evaluación, no la sustituye.
	 */
	public function test_una_frase_sin_respaldo_aparente_se_señala_en_la_peticion_al_proveedor(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$embeddings = new EmbeddingsFalso(
			static function ( string $texto ): array {
				if ( str_contains( $texto, 'respaldado' ) ) {
					return array( 1.0, 0.0 );
				}

				return array( 0.0, 1.0 );
			}
		);

		$proveedor = new ProveedorLenguajeFalso(
			'{"hechos": {"aprobado": true, "detalle": "ok"}, "proporcion_interpretativa": {"aprobado": true, "detalle": "ok"}, '
				. '"titular_honesto": {"aprobado": true, "detalle": "ok"}, "matriz_y_lineas_rojas": {"aprobado": true, "detalle": "ok"}}'
		);

		$corrector = new CorrectorInterno(
			$proveedor,
			new VerificadorVoz(),
			new VerificadorNGramas(),
			new VerificadorTrazabilidadDeterminista( $embeddings, new SegmentadorUnidadesFactuales() ),
			$this->verificadorContradiccion()
		);

		$expediente = new Expediente(
			'una tendencia',
			array( new HechoFuente( 'un hecho respaldado', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ) )
		);

		$corrector->revisar( $this->periodista(), $expediente, $this->ficha(), 'Título', 'Este dato fue completamente inventado sin fuente alguna.' );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringContainsString( 'ALERTA DE TRAZABILIDAD DETERMINISTA', $proveedor->ultimaPeticion->directrices );
		self::assertStringContainsString( 'inventado', $proveedor->ultimaPeticion->directrices );
	}

	/**
	 * Contraprueba: un borrador enteramente trazable al expediente no dispara
	 * la alerta — la capa determinista no genera ruido cuando no hace falta.
	 */
	public function test_un_borrador_trazable_no_dispara_la_alerta_de_trazabilidad(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$embeddings = new EmbeddingsFalso( static fn (): array => array( 1.0, 0.0 ) );

		$proveedor = new ProveedorLenguajeFalso(
			'{"hechos": {"aprobado": true, "detalle": "ok"}, "proporcion_interpretativa": {"aprobado": true, "detalle": "ok"}, '
				. '"titular_honesto": {"aprobado": true, "detalle": "ok"}, "matriz_y_lineas_rojas": {"aprobado": true, "detalle": "ok"}}'
		);

		$corrector = new CorrectorInterno(
			$proveedor,
			new VerificadorVoz(),
			new VerificadorNGramas(),
			new VerificadorTrazabilidadDeterminista( $embeddings, new SegmentadorUnidadesFactuales() ),
			$this->verificadorContradiccion()
		);

		$corrector->revisar( $this->periodista(), $this->expediente(), $this->ficha(), 'Título', 'Un cuerpo cualquiera sin copias.' );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringNotContainsString( 'ALERTA DE TRAZABILIDAD DETERMINISTA', $proveedor->ultimaPeticion->directrices );
	}

	/**
	 * NCP-3 Porción 1 (`ADR 0021`): cuando la capa determinista NLI detecta
	 * una frase que contradice el expediente, `CorrectorInterno` la señala en
	 * la petición al proveedor de lenguaje para el punto "hechos".
	 */
	public function test_una_frase_contradictoria_se_señala_en_la_peticion_al_proveedor(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$proveedor = new ProveedorLenguajeFalso(
			'{"hechos": {"aprobado": true, "detalle": "ok"}, "proporcion_interpretativa": {"aprobado": true, "detalle": "ok"}, '
				. '"titular_honesto": {"aprobado": true, "detalle": "ok"}, "matriz_y_lineas_rojas": {"aprobado": true, "detalle": "ok"}}'
		);

		$corrector = new CorrectorInterno(
			$proveedor,
			new VerificadorVoz(),
			new VerificadorNGramas(),
			new VerificadorTrazabilidadDeterminista( new EmbeddingsFalso(), new SegmentadorUnidadesFactuales() ),
			$this->verificadorContradiccion( 'contradiction' )
		);

		$corrector->revisar( $this->periodista(), $this->expediente(), $this->ficha(), 'Título', 'El alcalde no renunció.' );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringContainsString( 'ALERTA DE CONTRADICCIÓN DETERMINISTA (NLI)', $proveedor->ultimaPeticion->directrices );
		self::assertStringContainsString( 'no renunció', $proveedor->ultimaPeticion->directrices );
	}

	/**
	 * Contraprueba: sin ninguna contradicción real, la alerta NLI no se
	 * dispara — la capa determinista no genera ruido cuando no hace falta.
	 */
	public function test_sin_contradiccion_no_dispara_la_alerta_de_contradiccion(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"hechos": {"aprobado": true, "detalle": "ok"}, "proporcion_interpretativa": {"aprobado": true, "detalle": "ok"}, '
				. '"titular_honesto": {"aprobado": true, "detalle": "ok"}, "matriz_y_lineas_rojas": {"aprobado": true, "detalle": "ok"}}'
		);

		$anotaciones = $this->corrector( $proveedor )->revisar( $this->periodista(), $this->expediente(), $this->ficha(), 'Título', 'Un cuerpo cualquiera sin copias.' );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringNotContainsString( 'ALERTA DE CONTRADICCIÓN DETERMINISTA', $proveedor->ultimaPeticion->directrices );
		self::assertNotEmpty( $anotaciones );
	}
}
