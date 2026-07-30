<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Mockery;
use Pluma\Compuertas\EstadoModoRespeto;
use Pluma\Compuertas\GestorModoRespeto;
use Pluma\Datos\RepositorioBorradoresInterface;
use Pluma\Datos\RepositorioColaPublicacionInterface;
use Pluma\Datos\RepositorioMemoriaEditorialInterface;
use Pluma\Datos\RepositorioModoRespetoInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Datos\RepositorioTendenciasInterface;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\LectorConfiguracionCadencia;
use Pluma\Pipeline\Pieza;
use Pluma\Pipeline\ProgramadorCadencia;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Redaccion\AsignadorPeriodista;
use Pluma\Redaccion\AvisoTransparenciaIa;
use Pluma\Redaccion\ClasificadorNoticia;
use Pluma\Redaccion\CompiladorDirectrices;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\CorrectorInterno;
use Pluma\Redaccion\DecisionEditorial;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\Especialidad;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\GeneradorBloqueEditor;
use Pluma\Redaccion\GeneradorEsqueleto;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\RedactorConFallbackMecanico;
use Pluma\Redaccion\RedactorMecanico;
use Pluma\Redaccion\RedactorSintetico;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\SegmentadorUnidadesFactuales;
use Pluma\Redaccion\SelectorAngulo;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Redaccion\VerificadorFalseabilidad;
use Pluma\Redaccion\VerificadorNGramas;
use Pluma\Redaccion\VerificadorTrazabilidadDeterminista;
use Pluma\Redaccion\VerificadorVoz;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\AzarFijo;
use Pluma\Tests\Unit\Dobles\EmbeddingsFalso;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeQueFalla;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeSecuencial;
use Pluma\Tests\Unit\Dobles\RelojFijo;
use RuntimeException;

/**
 * Fallback documentado por el propietario ("notificar y usar el redactor
 * mecánico" — sin presupuesto/credenciales), y propagación de fallos
 * técnicos reales (CLAUDE.md § Contrato del Orquestador: "escasez honesta").
 *
 * @covers \Pluma\Redaccion\RedactorConFallbackMecanico
 */
final class RedactorConFallbackMecanicoTest extends CasoDePruebaUnitario {

	private function gestorModoRespeto(): GestorModoRespeto {
		$repoModoRespeto = $this->createMock( RepositorioModoRespetoInterface::class );
		$repoModoRespeto->method( 'estadoActual' )->willReturn( EstadoModoRespeto::inactivo() );

		return new GestorModoRespeto(
			$repoModoRespeto,
			$this->createMock( RepositorioTendenciasInterface::class ),
			$this->createMock( RepositorioColaPublicacionInterface::class ),
			new ProgramadorCadencia( new AzarFijo( 0 ) ),
			new LectorConfiguracionCadencia()
		);
	}

