# Etapa 6 — Producto en venta

**Estado: EN CURSO.** Porciones 1 (Versionado SemVer + empaquetado reproducible + matriz de compatibilidad), 2 (telemetría opt-in + modo diagnóstico), 3 (documentación de venta) y 4a (cumplimiento Art. 50 UE — capa de emisión de frontend + marcado legible por máquina, primera del arco N.3) completas. Pendientes: porciones 4b (página de autor con identidad sintética) y 4c (tipo de aprobación + aprobar-activo en Copiloto) del arco N.3, y el cierre formal con beta cerrada externa.

## Objetivo y criterio de salida (PLAN-MAESTRO)

> Licenciamiento + updates firmadas, empaquetado reproducible, telemetría opt-in, docs de venta, beta cerrada.
> **Criterio de salida**: GOVERNANCE §5 íntegro¹; 3 instalaciones beta externas estables 2 semanas; 0 incidencias de seguridad.
>
> ¹ Nota (2026-07-24): GOVERNANCE §5.4 (licenciamiento + servidor de actualizaciones propio con firmas) queda fuera de esta Etapa por decisión explícita del propietario — ver "Decisiones de producto" abajo. El criterio de salida se entiende cumplido sobre §5.1, 5.2, 5.3, 5.5, 5.6, 5.7.

A diferencia de las Etapas 1-5, esta no añade un módulo de dominio editorial nuevo — endurece la infraestructura de release para que un ZIP publicado sea instalable con confianza en el WordPress de un cliente de pago.

## Decisiones de producto tomadas al abrir la etapa (2026-07-24)

- **GOVERNANCE §5.4 pospuesto**: el licenciamiento + servidor de actualizaciones propio con firmas requiere infraestructura de servidor fuera de este repositorio (emisión/validación de licencias, firma y hospedaje de ZIPs de actualización), que no existe ni está diseñada. El propietario decidió documentarlo como deuda aceptada y posponerlo al final del desarrollo — `PLUMA-E6-1` en `docs/deuda.md`, nota¹ en `PLAN-MAESTRO.md`. La beta cerrada externa (criterio de salida de esta Etapa) se distribuye sin verificación de licencia.
- **Porciones en orden de dependencia**: (1) versionado + empaquetado reproducible + matriz de compatibilidad — la base técnica de cualquier release real; (2) telemetría opt-in + modo diagnóstico; (3) documentación de venta; (4) cierre formal + coordinación de la beta cerrada externa.
- **Discovery de skills registrado** en `docs/skills-descubiertas.md` ("Apertura de Etapa 6"): primera etapa sin skill de dominio `pl-*` nueva — se apoya en `pl-wp-core` (empaquetado, migraciones, i18n) y `pl-testing` (matriz como suite real), más el stack `lg-*` de siempre para las decisiones de una sola dirección.

## Porción 1 — Versionado SemVer + empaquetado reproducible + matriz de compatibilidad (commit pendiente)

**Qué se agregó:**

