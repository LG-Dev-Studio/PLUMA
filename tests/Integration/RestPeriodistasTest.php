<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Admin\RestPeriodistas;
use Pluma\Datos\RepositorioPeriodistas;
use Pluma\Datos\RepositorioPiezas;
use Pluma\Datos\RepositorioTendencias;
use Pluma\Kernel\Activador;
use Pluma\Kernel\Nucleo;
use Pluma\Kernel\RelojSistema;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Redaccion\CandidatoTesis;
use Pluma\Redaccion\ClasificacionNoticia;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EsqueletoPieza;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\FichaDecisionEditorial;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\NovedadNoticia;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Sensores\PuntuacionOportunidad;
use Pluma\Sensores\TendenciaDetectada;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Banco de Periodistas + Estudio de Conducta (Libro Cap. 10.2) contra
 * WordPress real: capacidad propia `pluma_gestionar_periodistas`, métricas
 * vivas reales, y la vista previa respetando el presupuesto compartido sin
 * bypass (en wp-env no hay llave de OpenRouter configurada, así que el
 * camino determinista y sin red es "sin credenciales" → 503).
 *
 * @covers \Pluma\Admin\RestPeriodistas
 * @covers \Pluma\Datos\RepositorioPiezas
 */
final class RestPeriodistasTest extends WP_UnitTestCase {

	private function registrarRutas(): void {
		$nucleo = new Nucleo();
		$nucleo->contenedor()->obtener( RestPeriodistas::class )->registrar();
		do_action( 'rest_api_init' );
	}

	private function diales(): Diales {
		return new Diales( 60, 40, 20, 60, 50, 50, 60, 50 );
	}

	private function reglas(): ReglasConducta {
		return new ReglasConducta( 'Línea de prueba.', array(), array(), array(), TratamientoLector::Tu, 'Pregunta de cierre.' );
	}

	private function matriz(): MatrizTonos {
		return MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
	}

	private function crearPeriodista( string $nombre, EstadoPeriodista $estado = EstadoPeriodista::Activo ): int {
		global $wpdb;

		return ( new RepositorioPeriodistas( $wpdb ) )->crear(
			$nombre,
			null,
			'Biografía de prueba.',
			RolPeriodista::Columnista,
			array(),
			$estado,
			$this->diales(),
			$this->reglas(),
			$this->matriz(),
			( new RelojSistema() )->ahora()
		);
	}

