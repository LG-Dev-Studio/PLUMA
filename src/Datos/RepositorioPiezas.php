<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Compuertas\ResultadoEvaluacion;
use Pluma\Investigacion\Expediente;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\Pieza;
use Pluma\Pipeline\TipoPieza;
use Pluma\Redaccion\FichaDecisionEditorial;
use Pluma\Seo\DatosSeo;
use Pluma\Taxonomia\ResultadoTaxonomia;
use wpdb;

/**
 * Único punto del plugin con `$wpdb` para `pluma_piezas` (CLAUDE.md § Ley de
 * Arquitectura). Las consultas por estado están indexadas (estado,
 * actualizada_en) porque el motor siempre pregunta "dame N piezas en estado
 * X por prioridad".
 *
 * Nota sobre los `argument.type` ignorados: `phpstan-wordpress` exige que el
 * primer argumento de `$wpdb->prepare()` sea un "literal-string" para
 * prevenir inyección SQL. El nombre de tabla que interpolamos viene siempre
 * de `$this->tabla()` (interno, nunca de entrada de usuario) — por eso se
 * ignora puntualmente en vez de perseguir un literal imposible con un
 * prefijo de tabla dinámico.
 */
final class RepositorioPiezas implements RepositorioPiezasInterface {

	private const LIMITE_MUESTRA_VERTICALES = 200;
	private const MAXIMO_VERTICALES_TOP     = 3;

	public function __construct( private readonly wpdb $wpdb ) {
	}

	private function tabla(): string {
		return $this->wpdb->prefix . 'pluma_piezas';
	}

