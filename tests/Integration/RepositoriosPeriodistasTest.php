<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\RepositorioBorradores;
use Pluma\Datos\RepositorioMemoriaEditorial;
use Pluma\Datos\RepositorioPeriodistas;
use Pluma\Datos\RepositorioPiezas;
use Pluma\Datos\RepositorioTendencias;
use Pluma\Kernel\RelojSistema;
use Pluma\Redaccion\AnotacionCorrector;
use Pluma\Redaccion\CandidatoTesis;
use Pluma\Redaccion\ClasificacionNoticia;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\Especialidad;
use Pluma\Redaccion\EsqueletoPieza;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\FichaDecisionEditorial;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\NovedadNoticia;
use Pluma\Redaccion\PuntoCorrector;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoMemoria;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Sensores\PuntuacionOportunidad;
use Pluma\Sensores\TendenciaDetectada;
use WP_UnitTestCase;

/**
 * Banco de periodistas, memoria editorial y borradores (Etapa 2) contra
 * tablas reales (pl-testing: "Integración (wp-env): repositorios pluma_*").
 *
 * @covers \Pluma\Datos\RepositorioPeriodistas
 * @covers \Pluma\Datos\RepositorioMemoriaEditorial
 * @covers \Pluma\Datos\RepositorioBorradores
 */
final class RepositoriosPeriodistasTest extends WP_UnitTestCase {

	private function dialesDePrueba(): Diales {
		return new Diales(
			agudezaCritica: 80,
			humor: 55,
			satira: 40,
			formalidad: 60,
			vehemencia: 65,
			empatia: 50,
			densidadDatos: 70,
			longitudPreferida: 50
		);
	}

	private function reglasDePrueba(): ReglasConducta {
		return new ReglasConducta(
			'Escéptica del poder, optimista de la tecnología.',
			array( 'menores de edad', 'víctimas de violencia sexual' ),
			array( 'abre con una pregunta retórica', 'cierra con una cifra' ),
			array( 'en el ojo del huracán' ),
			TratamientoLector::Tu,
			'¿A quién le crees aquí, y por qué?'
		);
	}

