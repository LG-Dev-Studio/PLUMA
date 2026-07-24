# Etapa 6 — Producto en venta

**Estado: EN CURSO.** Porción 1 (Versionado SemVer + empaquetado reproducible + matriz de compatibilidad) completa. Porciones 2-4 (telemetría + modo diagnóstico, documentación de venta, cierre formal con beta cerrada externa) pendientes.

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
