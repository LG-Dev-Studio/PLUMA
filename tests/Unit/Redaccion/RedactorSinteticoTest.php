<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Datos\RepositorioBorradoresInterface;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Redaccion\AvisoTransparenciaIa;
use Pluma\Redaccion\CandidatoTesis;
use Pluma\Redaccion\ClasificacionNoticia;
use Pluma\Redaccion\CompiladorDirectrices;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\CorrectorInterno;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EsqueletoPieza;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\FichaDecisionEditorial;
use Pluma\Redaccion\GeneradorBloqueEditor;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Proveedores\EtiquetaNli;
use Pluma\Proveedores\ResultadoNli;
use Pluma\Redaccion\VerificadorContradiccionNli;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\NovedadNoticia;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\RedactorSintetico;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\SegmentadorUnidadesFactuales;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Redaccion\VerificadorNGramas;
use Pluma\Redaccion\VerificadorTrazabilidadDeterminista;
use Pluma\Redaccion\VerificadorVoz;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\EmbeddingsFalso;
use Pluma\Tests\Unit\Dobles\NliFalso;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeSecuencial;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Redacción en dos pasadas + autocrítica (Libro Cap. 5.6): máximo 2 ciclos,
 * al tercero RETENIDA.
 *
 * @covers \Pluma\Redaccion\RedactorSintetico
 */
final class RedactorSinteticoTest extends CasoDePruebaUnitario {

	private const CORRECTOR_APRUEBA         = '{"hechos": {"aprobado": true, "detalle": "ok"}, "proporcion_interpretativa": {"aprobado": true, "detalle": "ok"}, "titular_honesto": {"aprobado": true, "detalle": "ok"}, "matriz_y_lineas_rojas": {"aprobado": true, "detalle": "ok"}}';
	private const CORRECTOR_REPRUEBA        = '{"hechos": {"aprobado": false, "detalle": "una cifra no está en el expediente"}, "proporcion_interpretativa": {"aprobado": true, "detalle": "ok"}, "titular_honesto": {"aprobado": true, "detalle": "ok"}, "matriz_y_lineas_rojas": {"aprobado": true, "detalle": "ok"}}';
	private const CORRECTOR_REPRUEBA_TRIPLE = '{"hechos": {"aprobado": false, "detalle": "falla de hechos"}, "proporcion_interpretativa": {"aprobado": false, "detalle": "falla de proporcion"}, "titular_honesto": {"aprobado": true, "detalle": "ok"}, "matriz_y_lineas_rojas": {"aprobado": false, "detalle": "falla de matriz"}}';
	private const BLOQUE_EDITOR             = '{"comentario": "Yo ya lo veía venir.", "pregunta": "¿A quién le crees aquí?"}';

	private function periodista(): Periodista {
		$diales   = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas   = new ReglasConducta( 'linea editorial', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
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
			array( new HechoFuente( 'un hecho verificado cualquiera', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ) )
		);
	}

	private function ficha(): FichaDecisionEditorial {
		return new FichaDecisionEditorial(
			1,
			1,
			new ClasificacionNoticia( 'economia', 30, 'x', NovedadNoticia::Primicia, 50, TipoNoticia::DatoEconomico ),
			array( new CandidatoTesis( 'tesis elegida', 80.0, 80.0, 80.0, 80.0 ) ),
			0,
			Tono::Analitico,
			Tono::Persuasivo,
			new EsqueletoPieza( 'gancho', 'hechos', array( 'm1', 'm2' ), 'contra', 'remate' ),
			new DateTimeImmutable( '2026-07-22T12:00:00+00:00' )
		);
	}

	private function redactor( ProveedorLenguajeSecuencial $proveedor, RepositorioBorradoresInterface $repoBorradores ): RedactorSintetico {
		return new RedactorSintetico(
			$proveedor,
			new CompiladorDirectrices(),
			new CorrectorInterno(
				$proveedor,
				new VerificadorVoz(),
				new VerificadorNGramas(),
				new VerificadorTrazabilidadDeterminista( new EmbeddingsFalso(), new SegmentadorUnidadesFactuales() ),
				$this->verificadorContradiccion()
			),
			new GeneradorBloqueEditor( $proveedor ),
			new AvisoTransparenciaIa(),
			$repoBorradores,
			new RelojFijo()
		);
	}

