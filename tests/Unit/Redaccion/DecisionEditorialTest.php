<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Compuertas\ActivadorModoRespeto;
use Pluma\Compuertas\EstadoModoRespeto;
use Pluma\Compuertas\GestorModoRespeto;
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
use Pluma\Redaccion\AsignadorPeriodista;
use Pluma\Redaccion\ClasificadorNoticia;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\DecisionEditorial;
use Pluma\Redaccion\DecisionEditorialException;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EntradaMemoria;
use Pluma\Redaccion\Especialidad;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\GeneradorEsqueleto;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\SelectorAngulo;
use Pluma\Redaccion\TipoMemoria;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Redaccion\VerificadorFalseabilidad;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\AzarFijo;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeSecuencial;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Los 4 pasos del Algoritmo de Decisión Editorial encadenados (Libro Cap. 5.5).
 *
 * @covers \Pluma\Redaccion\DecisionEditorial
 */
final class DecisionEditorialTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( false );
	}

	private function periodista(): Periodista {
		$diales   = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas   = new ReglasConducta( 'Escéptica del poder.', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 7, 1, $diales, $reglas, $matriz, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

		return new Periodista(
			1,
			'Valentina Ruiz',
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

	private function expediente(): Expediente {
		return new Expediente(
			'una tendencia',
			array( new HechoFuente( 'un hecho verificado', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ) )
		);
	}

	private function gestorModoRespeto( bool $activo = false ): GestorModoRespeto {
		$repoModoRespeto = $this->createMock( RepositorioModoRespetoInterface::class );
		$repoModoRespeto->method( 'estadoActual' )->willReturn(
			$activo
				? new EstadoModoRespeto( true, new DateTimeImmutable( '2026-07-27T12:00:00+00:00' ), ActivadorModoRespeto::Manual, 'evento grave', new DateTimeImmutable( '2026-07-27T18:00:00+00:00' ) )
				: EstadoModoRespeto::inactivo()
		);

		return new GestorModoRespeto(
			$repoModoRespeto,
			$this->createMock( RepositorioTendenciasInterface::class ),
			$this->createMock( RepositorioColaPublicacionInterface::class ),
			new ProgramadorCadencia( new AzarFijo( 0 ) ),
			new LectorConfiguracionCadencia()
		);
	}

	private function construirDecision( ProveedorLenguajeSecuencial $proveedor, Periodista $periodista, bool $modoRespetoActivo = false ): DecisionEditorial {
		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->method( 'obtenerActivos' )->willReturn( array( $periodista ) );

		$repoMemoria = $this->createMock( RepositorioMemoriaEditorialInterface::class );
		$repoMemoria->method( 'existeCoberturaDelTema' )->willReturn( false );
		$repoMemoria->method( 'obtenerPosturasPorTema' )->willReturn( array() );
		$repoMemoria->method( 'obtenerPosturasColectivasPorTema' )->willReturn( array() );

		$repoPiezas = $this->createMock( RepositorioPiezasInterface::class );
		$repoPiezas->method( 'contarAsignadasDesde' )->willReturn( 0 );

		return new DecisionEditorial(
			new ClasificadorNoticia( $proveedor ),
			new AsignadorPeriodista( new AzarFijo( 0 ) ),
			new SelectorAngulo( $proveedor ),
			new GeneradorEsqueleto( $proveedor ),
			$repoPeriodistas,
			$repoMemoria,
			$repoPiezas,
			new RelojFijo(),
			new VerificadorFalseabilidad( $proveedor ),
			$this->gestorModoRespeto( $modoRespetoActivo )
		);
	}

	public function test_decidir_encadena_los_cuatro_pasos_y_produce_una_ficha_completa(): void {
		$proveedor = new ProveedorLenguajeSecuencial(
			array(
				'{"tema": "economia", "gravedad": 30, "polaridad": "gobierno vs oposición", "novedad": "primicia", "potencialConversacional": 70, "tipoNoticia": "dato_economico"}',
				'{"candidatos": [{"tesis": "la cifra oficial esconde el dato real", "puntuacionOriginalidad": 80, "puntuacionCompatibilidadLinea": 80, "puntuacionSustento": 90, "puntuacionConversacional": 70}]}',
				'{"casoEnContra": "el caso en contra es débil", "fuerzaSustento": 10}',
				'{"gancho": "gancho", "hechosEsencialesConAtribucion": "hechos", "movimientosArgumentales": ["m1", "m2"], "contraargumentoReconocido": "contra", "remate": "remate"}',
			)
		);

		$resultado = $this->construirDecision( $proveedor, $this->periodista() )->decidir( $this->expediente() );

		self::assertSame( 1, $resultado['periodista']->id );
		self::assertSame( 'economia', $resultado['ficha']->clasificacion->tema );
		self::assertSame( 'la cifra oficial esconde el dato real', $resultado['ficha']->tesisElegida()->tesis );
		self::assertSame( Tono::Analitico, $resultado['ficha']->tonoDominante );
		self::assertSame( Tono::Persuasivo, $resultado['ficha']->tonoApoyo );
		self::assertCount( 2, $resultado['ficha']->esqueleto->movimientosArgumentales );
		self::assertSame( 7, $resultado['ficha']->periodistaVersionId );
	}

	/**
	 * Nivel Dos C.2 punto 2: "quien empezó esta historia, la sigue" —
	 * `decidir()` consulta la pieza original vía `piezaOriginalId` y pasa a
	 * `AsignadorPeriodista` el periodista que ya la cubrió, para que gane el
	 * desempate aunque el azar (o el orden del array) apunte a otro.
	 */
	public function test_decidir_pasa_el_periodista_de_la_historia_especifica_al_asignador(): void {
		$proveedor = new ProveedorLenguajeSecuencial(
			array(
				'{"tema": "economia", "gravedad": 30, "polaridad": "gobierno vs oposición", "novedad": "primicia", "potencialConversacional": 70, "tipoNoticia": "dato_economico"}',
				'{"candidatos": [{"tesis": "la cifra oficial esconde el dato real", "puntuacionOriginalidad": 80, "puntuacionCompatibilidadLinea": 80, "puntuacionSustento": 90, "puntuacionConversacional": 70}]}',
				'{"casoEnContra": "el caso en contra es débil", "fuerzaSustento": 10}',
				'{"gancho": "gancho", "hechosEsencialesConAtribucion": "hechos", "movimientosArgumentales": ["m1", "m2"], "contraargumentoReconocido": "contra", "remate": "remate"}',
			)
		);

		$original   = $this->periodista();
		$competidor = new Periodista(
			2,
			'Otro Periodista',
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array( new Especialidad( 'economia', 3 ) ),
			EstadoPeriodista::Activo,
			$original->conductaActual,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);

		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->method( 'obtenerActivos' )->willReturn( array( $competidor, $original ) );

		$repoMemoria = $this->createMock( RepositorioMemoriaEditorialInterface::class );
		$repoMemoria->method( 'existeCoberturaDelTema' )->willReturn( false );
		$repoMemoria->method( 'obtenerPosturasPorTema' )->willReturn( array() );
		$repoMemoria->method( 'obtenerPosturasColectivasPorTema' )->willReturn( array() );

		$repoPiezas = $this->createMock( RepositorioPiezasInterface::class );
		$repoPiezas->method( 'contarAsignadasDesde' )->willReturn( 0 );
		$repoPiezas->method( 'obtenerPorId' )->with( 99 )->willReturn(
			new Pieza(
				99,
				5,
				EstadoPieza::Publicada,
				null,
				null,
				new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
				new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
				$original->id
			)
		);

		// Azar en 1 elegiría al primero del array (competidor) si la historia
		// específica no interviniera — con historia específica, gana $original.
		$decision = new DecisionEditorial(
			new ClasificadorNoticia( $proveedor ),
			new AsignadorPeriodista( new AzarFijo( 1 ) ),
			new SelectorAngulo( $proveedor ),
			new GeneradorEsqueleto( $proveedor ),
			$repoPeriodistas,
			$repoMemoria,
			$repoPiezas,
			new RelojFijo(),
			new VerificadorFalseabilidad( $proveedor ),
			$this->gestorModoRespeto()
		);

		$resultado = $decision->decidir( $this->expediente(), 99 );

		self::assertSame( $original->id, $resultado['periodista']->id );
	}

	/**
	 * Nivel Tres O.1, Fase 3.5: si el caso en contra de la tesis ganadora
	 * supera el umbral de retorno, esa tesis se descarta y se reevalúa entre
	 * los candidatos restantes — no se pierde toda la decisión, ni se
	 * publica la tesis ya derrotada.
	 */
	public function test_una_tesis_derrotada_por_falseabilidad_se_descarta_y_se_reevalua(): void {
		$proveedor = new ProveedorLenguajeSecuencial(
			array(
				'{"tema": "economia", "gravedad": 30, "polaridad": "x", "novedad": "primicia", "potencialConversacional": 50, "tipoNoticia": "dato_economico"}',
				'{"candidatos": ['
					. '{"tesis": "tesis A, la mejor puntuada", "puntuacionOriginalidad": 90, "puntuacionCompatibilidadLinea": 90, "puntuacionSustento": 90, "puntuacionConversacional": 90}, '
					. '{"tesis": "tesis B, la segunda", "puntuacionOriginalidad": 50, "puntuacionCompatibilidadLinea": 50, "puntuacionSustento": 50, "puntuacionConversacional": 50}'
					. ']}',
				'{"casoEnContra": "el expediente contradice directamente la tesis A", "fuerzaSustento": 90}',
				'{"casoEnContra": "caso débil contra la tesis B", "fuerzaSustento": 5}',
				'{"gancho": "g", "hechosEsencialesConAtribucion": "h", "movimientosArgumentales": ["m1", "m2"], "contraargumentoReconocido": "c", "remate": "r"}',
			)
		);

		$resultado = $this->construirDecision( $proveedor, $this->periodista() )->decidir( $this->expediente() );

		self::assertSame( 'tesis B, la segunda', $resultado['ficha']->tesisElegida()->tesis );
		self::assertNull( $resultado['ficha']->tensionFalseabilidad );
	}

	/**
	 * Nivel Tres O.1, Fase 3.5: si el caso en contra es comparable a la
	 * tesis ganadora (misma fuerza o más, sin llegar al umbral de retorno),
	 * la tesis NO se descarta, pero la Ficha registra la tensión y el
	 * esqueleto debe incorporar el caso en contra.
	 */
	public function test_un_caso_en_contra_comparable_registra_la_tension_sin_descartar_la_tesis(): void {
		$proveedor = new ProveedorLenguajeSecuencial(
			array(
				'{"tema": "economia", "gravedad": 30, "polaridad": "x", "novedad": "primicia", "potencialConversacional": 50, "tipoNoticia": "dato_economico"}',
				'{"candidatos": [{"tesis": "tesis con sustento moderado", "puntuacionOriginalidad": 80, "puntuacionCompatibilidadLinea": 80, "puntuacionSustento": 45, "puntuacionConversacional": 80}]}',
				'{"casoEnContra": "un caso comparable, no dominante", "fuerzaSustento": 50}',
				'{"gancho": "g", "hechosEsencialesConAtribucion": "h", "movimientosArgumentales": ["m1", "m2"], "contraargumentoReconocido": "c", "remate": "r"}',
			)
		);

		$resultado = $this->construirDecision( $proveedor, $this->periodista() )->decidir( $this->expediente() );

		self::assertSame( 'tesis con sustento moderado', $resultado['ficha']->tesisElegida()->tesis );
		self::assertSame( 'un caso comparable, no dominante', $resultado['ficha']->tensionFalseabilidad );
	}

	/**
	 * Nivel Tres O.1: si TODOS los candidatos son derrotados por la Prueba
	 * de Falseabilidad, no queda ninguna tesis que defender — la Pieza no
	 * se fuerza a publicar la menos derrotada.
	 */
	public function test_todos_los_candidatos_derrotados_lanza_excepcion(): void {
		$proveedor = new ProveedorLenguajeSecuencial(
			array(
				'{"tema": "economia", "gravedad": 30, "polaridad": "x", "novedad": "primicia", "potencialConversacional": 50, "tipoNoticia": "dato_economico"}',
				'{"candidatos": [{"tesis": "única tesis", "puntuacionOriginalidad": 80, "puntuacionCompatibilidadLinea": 80, "puntuacionSustento": 80, "puntuacionConversacional": 80}]}',
				'{"casoEnContra": "el expediente la contradice de forma directa", "fuerzaSustento": 90}',
			)
		);

		$this->expectException( DecisionEditorialException::class );

		$this->construirDecision( $proveedor, $this->periodista() )->decidir( $this->expediente() );
	}

	/**
	 * Nivel Dos E.2, memoria colectiva del sitio: `decidir()` consulta
	 * `obtenerPosturasColectivasPorTema()`, excluye la entrada que pertenece
	 * al propio periodista asignado (ya cubierta por la memoria individual),
	 * y resuelve la atribución activo/jubilado vía `RepositorioPeriodistasInterface`
	 * antes de pasarla a `SelectorAngulo`.
	 */
	public function test_decidir_pasa_memoria_colectiva_excluyendo_al_propio_periodista_y_resolviendo_atribucion(): void {
		$proveedor = new ProveedorLenguajeSecuencial(
			array(
				'{"tema": "economia", "gravedad": 30, "polaridad": "x", "novedad": "primicia", "potencialConversacional": 50, "tipoNoticia": "dato_economico"}',
				'{"candidatos": [{"tesis": "x", "puntuacionOriginalidad": 80, "puntuacionCompatibilidadLinea": 80, "puntuacionSustento": 80, "puntuacionConversacional": 80}]}',
				'{"casoEnContra": "caso débil", "fuerzaSustento": 5}',
				'{"gancho": "g", "hechosEsencialesConAtribucion": "h", "movimientosArgumentales": ["m1", "m2"], "contraargumentoReconocido": "c", "remate": "r"}',
			)
		);

		$periodistaAsignado = $this->periodista();
		$colegaActivo       = new Periodista(
			2,
			'Colega Activo',
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array(),
			EstadoPeriodista::Activo,
			$periodistaAsignado->conductaActual,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);
		$colegaJubilado     = new Periodista(
			3,
			'Colega Jubilado',
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array(),
			EstadoPeriodista::Jubilado,
			$periodistaAsignado->conductaActual,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);

		$posturaDelPropioPeriodista = new EntradaMemoria( 1, $periodistaAsignado->id, TipoMemoria::Postura, 'economia', array( 'postura' => 'no debe llegar como colectiva' ), null, new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ) );
		$posturaColegaActivo        = new EntradaMemoria( 2, $colegaActivo->id, TipoMemoria::Postura, 'economia', array( 'postura' => 'postura del colega activo' ), null, new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ) );
		$posturaColegaJubilado      = new EntradaMemoria( 3, $colegaJubilado->id, TipoMemoria::Postura, 'economia', array( 'postura' => 'postura del colega jubilado' ), null, new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ) );
		$posturaHuerfana            = new EntradaMemoria( 4, 999, TipoMemoria::Postura, 'economia', array( 'postura' => 'postura de un periodista eliminado' ), null, new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ) );

		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->method( 'obtenerActivos' )->willReturn( array( $periodistaAsignado ) );
		$repoPeriodistas->method( 'obtenerPorId' )->willReturnMap(
			array(
				array( $colegaActivo->id, $colegaActivo ),
				array( $colegaJubilado->id, $colegaJubilado ),
				array( 999, null ),
			)
		);

		$repoMemoria = $this->createMock( RepositorioMemoriaEditorialInterface::class );
		$repoMemoria->method( 'existeCoberturaDelTema' )->willReturn( false );
		$repoMemoria->method( 'obtenerPosturasPorTema' )->willReturn( array() );
		$repoMemoria->method( 'obtenerPosturasColectivasPorTema' )->willReturn(
			array( $posturaDelPropioPeriodista, $posturaColegaActivo, $posturaColegaJubilado, $posturaHuerfana )
		);

		$repoPiezas = $this->createMock( RepositorioPiezasInterface::class );
		$repoPiezas->method( 'contarAsignadasDesde' )->willReturn( 0 );

		$decision = new DecisionEditorial(
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
		);

		$decision->decidir( $this->expediente() );

		$materialEnviado = $proveedor->peticiones[1]->material;

		self::assertStringContainsString( 'un colega de esta redacción, Colega Activo, sostuvo: postura del colega activo', $materialEnviado );
		self::assertStringContainsString( 'esta redacción sostuvo antes', $materialEnviado );
		self::assertStringContainsString( 'postura del colega jubilado', $materialEnviado );
		self::assertStringNotContainsString( 'no debe llegar como colectiva', $materialEnviado );
		self::assertStringNotContainsString( 'postura de un periodista eliminado', $materialEnviado );
	}

	public function test_lanza_excepcion_si_no_hay_periodistas_activos(): void {
		$proveedor = new ProveedorLenguajeSecuencial(
			array( '{"tema": "economia", "gravedad": 30, "polaridad": "x", "novedad": "primicia", "potencialConversacional": 50, "tipoNoticia": "dato_economico"}' )
		);

		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->method( 'obtenerActivos' )->willReturn( array() );

		$repoMemoria = $this->createMock( RepositorioMemoriaEditorialInterface::class );
		$repoPiezas  = $this->createMock( RepositorioPiezasInterface::class );

		$decision = new DecisionEditorial(
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
		);

		$this->expectException( DecisionEditorialException::class );

		$decision->decidir( $this->expediente() );
	}

	/**
	 * Nivel Dos F.3: con el modo respeto activo, toda pieza en EN_REDACCION
	 * se re-evalúa contra la matriz de tono forzada a Tragedia —
	 * independientemente de su clasificación real (aquí, "dato_economico").
	 * `Pluma\Redaccion\MatrizTonos::filaSistemaTragedia()` fuerza
	 * InformativoEmpatico/Analitico/sátira bloqueada, sin importar lo que
	 * tenga configurado el periodista para dato_economico.
	 */
	public function test_con_modo_respeto_activo_fuerza_el_tono_de_tragedia(): void {
		$proveedor = new ProveedorLenguajeSecuencial(
			array(
				'{"tema": "economia", "gravedad": 30, "polaridad": "x", "novedad": "primicia", "potencialConversacional": 50, "tipoNoticia": "dato_economico"}',
				'{"candidatos": [{"tesis": "x", "puntuacionOriginalidad": 80, "puntuacionCompatibilidadLinea": 80, "puntuacionSustento": 80, "puntuacionConversacional": 80}]}',
				'{"casoEnContra": "caso débil", "fuerzaSustento": 5}',
				'{"gancho": "g", "hechosEsencialesConAtribucion": "h", "movimientosArgumentales": ["m1", "m2"], "contraargumentoReconocido": "c", "remate": "r"}',
			)
		);

		$resultado = $this->construirDecision( $proveedor, $this->periodista(), modoRespetoActivo: true )->decidir( $this->expediente() );

		self::assertSame( Tono::InformativoEmpatico, $resultado['ficha']->tonoDominante );
		self::assertSame( Tono::Analitico, $resultado['ficha']->tonoApoyo );
		// La clasificación real guardada en la ficha NO se altera — solo el tono buscado.
		self::assertSame( 'dato_economico', $resultado['ficha']->clasificacion->tipoNoticia->value );
	}

	/**
	 * Contraprueba: sin modo respeto, la misma pieza usa el tono real
	 * configurado para "dato_economico" (Analítico/Persuasivo, ver
	 * `periodista()`), no el de Tragedia.
	 */
	public function test_sin_modo_respeto_usa_el_tono_real_de_la_clasificacion(): void {
		$proveedor = new ProveedorLenguajeSecuencial(
			array(
				'{"tema": "economia", "gravedad": 30, "polaridad": "x", "novedad": "primicia", "potencialConversacional": 50, "tipoNoticia": "dato_economico"}',
				'{"candidatos": [{"tesis": "x", "puntuacionOriginalidad": 80, "puntuacionCompatibilidadLinea": 80, "puntuacionSustento": 80, "puntuacionConversacional": 80}]}',
				'{"casoEnContra": "caso débil", "fuerzaSustento": 5}',
				'{"gancho": "g", "hechosEsencialesConAtribucion": "h", "movimientosArgumentales": ["m1", "m2"], "contraargumentoReconocido": "c", "remate": "r"}',
			)
		);

		$resultado = $this->construirDecision( $proveedor, $this->periodista(), modoRespetoActivo: false )->decidir( $this->expediente() );

		self::assertSame( Tono::Analitico, $resultado['ficha']->tonoDominante );
		self::assertSame( Tono::Persuasivo, $resultado['ficha']->tonoApoyo );
	}
}
