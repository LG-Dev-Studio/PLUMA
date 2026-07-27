<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Datos\RepositorioMemoriaEditorialInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\Pieza;
use Pluma\Redaccion\AsignadorPeriodista;
use Pluma\Redaccion\ClasificadorNoticia;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\DecisionEditorial;
use Pluma\Redaccion\DecisionEditorialException;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\Especialidad;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\GeneradorEsqueleto;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\SelectorAngulo;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
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
		$conducta = new ConductaVersion( 7, 1, $diales, $reglas, $matriz, false, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

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

	private function construirDecision( ProveedorLenguajeSecuencial $proveedor, Periodista $periodista ): DecisionEditorial {
		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->method( 'obtenerActivos' )->willReturn( array( $periodista ) );

		$repoMemoria = $this->createMock( RepositorioMemoriaEditorialInterface::class );
		$repoMemoria->method( 'existeCoberturaDelTema' )->willReturn( false );
		$repoMemoria->method( 'obtenerPosturasPorTema' )->willReturn( array() );

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
			new RelojFijo()
		);
	}

	public function test_decidir_encadena_los_cuatro_pasos_y_produce_una_ficha_completa(): void {
		$proveedor = new ProveedorLenguajeSecuencial(
			array(
				'{"tema": "economia", "gravedad": 30, "polaridad": "gobierno vs oposición", "novedad": "primicia", "potencialConversacional": 70, "tipoNoticia": "dato_economico"}',
				'{"candidatos": [{"tesis": "la cifra oficial esconde el dato real", "puntuacionOriginalidad": 80, "puntuacionCompatibilidadLinea": 80, "puntuacionSustento": 90, "puntuacionConversacional": 70}]}',
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
			new RelojFijo()
		);

		$resultado = $decision->decidir( $this->expediente(), 99 );

		self::assertSame( $original->id, $resultado['periodista']->id );
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
			new RelojFijo()
		);

		$this->expectException( DecisionEditorialException::class );

		$decision->decidir( $this->expediente() );
	}
}
