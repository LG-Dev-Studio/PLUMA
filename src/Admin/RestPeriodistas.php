<?php

declare(strict_types=1);

namespace Pluma\Admin;

use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioMemoriaEditorialInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Kernel\Capacidades;
use Pluma\Kernel\ContextoEjecucion;
use Pluma\Kernel\RelojInterface;
use Pluma\Pipeline\AccionNoDisponibleException;
use Pluma\Pipeline\GestorSalaRevision;
use Pluma\Pipeline\Orquestador;
use Pluma\Pipeline\PeriodistaNoEncontradoException;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMemoria;
use Pluma\Redaccion\Especialidad;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\GeneradorVistaPrevia;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\PlantillaPeriodista;
use Pluma\Redaccion\PlantillasSiembra;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Proveedores\OrigenLlamada;
use Pluma\Proveedores\ProveedorLenguajeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Banco de Periodistas + Estudio de Conducta (Libro Cap. 10.2, "la pantalla
 * estrella"): tarjetas con métricas vivas, crear desde plantilla, clonar,
 * ajustar la Conducta (nueva versión, nunca sobrescribe), jubilar, y la
 * vista previa en vivo. Protegido con `pluma_gestionar_periodistas` — la
 * misma capacidad que ya protege export/import del banco.
 */
final class RestPeriodistas {

	private const RUTA_LISTAR = '/periodistas';
	// Mismo path que RUTA_LISTAR: WordPress distingue por método HTTP (GET
	// listar, POST crear) — mismo patrón ya usado por RUTA_LLAVE en
	// RestSalaMaquinas (POST/DELETE en un único array de configs).
	private const RUTA_CREAR                 = '/periodistas';
	private const RUTA_PLANTILLAS            = '/periodistas/plantillas';
	private const RUTA_CREAR_DESDE_PLANTILLA = '/periodistas/plantilla';
	private const RUTA_VISTA_PREVIA          = '/periodistas/vista-previa';
	private const RUTA_DETALLE               = '/periodistas/(?P<id>\d+)';
	// Mismo patrón de regex que RUTA_DETALLE: GET detalle, PUT edita Identidad.
	private const RUTA_IDENTIDAD = '/periodistas/(?P<id>\d+)';
	private const RUTA_CLONAR    = '/periodistas/(?P<id>\d+)/clonar';
	private const RUTA_CONDUCTA  = '/periodistas/(?P<id>\d+)/conducta';
	private const RUTA_JUBILAR   = '/periodistas/(?P<id>\d+)/jubilar';
	// Trabajo posterior a la Etapa 9 (creación automática de periodistas):
	// acciones humanas sobre un periodista Propuesto antes de que expire su
	// ventana de veto — mismo patrón que RestSalaRevision::aprobarAhora()/descartar().
	private const RUTA_APROBAR_PROPUESTA_AHORA = '/periodistas/(?P<id>\d+)/aprobar-ahora';
	private const RUTA_DESCARTAR_PROPUESTA     = '/periodistas/(?P<id>\d+)/propuesta';

	private const LIMITE_MEMORIA_RECIENTE = 20;

	/**
	 * Rango válido de `nivelDominio` (Libro Cap. 5.2, Capa 1 — Identidad): el
	 * DTO `Especialidad` no lo valida (es un DTO sin lógica), así que el
	 * borde REST es donde corresponde rechazar un nivel fuera de rango.
	 */
	private const NIVEL_DOMINIO_MINIMO = 1;
	private const NIVEL_DOMINIO_MAXIMO = 5;

	/**
	 * @var array<string, callable(): PlantillaPeriodista>
	 */
	private const PLANTILLAS = array(
		'analista'         => array( PlantillasSiembra::class, 'analistaDeDatosSobrio' ),
		'columnista'       => array( PlantillasSiembra::class, 'columnistaCriticaVehemente' ),
		'cronista'         => array( PlantillasSiembra::class, 'cronistaSatirico' ),
		'cronista_factual' => array( PlantillasSiembra::class, 'cronistaFactual' ),
	);

	public function __construct(
		private readonly RepositorioPeriodistasInterface $periodistas,
		private readonly RepositorioPiezasInterface $piezas,
		private readonly RepositorioMemoriaEditorialInterface $memoria,
		private readonly GeneradorVistaPrevia $generadorVistaPrevia,
		private readonly RelojInterface $reloj,
		private readonly GestorSalaRevision $gestorSalaRevision,
		private readonly ContextoEjecucion $contextoEjecucion,
	) {
	}

	public function registrar(): void {
		add_action( 'rest_api_init', array( $this, 'registrarRutas' ) );
	}

	public function registrarRutas(): void {
		register_rest_route(
			'pluma/v1',
			self::RUTA_PLANTILLAS,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'plantillas' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_LISTAR,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'listar' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_CREAR,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'crear' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_IDENTIDAD,
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'editarIdentidad' ),
				'permission_callback' => array( $this, 'autorizado' ),
				'args'                => $this->argumentoId(),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_CREAR_DESDE_PLANTILLA,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'crearDesdePlantilla' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_VISTA_PREVIA,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'vistaPrevia' ),
				'permission_callback' => array( $this, 'autorizado' ),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_DETALLE,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'detalle' ),
				'permission_callback' => array( $this, 'autorizado' ),
				'args'                => $this->argumentoId(),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_CLONAR,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'clonar' ),
				'permission_callback' => array( $this, 'autorizado' ),
				'args'                => $this->argumentoId(),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_CONDUCTA,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'ajustarConducta' ),
				'permission_callback' => array( $this, 'autorizado' ),
				'args'                => $this->argumentoId(),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_JUBILAR,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'jubilar' ),
				'permission_callback' => array( $this, 'autorizado' ),
				'args'                => $this->argumentoId(),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_APROBAR_PROPUESTA_AHORA,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'aprobarPropuestaAhora' ),
				'permission_callback' => array( $this, 'autorizado' ),
				'args'                => $this->argumentoId(),
			)
		);

		register_rest_route(
			'pluma/v1',
			self::RUTA_DESCARTAR_PROPUESTA,
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'descartarPropuesta' ),
				'permission_callback' => array( $this, 'autorizado' ),
				'args'                => $this->argumentoId(),
			)
		);
	}

	public function autorizado(): bool {
		return current_user_can( Capacidades::GESTIONAR_PERIODISTAS );
	}

	public function plantillas(): WP_REST_Response {
		$respuesta = array();

		foreach ( self::PLANTILLAS as $slug => $fabrica ) {
			$plantilla   = call_user_func( $fabrica );
			$respuesta[] = array(
				'slug'      => $slug,
				'nombre'    => $plantilla->nombre,
				'biografia' => $plantilla->biografia,
				'rol'       => $plantilla->rol->value,
			);
		}

		return new WP_REST_Response( $respuesta, 200 );
	}

	public function listar(): WP_REST_Response {
		$respuesta = array_map(
			fn ( Periodista $periodista ): array => $this->resumenTarjeta( $periodista ),
			$this->periodistas->obtenerTodos()
		);

		return new WP_REST_Response( $respuesta, 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function detalle( WP_REST_Request $request ) {
		$periodista = $this->periodistas->obtenerPorId( (int) $request->get_param( 'id' ) );

		if ( null === $periodista ) {
			return $this->errorNoEncontrado();
		}

		$memoriaReciente = array_map(
			static fn ( EntradaMemoria $entrada ): array => array(
				'tipo'      => $entrada->tipo->value,
				'tema'      => $entrada->tema,
				'contenido' => $entrada->contenido,
				'creadaEn'  => $entrada->creadaEn->format( DATE_ATOM ),
			),
			$this->memoria->obtenerPorPeriodista( $periodista->id, null, self::LIMITE_MEMORIA_RECIENTE )
		);

		return new WP_REST_Response(
			array(
				'id'                  => $periodista->id,
				'nombre'              => $periodista->nombre,
				'avatarUrl'           => $periodista->avatarUrl,
				'biografia'           => $periodista->biografia,
				'rol'                 => $periodista->rol->value,
				'especialidades'      => array_map( static fn ( $e ): array => $e->aArray(), $periodista->especialidades ),
				'estado'              => $periodista->estado->value,
				'diales'              => $periodista->conductaActual->diales->aArray(),
				'reglasConducta'      => $periodista->conductaActual->reglas->aArray(),
				'matrizTonos'         => $periodista->conductaActual->matrizTonos->aArray(),
				'metricas'            => $this->piezas->metricasPorPeriodista( $periodista->id ),
				'memoriaReciente'     => $memoriaReciente,
				'ventanaVetoExpiraEn' => $this->ventanaVetoExpiraEn( $periodista ),
			),
			200
		);
	}

	/**
	 * Creación personalizada (aditiva a `crearDesdePlantilla()`, que se
	 * mantiene intacta como camino rápido): el editor declara nombre, rol,
	 * biografía y especialidades reales, en vez de partir de una de las 4
	 * plantillas fijas. La Conducta inicial parte de la plantilla neutra
	 * `analistaDeDatosSobrio()` — el editor la ajusta después en el Estudio
	 * de Conducta ya existente, que se abre automáticamente tras crear.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function crear( WP_REST_Request $request ) {
		$identidad = $this->identidadDesdeRequest( $request );

		if ( $identidad instanceof WP_Error ) {
			return $identidad;
		}

		$conductaBase = PlantillasSiembra::analistaDeDatosSobrio();

		$id = $this->periodistas->crear(
			$identidad['nombre'],
			$identidad['avatarUrl'],
			$identidad['biografia'],
			$identidad['rol'],
			$identidad['especialidades'],
			EstadoPeriodista::Activo,
			$conductaBase->diales,
			$conductaBase->reglas,
			$conductaBase->matrizTonos,
			$this->reloj->ahora()
		);

		return new WP_REST_Response( array( 'id' => $id ), 201 );
	}

	/**
	 * Edita la Identidad (nombre/avatarUrl/biografia/rol/especialidades) de
	 * un periodista YA EXISTENTE, sin tocar su Conducta — el caso de uso
	 * directo de ampliar la cobertura de un periodista que quedó con Piezas
	 * en SIN_PERIODISTA_IDONEO.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function editarIdentidad( WP_REST_Request $request ) {
		$periodistaId = (int) $request->get_param( 'id' );

		if ( null === $this->periodistas->obtenerPorId( $periodistaId ) ) {
			return $this->errorNoEncontrado();
		}

		$identidad = $this->identidadDesdeRequest( $request );

		if ( $identidad instanceof WP_Error ) {
			return $identidad;
		}

		$this->periodistas->actualizarIdentidad(
			$periodistaId,
			$identidad['nombre'],
			$identidad['avatarUrl'],
			$identidad['biografia'],
			$identidad['rol'],
			$identidad['especialidades'],
			$this->reloj->ahora()
		);

		return new WP_REST_Response( array( 'id' => $periodistaId ), 200 );
	}

	/**
	 * Validación compartida entre `crear()` y `editarIdentidad()`. El front
	 * nunca envía el vertical sentinela directamente: envía el toggle
	 * `cubreTodosLosTemas` + `nivelDominioComodin`, y aquí se construye la
	 * `Especialidad` comodín — así el contrato público no depende del valor
	 * interno del sentinela.
	 *
	 * @return array{nombre: string, avatarUrl: ?string, biografia: string, rol: RolPeriodista, especialidades: list<Especialidad>}|WP_Error
	 */
	private function identidadDesdeRequest( WP_REST_Request $request ) {
		$nombre = $request->get_param( 'nombre' );

		if ( ! is_string( $nombre ) || '' === trim( $nombre ) ) {
			return $this->errorIdentidadInvalida( __( 'El nombre es obligatorio.', 'pluma-engine' ) );
		}

		$biografia = $request->get_param( 'biografia' );

		if ( ! is_string( $biografia ) || '' === trim( $biografia ) ) {
			return $this->errorIdentidadInvalida( __( 'La biografía es obligatoria.', 'pluma-engine' ) );
		}

		$avatarUrlParam = $request->get_param( 'avatarUrl' );
		$avatarUrl      = is_string( $avatarUrlParam ) && '' !== trim( $avatarUrlParam ) ? esc_url_raw( $avatarUrlParam ) : null;

		$rolParam = $request->get_param( 'rol' );
		$rol      = is_string( $rolParam ) ? RolPeriodista::tryFrom( $rolParam ) : null;

		if ( null === $rol ) {
			return $this->errorIdentidadInvalida( __( 'El rol no es válido.', 'pluma-engine' ) );
		}

		$cubreTodosLosTemas = (bool) $request->get_param( 'cubreTodosLosTemas' );

		if ( $cubreTodosLosTemas ) {
			$nivelComodin = $request->get_param( 'nivelDominioComodin' );

			if ( ! $this->esNivelDominioValido( $nivelComodin ) ) {
				return $this->errorIdentidadInvalida( __( 'El nivel de dominio del comodín debe ser un número entre 1 y 5.', 'pluma-engine' ) );
			}

			return array(
				'nombre'         => sanitize_text_field( $nombre ),
				'avatarUrl'      => $avatarUrl,
				'biografia'      => sanitize_textarea_field( $biografia ),
				'rol'            => $rol,
				'especialidades' => array( new Especialidad( Especialidad::VERTICAL_COMODIN, (int) $nivelComodin ) ),
			);
		}

		$especialidadesParam = $request->get_param( 'especialidades' );

		if ( ! is_array( $especialidadesParam ) || array() === $especialidadesParam ) {
			return $this->errorIdentidadInvalida( __( 'Declara al menos una especialidad, o activa "cubre todos los temas".', 'pluma-engine' ) );
		}

		$especialidades = array();

		foreach ( $especialidadesParam as $entrada ) {
			if ( ! is_array( $entrada ) ) {
				return $this->errorIdentidadInvalida( __( 'Cada especialidad debe tener un vertical y un nivel de dominio.', 'pluma-engine' ) );
			}

			$vertical     = $entrada['vertical'] ?? null;
			$nivelDominio = $entrada['nivelDominio'] ?? null;

			if ( ! is_string( $vertical ) || '' === trim( $vertical ) ) {
				return $this->errorIdentidadInvalida( __( 'Cada especialidad necesita un vertical.', 'pluma-engine' ) );
			}

			if ( ! $this->esNivelDominioValido( $nivelDominio ) ) {
				return $this->errorIdentidadInvalida( __( 'El nivel de dominio de cada especialidad debe ser un número entre 1 y 5.', 'pluma-engine' ) );
			}

			$especialidades[] = new Especialidad( sanitize_text_field( trim( $vertical ) ), (int) $nivelDominio );
		}

		return array(
			'nombre'         => sanitize_text_field( $nombre ),
			'avatarUrl'      => $avatarUrl,
			'biografia'      => sanitize_textarea_field( $biografia ),
			'rol'            => $rol,
			'especialidades' => $especialidades,
		);
	}

	private function esNivelDominioValido( mixed $valor ): bool {
		if ( ! is_numeric( $valor ) ) {
			return false;
		}

		// Rechaza decimales (2.5 no es un nivel de dominio válido): comparar
		// el valor contra su propio truncado a entero, ambos como float para
		// que la comparación no dependa del tipo original del parámetro.
		if ( (float) (int) $valor !== (float) $valor ) {
			return false;
		}

		$entero = (int) $valor;

		return $entero >= self::NIVEL_DOMINIO_MINIMO && $entero <= self::NIVEL_DOMINIO_MAXIMO;
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function crearDesdePlantilla( WP_REST_Request $request ) {
		$slug = $request->get_param( 'plantilla' );

		if ( ! is_string( $slug ) || ! isset( self::PLANTILLAS[ $slug ] ) ) {
			return new WP_Error( 'pluma_plantilla_invalida', __( 'Plantilla desconocida.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$plantilla      = call_user_func( self::PLANTILLAS[ $slug ] );
		$nombre         = $request->get_param( 'nombre' );
		$lineaEditorial = $request->get_param( 'lineaEditorial' );
		$reglas         = is_string( $lineaEditorial ) && '' !== trim( $lineaEditorial )
			? new ReglasConducta(
				sanitize_textarea_field( $lineaEditorial ),
				$plantilla->reglas->lineasRojas,
				$plantilla->reglas->muletillas,
				$plantilla->reglas->vocabularioProhibido,
				$plantilla->reglas->tratamientoLector,
				$plantilla->reglas->estiloPreguntaFinal
			)
			: $plantilla->reglas;

		$id = $this->periodistas->crear(
			is_string( $nombre ) && '' !== trim( $nombre ) ? sanitize_text_field( $nombre ) : $plantilla->nombre,
			$plantilla->avatarUrl,
			$plantilla->biografia,
			$plantilla->rol,
			$plantilla->especialidades,
			$plantilla->estado,
			$plantilla->diales,
			$reglas,
			$plantilla->matrizTonos,
			$this->reloj->ahora()
		);

		return new WP_REST_Response( array( 'id' => $id ), 201 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function clonar( WP_REST_Request $request ) {
		$origen = $this->periodistas->obtenerPorId( (int) $request->get_param( 'id' ) );

		if ( null === $origen ) {
			return $this->errorNoEncontrado();
		}

		$nombreNuevo = $request->get_param( 'nombre' );

		if ( ! is_string( $nombreNuevo ) || '' === trim( $nombreNuevo ) ) {
			return new WP_Error( 'pluma_nombre_requerido', __( 'El clon necesita un nombre nuevo.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$id = $this->periodistas->crear(
			sanitize_text_field( $nombreNuevo ),
			$origen->avatarUrl,
			$origen->biografia,
			$origen->rol,
			$origen->especialidades,
			$origen->estado,
			$origen->conductaActual->diales,
			$origen->conductaActual->reglas,
			$origen->conductaActual->matrizTonos,
			$this->reloj->ahora()
		);

		return new WP_REST_Response( array( 'id' => $id ), 201 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function ajustarConducta( WP_REST_Request $request ) {
		$periodistaId = (int) $request->get_param( 'id' );

		if ( null === $this->periodistas->obtenerPorId( $periodistaId ) ) {
			return $this->errorNoEncontrado();
		}

		$conducta = $this->conductaCandidataDesdeRequest( $request );

		if ( null === $conducta ) {
			return $this->errorConductaInvalida();
		}

		[$diales, $reglas, $matriz] = $conducta;

		$versionId = $this->periodistas->nuevaVersionConducta( $periodistaId, $diales, $reglas, $matriz, $this->reloj->ahora() );

		return new WP_REST_Response( array( 'versionId' => $versionId ), 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function jubilar( WP_REST_Request $request ) {
		$periodistaId = (int) $request->get_param( 'id' );

		if ( null === $this->periodistas->obtenerPorId( $periodistaId ) ) {
			return $this->errorNoEncontrado();
		}

		$this->periodistas->jubilar( $periodistaId, $this->reloj->ahora() );

		return new WP_REST_Response( array( 'id' => $periodistaId ), 200 );
	}

	/**
	 * Trabajo posterior a la Etapa 9 (creación automática de periodistas):
	 * promueve un periodista Propuesto a Activo antes de que expire su
	 * ventana de veto, y reanuda de inmediato las Piezas que ya cubre
	 * (`GestorSalaRevision::promoverPeriodistaPropuesto()`).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function aprobarPropuestaAhora( WP_REST_Request $request ) {
		$periodistaId = (int) $request->get_param( 'id' );

		try {
			$this->gestorSalaRevision->promoverPeriodistaPropuesto( $periodistaId, $this->reloj->ahora() );
		} catch ( PeriodistaNoEncontradoException $e ) {
			return $this->errorNoEncontrado();
		} catch ( AccionNoDisponibleException $e ) {
			return new WP_Error( 'pluma_periodista_no_propuesto', $e->getMessage(), array( 'status' => 409 ) );
		}

		return new WP_REST_Response( array( 'id' => $periodistaId ), 200 );
	}

	/**
	 * Trabajo posterior a la Etapa 9 (creación automática de periodistas):
	 * descarta una propuesta rechazada por el editor — las Piezas
	 * contribuyentes quedan intactas en SIN_PERIODISTA_IDONEO.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function descartarPropuesta( WP_REST_Request $request ) {
		$periodistaId = (int) $request->get_param( 'id' );

		try {
			$this->gestorSalaRevision->descartarPeriodistaPropuesto( $periodistaId );
		} catch ( PeriodistaNoEncontradoException $e ) {
			return $this->errorNoEncontrado();
		} catch ( AccionNoDisponibleException $e ) {
			return new WP_Error( 'pluma_periodista_no_propuesto', $e->getMessage(), array( 'status' => 409 ) );
		}

		return new WP_REST_Response( array( 'id' => $periodistaId ), 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function vistaPrevia( WP_REST_Request $request ) {
		$periodistaIdParam = $request->get_param( 'periodistaId' );

		if ( ! is_numeric( $periodistaIdParam ) ) {
			return new WP_Error( 'pluma_periodista_invalido', __( 'Falta el periodista.', 'pluma-engine' ), array( 'status' => 400 ) );
		}

		$periodista = $this->periodistas->obtenerPorId( (int) $periodistaIdParam );

		if ( null === $periodista ) {
			return $this->errorNoEncontrado();
		}

		$conducta = $this->conductaCandidataDesdeRequest( $request );

		if ( null === $conducta ) {
			return $this->errorConductaInvalida();
		}

		[$diales, $reglas, $matriz] = $conducta;

		// NCP-1 (`ADR 0010`): la vista previa en vivo del Estudio de Conducta
		// dispara una llamada real al modelo desde una sesión de panel
		// autenticada por capacidad — origen `panel`, nunca `visitante`.
		$this->contextoEjecucion->declarar( OrigenLlamada::Panel );

		try {
			$texto = $this->generadorVistaPrevia->generar( $periodista, $diales, $reglas, $matriz );
		} catch ( ProveedorLenguajeException $e ) {
			$estado = $e->presupuestoAgotado ? 409 : 503;

			return new WP_Error( 'pluma_vista_previa_no_disponible', $e->getMessage(), array( 'status' => $estado ) );
		}

		return new WP_REST_Response( array( 'texto' => $texto ), 200 );
	}

	/**
	 * @return array{0: Diales, 1: ReglasConducta, 2: MatrizTonos}|null
	 */
	private function conductaCandidataDesdeRequest( WP_REST_Request $request ): ?array {
		$diales      = $request->get_param( 'diales' );
		$reglas      = $request->get_param( 'reglasConducta' );
		$matrizTonos = $request->get_param( 'matrizTonos' );

		if ( ! is_array( $diales ) || ! is_array( $reglas ) || ! is_array( $matrizTonos ) ) {
			return null;
		}

		try {
			/** @var array{agudezaCritica: int, humor: int, satira: int, formalidad: int, vehemencia: int, empatia: int, densidadDatos: int, longitudPreferida: int} $diales */
			/** @var array{lineaEditorial: string, lineasRojas: list<string>, muletillas: list<string>, vocabularioProhibido: list<string>, tratamientoLector: string, estiloPreguntaFinal: string} $reglas */
			/** @var array<string, array{tipoNoticia: string, tonoDominante: string, tonoApoyo: string, nivelSatira: string}> $matrizTonos */
			return array( Diales::desdeArray( $diales ), ReglasConducta::desdeArray( $reglas ), MatrizTonos::desdeArray( $matrizTonos ) );
		} catch ( \Throwable ) {
			return null;
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function resumenTarjeta( Periodista $periodista ): array {
		return array(
			'id'                  => $periodista->id,
			'nombre'              => $periodista->nombre,
			'avatarUrl'           => $periodista->avatarUrl,
			'rol'                 => $periodista->rol->value,
			'especialidades'      => array_map( static fn ( $e ): array => $e->aArray(), $periodista->especialidades ),
			'estado'              => $periodista->estado->value,
			'metricas'            => $this->piezas->metricasPorPeriodista( $periodista->id ),
			'ventanaVetoExpiraEn' => $this->ventanaVetoExpiraEn( $periodista ),
		);
	}

	/**
	 * Trabajo posterior a la Etapa 9 (creación automática de periodistas):
	 * mismo cálculo que `GestorSalaRevision::obtenerColaDeVeto()` hace para
	 * Piezas (`horaLimiteVeto`) — el servidor hace la aritmética de fechas,
	 * nunca el cliente, y solo tiene sentido para un periodista Propuesto.
	 */
	private function ventanaVetoExpiraEn( Periodista $periodista ): ?string {
		if ( EstadoPeriodista::Propuesto !== $periodista->estado ) {
			return null;
		}

		$ventanaVetoHoras = get_option( Orquestador::OPCION_VENTANA_VETO_HORAS, 2 );
		$horas            = is_numeric( $ventanaVetoHoras ) ? (int) $ventanaVetoHoras : 2;

		return $periodista->creadoEn->modify( "+{$horas} hours" )->format( DATE_ATOM );
	}

	private function errorNoEncontrado(): WP_Error {
		return new WP_Error( 'pluma_periodista_no_encontrado', __( 'Periodista no encontrado.', 'pluma-engine' ), array( 'status' => 404 ) );
	}

	private function errorConductaInvalida(): WP_Error {
		return new WP_Error( 'pluma_conducta_invalida', __( 'Faltan o son inválidos los diales, reglas o matriz de tonos.', 'pluma-engine' ), array( 'status' => 400 ) );
	}

	private function errorIdentidadInvalida( string $mensaje ): WP_Error {
		return new WP_Error( 'pluma_identidad_invalida', $mensaje, array( 'status' => 400 ) );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function argumentoId(): array {
		return array(
			'id' => array(
				'required'          => true,
				'validate_callback' => static fn ( $valor ): bool => is_numeric( $valor ),
			),
		);
	}
}