	/**
	 * El doble de NLI nunca marca contradicción por defecto.
	 */
	private function verificadorContradiccion(): VerificadorContradiccionNli {
		$resultados = array( new ResultadoNli( EtiquetaNli::Neutral, 0.9 ) );
		$nli        = new NliFalso( static fn ( string $premisa, string $hipotesis ): array => $resultados );

		return new VerificadorContradiccionNli( $nli, new SegmentadorUnidadesFactuales() );
	}

	private function mockearFuncionesDeEnsamblado(): void {
		Functions\when( 'esc_html' )->alias( static fn ( string $s ): string => htmlspecialchars( $s, ENT_QUOTES ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $s ): string => htmlspecialchars( $s, ENT_QUOTES ) );
		Functions\when( 'esc_url' )->alias( static fn ( string $s ): string => $s );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( '__' )->alias( static fn ( string $s ): string => $s );

		if ( ! defined( 'AUTH_KEY' ) ) {
			define( 'AUTH_KEY', 'clave-app-de-prueba' );
			define( 'SECURE_AUTH_KEY', 'clave-secure-de-prueba' );
		}
	}

	public function test_aprobado_en_el_primer_ciclo_devuelve_el_resultado_con_bloque_editor(): void {
		$this->mockearFuncionesDeEnsamblado();

		$proveedor = new ProveedorLenguajeSecuencial(
			array(
				'{"titulo": "Un titular honesto", "cuerpo": "Primer párrafo original.\n\nSegundo párrafo con la tesis."}',
				self::CORRECTOR_APRUEBA,
				self::BLOQUE_EDITOR,
			)
		);

		$repoBorradores = $this->createMock( RepositorioBorradoresInterface::class );
		$repoBorradores->expects( self::once() )->method( 'crear' );

		$resultado = $this->redactor( $proveedor, $repoBorradores )->redactar( 1, $this->periodista(), $this->expediente(), $this->ficha() );

		self::assertFalse( $resultado->retenida );
		self::assertSame( 'Un titular honesto', $resultado->titulo );
		self::assertSame( 1, $resultado->ciclosUsados );
		self::assertStringContainsString( 'Primer párrafo original.', $resultado->cuerpoHtml );
		self::assertStringContainsString( 'Yo ya lo veía venir.', $resultado->cuerpoHtml );

		// GOVERNANCE §2.5: toda fuente usada se cita y enlaza — garantizado
		// mecánicamente, no delegado a la prosa generada por el modelo.
		self::assertStringContainsString( 'example.com', $resultado->cuerpoHtml );
		self::assertStringContainsString( '<a href="https://example.com">', $resultado->cuerpoHtml );

		// GOVERNANCE §2.6: el aviso de transparencia de autoría IA existe siempre.
		self::assertStringContainsString( 'periodista sintético', $resultado->cuerpoHtml );
	}

	public function test_un_fallo_en_el_primer_ciclo_dispara_una_segunda_pasada_que_aprueba(): void {
		$this->mockearFuncionesDeEnsamblado();

		$proveedor = new ProveedorLenguajeSecuencial(
			array(
				'{"titulo": "Titular", "cuerpo": "Borrador inicial con un dato flojo."}',
				self::CORRECTOR_REPRUEBA,
				'{"titulo": "Titular corregido", "cuerpo": "Borrador revisado sin el dato problemático."}',
				self::CORRECTOR_APRUEBA,
				self::BLOQUE_EDITOR,
			)
		);

		$repoBorradores = $this->createMock( RepositorioBorradoresInterface::class );
		$repoBorradores->expects( self::exactly( 2 ) )->method( 'crear' );

		$resultado = $this->redactor( $proveedor, $repoBorradores )->redactar( 1, $this->periodista(), $this->expediente(), $this->ficha() );

		self::assertFalse( $resultado->retenida );
		self::assertSame( 'Titular corregido', $resultado->titulo );
		self::assertSame( 2, $resultado->ciclosUsados );

		// La segunda pasada debe recibir las anotaciones del primer fallo.
		self::assertStringContainsString( 'una cifra no está en el expediente', $proveedor->peticiones[2]->directrices );
	}

