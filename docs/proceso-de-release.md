# Proceso de release — PLUMA Engine

Gobernado por GOVERNANCE.md §5. Este documento es la referencia operativa del checklist ESQUEMA de `AGENTS.md` y de la Etapa 6, porción 1 (`docs/etapa-6-producto-en-venta.md`).

## 1. Versionado (§5.1)

- SemVer estricto: `MAJOR.MINOR.PATCH`. `MAJOR` = cambio incompatible en un contrato público (hooks públicos — hoy ninguno, ver `docs/hooks.md` — o el formato de export/import del Banco de Periodistas). `MINOR` = funcionalidad nueva compatible hacia atrás. `PATCH` = corrección sin cambio de esquema ni de comportamiento observable.
- La versión vive en **cuatro lugares que deben coincidir siempre**: el comentario `Version:` de `pluma-engine.php`, la constante `PLUMA_ENGINE_VERSION`, `composer.json["version"]`, `package.json["version"]`. `tests/Unit/Datos/VersionConsistencyTest.php` es la guardia automática — corre en `composer test:unit`, temprano en el gate de CI. Un desincronizado real entre el comentario y la constante rompió el primer CI de la Etapa 5 (ver `docs/etapa-5-la-maquina-que-aprende.md`); este test existe para que no vuelva a pasar en silencio.
- Toda release parte de una rama `release/x.y.z` desde `main`. El `CHANGELOG.md` recibe una entrada visible para el cliente (nunca jerga interna de "porción") antes de fusionar.

## 2. Migración de esquema N-1 → N (§5.1, checklist ESQUEMA de `AGENTS.md`)

- `pluma_db_version` (constante `PLUMA_ENGINE_DB_VERSION_OBJETIVO`) es independiente de la versión del plugin — solo sube cuando `src/Datos/Esquema.php` cambia.
- `Esquema::sentenciasCreateTable()` es acumulativo (cada versión devuelve el `CREATE TABLE` completo de cada tabla) y `dbDelta` diffea contra lo instalado — el patrón estándar de WordPress para columnas nuevas sobre una tabla existente.
- **Toda transición de esquema que añada/cambie una columna o tabla debe registrar su reversa** en `Esquema::sentenciasReversaDesde()` en la misma porción que la introduce. `Migrador::revertirA()` ejecuta esa reversa y lanza `ReversaNoDisponibleException` si la transición no está registrada — nunca revierte "a ciegas". Las Etapas 0-5 no registraron reversa (deuda histórica aceptada, no se reconstruye retroactivamente); la transición `0.11.0→0.12.0` (Etapa 5, porción 3) es el caso de referencia construido en la Etapa 6, porción 1.
- Checklist obligatorio por bump de esquema (ver `AGENTS.md`, sub-agente ESQUEMA):
  - [ ] `up` limpio (migración hacia adelante probada con datos reales sembrados, no solo con `$wpdb` mockeado — ver `tests/Integration/MigracionConDatosRealesTest.php` como plantilla)
  - [ ] `reversa` limpia y probada (`Esquema::sentenciasReversaDesde()` + `Migrador::revertirA()`)
  - [ ] índices en todo campo de estado+fecha nuevo
  - [ ] tipos de columna alineados a los DTOs correspondientes
  - [ ] renombrados en 3 pasos (crear-copiar-borrar), nunca destructivo-directo
  - [ ] migración N-1→N probada contra una copia de datos reales antes de publicar la release

## 3. Empaquetado reproducible (§5.2)

- `bin/build-zip [version]` construye el árbol de producción **dos veces** en directorios independientes y compara su huella (rutas + contenido, sin metadata de timestamp) antes de comprimir — si el mismo commit produce árboles distintos, el script falla en vez de publicar un ZIP no determinista.
- Produce `pluma-engine-{version}.zip` + `pluma-engine-{version}.zip.sha256`. El checksum se publica junto al ZIP en cada release para que el cliente (o el instalador de una futura actualización, cuando exista GOVERNANCE §5.4) pueda verificar integridad.
- El ZIP se instala en un WP limpio como smoke test obligatorio (`.github/workflows/ci.yml`, job `empaquetado`) antes de publicar cualquier release.

## 4. Matriz de compatibilidad (§5.3)

- `.github/workflows/compatibilidad.yml`, disparado manualmente (`workflow_dispatch`) y automáticamente en cada push a `release/**` — **no en cada commit** (GOVERNANCE pide matriz "testeada por release", no por commit; ejecutar las 12 combinaciones teóricas en cada push sería carísimo sin aportar señal adicional a la CI rápida).
- 6 combinaciones reales: {WP mínimo (`Requires at least` del header, hoy 6.4), WP latest (pin de `.wp-env.json`, hoy 6.7.1)} × {PHP 8.2, PHP 8.3} sin plugin SEO (compatibilidad de plataforma) + {Yoast, Rank Math} en un único baseline fijo (WP latest + PHP 8.2, compatibilidad de convivencia SEO real, no mockeada).
- Antes de publicar una release: disparar `compatibilidad.yml` manualmente y confirmar las 6 combinaciones en verde con evidencia real (no asumida) — igual disciplina que la verificación de CI de cada porción (`docs/deuda.md`, feedback de confirmación de git de este proyecto).

## 5. Fuera de alcance de la Etapa 6 (deuda aceptada)

GOVERNANCE §5.4 (licenciamiento + servidor de actualizaciones propio con firmas) requiere infraestructura de servidor fuera de este repositorio. Pospuesto al final del desarrollo por decisión explícita del propietario — registrado como `PLUMA-E6-1` en `docs/deuda.md`. Este proceso de release no lo cubre todavía; la beta cerrada externa (criterio de salida de la Etapa 6) se distribuye sin verificación de licencia.
