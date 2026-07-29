<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Seo;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Mockery;
use Pluma\Datos\RepositorioExperimentosTitularInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Investigacion\Expediente;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\Pieza;
use Pluma\Redaccion\CandidatoTesis;
use Pluma\Redaccion\ClasificacionNoticia;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EsqueletoPieza;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\FichaDecisionEditorial;
use Pluma\Redaccion\GeneradorTitularAlternativo;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\NovedadNoticia;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Seo\ExperimentoTitular;
use Pluma\Seo\GestorExperimentosTitular;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Nivel Cuatro Y.2 — el experimento de titular editorial.
 *
 * @covers \Pluma\Seo\GestorExperimentosTitular
 */
final class GestorExperimentosTitularTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();
		GestorExperimentosTitular::reiniciarCachePorPeticion();
	}

	private function periodista(): Periodista {
		$diales   = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas   = new ReglasConducta( 'linea', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 1, 1, $diales, $reglas, $matriz, true, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

		return new Periodista( 7, 'Valentina Ruiz', null, 'Bio.', RolPeriodista::Columnista, array(), EstadoPeriodista::Activo, $conducta, new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ), new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ) );
	}

	private function fichaConTesis(): FichaDecisionEditorial {
		return new FichaDecisionEditorial(
			7,
			1,
			new ClasificacionNoticia( 'economia', 30, 'x', NovedadNoticia::Primicia, 50, TipoNoticia::DatoEconomico ),
			array( new CandidatoTesis( 'la inflación estructural no cede', 80.0, 80.0, 80.0, 80.0 ) ),
			0,
			Tono::Analitico,
			Tono::Persuasivo,
			new EsqueletoPieza( 'gancho', 'hechos', array( 'm1' ), 'contra', 'remate' ),
			new DateTimeImmutable( '2026-07-22T12:00:00+00:00' )
		);
	}

	private function pieza( ?int $postId, ?int $periodistaId = 7, ?FichaDecisionEditorial $ficha = null ): Pieza {
		$reloj = new DateTimeImmutable( '2026-07-22T12:00:00+00:00' );

		return new Pieza( 1, 100, EstadoPieza::Publicada, new Expediente( 'tendencia', array() ), $postId, $reloj, $reloj, $periodistaId, null, $ficha );
	}

	private function experimento( int $id, int $postId ): ExperimentoTitular {
		$reloj = new RelojFijo();

		return new ExperimentoTitular( $id, 1, $postId, 'Titular A', 'Titular B', 10, 2, 10, 5, null, null, $reloj->ahora() );
	}

	private function construir(
		?RepositorioExperimentosTitularInterface $experimentos = null,
		?RepositorioPiezasInterface $piezas = null,
		?RepositorioPeriodistasInterface $periodistas = null,
		string $jsonRespuesta = '{"tituloAlternativo": "Titular B"}'
	): GestorExperimentosTitular {
		return new GestorExperimentosTitular(
			$experimentos ?? Mockery::mock( RepositorioExperimentosTitularInterface::class ),
			$piezas ?? Mockery::mock( RepositorioPiezasInterface::class ),
			$periodistas ?? Mockery::mock( RepositorioPeriodistasInterface::class ),
			new GeneradorTitularAlternativo( new ProveedorLenguajeFalso( $jsonRespuesta ) ),
			new RelojFijo()
		);
	}

	public function test_procesar_publicacion_sin_post_id_no_hace_nada(): void {
		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorId' )->with( 1 )->andReturn( $this->pieza( null ) );

		$this->construir( null, $piezas )->procesarPublicacion( 1 );

		self::assertTrue( true );
	}

	public function test_procesar_publicacion_crea_el_experimento(): void {
		Functions\when( 'get_post' )->justReturn( (object) array( 'post_title' => 'Titular A' ) );

		$piezas = Mockery::mock( RepositorioPiezasInterface::class );
		$piezas->expects( 'obtenerPorId' )->with( 1 )->andReturn( $this->pieza( 50, 7, $this->fichaConTesis() ) );

		$periodistas = Mockery::mock( RepositorioPeriodistasInterface::class );
		$periodistas->expects( 'obtenerPorId' )->with( 7 )->andReturn( $this->periodista() );

		$experimentos = Mockery::mock( RepositorioExperimentosTitularInterface::class );
		$experimentos->expects( 'crear' )->with( 1, 50, 'Titular A', 'Titular B', Mockery::any() )->andReturn( 9 );

		$this->construir( $experimentos, $piezas, $periodistas )->procesarPublicacion( 1 );

		self::assertTrue( true );
	}

	public function test_servir_y_registrar_sin_experimento_deja_pasar_el_titulo_original(): void {
		$experimentos = Mockery::mock( RepositorioExperimentosTitularInterface::class );
		$experimentos->expects( 'obtenerPorPostId' )->with( 50 )->andReturn( null );

		$resultado = $this->construir( $experimentos )->servirYRegistrar( 'Titular original', 50 );

		self::assertSame( 'Titular original', $resultado );
	}

	public function test_servir_y_registrar_con_experimento_incrementa_impresion_fuera_del_singular(): void {
		Functions\when( 'is_singular' )->justReturn( false );

		$experimentos = Mockery::mock( RepositorioExperimentosTitularInterface::class );
		$experimentos->expects( 'obtenerPorPostId' )->with( 50 )->andReturn( $this->experimento( 9, 50 ) );
		$experimentos->expects( 'incrementarImpresion' )->with( 9, Mockery::on( static fn ( $v ) => in_array( $v, array( 'a', 'b' ), true ) ) )->once();
		$experimentos->expects( 'incrementarClic' )->never();

		$resultado = $this->construir( $experimentos )->servirYRegistrar( 'Titular original', 50 );

		self::assertContains( $resultado, array( 'Titular A', 'Titular B' ) );
	}

	public function test_servir_y_registrar_con_experimento_incrementa_clic_en_el_singular(): void {
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_the_ID' )->justReturn( 50 );

		$experimentos = Mockery::mock( RepositorioExperimentosTitularInterface::class );
		$experimentos->expects( 'obtenerPorPostId' )->with( 50 )->andReturn( $this->experimento( 9, 50 ) );
		$experimentos->expects( 'incrementarClic' )->once();
		$experimentos->expects( 'incrementarImpresion' )->never();

		$this->construir( $experimentos )->servirYRegistrar( 'Titular original', 50 );

		self::assertTrue( true );
	}

	public function test_consolidar_vencidos_elige_el_ganador_por_ctr_y_actualiza_el_post(): void {
		Functions\when( 'get_option' )->justReturn( false );
		$actualizaciones = array();
		Functions\when( 'wp_update_post' )->alias(
			static function ( array $datos ) use ( &$actualizaciones ) {
				$actualizaciones[] = $datos;

				return $datos['ID'];
			}
		);

		// B: 5 clics / 10 impresiones = 0.5 > A: 2/10 = 0.2 -> gana B.
		$experimentos = Mockery::mock( RepositorioExperimentosTitularInterface::class );
		$experimentos->expects( 'obtenerListosParaConsolidar' )->andReturn( array( $this->experimento( 9, 50 ) ) );
		$experimentos->expects( 'consolidar' )->with( 9, 'b', Mockery::any() )->andReturn( true );

		$consolidados = $this->construir( $experimentos )->consolidarVencidos();

		self::assertSame( 1, $consolidados );
		self::assertSame( 50, $actualizaciones[0]['ID'] );
		self::assertSame( 'Titular B', $actualizaciones[0]['post_title'] );
	}
}
