<?php

declare(strict_types=1);

namespace Pluma\Datos;

use wpdb;

/**
 * Definiciones de esquema (sub-agente ESQUEMA). Acumulativo por diseño: cada
 * versión devuelve el `CREATE TABLE` COMPLETO de cada tabla (columnas viejas
 * y nuevas); `dbDelta` diffea contra lo instalado y genera los `ALTER TABLE`
 * necesarios — así es como WordPress espera que se migren columnas nuevas
 * sobre una tabla existente (nunca un `ALTER TABLE` escrito a mano aquí).
 *
 * Formato estricto de `dbDelta`: cada columna en su propia línea, dos
 * espacios antes de `PRIMARY KEY`, sin comillas ni backticks en nombres.
 * Índices en todo campo de estado+fecha — el motor consulta siempre
 * "dame N piezas en estado X por prioridad" (CLAUDE.md § Orquestador).
 */
final class Esquema {

	/**
	 * @return list<string>
	 */
	public static function sentenciasCreateTable( wpdb $wpdb ): array {
		$prefijo = $wpdb->prefix . 'pluma_';
		$charset = $wpdb->get_charset_collate();

		return array(
			// Etapa 4 añade estado (Libro Cap. 11: la tabla de tendencias lleva
			// estado; la Sala de Tendencias lo usa para las acciones directas
			// Cubrir ahora / Ignorar / Vigilar, Cap. 10.2).
			// Etapa 5 (huella semántica, Libro Cap. 3.4) añade
			// tendencia_original_id: cuando el Radar detecta que esta tendencia
			// es la evolución de una historia ya cubierta ("dos golpes"), este
			// campo apunta a esa tendencia original — nulo en el caso normal.
			// Etapa 8, porción 7a (Nivel Dos F.1-F.2, modo respeto): gravedad,
			// campo_tematico y campo_geografico — clasificados por
			// `Pluma\Compuertas\ClasificadorGravedadTendencia` justo cuando la
			// tendencia entra al pipeline (nulos hasta entonces). El disparador
			// automático del modo respeto consulta por `gravedad >= umbral AND
			// detectada_en >= ventana` — índice propio en `gravedad` (columna
			// líder de la consulta), independiente de `estado`; deliberadamente
			// NO compuesto con `detectada_en` por una limitación conocida de
			// `dbDelta` detectando claves multi-columna en migraciones
			// repetidas (verificado empíricamente contra wp-env real).
			// Etapa 8, porción 9 (Nivel Dos G.1, legitimidad del insumo):
			// diversidad_fuente y motivo_legitimidad — diagnóstico de
			// `Pluma\Sensores\EvaluadorLegitimidadInsumo` cuando el estado es
			// SOSPECHA_MANIPULACION, guardado para auditoría y calibración
			// futura del umbral (nulos en el caso normal).
			"CREATE TABLE {$prefijo}tendencias (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                termino VARCHAR(191) NOT NULL,
                fuente_senal VARCHAR(50) NOT NULL,
                puntuacion_velocidad DECIMAL(5,2) NOT NULL,
                puntuacion_afinidad DECIMAL(5,2) NOT NULL,
                puntuacion_total DECIMAL(5,2) NOT NULL,
                articulos_relacionados LONGTEXT NOT NULL,
                estado VARCHAR(30) NOT NULL DEFAULT 'en_pipeline',
                tendencia_original_id BIGINT UNSIGNED NULL,
                gravedad TINYINT UNSIGNED NULL,
                campo_tematico VARCHAR(191) NULL,
                campo_geografico VARCHAR(191) NULL,
                diversidad_fuente DECIMAL(4,2) NULL,
                motivo_legitimidad VARCHAR(500) NULL,
                detectada_en DATETIME NOT NULL,
                creada_en DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY termino_fuente (termino(100), fuente_senal),
                KEY puntuacion_total (puntuacion_total),
                KEY estado (estado),
                KEY tendencia_original_id (tendencia_original_id),
                KEY gravedad (gravedad)
            ) {$charset};",
			// Etapa 2 añade periodista_id, periodista_version_id (trazabilidad de
			// qué Conducta redactó la pieza, pl-periodistas §1) y
			// ficha_decision_editorial (Libro Cap. 5.5) sobre la tabla de la Etapa 1.
			// Etapa 3 añade modo_efectivo (denormalizado para consulta rápida del
			// Orquestador, "dame piezas en modo X") y diagnostico_compuertas (JSON
			// completo de `ResultadoEvaluacion`, Libro Cap. 8.4). También añade
			// keyword_principal (indexada — la Auditoría de Canibalización de
			// `Pluma\Seo` pregunta "¿alguna OTRA pieza publicada ya usa esta
			// keyword?", Libro Cap. 6.3), datos_seo (JSON completo de
			// `DatosSeo`) y resultado_taxonomia (JSON completo de
			// `ResultadoTaxonomia`, Libro Cap. 7).
			// Etapa 4 añade prioridad: "Cubrir ahora (salta la cola)" de la Sala
			// de Tendencias (Cap. 10.2) — el Orquestador ordena cada lote por
			// prioridad DESC antes que por antigüedad.
			// Etapa 5 (huella semántica, Libro Cap. 3.4) añade
			// pieza_original_id: cuando el editor confirma "Cubrir como
			// actualización" sobre una tendencia marcada POSIBLE_ACTUALIZACION,
			// la Pieza nueva queda enlazada a la Pieza que actualiza ("dos
			// golpes") — nulo para toda Pieza de cobertura original.
			// Etapa 9, porción 1 (Nivel Cuatro U.1/U.4): historia_id agrupa
			// la Pieza dentro de una saga (nulo — la mayoría de las Piezas
			// nunca pertenecen a una); tipo distingue original/actualización/
			// corrección/cierre dentro de esa saga (Libro Cap. 3.4 "dos
			// golpes" formalizado como campo de primera clase).
			"CREATE TABLE {$prefijo}piezas (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tendencia_id BIGINT UNSIGNED NOT NULL,
                periodista_id BIGINT UNSIGNED NULL,
                periodista_version_id BIGINT UNSIGNED NULL,
                estado VARCHAR(30) NOT NULL,
                prioridad TINYINT UNSIGNED NOT NULL DEFAULT 0,
                expediente LONGTEXT NULL,
                ficha_decision_editorial LONGTEXT NULL,
                modo_efectivo VARCHAR(20) NULL,
                diagnostico_compuertas LONGTEXT NULL,
                keyword_principal VARCHAR(191) NULL,
                datos_seo LONGTEXT NULL,
                resultado_taxonomia LONGTEXT NULL,
                post_id BIGINT UNSIGNED NULL,
                pieza_original_id BIGINT UNSIGNED NULL,
                historia_id BIGINT UNSIGNED NULL,
                tipo VARCHAR(20) NOT NULL DEFAULT 'original',
                creada_en DATETIME NOT NULL,
                actualizada_en DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY estado_actualizada (estado, actualizada_en),
                KEY estado_prioridad (estado, prioridad, actualizada_en),
                KEY tendencia_id (tendencia_id),
                KEY periodista_id (periodista_id),
                KEY keyword_principal (keyword_principal(100)),
                KEY pieza_original_id (pieza_original_id),
                KEY historia_id (historia_id)
            ) {$charset};",
			// Etapa 9, porción 1 (Nivel Cuatro U.1): la entidad Historia —
			// agrupa Piezas de una misma saga, por encima de la Pieza
			// individual. periodista_titular_id es quien la sigue (protege
			// la coherencia narrativa que Nivel Dos C.2 ya cuida por Pieza).
			// Sin columnas para el bloque "lo que sabemos/no sabemos": se
			// calcula en caliente a partir de los expedientes de las Piezas
			// asociadas (GestorHistorias), nunca se persiste duplicado.
			"CREATE TABLE {$prefijo}historias (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                titulo VARCHAR(191) NOT NULL,
                estado VARCHAR(20) NOT NULL DEFAULT 'abierta',
                periodista_titular_id BIGINT UNSIGNED NULL,
                creada_en DATETIME NOT NULL,
                actualizada_en DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY estado (estado),
                KEY periodista_titular_id (periodista_titular_id)
            ) {$charset};",
			// Etapa 8, porción 10 (Nivel Tres Q.1): locale_editorial —
			// determina qué catálogo localizado (vocabulario prohibido,
			// ejemplos-ancla) aplica al compilar directrices. Campo desde
			// ya para no migrar el banco completo después.
			"CREATE TABLE {$prefijo}periodistas (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                nombre VARCHAR(191) NOT NULL,
                avatar_url VARCHAR(2000) NULL,
                biografia TEXT NOT NULL,
                rol VARCHAR(20) NOT NULL,
                especialidades LONGTEXT NOT NULL,
                estado VARCHAR(20) NOT NULL,
                version_conducta_actual_id BIGINT UNSIGNED NOT NULL,
                locale_editorial VARCHAR(10) NOT NULL DEFAULT 'es-ES',
                creado_en DATETIME NOT NULL,
                actualizado_en DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY estado (estado)
            ) {$charset};",
			// Etapa 5 (respuestas asistidas a comentarios, Libro Cap. 5.7) añade
			// respuestas_habilitadas: interruptor "modo configurable" por
			// periodista — decisión del propietario, 2026-07-23: vive en la
			// Conducta, no como opción global del motor.
			"CREATE TABLE {$prefijo}periodistas_conducta_versiones (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                periodista_id BIGINT UNSIGNED NOT NULL,
                diales LONGTEXT NOT NULL,
                reglas_conducta LONGTEXT NOT NULL,
                matriz_tonos LONGTEXT NOT NULL,
                respuestas_habilitadas TINYINT(1) NOT NULL DEFAULT 0,
                creada_en DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY periodista_id (periodista_id)
            ) {$charset};",
			// Etapa 8 (Nivel Dos E.2, memoria colectiva del sitio): índice
			// `tema` en solitario — el índice compuesto `periodista_tema` tiene
			// `periodista_id` como columna líder y no sirve para la consulta
			// agregada "todas las posturas sobre este tema, de cualquier
			// periodista" que la memoria colectiva necesita (regla del prefijo
			// izquierdo de MySQL).
			"CREATE TABLE {$prefijo}memoria_editorial (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                periodista_id BIGINT UNSIGNED NOT NULL,
                tipo VARCHAR(20) NOT NULL,
                tema VARCHAR(191) NOT NULL,
                contenido LONGTEXT NOT NULL,
                pieza_id BIGINT UNSIGNED NULL,
                creada_en DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY periodista_tema (periodista_id, tema(100)),
                KEY pieza_id (pieza_id),
                KEY tema (tema(100))
            ) {$charset};",
			// Etapa 4 añade editado_manualmente (Mesa Editorial, Cap. 10.2:
			// distingue un ciclo del Corrector Interno de una edición humana).
			"CREATE TABLE {$prefijo}borradores (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                pieza_id BIGINT UNSIGNED NOT NULL,
                numero_ciclo TINYINT UNSIGNED NOT NULL,
                contenido LONGTEXT NOT NULL,
                anotaciones_corrector LONGTEXT NULL,
                aprobado_por_corrector TINYINT(1) NOT NULL DEFAULT 0,
                editado_manualmente TINYINT(1) NOT NULL DEFAULT 0,
                creado_en DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY pieza_id (pieza_id)
            ) {$charset};",
			"CREATE TABLE {$prefijo}fuentes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                pieza_id BIGINT UNSIGNED NOT NULL,
                url VARCHAR(2000) NOT NULL,
                extracto TEXT NOT NULL,
                nivel_verificacion VARCHAR(20) NOT NULL,
                fecha DATETIME NOT NULL,
                creada_en DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY pieza_id (pieza_id)
            ) {$charset};",
			"CREATE TABLE {$prefijo}bitacora_motor (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                iniciada_en DATETIME NOT NULL,
                finalizada_en DATETIME NULL,
                lotes_procesados INT UNSIGNED NOT NULL DEFAULT 0,
                errores LONGTEXT NULL,
                candado_adquirido TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY  (id),
                KEY iniciada_en (iniciada_en)
            ) {$charset};",
			// Etapa 6, porción 4c (Art. 50 UE, Nivel Tres N.3 (c)): tipo_aprobacion
			// distingue, solo en la transición programada→publicada, si la pieza
			// se publicó por aprobación humana activa ("aprobar ahora" en la cola
			// de veto de Copiloto) o automáticamente por expiración de ventana —
			// nulo en cualquier otra transición. Es el registro auditable exigido
			// por la excepción del Art. 50 al marcado de contenido generado por IA.
			"CREATE TABLE {$prefijo}auditoria (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                pieza_id BIGINT UNSIGNED NOT NULL,
                estado_anterior VARCHAR(30) NULL,
                estado_nuevo VARCHAR(30) NOT NULL,
                actor VARCHAR(20) NOT NULL,
                motivo VARCHAR(255) NOT NULL,
                tipo_aprobacion VARCHAR(30) NULL,
                ocurrida_en DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY pieza_id (pieza_id),
                KEY ocurrida_en (ocurrida_en)
            ) {$charset};",
			// Etapa 3 (Taxónomo, Libro Cap. 7): categorías fijas y etiquetas
			// dinámicas del sitio. "tipo" distingue ambas ramas; "slug" es el
			// nombre normalizado para reconciliación por coincidencia exacta
			// (Cap. 7.2 punto 2); "en_cuarentena" implementa el umbral de
			// creación (Cap. 7.2 punto 3): una etiqueta nueva no es indexable
			// hasta acumular 3+ piezas (veces_usada).
			"CREATE TABLE {$prefijo}vocabulario (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tipo VARCHAR(20) NOT NULL,
                nombre VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                alias LONGTEXT NOT NULL,
                en_cuarentena TINYINT(1) NOT NULL DEFAULT 0,
                veces_usada INT UNSIGNED NOT NULL DEFAULT 0,
                creado_en DATETIME NOT NULL,
                actualizado_en DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY tipo_slug (tipo, slug(100))
            ) {$charset};",
			// Etapa 3 (Publicador, Libro Cap. 9.2-9.3): ranuras programadas.
			// "vertical" y "periodista_id" desnormalizados para los topes de
			// cuota por vertical/periodista sin deserializar la Pieza; "estado"
			// distingue programada/publicada/expirada (perecibilidad — Cap. 9.3
			// punto 4: "mejor no publicar que publicar tarde").
			// Etapa 6, porción 4c: aprobacion_activa marca que un humano usó
			// "aprobar ahora" en la cola de veto de Copiloto — el Orquestador la
			// usa para saltar la ventana de veto restante y para que el marcado
			// de IA del frontend (Art. 50 UE) NO se emita sobre esta pieza.
			"CREATE TABLE {$prefijo}cola_publicacion (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                pieza_id BIGINT UNSIGNED NOT NULL,
                vertical VARCHAR(191) NOT NULL,
                periodista_id BIGINT UNSIGNED NULL,
                hora_programada DATETIME NOT NULL,
                estado VARCHAR(20) NOT NULL,
                aprobacion_activa TINYINT(1) NOT NULL DEFAULT 0,
                creada_en DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY pieza_id (pieza_id),
                KEY estado_hora (estado, hora_programada),
                KEY vertical (vertical(100)),
                KEY periodista_id (periodista_id)
            ) {$charset};",
			// Etapa 5 (bucle de Search Console, Libro Cap. 6.4): métricas
			// reales de `searchAnalytics.query` agregadas por página+consulta.
			// "pieza_id" nulo cuando la URL no mapea a ninguna Pieza gestionada
			// por PLUMA (contenido ajeno del sitio) — dato real, no se descarta.
			"CREATE TABLE {$prefijo}metricas_search_console (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                post_id BIGINT UNSIGNED NOT NULL,
                pieza_id BIGINT UNSIGNED NULL,
                consulta VARCHAR(191) NOT NULL,
                clics INT UNSIGNED NOT NULL,
                impresiones INT UNSIGNED NOT NULL,
                ctr DECIMAL(6,4) NOT NULL,
                posicion DECIMAL(6,2) NOT NULL,
                sincronizada_en DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY post_id_consulta (post_id, consulta(100)),
                KEY pieza_id (pieza_id)
            ) {$charset};",
			// Etapa 5 (memoria de audiencia + respuestas asistidas, Libro Cap.
			// 5.7): un comentario real de WordPress procesado como mucho una
			// vez (UNIQUE comentario_id). "borrador"/"periodista_id" nulos
			// cuando la Pieza no tiene periodista o tiene las respuestas
			// deshabilitadas — solo alimenta memoria, sin borrador de
			// respuesta. "comentario_respuesta_id" queda poblado tras
			// aprobar (Libro Cap. 5.7: "el editor aprueba con un clic").
			"CREATE TABLE {$prefijo}respuestas_comentarios (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                pieza_id BIGINT UNSIGNED NOT NULL,
                comentario_id BIGINT UNSIGNED NOT NULL,
                periodista_id BIGINT UNSIGNED NULL,
                borrador LONGTEXT NULL,
                estado VARCHAR(30) NOT NULL,
                comentario_respuesta_id BIGINT UNSIGNED NULL,
                creada_en DATETIME NOT NULL,
                resuelta_en DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY comentario_id (comentario_id),
                KEY pieza_id (pieza_id),
                KEY estado (estado)
            ) {$charset};",
			// Etapa 8, porción 7a (Nivel Dos F.1-F.3, modo respeto): registro
			// histórico de activaciones — append-only, nunca se sobrescribe una
			// fila. El estado ACTUAL es la fila más reciente con
			// `desactivado_en IS NULL` (a lo sumo una a la vez).
			// `duracion_minima_horas` congela, en la propia activación, el piso
			// de fábrica vigente ese momento — para que un cambio posterior del
			// piso configurado no reabra ni acorte una ventana ya en curso.
			"CREATE TABLE {$prefijo}modo_respeto (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                activado_en DATETIME NOT NULL,
                activado_por VARCHAR(20) NOT NULL,
                motivo VARCHAR(255) NOT NULL,
                duracion_minima_horas DECIMAL(5,2) NOT NULL,
                desactivado_en DATETIME NULL,
                PRIMARY KEY  (id),
                KEY desactivado_en (desactivado_en)
            ) {$charset};",
		);
	}

	/**
	 * @return list<string> nombres de tabla completos (con `$wpdb->prefix`) para la reversa de desinstalación
	 */
	public static function nombresTablas( wpdb $wpdb ): array {
		$prefijo = $wpdb->prefix . 'pluma_';

		return array(
			$prefijo . 'tendencias',
			$prefijo . 'piezas',
			$prefijo . 'periodistas',
			$prefijo . 'periodistas_conducta_versiones',
			$prefijo . 'memoria_editorial',
			$prefijo . 'borradores',
			$prefijo . 'fuentes',
			$prefijo . 'bitacora_motor',
			$prefijo . 'auditoria',
			$prefijo . 'vocabulario',
			$prefijo . 'cola_publicacion',
			$prefijo . 'metricas_search_console',
			$prefijo . 'respuestas_comentarios',
			$prefijo . 'modo_respeto',
			$prefijo . 'historias',
		);
	}

	/**
	 * Reversa de {@see sentenciasCreateTable()}: solo se invoca cuando el
	 * cliente eligió explícitamente NO conservar datos (GOVERNANCE §5.4).
	 */
	public static function eliminarTablas( wpdb $wpdb ): void {
		foreach ( self::nombresTablas( $wpdb ) as $tabla ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla generado internamente, no hay identificador parametrizable en $wpdb->prepare().
			$wpdb->query( "DROP TABLE IF EXISTS {$tabla}" );
		}
	}

	/**
	 * Sentencias de reversa por transición de versión (GOVERNANCE §5.1: "toda
	 * migración tiene procedimiento de reversa probado"). Registro explícito
	 * únicamente — una reversa nunca se infiere del `CREATE TABLE` acumulativo
	 * de {@see sentenciasCreateTable()}, porque este no distingue qué columna
	 * llegó en qué versión.
	 *
	 * Las Etapas 0-5 no registraron reversa (no se reconstruye retroactivamente
	 * — deuda histórica aceptada, ver `docs/deuda.md`). A partir de la Etapa 6,
	 * todo bump de esquema debe añadir su transición aquí en la misma porción
	 * que lo introduce. La transición 0.12.0→0.11.0 (Etapa 5, porción 3: añadió
	 * `respuestas_habilitadas` a `periodistas_conducta_versiones` y la tabla
	 * `respuestas_comentarios`) es el caso de referencia.
	 *
	 * @return list<string>
	 *
	 * @throws ReversaNoDisponibleException si la transición no está registrada.
	 */
	public static function sentenciasReversaDesde( wpdb $wpdb, string $versionOrigen, string $versionDestino ): array {
		$prefijo = $wpdb->prefix . 'pluma_';

		return match ( $versionOrigen . '->' . $versionDestino ) {
			'0.18.0->0.17.0' => array(
				"ALTER TABLE {$prefijo}piezas DROP COLUMN historia_id, DROP COLUMN tipo;",
				"DROP TABLE IF EXISTS {$prefijo}historias;",
			),
			'0.17.0->0.16.0' => array(
				"ALTER TABLE {$prefijo}periodistas DROP COLUMN locale_editorial;",
			),
			'0.16.0->0.15.0' => array(
				"ALTER TABLE {$prefijo}tendencias DROP COLUMN diversidad_fuente, DROP COLUMN motivo_legitimidad;",
			),
			'0.15.0->0.14.0' => array(
				"ALTER TABLE {$prefijo}tendencias DROP COLUMN gravedad, DROP COLUMN campo_tematico, DROP COLUMN campo_geografico;",
				"DROP TABLE IF EXISTS {$prefijo}modo_respeto;",
			),
			'0.14.0->0.13.0' => array(
				"DROP INDEX tema ON {$prefijo}memoria_editorial;",
			),
			'0.13.0->0.12.0' => array(
				"ALTER TABLE {$prefijo}auditoria DROP COLUMN tipo_aprobacion;",
				"ALTER TABLE {$prefijo}cola_publicacion DROP COLUMN aprobacion_activa;",
			),
			'0.12.0->0.11.0' => array(
				"ALTER TABLE {$prefijo}periodistas_conducta_versiones DROP COLUMN respuestas_habilitadas;",
				"DROP TABLE IF EXISTS {$prefijo}respuestas_comentarios;",
			),
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje construido internamente por la propia excepción, sin entrada de usuario.
			default => throw new ReversaNoDisponibleException( $versionOrigen, $versionDestino ),
		};
	}
}