	private function periodista(): Periodista {
		$diales   = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas   = new ReglasConducta( 'linea editorial', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 7, 1, $diales, $reglas, $matriz, false, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

		return new Periodista(
			1,
			'Periodista',
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array( new Especialidad( 'economia', 3 ) ),
			EstadoPeriodista::Activo,
			$conducta,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);
	}

	private function pieza( ?Expediente $expediente ): Pieza {
		$reloj = new RelojFijo();

		return new Pieza( 5, 100, EstadoPieza::EnRedaccion, $expediente, null, $reloj->ahora(), $reloj->ahora() );
	}

	private function expediente(): Expediente {
		return new Expediente(
			'una tendencia',
			array( new HechoFuente( 'un hecho verificado cualquiera', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ) )
		);
	}

	public function test_lanza_excepcion_si_la_pieza_no_tiene_expediente(): void {
		$redactor = $this->construir( new ProveedorLenguajeQueFalla( new ProveedorLenguajeException( 'no debería llamarse' ) ) );

		$this->expectException( RuntimeException::class );

		$redactor->redactar( $this->pieza( null ) );
	}

	public function test_camino_feliz_usa_el_redactor_sintetico_y_persiste_la_asignacion(): void {
		Functions\when( 'esc_html' )->alias( static fn ( string $s ): string => htmlspecialchars( $s, ENT_QUOTES ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $s ): string => htmlspecialchars( $s, ENT_QUOTES ) );
		Functions\when( 'esc_url' )->alias( static fn ( string $s ): string => $s );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( '__' )->alias( static fn ( string $s ): string => $s );

		$proveedor = new ProveedorLenguajeSecuencial(
			array(
				'{"tema": "economia", "gravedad": 30, "polaridad": "x", "novedad": "primicia", "potencialConversacional": 50, "tipoNoticia": "dato_economico"}',
				'{"candidatos": [{"tesis": "tesis elegida", "puntuacionOriginalidad": 80, "puntuacionCompatibilidadLinea": 80, "puntuacionSustento": 80, "puntuacionConversacional": 80}]}',
				'{"casoEnContra": "el caso en contra es débil", "fuerzaSustento": 10}',
				'{"gancho": "g", "hechosEsencialesConAtribucion": "h", "movimientosArgumentales": ["m1", "m2"], "contraargumentoReconocido": "c", "remate": "r"}',
				'{"titulo": "Un titular", "cuerpo": "Cuerpo redactado por el periodista sintético."}',
				'{"hechos": {"aprobado": true, "detalle": "ok"}, "proporcion_interpretativa": {"aprobado": true, "detalle": "ok"}, "titular_honesto": {"aprobado": true, "detalle": "ok"}, "matriz_y_lineas_rojas": {"aprobado": true, "detalle": "ok"}}',
				'{"comentario": "comentario", "pregunta": "¿pregunta?"}',
			)
		);

		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->method( 'obtenerActivos' )->willReturn( array( $this->periodista() ) );

		$repoMemoria = $this->createMock( RepositorioMemoriaEditorialInterface::class );
		$repoMemoria->method( 'existeCoberturaDelTema' )->willReturn( false );
		$repoMemoria->method( 'obtenerPosturasPorTema' )->willReturn( array() );

		$repoPiezas = $this->createMock( RepositorioPiezasInterface::class );
		$repoPiezas->method( 'contarAsignadasDesde' )->willReturn( 0 );
		$repoPiezas->expects( self::once() )->method( 'asignarPeriodista' )->with( 5, 1, 7 );
		$repoPiezas->expects( self::once() )->method( 'actualizarFichaDecisionEditorial' );

		$repoBorradores = $this->createMock( RepositorioBorradoresInterface::class );

		$redactor = new RedactorConFallbackMecanico(
			new DecisionEditorial(
				new ClasificadorNoticia( $proveedor ),
				new AsignadorPeriodista( new AzarFijo( 0 ) ),
				new SelectorAngulo( $proveedor ),
				new GeneradorEsqueleto( $proveedor ),
				$repoPeriodistas,
				$repoMemoria,
				$repoPiezas,
				new RelojFijo(),
				new VerificadorFalseabilidad( $proveedor ),
				$this->gestorModoRespeto()
			),
			new RedactorSintetico(
				$proveedor,
				new CompiladorDirectrices(),
				new CorrectorInterno( $proveedor, new VerificadorVoz(), new VerificadorNGramas(), new VerificadorTrazabilidadDeterminista( new EmbeddingsFalso(), new SegmentadorUnidadesFactuales() ) ),
				new GeneradorBloqueEditor( $proveedor ),
				new AvisoTransparenciaIa(),
				$repoBorradores,
				new RelojFijo()
			),
			new RedactorMecanico(),
			$repoPiezas,
			new RelojFijo()
		);

		$resultado = $redactor->redactar( $this->pieza( $this->expediente() ) );

		self::assertFalse( $resultado->retenida );
		self::assertSame( 'Un titular', $resultado->titulo );
		self::assertStringContainsString( 'Cuerpo redactado por el periodista sintético.', $resultado->cuerpoHtml );
	}

	public function test_sin_credenciales_dispara_notificacion_y_usa_el_redactor_mecanico(): void {
		Functions\when( 'esc_html' )->alias( static fn ( string $s ): string => htmlspecialchars( $s, ENT_QUOTES ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $s ): string => htmlspecialchars( $s, ENT_QUOTES ) );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'esc_url' )->alias( static fn ( string $s ): string => $s );

		Functions\expect( 'do_action' )->once()->with( 'pluma/redactor_fallback_mecanico', 5, Mockery::type( 'string' ) );

		$proveedor  = new ProveedorLenguajeQueFalla( new ProveedorLenguajeException( 'sin llave configurada', sinCredenciales: true ) );
		$repoPiezas = $this->createMock( RepositorioPiezasInterface::class );
		$repoPiezas->expects( self::never() )->method( 'asignarPeriodista' );

		$redactor = $this->construir( $proveedor, $repoPiezas );

		$resultado = $redactor->redactar( $this->pieza( $this->expediente() ) );

		self::assertFalse( $resultado->retenida );
		self::assertStringContainsString( 'Borrador generado automáticamente', $resultado->cuerpoHtml );
	}