- **Versionado (§5.1)**: `composer.json["version"]` y `package.json["version"]` (antes `"0.1.0"`, desincronizado hace tiempo) ahora se mantienen sincronizados con `PLUMA_ENGINE_VERSION`. `tests/Unit/Datos/VersionConsistencyTest.php` (nuevo) parsea las cuatro fuentes de verdad (comentario `Version:`, constante `PLUMA_ENGINE_VERSION`, `composer.json`, `package.json`) y falla si divergen — cierra el hueco exacto que causó el incidente real del cierre de la Etapa 5 (comentario en `0.13.0`, constante todavía en `0.12.0`).
- **Mecanismo de reversa de esquema (§5.1, checklist ESQUEMA de `AGENTS.md`)**: `Pluma\Datos\Esquema::sentenciasReversaDesde()` (nuevo) registra explícitamente la reversa SQL por transición de versión — nunca se infiere. `Pluma\Datos\Migrador::revertirA()` (nuevo) la ejecuta y actualiza `pluma_db_version`; lanza `ReversaNoDisponibleException` (nueva) si la transición solicitada no está registrada. La transición real `0.11.0→0.12.0` (Etapa 5, porción 3: añadió `respuestas_habilitadas` y la tabla `pluma_respuestas_comentarios`) es el caso de referencia, probado con datos reales sembrados en `tests/Integration/MigracionConDatosRealesTest.php`. Las transiciones de las Etapas 0-5 anteriores no tienen reversa registrada — deuda histórica no reconstruida retroactivamente; a partir de aquí, todo bump de esquema futuro debe registrar la suya en la misma porción que lo introduce (checklist en `docs/proceso-de-release.md`).
- **Empaquetado reproducible (§5.2)**: `bin/build-zip` ahora construye el árbol de producción **dos veces** en directorios independientes y compara su huella (rutas + contenido, sin metadata de timestamp) antes de comprimir — si el mismo commit produce árboles distintos, falla en vez de publicar un ZIP no determinista. Produce `pluma-engine-{version}.zip.sha256` junto al ZIP.
- **Matriz de compatibilidad (§5.3)**: `.github/workflows/compatibilidad.yml` (nuevo), disparado manualmente (`workflow_dispatch`) o en push a `release/**` — no en cada commit (GOVERNANCE pide matriz "testeada por release"). 6 combinaciones reales en vez de las 12 teóricas: {WP mínimo, WP latest} × {PHP 8.2, PHP 8.3} sin plugin SEO (plataforma) + {Yoast, Rank Math} en un baseline fijo (convivencia SEO real). `bin/generar-wp-env-matriz` (nuevo) genera el `.wp-env.override.json` de cada combinación, reproducible localmente. `tests/Integration/CompatibilidadSeoTest.php` (nuevo) asevera de verdad contra el plugin SEO real cuando está activo (skip en la suite rápida de cada commit).
- `docs/proceso-de-release.md` (nuevo): referencia operativa de las cuatro secciones anteriores + el checklist ESQUEMA completo.

**Honestidad de alcance (cero invención), decidida antes de abrir esta porción:** las 12 combinaciones teóricas de la matriz se reducen a 6 — ejecutar todas en cada `workflow_dispatch` sería carísimo sin aportar señal que las 6 no cubran ya (plataforma y convivencia SEO son dimensiones independientes en la práctica). La reversa de esquema solo se construye para la transición de referencia (0.11.0→0.12.0); reconstruir la reversa de cada bump histórico desde la Etapa 1 está fuera de alcance — no cambia ningún comportamiento hoy, solo documentaría retroactivamente algo que nunca se necesitó ejecutar.

**Hallazgo real durante la verificación — bug pre-existente destapado por la propia verificación de reproducibilidad:** `bin/build-zip` nunca copiaba `composer.lock` al paquete, así que `composer install --no-dev` dentro del árbol copiado no encontraba lock file y **re-resolvía dependencias a "latest"** en cada build en vez de usar las versiones fijadas — sin efecto visible porque el proyecto todavía no tiene dependencias de producción reales, pero exactamente el tipo de no-determinismo silencioso que GOVERNANCE §5.2 exige prevenir. Corregido copiando `composer.lock` junto a `composer.json`.

**Hallazgo real durante la verificación — comportamiento no documentado de `WP_UnitTestCase`:** `tests/Integration/MigracionConDatosRealesTest.php` inicialmente fallaba de forma silenciosa (la reversa "no borraba" `pluma_respuestas_comentarios`, sin ningún error). La causa: WordPress core intercepta **tanto** `CREATE TABLE` como `DROP TABLE` dentro de la ventana de `WP_UnitTestCase::set_up()`/`tear_down()` de un test normal (`_create_temporary_tables` y su gemelo `_drop_temporary_tables`, ambos en `abstract-testcase.php` del core de pruebas de WordPress) — el segundo no estaba documentado en las lecciones previas del proyecto (solo se conocía el primero, de la Etapa 3). Un `DROP TABLE IF EXISTS` real se reescribe silenciosamente a `DROP TEMPORARY TABLE IF EXISTS`, que no toca la tabla permanente y no lanza error (`IF EXISTS` absorbe el "unknown table" de la temporal inexistente). Corregido quitando ambos filtros (`remove_filter('query', ...)`) justo antes de invocar la reversa real dentro del test, y restaurándolos después — documentado en el docblock de la clase para que no se repita el mismo hallazgo "a la mala" en un futuro test que necesite DDL real dentro de un método normal.