	public function crear( int $tendenciaId, DateTimeImmutable $ahora ): int {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'tendencia_id'   => $tendenciaId,
				'estado'         => EstadoPieza::Detectada->value,
				'expediente'     => null,
				'post_id'        => null,
				'creada_en'      => $ahora->format( 'Y-m-d H:i:s' ),
				'actualizada_en' => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	public function crearComoActualizacion( int $tendenciaId, int $piezaOriginalId, DateTimeImmutable $ahora ): int {
		$this->wpdb->insert(
			$this->tabla(),
			array(
				'tendencia_id'      => $tendenciaId,
				'estado'            => EstadoPieza::Detectada->value,
				'expediente'        => null,
				'post_id'           => null,
				'pieza_original_id' => $piezaOriginalId,
				'creada_en'         => $ahora->format( 'Y-m-d H:i:s' ),
				'actualizada_en'    => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	public function obtenerUltimaPorTendencia( int $tendenciaId ): ?Pieza {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tabla()} WHERE tendencia_id = %d ORDER BY id DESC LIMIT 1", $tendenciaId ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$fila = $this->wpdb->get_row( $sql, ARRAY_A );

		return null !== $fila ? $this->filaAPieza( $fila ) : null;
	}

	public function priorizar( int $id, DateTimeImmutable $ahora ): bool {
		$actualizadas = $this->wpdb->update(
			$this->tabla(),
			array(
				'prioridad'      => 1,
				'actualizada_en' => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return false !== $actualizadas && $actualizadas > 0;
	}

	public function obtenerPorId( int $id ): ?Pieza {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tabla()} WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$fila = $this->wpdb->get_row( $sql, ARRAY_A );

		return null !== $fila ? $this->filaAPieza( $fila ) : null;
	}

	public function obtenerPorPostId( int $postId ): ?Pieza {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tabla()} WHERE post_id = %d ORDER BY id DESC LIMIT 1", $postId ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$fila = $this->wpdb->get_row( $sql, ARRAY_A );

		return null !== $fila ? $this->filaAPieza( $fila ) : null;
	}

	public function obtenerPublicadasParaSincronizarComentarios( int $diasVentana, int $limite, DateTimeImmutable $ahora ): array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->tabla()} WHERE estado = %s AND post_id IS NOT NULL AND actualizada_en >= DATE_SUB(%s, INTERVAL %d DAY) ORDER BY actualizada_en DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			EstadoPieza::Publicada->value,
			$ahora->format( 'Y-m-d H:i:s' ),
			$diasVentana,
			$limite
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		return array_map( fn ( array $fila ): Pieza => $this->filaAPieza( $fila ), $filas ?? array() );
	}

	public function obtenerPorEstado( EstadoPieza $estado, int $limite ): array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->tabla()} WHERE estado = %s ORDER BY prioridad DESC, actualizada_en ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$estado->value,
			$limite
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		return array_map( fn ( array $fila ): Pieza => $this->filaAPieza( $fila ), $filas ?? array() );
	}

	public function obtenerPorEstadoEntre( EstadoPieza $estado, DateTimeImmutable $desde, DateTimeImmutable $hasta, int $limite ): array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->tabla()} WHERE estado = %s AND actualizada_en BETWEEN %s AND %s ORDER BY actualizada_en DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$estado->value,
			$desde->format( 'Y-m-d H:i:s' ),
			$hasta->format( 'Y-m-d H:i:s' ),
			$limite
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		return array_map( fn ( array $fila ): Pieza => $this->filaAPieza( $fila ), $filas ?? array() );
	}

	public function contarPorEstado( EstadoPieza $estado ): int {
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->tabla()} WHERE estado = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$estado->value
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$total = $this->wpdb->get_var( $sql );

		return null !== $total ? (int) $total : 0;
	}

	public function actualizarEstado(
		int $id,
		EstadoPieza $estadoEsperado,
		EstadoPieza $nuevoEstado,
		DateTimeImmutable $ahora
	): bool {
		$sql = $this->wpdb->prepare(
			"UPDATE {$this->tabla()} SET estado = %s, actualizada_en = %s WHERE id = %d AND estado = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$nuevoEstado->value,
			$ahora->format( 'Y-m-d H:i:s' ),
			$id,
			$estadoEsperado->value
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filasAfectadas = $this->wpdb->query( $sql );

		return 1 === $filasAfectadas;
	}

	public function actualizarExpediente( int $id, Expediente $expediente, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array(
				'expediente'     => wp_json_encode( $expediente->aArray() ),
				'actualizada_en' => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	public function actualizarPostId( int $id, int $postId, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array(
				'post_id'        => $postId,
				'actualizada_en' => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	public function asignarPeriodista( int $id, int $periodistaId, int $periodistaVersionId, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array(
				'periodista_id'         => $periodistaId,
				'periodista_version_id' => $periodistaVersionId,
				'actualizada_en'        => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%d', '%d', '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	public function actualizarFichaDecisionEditorial( int $id, FichaDecisionEditorial $ficha, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array(
				'ficha_decision_editorial' => wp_json_encode( $ficha->aArray() ),
				'actualizada_en'           => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	public function actualizarResultadoCompuertas( int $id, ResultadoEvaluacion $resultado, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array(
				'modo_efectivo'          => $resultado->modoEfectivo->value,
				'diagnostico_compuertas' => wp_json_encode( $resultado->aArray() ),
				'actualizada_en'         => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	public function actualizarDatosSeo( int $id, DatosSeo $datos, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array(
				'keyword_principal' => $datos->palabrasClave->principal,
				'datos_seo'         => wp_json_encode( $datos->aArray() ),
				'actualizada_en'    => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	public function vincularHistoria( int $id, int $historiaId, TipoPieza $tipo, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array(
				'historia_id'    => $historiaId,
				'tipo'           => $tipo->value,
				'actualizada_en' => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	public function obtenerPorHistoria( int $historiaId ): array {
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->tabla()} WHERE historia_id = %d ORDER BY creada_en ASC", $historiaId ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		return array_map( fn ( array $fila ): Pieza => $this->filaAPieza( $fila ), $filas ?? array() );
	}

	public function existePiezaPublicadaConKeyword( string $keywordPrincipal, int $excluirPiezaId ): bool {
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->tabla()} WHERE keyword_principal = %s AND estado = %s AND id != %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$keywordPrincipal,
			EstadoPieza::Publicada->value,
			$excluirPiezaId
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		return (int) $this->wpdb->get_var( $sql ) > 0;
	}

	public function actualizarResultadoTaxonomia( int $id, ResultadoTaxonomia $resultado, DateTimeImmutable $ahora ): bool {
		$filasAfectadas = $this->wpdb->update(
			$this->tabla(),
			array(
				'resultado_taxonomia' => wp_json_encode( $resultado->aArray() ),
				'actualizada_en'      => $ahora->format( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $filasAfectadas;
	}

	public function contarAsignadasDesde( int $periodistaId, DateTimeImmutable $desde ): int {
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->tabla()} WHERE periodista_id = %d AND creada_en >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$periodistaId,
			$desde->format( 'Y-m-d H:i:s' )
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		return (int) $this->wpdb->get_var( $sql );
	}

	public function metricasPorPeriodista( int $periodistaId ): array {
		$sqlConteo = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->tabla()} WHERE periodista_id = %d AND estado = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$periodistaId,
			EstadoPieza::Publicada->value
		);
		assert( null !== $sqlConteo );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$piezasPublicadas = (int) $this->wpdb->get_var( $sqlConteo );

		$sqlFichas = $this->wpdb->prepare(
			"SELECT ficha_decision_editorial FROM {$this->tabla()} WHERE periodista_id = %d AND estado = %s ORDER BY actualizada_en DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$periodistaId,
			EstadoPieza::Publicada->value,
			self::LIMITE_MUESTRA_VERTICALES
		);
		assert( null !== $sqlFichas );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sqlFichas, ARRAY_A );

		$conteoPorVertical = array();

		foreach ( $filas ?? array() as $fila ) {
			$fichaJson = $fila['ficha_decision_editorial'] ?? null;

			if ( ! is_string( $fichaJson ) || '' === $fichaJson ) {
				continue;
			}

			/** @var array{clasificacion?: array{tema?: string}} $datos */
			$datos = json_decode( $fichaJson, true ) ?? array();
			$tema  = $datos['clasificacion']['tema'] ?? null;

			if ( null === $tema || '' === $tema ) {
				continue;
			}

			$conteoPorVertical[ $tema ] = ( $conteoPorVertical[ $tema ] ?? 0 ) + 1;
		}

		arsort( $conteoPorVertical );

		return array(
			'piezasPublicadas' => $piezasPublicadas,
			'verticalesTop'    => array_slice( array_keys( $conteoPorVertical ), 0, self::MAXIMO_VERTICALES_TOP ),
		);
	}

	public function obtenerPublicadasRecientesPorPeriodista( int $periodistaId, int $limite ): array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->tabla()} WHERE periodista_id = %d AND estado = %s ORDER BY actualizada_en DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			$periodistaId,
			EstadoPieza::Publicada->value,
			$limite
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		return array_map( fn ( array $fila ): Pieza => $this->filaAPieza( $fila ), $filas ?? array() );
	}

	public function obtenerCanibalizacion(): array {
		$sql = $this->wpdb->prepare(
			"SELECT keyword_principal, GROUP_CONCAT(id ORDER BY id) AS ids FROM {$this->tabla()} WHERE estado = %s AND keyword_principal IS NOT NULL AND keyword_principal != '' GROUP BY keyword_principal HAVING COUNT(*) > 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna. @phpstan-ignore-line argument.type
			EstadoPieza::Publicada->value
		);
		assert( null !== $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql ya se construyó con $wpdb->prepare() arriba.
		$filas = $this->wpdb->get_results( $sql, ARRAY_A );

		return array_map(
			static fn ( array $fila ): array => array(
				'keywordPrincipal' => (string) $fila['keyword_principal'],
				'piezaIds'         => array_map( static fn ( string $id ): int => (int) $id, explode( ',', (string) $fila['ids'] ) ),
			),
			$filas ?? array()
		);
	}

	/**
	 * @param array<string, mixed> $fila
	 */
	private function filaAPieza( array $fila ): Pieza {
		$expedienteJson = $fila['expediente'] ?? null;
		$expediente     = null;

		if ( is_string( $expedienteJson ) && '' !== $expedienteJson ) {
			/** @var array{tendenciaOrigen: string, hechos: list<array{extracto: string, url: string, fecha: string, nivel: string}>} $datos */
			$datos      = json_decode( $expedienteJson, true );
			$expediente = Expediente::desdeArray( $datos );
		}

		$fichaJson = $fila['ficha_decision_editorial'] ?? null;
		$ficha     = null;

		if ( is_string( $fichaJson ) && '' !== $fichaJson ) {
			/** @var array{periodistaId: int, periodistaVersionId: int, clasificacion: array{tema: string, gravedad: int, polaridad: string, novedad: string, potencialConversacional: int, tipoNoticia: string}, candidatosTesis: list<array{tesis: string, puntuacionOriginalidad: float, puntuacionCompatibilidadLinea: float, puntuacionSustento: float, puntuacionConversacional: float}>, indiceTesisElegida: int, tonoDominante: string, tonoApoyo: string, esqueleto: array{gancho: string, hechosEsencialesConAtribucion: string, movimientosArgumentales: list<string>, contraargumentoReconocido: string, remate: string}, creadaEn: string} $datosFicha */
			$datosFicha = json_decode( $fichaJson, true );
			$ficha      = FichaDecisionEditorial::desdeArray( $datosFicha );
		}

		$diagnosticoJson     = $fila['diagnostico_compuertas'] ?? null;
		$resultadoCompuertas = null;

		if ( is_string( $diagnosticoJson ) && '' !== $diagnosticoJson ) {
			/** @var array{aprobada: bool, retenida: bool, motivos: list<string>, modoEfectivo: string, calidad: array{puntuacionTotal: int, umbral: int, sustentoAprobado: bool, detalle: list<string>}, riesgo: array{implicaTragedia: bool, implicaMenores: bool, implicaSalud: bool, implicaViolencia: bool, riesgoDifamacion: bool, detalleDifamacion: string, hechosDisputadosSinSenalar: bool, temaRegulado: ?string}, originalidad: array{solapamientoConFuentes: bool, solapamientoConSitioPropio: bool, ratioGananciaInformacion: float, umbralGananciaMinima: float}} $datosResultado */
			$datosResultado      = json_decode( $diagnosticoJson, true );
			$resultadoCompuertas = ResultadoEvaluacion::desdeArray( $datosResultado );
		}

		$datosSeoJson = $fila['datos_seo'] ?? null;
		$datosSeo     = null;

		if ( is_string( $datosSeoJson ) && '' !== $datosSeoJson ) {
			/** @var array{palabrasClave: array{principal: string, secundarias: list<string>}, metadatos: array{tituloSeo: string, metaDescripcion: string}, tipoEsquema: string, pluginDetectado: string, enlacesInternos: list<array{postId: int, url: string, titulo: string}>, canibalizacionDetectada: bool} $datosDecodificados */
			$datosDecodificados = json_decode( $datosSeoJson, true );
			$datosSeo           = DatosSeo::desdeArray( $datosDecodificados );
		}

		$resultadoTaxonomiaJson = $fila['resultado_taxonomia'] ?? null;
		$resultadoTaxonomia     = null;

		if ( is_string( $resultadoTaxonomiaJson ) && '' !== $resultadoTaxonomiaJson ) {
			/** @var array{categoriaAsignada: ?string, etiquetas: list<array{vocabularioId: int, nombre: string, esNueva: bool, enCuarentena: bool}>} $datosTaxonomia */
			$datosTaxonomia     = json_decode( $resultadoTaxonomiaJson, true );
			$resultadoTaxonomia = ResultadoTaxonomia::desdeArray( $datosTaxonomia );
		}

		$piezaOriginalId = null !== ( $fila['pieza_original_id'] ?? null ) ? (int) $fila['pieza_original_id'] : null;
		$historiaId      = null !== ( $fila['historia_id'] ?? null ) ? (int) $fila['historia_id'] : null;
		$tipo            = isset( $fila['tipo'] ) && is_string( $fila['tipo'] ) ? ( TipoPieza::tryFrom( $fila['tipo'] ) ?? TipoPieza::Original ) : TipoPieza::Original;

		return new Pieza(
			(int) $fila['id'],
			(int) $fila['tendencia_id'],
			EstadoPieza::from( (string) $fila['estado'] ),
			$expediente,
			null !== $fila['post_id'] ? (int) $fila['post_id'] : null,
			new DateTimeImmutable( (string) $fila['creada_en'] ),
			new DateTimeImmutable( (string) $fila['actualizada_en'] ),
			null !== ( $fila['periodista_id'] ?? null ) ? (int) $fila['periodista_id'] : null,
			null !== ( $fila['periodista_version_id'] ?? null ) ? (int) $fila['periodista_version_id'] : null,
			$ficha,
			$resultadoCompuertas,
			$datosSeo,
			$resultadoTaxonomia,
			$piezaOriginalId,
			$historiaId,
			$tipo
		);
	}
}