	public function test_presupuesto_agotado_tambien_activa_el_fallback(): void {
		Functions\when( 'esc_html' )->alias( static fn ( string $s ): string => htmlspecialchars( $s, ENT_QUOTES ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $s ): string => htmlspecialchars( $s, ENT_QUOTES ) );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'esc_url' )->alias( static fn ( string $s ): string => $s );
		Functions\when( 'do_action' )->justReturn( null );

		$proveedor = new ProveedorLenguajeQueFalla( new ProveedorLenguajeException( 'presupuesto diario agotado', presupuestoAgotado: true ) );

		$resultado = $this->construir( $proveedor )->redactar( $this->pieza( $this->expediente() ) );

		self::assertFalse( $resultado->retenida );
	}

	/**
	 * Nivel Dos C.3: si ningún periodista supera el umbral de dominio, el
	 * resultado se marca `sinPeriodistaIdoneo` — sin borrador mecánico, sin
	 * borrador sintético, "no se asigna a el menos malo".
	 */
	public function test_ningun_periodista_idoneo_no_dispara_el_fallback_mecanico(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$proveedor = new ProveedorLenguajeSecuencial(
			array( '{"tema": "economia", "gravedad": 30, "polaridad": "x", "novedad": "primicia", "potencialConversacional": 50, "tipoNoticia": "dato_economico"}' )
		);

		$periodistaSinDominio = new Periodista(
			1,
			'Periodista',
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array( new Especialidad( 'cultura', 1 ) ),
			EstadoPeriodista::Activo,
			$this->periodista()->conductaActual,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);

		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->method( 'obtenerActivos' )->willReturn( array( $periodistaSinDominio ) );

		$repoMemoria = $this->createMock( RepositorioMemoriaEditorialInterface::class );
		$repoMemoria->method( 'existeCoberturaDelTema' )->willReturn( false );

		$repoPiezas = $this->createMock( RepositorioPiezasInterface::class );
		$repoPiezas->method( 'contarAsignadasDesde' )->willReturn( 0 );
		$repoPiezas->expects( self::never() )->method( 'asignarPeriodista' );
		// Trabajo posterior a la Etapa 9 (creación automática de
		// periodistas): el tema ya calculado por ClasificadorNoticia se
		// persiste en la Pieza en el momento exacto en que cae a
		// SIN_PERIODISTA_IDONEO — señal real para CreadorAutomaticoPeriodistas.
		$repoPiezas->expects( self::once() )->method( 'actualizarTemaSinCubrir' )->with( 5, 'economia' );

		$redactor = new RedactorConFallbackMecanico(
			new DecisionEditorial(
				new ClasificadorNoticia( $proveedor ),
				new AsignadorPeriodista( new AzarFijo( 0 ) ),
				new SelectorAngulo( $proveedor ),
				new GeneradorEsqueleto( $proveedor ),
				$repoPeriodistas,
				$repoMemoria,
				$repoPiezas,
				new RelojFijo(),
				new VerificadorFalseabilidad( $proveedor ),
				$this->gestorModoRespeto()
			),
			new RedactorSintetico(
				$proveedor,
				new CompiladorDirectrices(),
				new CorrectorInterno( $proveedor, new VerificadorVoz(), new VerificadorNGramas(), new VerificadorTrazabilidadDeterminista( new EmbeddingsFalso(), new SegmentadorUnidadesFactuales() ) ),
				new GeneradorBloqueEditor( $proveedor ),
				new AvisoTransparenciaIa(),
				$this->createMock( RepositorioBorradoresInterface::class ),
				new RelojFijo()
			),
			new RedactorMecanico(),
			$repoPiezas,
			new RelojFijo()
		);

		$resultado = $redactor->redactar( $this->pieza( $this->expediente() ) );

		self::assertTrue( $resultado->sinPeriodistaIdoneo );
		self::assertSame( '', $resultado->titulo );
		self::assertSame( '', $resultado->cuerpoHtml );
		self::assertFalse( $resultado->retenida );
		self::assertNotNull( $resultado->motivoSinPeriodistaIdoneo );
	}

	public function test_un_fallo_tecnico_real_se_propaga_sin_usar_el_fallback(): void {
		Functions\when( 'esc_html' )->alias( static fn ( string $s ): string => htmlspecialchars( $s, ENT_QUOTES ) );

		$excepcion = new ProveedorLenguajeException( 'circuito abierto: fallo de red' );
		$proveedor = new ProveedorLenguajeQueFalla( $excepcion );

		Functions\expect( 'do_action' )->never()->with( 'pluma/redactor_fallback_mecanico', Mockery::any(), Mockery::any() );

		$this->expectException( ProveedorLenguajeException::class );
		$this->expectExceptionMessage( 'circuito abierto: fallo de red' );

		$this->construir( $proveedor )->redactar( $this->pieza( $this->expediente() ) );
	}

	private function construir( LenguajeInterface $proveedor, ?RepositorioPiezasInterface $repoPiezasCompartido = null ): RedactorConFallbackMecanico {
		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->method( 'obtenerActivos' )->willReturn( array( $this->periodista() ) );

		$repoMemoria = $this->createMock( RepositorioMemoriaEditorialInterface::class );
		$repoMemoria->method( 'existeCoberturaDelTema' )->willReturn( false );
		$repoMemoria->method( 'obtenerPosturasPorTema' )->willReturn( array() );

		$repoPiezas = $repoPiezasCompartido ?? $this->createMock( RepositorioPiezasInterface::class );

		if ( null === $repoPiezasCompartido ) {
			$repoPiezas->method( 'contarAsignadasDesde' )->willReturn( 0 );
		}

		$repoBorradores = $this->createMock( RepositorioBorradoresInterface::class );

		return new RedactorConFallbackMecanico(
			new DecisionEditorial(
				new ClasificadorNoticia( $proveedor ),
				new AsignadorPeriodista( new AzarFijo( 0 ) ),
				new SelectorAngulo( $proveedor ),
				new GeneradorEsqueleto( $proveedor ),
				$repoPeriodistas,
				$repoMemoria,
				$repoPiezas,
				new RelojFijo(),
				new VerificadorFalseabilidad( $proveedor ),
				$this->gestorModoRespeto()
			),
			new RedactorSintetico(
				$proveedor,
				new CompiladorDirectrices(),
				new CorrectorInterno( $proveedor, new VerificadorVoz(), new VerificadorNGramas(), new VerificadorTrazabilidadDeterminista( new EmbeddingsFalso(), new SegmentadorUnidadesFactuales() ) ),
				new GeneradorBloqueEditor( $proveedor ),
				new AvisoTransparenciaIa(),
				$repoBorradores,
				new RelojFijo()
			),
			new RedactorMecanico(),
			$repoPiezas,
			new RelojFijo()
		);
	}
}
