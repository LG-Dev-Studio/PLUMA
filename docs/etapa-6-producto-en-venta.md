# Etapa 6 — Producto en venta

**Estado: EN CURSO.** Porciones 1 (Versionado SemVer + empaquetado reproducible + matriz de compatibilidad), 2 (telemetría opt-in + modo diagnóstico) y 3 (documentación de venta) completas. Porción 4 (cierre formal con beta cerrada externa) pendiente.

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
| `.github/workflows/compatibilidad.yml` (CI real, `workflow_dispatch`) | pendiente de disparar tras el push — requiere que el workflow exista en `origin` |

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