	private function matrizDePrueba(): MatrizTonos {
		return MatrizTonos::desdeFilas(
			array(
				new EntradaMatrizTono( TipoNoticia::AnuncioCorporativo, Tono::Analitico, Tono::Critico, NivelSatiraPermitida::EnRemate ),
				new EntradaMatrizTono( TipoNoticia::EscandaloPolitico, Tono::Critico, Tono::Analitico, NivelSatiraPermitida::ConModeracion ),
				new EntradaMatrizTono( TipoNoticia::CulturaViral, Tono::Humoristico, Tono::Opinion, NivelSatiraPermitida::PiezaCompleta ),
				new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ),
			)
		);
	}

	public function test_crear_periodista_y_leerlo_con_su_conducta_actual(): void {
		global $wpdb;
		$repo  = new RepositorioPeriodistas( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->crear(
			'Valentina Ruiz',
			null,
			'Economista de formación, alérgica a los eufemismos corporativos.',
			RolPeriodista::Columnista,
			array( new Especialidad( 'economia', 5 ), new Especialidad( 'tecnologia', 3 ) ),
			EstadoPeriodista::Activo,
			$this->dialesDePrueba(),
			$this->reglasDePrueba(),
			$this->matrizDePrueba(),
			$reloj->ahora()
		);

		self::assertGreaterThan( 0, $id );

		$periodista = $repo->obtenerPorId( $id );
		self::assertNotNull( $periodista );
		self::assertSame( 'Valentina Ruiz', $periodista->nombre );
		self::assertSame( RolPeriodista::Columnista, $periodista->rol );
		self::assertSame( EstadoPeriodista::Activo, $periodista->estado );
		self::assertSame( 5, $periodista->dominioDe( 'economia' ) );
		self::assertSame( 0, $periodista->dominioDe( 'deportes' ) );
		self::assertSame( 80, $periodista->conductaActual->diales->agudezaCritica );
		self::assertSame( 'Bloqueada', NivelSatiraPermitida::Bloqueada->name );
		self::assertSame(
			NivelSatiraPermitida::Bloqueada,
			$periodista->conductaActual->matrizTonos->paraTipo( TipoNoticia::Tragedia )->nivelSatira,
			'La fila de Tragedia debe imponerse aunque nunca se haya configurado explícitamente.'
		);

		self::assertContains(
			$periodista->id,
			array_map( static fn ( $p ) => $p->id, $repo->obtenerActivos() ),
			'El periodista recién creado debe aparecer en la lista de activos.'
		);
	}

	public function test_nueva_version_de_conducta_no_sobrescribe_la_anterior(): void {
		global $wpdb;
		$repo  = new RepositorioPeriodistas( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->crear(
			'Cronista de Prueba',
			null,
			'Bio.',
			RolPeriodista::Cronista,
			array(),
			EstadoPeriodista::Activo,
			$this->dialesDePrueba(),
			$this->reglasDePrueba(),
			$this->matrizDePrueba(),
			$reloj->ahora()
		);

		$primeraVersionId = $repo->obtenerPorId( $id )->conductaActual->id;

		$dialesNuevos     = new Diales( 90, 55, 40, 60, 65, 50, 70, 50 );
		$segundaVersionId = $repo->nuevaVersionConducta( $id, $dialesNuevos, $this->reglasDePrueba(), $this->matrizDePrueba(), true, $reloj->ahora() );

		self::assertNotSame( $primeraVersionId, $segundaVersionId );

		$versionVieja = $repo->obtenerVersionConducta( $primeraVersionId );
		self::assertNotNull( $versionVieja );
		self::assertSame( 80, $versionVieja->diales->agudezaCritica, 'La versión vieja debe seguir intacta.' );
		self::assertFalse( $versionVieja->respuestasHabilitadas, 'Un periodista nuevo/clonado nunca arranca respondiendo comentarios automáticamente.' );

		$periodistaActualizado = $repo->obtenerPorId( $id );
		self::assertSame( 90, $periodistaActualizado->conductaActual->diales->agudezaCritica );
		self::assertTrue( $periodistaActualizado->conductaActual->respuestasHabilitadas, 'La nueva versión debe reflejar el respuestasHabilitadas que se pidió activar.' );
	}

	public function test_jubilar_saca_al_periodista_de_los_activos(): void {
		global $wpdb;
		$repo  = new RepositorioPeriodistas( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->crear(
			'Satírico de Prueba',
			null,
			'Bio.',
			RolPeriodista::Satirico,
			array(),
			EstadoPeriodista::Activo,
			$this->dialesDePrueba(),
			$this->reglasDePrueba(),
			$this->matrizDePrueba(),
			$reloj->ahora()
		);

		self::assertTrue( $repo->jubilar( $id, $reloj->ahora() ) );

		$periodista = $repo->obtenerPorId( $id );
		self::assertSame( EstadoPeriodista::Jubilado, $periodista->estado );
		self::assertNotContains( $id, array_map( static fn ( $p ) => $p->id, $repo->obtenerActivos() ) );
	}

	public function test_memoria_editorial_registra_y_consulta_posturas_por_tema(): void {
		global $wpdb;
		$repoPeriodistas = new RepositorioPeriodistas( $wpdb );
		$repoMemoria     = new RepositorioMemoriaEditorial( $wpdb );
		$reloj           = new RelojSistema();

		$periodistaId = $repoPeriodistas->crear(
			'Analista de Prueba',
			null,
			'Bio.',
			RolPeriodista::Analista,
			array(),
			EstadoPeriodista::Activo,
			$this->dialesDePrueba(),
			$this->reglasDePrueba(),
			$this->matrizDePrueba(),
			$reloj->ahora()
		);

		self::assertSame( array(), $repoMemoria->obtenerPosturasPorTema( $periodistaId, 'inflacion' ) );

		$repoMemoria->registrar(
			$periodistaId,
			TipoMemoria::Postura,
			'inflacion',
			array(
				'postura' => 'La política monetaria actual es insuficiente.',
				'piezaId' => 42,
			),
			42,
			$reloj->ahora()
		);

		$posturas = $repoMemoria->obtenerPosturasPorTema( $periodistaId, 'inflacion' );
		self::assertCount( 1, $posturas );
		self::assertSame( 'inflacion', $posturas[0]->tema );
		self::assertSame( 'La política monetaria actual es insuficiente.', $posturas[0]->contenido['postura'] );
		self::assertSame( 42, $posturas[0]->piezaId );

		self::assertCount( 1, $repoMemoria->obtenerPorPeriodista( $periodistaId ) );
		self::assertCount( 1, $repoMemoria->obtenerPorPeriodista( $periodistaId, TipoMemoria::Postura ) );
		self::assertCount( 0, $repoMemoria->obtenerPorPeriodista( $periodistaId, TipoMemoria::Cobertura ) );
	}

	/**
	 * Nivel Dos E.2, memoria colectiva del sitio: `obtenerPosturasColectivasPorTema()`
	 * consulta por `tema` SIN filtrar por periodista — a diferencia de
	 * `obtenerPosturasPorTema()`, trae posturas de CUALQUIER periodista del
	 * banco, activo o jubilado, ejercitando en datos reales el nuevo índice
	 * `tema` (independiente del compuesto `periodista_tema`).
	 */
	public function test_memoria_editorial_obtiene_posturas_colectivas_de_cualquier_periodista_por_tema(): void {
		global $wpdb;
		$repoPeriodistas = new RepositorioPeriodistas( $wpdb );
		$repoMemoria     = new RepositorioMemoriaEditorial( $wpdb );
		$reloj           = new RelojSistema();

		$idA = $repoPeriodistas->crear(
			'Periodista Colectivo A',
			null,
			'Bio.',
			RolPeriodista::Analista,
			array(),
			EstadoPeriodista::Activo,
			$this->dialesDePrueba(),
			$this->reglasDePrueba(),
			$this->matrizDePrueba(),
			$reloj->ahora()
		);
		$idB = $repoPeriodistas->crear(
			'Periodista Colectivo B',
			null,
			'Bio.',
			RolPeriodista::Analista,
			array(),
			EstadoPeriodista::Activo,
			$this->dialesDePrueba(),
			$this->reglasDePrueba(),
			$this->matrizDePrueba(),
			$reloj->ahora()
		);

		self::assertSame( array(), $repoMemoria->obtenerPosturasColectivasPorTema( 'reforma-fiscal' ) );

		// Marcas de tiempo explícitas y distintas — `creada_en` es DATETIME
		// (precisión de segundo), y ambos registros pueden caer en el mismo
		// segundo con el reloj real, dejando el orden DESC indeterminado.
		$repoMemoria->registrar( $idA, TipoMemoria::Postura, 'reforma-fiscal', array( 'postura' => 'A favor de la reforma.' ), null, $reloj->ahora()->modify( '-1 minute' ) );
		$repoMemoria->registrar( $idB, TipoMemoria::Postura, 'reforma-fiscal', array( 'postura' => 'En contra de la reforma.' ), null, $reloj->ahora() );
		$repoMemoria->registrar( $idA, TipoMemoria::Cobertura, 'reforma-fiscal', array(), null, $reloj->ahora() );

		$colectivas = $repoMemoria->obtenerPosturasColectivasPorTema( 'reforma-fiscal' );

		self::assertCount( 2, $colectivas );
		self::assertSame( array( $idB, $idA ), array_map( static fn ( $entrada ) => $entrada->periodistaId, $colectivas ) );

		self::assertTrue( $repoPeriodistas->jubilar( $idB, $reloj->ahora() ) );
		self::assertSame( EstadoPeriodista::Jubilado, $repoPeriodistas->obtenerPorId( $idB )->estado );
	}

	public function test_borradores_registra_ciclos_de_revision_y_devuelve_el_ultimo(): void {
		global $wpdb;
		$repoTendencias = new RepositorioTendencias( $wpdb );
		$repoPiezas     = new RepositorioPiezas( $wpdb );
		$repoBorradores = new RepositorioBorradores( $wpdb );
		$reloj          = new RelojSistema();

		$tendenciaId = $repoTendencias->guardar(
			new TendenciaDetectada( 'tendencia para borrador', PuntuacionOportunidad::calcular( 50, 50 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);
		$piezaId     = $repoPiezas->crear( $tendenciaId, $reloj->ahora() );

		$primerBorradorId = $repoBorradores->crear(
			$piezaId,
			1,
			'Primer borrador del periodista.',
			array( new AnotacionCorrector( PuntoCorrector::Hechos, false, 'Una cifra no está en el expediente.' ) ),
			false,
			$reloj->ahora()
		);
		self::assertGreaterThan( 0, $primerBorradorId );

		$repoBorradores->crear(
			$piezaId,
			2,
			'Segundo borrador, corregido.',
			array( new AnotacionCorrector( PuntoCorrector::Hechos, true, 'Todo trazable al expediente.' ) ),
			true,
			$reloj->ahora()
		);

		$historial = $repoBorradores->obtenerPorPieza( $piezaId );
		self::assertCount( 2, $historial );
		self::assertSame( 1, $historial[0]->numeroCiclo );
		self::assertSame( 2, $historial[1]->numeroCiclo );

		$ultimo = $repoBorradores->obtenerUltimo( $piezaId );
		self::assertNotNull( $ultimo );
		self::assertSame( 2, $ultimo->numeroCiclo );
		self::assertTrue( $ultimo->aprobadoPorCorrector );
		self::assertFalse( $ultimo->agotoLosCiclos( 2 ) );
	}

	public function test_asignar_periodista_y_ficha_decision_editorial_sobre_una_pieza(): void {
		global $wpdb;
		$repoTendencias  = new RepositorioTendencias( $wpdb );
		$repoPiezas      = new RepositorioPiezas( $wpdb );
		$repoPeriodistas = new RepositorioPeriodistas( $wpdb );
		$reloj           = new RelojSistema();

		$tendenciaId = $repoTendencias->guardar(
			new TendenciaDetectada( 'tendencia para asignacion', PuntuacionOportunidad::calcular( 50, 50 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);
		$piezaId     = $repoPiezas->crear( $tendenciaId, $reloj->ahora() );

		$periodistaId = $repoPeriodistas->crear(
			'Periodista para Ficha',
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array(),
			EstadoPeriodista::Activo,
			$this->dialesDePrueba(),
			$this->reglasDePrueba(),
			$this->matrizDePrueba(),
			$reloj->ahora()
		);
		$periodista   = $repoPeriodistas->obtenerPorId( $periodistaId );

		self::assertTrue( $repoPiezas->asignarPeriodista( $piezaId, $periodistaId, $periodista->conductaActual->id, $reloj->ahora() ) );

		$pieza = $repoPiezas->obtenerPorId( $piezaId );
		self::assertSame( $periodistaId, $pieza->periodistaId );
		self::assertSame( $periodista->conductaActual->id, $pieza->periodistaVersionId );

		$ficha = new FichaDecisionEditorial(
			$periodistaId,
			$periodista->conductaActual->id,
			new ClasificacionNoticia( 'economia', 30, 'gobierno vs. oposición', NovedadNoticia::Primicia, 60, TipoNoticia::DatoEconomico ),
			array( new CandidatoTesis( 'La cifra oficial esconde el dato real.', 80.0, 70.0, 90.0, 65.0 ) ),
			0,
			Tono::Analitico,
			Tono::Persuasivo,
			new EsqueletoPieza( 'gancho', 'hechos esenciales', array( 'movimiento 1', 'movimiento 2' ), 'contraargumento', 'remate' ),
			$reloj->ahora()
		);

		self::assertTrue( $repoPiezas->actualizarFichaDecisionEditorial( $piezaId, $ficha, $reloj->ahora() ) );

		$piezaConFicha = $repoPiezas->obtenerPorId( $piezaId );
		self::assertNotNull( $piezaConFicha->fichaDecisionEditorial );
		self::assertSame( 'La cifra oficial esconde el dato real.', $piezaConFicha->fichaDecisionEditorial->tesisElegida()->tesis );
	}
}