	/**
	 * Crea y publica una Pieza asignada a `$periodistaId` con un tema real
	 * en su Ficha de Decisión Editorial — la base real de "verticalesTop".
	 */
	private function crearPiezaPublicada( int $periodistaId, int $periodistaVersionId, string $tema ): void {
		global $wpdb;
		$repoTendencias = new RepositorioTendencias( $wpdb );
		$repoPiezas     = new RepositorioPiezas( $wpdb );
		$reloj          = new RelojSistema();

		$tendenciaId = $repoTendencias->guardar(
			new TendenciaDetectada( 'tendencia metricas ' . uniqid(), PuntuacionOportunidad::calcular( 50, 50 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);
		$piezaId     = $repoPiezas->crear( $tendenciaId, $reloj->ahora() );

		$repoPiezas->asignarPeriodista( $piezaId, $periodistaId, $periodistaVersionId, $reloj->ahora() );

		$ficha = new FichaDecisionEditorial(
			$periodistaId,
			$periodistaVersionId,
			new ClasificacionNoticia( $tema, 30, 'neutra', NovedadNoticia::Primicia, 50, TipoNoticia::DatoEconomico ),
			array( new CandidatoTesis( 'tesis', 80.0, 70.0, 90.0, 60.0 ) ),
			0,
			Tono::Analitico,
			Tono::Persuasivo,
			new EsqueletoPieza( 'gancho', 'hechos', array( 'm1' ), 'contra', 'remate' ),
			$reloj->ahora()
		);
		$repoPiezas->actualizarFichaDecisionEditorial( $piezaId, $ficha, $reloj->ahora() );

		$repoPiezas->actualizarEstado( $piezaId, EstadoPieza::Detectada, EstadoPieza::Publicada, $reloj->ahora() );
	}

	public function test_rechaza_a_quien_no_tiene_la_capacidad(): void {
		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/periodistas' ) );

		self::assertContains( $respuesta->get_status(), array( 401, 403 ) );
	}

	public function test_listar_incluye_metricas_reales_por_periodista(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		global $wpdb;
		$periodistaId = $this->crearPeriodista( 'Periodista con Métricas' );
		$periodista   = ( new RepositorioPeriodistas( $wpdb ) )->obtenerPorId( $periodistaId );

		$this->crearPiezaPublicada( $periodistaId, $periodista->conductaActual->id, 'economia' );
		$this->crearPiezaPublicada( $periodistaId, $periodista->conductaActual->id, 'economia' );

		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/periodistas' ) );
		self::assertSame( 200, $respuesta->get_status() );

		$tarjeta = $this->tarjetaDe( $respuesta->get_data(), $periodistaId );
		self::assertNotNull( $tarjeta );
		self::assertSame( 2, $tarjeta['metricas']['piezasPublicadas'] );
		self::assertContains( 'economia', $tarjeta['metricas']['verticalesTop'] );
	}

	public function test_detalle_incluye_diales_reglas_matriz_y_memoria(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$periodistaId = $this->crearPeriodista( 'Periodista Detalle' );

		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', "/pluma/v1/periodistas/{$periodistaId}" ) );
		$datos     = $respuesta->get_data();

		self::assertSame( 200, $respuesta->get_status() );
		self::assertSame( 60, $datos['diales']['agudezaCritica'] );
		self::assertSame( 'Línea de prueba.', $datos['reglasConducta']['lineaEditorial'] );
		self::assertSame( 'analitico', $datos['matrizTonos']['dato_economico']['tonoDominante'] );
		self::assertSame( array(), $datos['memoriaReciente'] );
	}

	public function test_crear_desde_plantilla(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/periodistas/plantilla' );
		$peticion->set_param( 'plantilla', 'analista' );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 201, $respuesta->get_status() );

		global $wpdb;
		$periodista = ( new RepositorioPeriodistas( $wpdb ) )->obtenerPorId( $respuesta->get_data()['id'] );
		self::assertSame( 'Marcos Iriarte', $periodista->nombre );
	}

	public function test_crear_custom_con_especialidades_y_rol_arbitrarios(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/periodistas' );
		$peticion->set_body_params(
			array(
				'nombre'             => 'Periodista Personalizado',
				'biografia'          => 'Cubre deportes y ciencia.',
				'rol'                => 'cronista',
				'cubreTodosLosTemas' => false,
				'especialidades'     => array(
					array(
						'vertical'     => 'deportes',
						'nivelDominio' => 4,
					),
					array(
						'vertical'     => 'ciencia',
						'nivelDominio' => 3,
					),
				),
			)
		);
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 201, $respuesta->get_status() );

		global $wpdb;
		$periodista = ( new RepositorioPeriodistas( $wpdb ) )->obtenerPorId( $respuesta->get_data()['id'] );
		self::assertNotNull( $periodista );
		self::assertSame( 'Periodista Personalizado', $periodista->nombre );
		self::assertSame( RolPeriodista::Cronista, $periodista->rol );
		self::assertSame( 4, $periodista->dominioDe( 'deportes' ) );
		self::assertSame( 3, $periodista->dominioDe( 'ciencia' ) );
	}

	public function test_crear_custom_con_cubre_todos_los_temas_construye_el_comodin(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/periodistas' );
		$peticion->set_body_params(
			array(
				'nombre'              => 'Periodista Generalista',
				'biografia'           => 'Cubre lo que haga falta.',
				'rol'                 => 'cronista',
				'cubreTodosLosTemas'  => true,
				'nivelDominioComodin' => 3,
			)
		);
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 201, $respuesta->get_status() );