**Hallazgo real durante la verificación — `.github/workflows/compatibilidad.yml` disparado en CI real reveló dos bugs genuinos que la CI rápida nunca podía ver:**
1. `bin/generar-wp-env-matriz` construía el ref de "WP mínimo" como `6.4.0`, pero el mirror git de `WordPress/WordPress` etiqueta la primera versión de cada serie como `6.4` (sin `.0`) — `git fetch` fallaba con `fatal: couldn't find remote ref 6.4.0`. Corregido quitando el sufijo `.0` inventado.
2. El pin de "WP latest" en `.wp-env.json` estaba fijo en `6.7.1` desde la Etapa 0 — desactualizado en silencio. El job de convivencia con Yoast SEO falló de verdad contra WordPress real: la versión actual de Yoast exige WordPress 6.8 como mínimo, y 6.7.1 ya no lo cumple. Corregido actualizando el pin a `6.9.5` (la última estable real, verificada contra las tags del mirror git) — esto es exactamente el tipo de deriva que GOVERNANCE §5.3 existe para atrapar antes de que lo descubra un cliente.

Ambos se verificaron localmente (suite de Integración 141/141 y Playwright 2/2 contra WordPress 6.9.5 reconstruido) antes de volver a disparar el workflow en CI real. Corregidos en el commit `f908930` (subido y confirmado en el CI normal, run [30121262068](https://github.com/LG-Dev-Studio/PLUMA/actions/runs/30121262068)).

**`.github/workflows/compatibilidad.yml` verificado en CI real — 6/6 en verde**: tras el fix, se volvió a disparar manualmente (`workflow_dispatch`) y las 6 combinaciones terminaron en `success` (run [30139709968](https://github.com/LG-Dev-Studio/PLUMA/actions/runs/30139709968)): WP mínima × PHP 8.2, WP mínima × PHP 8.3, WP latest × PHP 8.2, WP latest × PHP 8.3, convivencia con Yoast SEO, convivencia con Rank Math. Con esto la porción 1 queda cerrada de verdad — la matriz no solo existe, se ejecutó y pasó contra WordPress real.

## Evidencia de gates — Porción 1

| Gate | Resultado |
|---|---|
| PHPCS (WordPress-Extra + Security) | 0 errores |
| PHPStan nivel 8 | 0 errores |
| `composer test:unit` | 368/368 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 137/137 (2 skipped: `CompatibilidadSeoTest`, sin Yoast/Rank Math instalados en la lane rápida — aseveran de verdad en `compatibilidad.yml`) |
| `npx vitest run` | 81/81 |
| `npx tsc --noEmit` | limpio |
| `npm run build` | build de producción real generado |
| `npx playwright test tests/e2e/salud.spec.ts` | 2/2 |
| `bin/build-zip` (local) | reproducibilidad verificada (huella idéntica en dos builds), `.zip.sha256` generado |
| `.github/workflows/compatibilidad.yml` (CI real, `workflow_dispatch`) | 6/6 combinaciones en verde (run [30139709968](https://github.com/LG-Dev-Studio/PLUMA/actions/runs/30139709968), tras corregir dos bugs reales — ver "Hallazgo real" arriba) |

## Porción 2 — Telemetría opt-in (consentimiento + payload) + Modo diagnóstico (commit pendiente)

**Qué se agregó:**

- **Telemetría (§5.5)**: nueva opción `pluma_telemetria_habilitada` (opt-in, `false` por defecto, registrada en `Activador::activar()`, purgada en `Desinstalador::purgar()`). `Pluma\Proveedores\TelemetriaInterface`/`ProveedorTelemetria` (nuevos) construyen localmente el payload anónimo — versión del plugin/esquema/PHP/WordPress/MySQL, multisitio, modo de operación, conteos agregados (periodistas activos, piezas publicadas) — sin enviarlo a ningún lado. La Sala de Máquinas tiene un interruptor nuevo con una vista previa exacta del payload (`GET /motor/telemetria`) antes de activarlo.
- **Modo diagnóstico (§5.6)**: `Pluma\Kernel\DetectorConflictos` (nuevo, deliberadamente acotado: hoy solo detecta Yoast+Rank Math activos a la vez, reutilizando la misma detección que `Pluma\Seo\DetectorPluginSeo` ya usa para decidir prioridad — cero invención, crece con evidencia real) y `Pluma\Kernel\ExportadorDiagnostico` (nuevo, mismo molde que `ExportadorBancoPeriodistas`: combina entorno + conflictos + bitácora reciente en un array puro). Nuevo botón "Descargar reporte de diagnóstico" en la Sala de Máquinas — primer uso de `Blob`/descarga de archivo en el panel, justificado por el caso de uso real (pegar en un ticket de soporte).
- `Pluma\Admin\RestSalaMaquinas` gana `GET`/`POST /motor/telemetria` y `GET /motor/diagnostico`, misma capacidad `pluma_configurar_motor` que el resto de la pantalla.

**Honestidad de alcance (cero invención), decidida antes de abrir esta porción:** igual que GOVERNANCE §5.4 (licenciamiento) al abrir la Etapa, §5.5 tropieza con la misma pared — enviar telemetría por HTTP requiere un servidor receptor que no existe. **Decisión del propietario**: esta porción construye el consentimiento y el payload, pero el envío real queda diferido — registrado como deuda `PLUMA-E6-2` (mismo motivo raíz que `PLUMA-E6-1`, mismo destino de pago futuro). El detector de conflictos se acota deliberadamente a lo que el propio código ya verifica en otro punto (Yoast+Rank Math) — no se inventa una lista de plugins de terceros supuestamente incompatibles sin evidencia real.

## Evidencia de gates — Porción 2

| Gate | Resultado |
|---|---|
| PHPCS (WordPress-Extra + Security) | 0 errores |
| PHPStan nivel 8 | 0 errores |
| `composer test:unit` | 372/372 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 141/141 (2 skipped: `CompatibilidadSeoTest`, sin Yoast/Rank Math instalados en la lane rápida) |
| `npx vitest run` | 84/84 |
| `npx tsc --noEmit` | limpio |
| `npm run build` | build de producción real generado |
| `npx playwright test tests/e2e/salud.spec.ts` | 2/2 |

## Porción 3 — Documentación de venta (commit pendiente)

**Qué se agregó:**

- **`documentation/`** (nueva, raíz del repo — fuera de `docs/`, que es 100% documentación interna de ingeniería): las cuatro piezas mínimas de GOVERNANCE §5.7, como HTML autocontenido (decisión explícita del propietario: no Markdown interno, diseño propio "digno de un best-seller") — `instalacion.html`, `onboarding.html`, `referencia-pantallas.html`, `faq-conflictos.html`, más `index.html` como portada de navegación.
- **`documentation/assets/estilo.css`**: sistema visual compartido por las cinco páginas — reutiliza literalmente los tokens de color de `panel/src/estilos.css` (mismos valores hexadecimales, misma variable `--pluma-color-*`) para que la documentación se sienta una extensión del producto real, no un sitio de marketing aparte. Tipografía serif para titulares/sans para cuerpo (mismo lenguaje que el panel, Libro Cap. 10.1), modo oscuro nativo vía `prefers-color-scheme`, totalmente responsivo.
- Contenido verificado contra el código real y el `CHANGELOG.md` antes de escribirse (cero invención): capacidades exactas por pantalla (`Capacidades::CONFIGURAR_MOTOR`/`APROBAR_PIEZAS`/`GESTIONAR_PERIODISTAS`, leídas de cada `RestXxx::autorizado()`), endpoint y cabecera reales del cron (`/pluma/v1/motor/tick`, `X-Pluma-Token`, límite de 30s entre llamadas), matriz de compatibilidad de la porción 1, y la tabla de límites conocidos (sitemap de noticias, imagen destacada, notificaciones Telegram/Slack, envío de telemetría) enlazada 1:1 con `docs/deuda.md`.

**Honestidad de alcance:** el FAQ de "límites conocidos" declara explícitamente lo que esta versión NO incluye todavía (mismo principio de "escasez honesta" que gobierna el resto del producto) en vez de omitirlo — incluye la propia telemetría diferida (`PLUMA-E6-2`) y la ausencia de licenciamiento (`PLUMA-E6-1`, mencionada como "no necesitas clave de licencia en esta versión", sin exponer jerga interna de deuda técnica al cliente).

**Verificación visual:** las cinco páginas se renderizaron con Playwright (Chromium) en modo claro y oscuro para confirmar el sistema de diseño antes de cerrar la porción — capturas de pantalla revisadas y descartadas (no forman parte del entregable).

## Porción 4a — Cumplimiento del Art. 50 UE: capa de emisión de frontend + marcado legible por máquina (commit pendiente)

Primera de las 3 porciones del arco N.3 (Nivel Tres N.3 + Nivel Cuatro verif. 1; plan en `docs/PLAN-MAESTRO-EVOLUCION.md`, ADR 0002). Núcleo legal embarcable antes de la beta.

**Qué se agregó:**

- **Primer hook de frontend del plugin** (`Pluma\Seo\EmisorEsquemaFrontend`, `wp_head`): sobre una pieza singular publicada por PLUMA emite (1) el documento JSON-LD `NewsArticle`/`OpinionNewsArticle`/`AnalysisNewsArticle` — **paga la deuda `PLUMA-E3-4`**, el JSON-LD que `ConstructorEsquemaNewsArticle` construía desde la Etapa 3 pero nunca se emitía en una página real; y (2) el marcado de transparencia de IA legible por máquina del Art. 50 (Reglamento (UE) 2024/1689): `<meta name="iptc.digitalSourceType" content="…/trainedAlgorithmicMedia">`, el valor de vocabulario controlado IPTC verificado contra la fuente oficial (cero invención — Art. 50 no manda un formato único, se implementa el más reconocido).
- **`Pluma\Publicacion\Publicador`** persiste al publicar una instantánea (`SnapshotPublicacion`) como post meta (`_pluma_pieza_id`, `_pluma_generado_ia`, `_pluma_modo_publicacion`, `_pluma_esquema_tipo`, `_pluma_autor_nombre`); el emisor lee solo esas metas en render — cero consultas a repositorios en el frontend (CLAUDE.md: peso adicional ≈ 0).
- **Piso de fábrica inamovible** (ADR 0002): el marcado existe en toda pieza generada y publicada por el sistema sin aprobación humana activa (Autónomo, o Copiloto por expiración de ventana). Hoy Copiloto solo publica por expiración → se marca todo lo publicado por el motor (postura conservadora correcta hasta la porción 4c).
- **Configurable por el cliente**: `Pluma\Admin\RestTransparencia` + sección "Transparencia y cumplimiento" del panel — selector del formato del bloque de transparencia visible (breve/extendido), con nota de solo lectura de que el marcado legible por máquina es piso de fábrica.

**Honestidad de alcance:** 4a entrega el núcleo legal. La página de autor con identidad sintética (N.3 (b)) y el tipo de aprobación de primera clase + acción "aprobar ahora" en Copiloto (N.3 (c)) quedan en las porciones 4b/4c, registradas como deuda `PLUMA-E6-3`. Sin nombre de periodista, el JSON-LD emite `author` como Organización (sitio) — se enriquece a Persona con página propia en 4b.

**Verificación real (no solo test):** publicada una pieza en el sitio dev de wp-env, el `<head>` real emite el `<script type="application/ld+json">` con `OpinionNewsArticle` y la etiqueta `iptc.digitalSourceType` apuntando a `trainedAlgorithmicMedia`.

### Evidencia de gates — Porción 4a

| Gate | Resultado |
|---|---|
| PHPCS | 0 errores (349 archivos) |
| PHPStan L8 | 0 errores |
| `composer test:unit` | 372/372 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 149/149 (2 skipped esperados) |
| `npx vitest run` | 86/86 |
| `npx tsc --noEmit` | limpio |
| `npm run build` | build de producción real |
| `npx playwright test tests/e2e/salud.spec.ts` | 2/2 |
| Verificación manual del `<head>` real | JSON-LD + marcado IPTC emitidos en la página publicada |

## Porción 4b — Página de autor con identidad sintética (commit pendiente)

Segunda de las 3 porciones del arco N.3. Paga la parte (b) de `PLUMA-E6-3`.

**Qué se agregó:**

- **`Pluma\Redaccion\DeclaracionIdentidadSintetica`** (mismo molde que `AvisoTransparenciaIa`): texto fijo, no configurable, que declara sin ambigüedad que el nombre del periodista es una identidad editorial sintética generada por IA, no una persona física, bajo dirección editorial humana. Cero cambio de esquema — se genera en el momento, igual que el aviso de transparencia.
- **`Pluma\Seo\PaginaAutorPeriodista`** — **primera página virtual del plugin**: `add_rewrite_rule` (`^periodista/([^/]+)/?$`), `query_vars`, `template_redirect` (resuelve el slug contra el banco de periodistas, solo `EstadoPeriodista::Activo`; si no resuelve, 404 real vía `$wp_query->set_404()` — nunca `exit`/`die`) y `template_include` (sirve `src/Seo/templates/pagina-autor.php`, integrada con `get_header()`/`get_footer()` del tema activo). Un periodista jubilado no gana página nueva, pero su firma en piezas ya publicadas no se toca.
- **`Pluma\Kernel\Activador`**: la regla debe existir desde la activación, pero `activar()` también corre en `plugins_loaded` (auto-actualización de esquema) antes de que `$wp_rewrite` exista — se difiere la purga a una opción-bandera (`pluma_flush_reescritura_pendiente`) que el próximo `init` real consume y borra una sola vez.
- **`Pluma\Seo\EmisorEsquemaFrontend`**: el `author.url` del JSON-LD (antes siempre ausente) ahora apunta a la página de autor cuando el nombre de la pieza resuelve a un periodista activo real — cierra el hueco que dejó abierto 4a.
- **`CLAUDE.md`** (ley de ingeniería, cambio declarado explícitamente): la lista de "el frontend público solo recibe…" se amplía para incluir la página de autor por periodista.

**Decisión del propietario:** página virtual renderizada por el plugin, no usuarios WP reales por periodista — evita contaminar `wp_users` con cuentas logueables innecesarias.

**Honestidad de alcance:** con esto, N.3 (a) y (b) están pagadas. Solo queda N.3 (c) — tipo de aprobación de primera clase + acción "aprobar ahora" en Copiloto — para la porción 4c.

**Verificación real (no solo test):** en el sitio dev de wp-env, plugin reactivado (confirma que la opción-bandera de purga se consume en el primer `init` real), un periodista creado vía `wp-cli`/`RepositorioPeriodistas`, `curl` a `/periodista/{slug}/` → 200 con el nombre y la declaración de identidad sintética presentes; `curl` a un slug inventado → 404 real.

### Evidencia de gates — Porción 4b

| Gate | Resultado |
|---|---|
| PHPCS | 0 errores |
| PHPStan L8 | 0 errores |
| `composer test:unit` | 374/374 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 155/155 (2 skipped esperados) |
| `npx vitest run` | 86/86 (sin cambios — porción 100% backend) |
| `npx tsc --noEmit` | limpio |
| `npm run build` | build de producción real, sin cambios |
| `npx playwright test tests/e2e/salud.spec.ts` | 2/2 |
| Verificación manual (`curl`) | `/periodista/{slug-real}/` → 200 con declaración; slug inventado → 404 real |

## Porción 4c — "Aprobar ahora" en Copiloto + tipo de aprobación auditable (commit pendiente)

Tercera y última porción del arco N.3. Paga por completo `PLUMA-E6-3` — el arco Art. 50 UE queda cerrado.

**Qué se agregó:**

- **`Pluma\Pipeline\GestorSalaRevision::aprobarAhora()`** (nuevo): aprobación humana activa sobre una pieza en la cola de veto de Copiloto, antes de que expire la ventana. Exige que la pieza esté `PROGRAMADA` en modo Copiloto con una ranura real; si no, lanza `AccionNoDisponibleException` (nueva). Endpoint `POST /pluma/v1/revision/{id}/aprobar-ahora`, misma capacidad `pluma_aprobar_piezas`.
- **Esquema `0.13.0`**: `pluma_cola_publicacion` gana `aprobacion_activa` (`TINYINT(1) DEFAULT 0`) y `pluma_auditoria` gana `tipo_aprobacion` (`VARCHAR(30) NULL`, valores `humana_activa`/`automatica_por_expiracion`, nuevo enum `Pluma\Pipeline\TipoAprobacion`) — nulo en cualquier transición que no sea programada→publicada. Reversa `0.13.0→0.12.0` registrada y probada con datos reales sembrados (`tests/Integration/MigracionA0130ConDatosRealesTest.php`).
- **`Pluma\Pipeline\Orquestador`**: `obtenerVencidas()` ahora también trae ranuras con `aprobacion_activa`, sin importar si su hora aún no llegó; el chequeo de ventana de Copiloto se salta cuando `aprobacionActiva` es verdadero. `snapshotPublicacion()` ya NO marca `generadoIa` cuando hubo aprobación activa — la misma excepción del Art. 50 que ya cubría a Piloto. La transición programada→publicada registra el `TipoAprobacion` correspondiente.
- **Panel**: nuevo botón "Aprobar ahora (publicar sin esperar)" en la Cola de veto de la Sala de Revisión, junto a "Vetar".

**Diseño deliberado:** "aprobar ahora" no publica de forma síncrona dentro del propio endpoint — solo marca la ranura. La publicación real sigue ocurriendo únicamente en el próximo tick del Orquestador (`procesarPublicacionesVencidas()`), preservando `wp_insert_post`/`Publicador` como el único punto de creación/actualización del post (CLAUDE.md § Ley de Arquitectura) y el patrón de lotes pequeños del cron real.

**Verificación real (no solo test):** en el sitio dev de wp-env — plugin reactivado (esquema confirmado en `0.13.0`, columnas nuevas presentes con sus valores por defecto); pieza real creada en estado Programada/Copiloto con una ranura 3 horas en el futuro; `GestorSalaRevision::aprobarAhora()` invocado; tick real del motor vía `POST /pluma/v1/motor/tick` con el token real; el post resultante quedó `publish` con `_pluma_generado_ia` vacío; la página real del post no emite `iptc.digitalSourceType` (a diferencia de una pieza publicada por expiración, que sí la lleva — 4a); la fila de `pluma_auditoria` quedó con `tipo_aprobacion = humana_activa`.

### Evidencia de gates — Porción 4c

| Gate | Resultado |
|---|---|
| PHPCS | 0 errores |
| PHPStan L8 | 0 errores |
| `composer test:unit` | 382/382 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 160/160 (2 skipped esperados) |
| `npx vitest run` | 87/87 |
| `npx tsc --noEmit` | limpio |
| `npm run build` | build de producción real |
| `npx playwright test tests/e2e/salud.spec.ts` | 2/2 |
| Migración `0.12.0→0.13.0` con datos reales | probada up y reversa (`MigracionA0130ConDatosRealesTest`) |
| Verificación manual end-to-end | aprobar ahora → tick real → post publicado sin marcado de IA, auditoría con `tipo_aprobacion=humana_activa` |