	/**
	 * Nivel Dos A.6: cuando el Corrector Interno reprueba varios puntos a la
	 * vez, la segunda pasada debe instruirlos en orden de REPARACIÓN
	 * (`PuntoCorrector::ordenDeReparacion()`: Hechos → MatrizYLineasRojas →
	 * SolapamientoNGrama → ProporcionInterpretativa → Voz → TitularHonesto),
	 * no en el orden de enumeración/verificación en que el Corrector los
	 * evalúa (Hechos → ProporcionInterpretativa → ... → MatrizYLineasRojas).
	 */
	public function test_varios_fallos_a_la_vez_se_instruyen_en_orden_de_reparacion_no_de_verificacion(): void {
		$this->mockearFuncionesDeEnsamblado();

		$proveedor = new ProveedorLenguajeSecuencial(
			array(
				'{"titulo": "Titular", "cuerpo": "Borrador inicial."}',
				self::CORRECTOR_REPRUEBA_TRIPLE,
				'{"titulo": "Titular corregido", "cuerpo": "Borrador revisado."}',
				self::CORRECTOR_APRUEBA,
				self::BLOQUE_EDITOR,
			)
		);

		$repoBorradores = $this->createMock( RepositorioBorradoresInterface::class );
		$repoBorradores->expects( self::exactly( 2 ) )->method( 'crear' );

		$this->redactor( $proveedor, $repoBorradores )->redactar( 1, $this->periodista(), $this->expediente(), $this->ficha() );

		$instruccion = $proveedor->peticiones[2]->directrices;

		$posicionHechos     = strpos( $instruccion, 'falla de hechos' );
		$posicionMatriz     = strpos( $instruccion, 'falla de matriz' );
		$posicionProporcion = strpos( $instruccion, 'falla de proporcion' );

		self::assertIsInt( $posicionHechos );
		self::assertIsInt( $posicionMatriz );
		self::assertIsInt( $posicionProporcion );

		// Orden de reparación: Hechos primero, luego MatrizYLineasRojas,
		// y solo al final ProporcionInterpretativa — pese a que el Corrector
		// las evalúa y devuelve en el orden contrario (verificación).
		self::assertLessThan( $posicionMatriz, $posicionHechos );
		self::assertLessThan( $posicionProporcion, $posicionMatriz );
	}

	public function test_dos_fallos_consecutivos_marcan_la_pieza_retenida_sin_generar_bloque_editor(): void {
		Functions\when( 'get_option' )->justReturn( false );

		if ( ! defined( 'AUTH_KEY' ) ) {
			define( 'AUTH_KEY', 'clave-app-de-prueba' );
			define( 'SECURE_AUTH_KEY', 'clave-secure-de-prueba' );
		}

		$proveedor = new ProveedorLenguajeSecuencial(
			array(
				'{"titulo": "Titular", "cuerpo": "Borrador con un dato flojo."}',
				self::CORRECTOR_REPRUEBA,
				'{"titulo": "Titular", "cuerpo": "Segundo intento, todavía con problemas."}',
				self::CORRECTOR_REPRUEBA,
			)
		);

		$repoBorradores = $this->createMock( RepositorioBorradoresInterface::class );
		$repoBorradores->expects( self::exactly( 2 ) )->method( 'crear' );

		$resultado = $this->redactor( $proveedor, $repoBorradores )->redactar( 1, $this->periodista(), $this->expediente(), $this->ficha() );

		self::assertTrue( $resultado->retenida );
		self::assertSame( '', $resultado->titulo );
		self::assertSame( 2, $resultado->ciclosUsados );
		self::assertNotNull( $resultado->motivoRetenida );
		self::assertCount( 4, $proveedor->peticiones );
	}
}