		global $wpdb;
		$periodista = ( new RepositorioPeriodistas( $wpdb ) )->obtenerPorId( $respuesta->get_data()['id'] );
		self::assertNotNull( $periodista );
		// Nunca visto antes: solo el comodín puede responder.
		self::assertSame( 3, $periodista->dominioDe( 'un tema completamente nuevo' ) );
	}

	public function test_crear_custom_con_rol_invalido_devuelve_400(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/periodistas' );
		$peticion->set_body_params(
			array(
				'nombre'              => 'X',
				'biografia'           => 'Y',
				'rol'                 => 'rol-que-no-existe',
				'cubreTodosLosTemas'  => true,
				'nivelDominioComodin' => 3,
			)
		);
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 400, $respuesta->get_status() );
	}

	public function test_crear_custom_sin_especialidades_ni_comodin_devuelve_400(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/periodistas' );
		$peticion->set_body_params(
			array(
				'nombre'             => 'X',
				'biografia'          => 'Y',
				'rol'                => 'analista',
				'cubreTodosLosTemas' => false,
				'especialidades'     => array(),
			)
		);
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 400, $respuesta->get_status() );
	}

	public function test_crear_custom_con_nivel_de_dominio_fuera_de_rango_devuelve_400(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/periodistas' );
		$peticion->set_body_params(
			array(
				'nombre'             => 'X',
				'biografia'          => 'Y',
				'rol'                => 'analista',
				'cubreTodosLosTemas' => false,
				'especialidades'     => array(
					array(
						'vertical'     => 'economia',
						'nivelDominio' => 9,
					),
				),
			)
		);
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 400, $respuesta->get_status() );
	}

	/**
	 * `ADR 0012`: solo `es-ES` tiene cobertura hoy (`Pluma\Idioma\ResolutorPerfilIdioma`)
	 * — el borde REST rechaza cualquier otro locale en vez de degradar en
	 * silencio al catálogo de `es-ES`.
	 */
	public function test_crear_custom_con_locale_no_soportado_devuelve_400(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/periodistas' );
		$peticion->set_body_params(
			array(
				'nombre'              => 'X',
				'biografia'           => 'Y',
				'rol'                 => 'analista',
				'localeEditorial'     => 'fr-FR',
				'cubreTodosLosTemas'  => true,
				'nivelDominioComodin' => 3,
			)
		);
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 400, $respuesta->get_status() );
	}

	public function test_crear_custom_persiste_el_locale_editorial_declarado(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/periodistas' );
		$peticion->set_body_params(
			array(
				'nombre'              => 'X',
				'biografia'           => 'Y',
				'rol'                 => 'analista',
				'localeEditorial'     => 'es-ES',
				'cubreTodosLosTemas'  => true,
				'nivelDominioComodin' => 3,
			)
		);
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 201, $respuesta->get_status() );

		global $wpdb;
		$periodista = ( new RepositorioPeriodistas( $wpdb ) )->obtenerPorId( $respuesta->get_data()['id'] );
		self::assertNotNull( $periodista );
		self::assertSame( 'es-ES', $periodista->localeEditorial );
	}

	public function test_editar_identidad_cambia_especialidades_y_preserva_la_conducta(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$periodistaId = $this->crearPeriodista( 'Dexter de Prueba' );

		global $wpdb;
		$repo  = new RepositorioPeriodistas( $wpdb );
		$antes = $repo->obtenerPorId( $periodistaId );
		self::assertNotNull( $antes );
		$versionAntes = $antes->conductaActual->id;

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'PUT', "/pluma/v1/periodistas/{$periodistaId}" );
		$peticion->set_body_params(
			array(
				'nombre'             => $antes->nombre,
				'biografia'          => $antes->biografia,
				'rol'                => $antes->rol->value,
				'cubreTodosLosTemas' => false,
				'especialidades'     => array(
					array(
						'vertical'     => 'deportes',
						'nivelDominio' => 4,
					),
				),
			)
		);
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 200, $respuesta->get_status() );

		$despues = $repo->obtenerPorId( $periodistaId );
		self::assertNotNull( $despues );
		self::assertSame( 4, $despues->dominioDe( 'deportes' ) );
		// Aislamiento de capas: editar Identidad no toca Conducta.
		self::assertSame( $versionAntes, $despues->conductaActual->id );
	}

	public function test_editar_identidad_de_un_periodista_inexistente_devuelve_404(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'PUT', '/pluma/v1/periodistas/999999' );
		$peticion->set_body_params(
			array(
				'nombre'              => 'X',
				'biografia'           => 'Y',
				'rol'                 => 'analista',
				'cubreTodosLosTemas'  => true,
				'nivelDominioComodin' => 3,
			)
		);
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 404, $respuesta->get_status() );
	}

	public function test_crear_y_editar_identidad_rechazan_a_quien_no_tiene_la_capacidad(): void {
		$this->registrarRutas();

		$crear = rest_get_server()->dispatch( new WP_REST_Request( 'POST', '/pluma/v1/periodistas' ) );
		self::assertContains( $crear->get_status(), array( 401, 403 ) );

		$editar = rest_get_server()->dispatch( new WP_REST_Request( 'PUT', '/pluma/v1/periodistas/1' ) );
		self::assertContains( $editar->get_status(), array( 401, 403 ) );
	}

	public function test_clonar_copia_identidad_y_conducta_bajo_un_nombre_nuevo(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$periodistaId = $this->crearPeriodista( 'Periodista Original' );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', "/pluma/v1/periodistas/{$periodistaId}/clonar" );
		$peticion->set_param( 'nombre', 'Periodista Clonado' );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 201, $respuesta->get_status() );

		global $wpdb;
		$clon = ( new RepositorioPeriodistas( $wpdb ) )->obtenerPorId( $respuesta->get_data()['id'] );
		self::assertSame( 'Periodista Clonado', $clon->nombre );
		self::assertSame( 60, $clon->conductaActual->diales->agudezaCritica );
	}

	public function test_ajustar_conducta_crea_una_version_nueva_sin_perder_el_historial(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$periodistaId = $this->crearPeriodista( 'Periodista Ajustable' );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', "/pluma/v1/periodistas/{$periodistaId}/conducta" );
		$peticion->set_param(
			'diales',
			array(
				'agudezaCritica'    => 99,
				'humor'             => 10,
				'satira'            => 5,
				'formalidad'        => 80,
				'vehemencia'        => 20,
				'empatia'           => 70,
				'densidadDatos'     => 90,
				'longitudPreferida' => 40,
			)
		);
		$peticion->set_param( 'reglasConducta', $this->reglas()->aArray() );
		$peticion->set_param( 'matrizTonos', $this->matriz()->aArray() );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 200, $respuesta->get_status() );

		global $wpdb;
		$repo        = new RepositorioPeriodistas( $wpdb );
		$actualizado = $repo->obtenerPorId( $periodistaId );
		self::assertSame( 99, $actualizado->conductaActual->diales->agudezaCritica );
		self::assertCount( 2, $repo->obtenerHistorialVersiones( $periodistaId ) );
	}

	public function test_jubilar_cambia_el_estado_sin_borrar_al_periodista(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$periodistaId = $this->crearPeriodista( 'Periodista a Jubilar' );

		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'POST', "/pluma/v1/periodistas/{$periodistaId}/jubilar" ) );
		self::assertSame( 200, $respuesta->get_status() );

		global $wpdb;
		$periodista = ( new RepositorioPeriodistas( $wpdb ) )->obtenerPorId( $periodistaId );
		self::assertSame( EstadoPeriodista::Jubilado, $periodista->estado );
	}

	public function test_vista_previa_sin_credenciales_configuradas_devuelve_503_sin_llamar_a_la_red(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$periodistaId = $this->crearPeriodista( 'Periodista Vista Previa' );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/periodistas/vista-previa' );
		$peticion->set_param( 'periodistaId', $periodistaId );
		$peticion->set_param( 'diales', $this->diales()->aArray() );
		$peticion->set_param( 'reglasConducta', $this->reglas()->aArray() );
		$peticion->set_param( 'matrizTonos', $this->matriz()->aArray() );
		$respuesta = rest_get_server()->dispatch( $peticion );

		// Sin OPCION_LLAVE_CIFRADA configurada en wp-env: ProveedorOpenRouter
		// lanza "sin credenciales" antes de cualquier llamada de red real.
		self::assertSame( 503, $respuesta->get_status() );
	}

	public function test_vista_previa_sin_conducta_candidata_devuelve_400(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$periodistaId = $this->crearPeriodista( 'Periodista Sin Conducta' );

		$this->registrarRutas();

		$peticion = new WP_REST_Request( 'POST', '/pluma/v1/periodistas/vista-previa' );
		$peticion->set_param( 'periodistaId', $periodistaId );
		$respuesta = rest_get_server()->dispatch( $peticion );

		self::assertSame( 400, $respuesta->get_status() );
	}

	public function test_un_periodista_inexistente_devuelve_404(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->registrarRutas();

		$respuesta = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/pluma/v1/periodistas/999999' ) );

		self::assertSame( 404, $respuesta->get_status() );
	}

	/**
	 * @param list<array<string, mixed>> $tarjetas
	 * @return array<string, mixed>|null
	 */
	private function tarjetaDe( array $tarjetas, int $periodistaId ): ?array {
		foreach ( $tarjetas as $tarjeta ) {
			if ( $tarjeta['id'] === $periodistaId ) {
				return $tarjeta;
			}
		}

		return null;
	}
}
