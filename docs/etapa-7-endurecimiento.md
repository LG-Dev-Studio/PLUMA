# Etapa 7 — Endurecimiento del criterio (retrofit crítico, TIER 0)

**Estado: EN CURSO.** Porción 1 (K.1 + K.2, pisos no compensables) completa. Pendientes: porción 2 (M.1 + N.1, derecho de réplica previa + perfil de jurisdicción) y porción 3 (J.1-J.2, contrato de independencia de familia de modelo).

## Objetivo y criterio de salida (`docs/PLAN-MAESTRO-EVOLUCION.md` §6)

> El sistema inmunológico enviado (Etapas 0-6) tiene agujeros; esta Etapa los cierra. Retrofit sobre `Compuertas`, `Redacción`, `GOVERNANCE` — no construcción nueva. Decisión de propietario ya resuelta de reabrir Etapas cerradas y verdes para endurecerlas (ADR 0001).

**Confirmado al abrir esta Etapa**: la fila "Gobernanza §5 completa" de la tabla de Etapa 7 ya estaba hecha desde la porción de gobernanza cerrada dentro de la Etapa 6 (`GOVERNANCE.md` §1.5/§1.6/§2.8, `docs/puntuaciones.md`, los 3 ADR de `docs/decisiones/`) — verificado por exploración de código antes de empezar, no repetido.

## Porción 1 — Puntuaciones: pisos no compensables (K.1 + K.2)

Ambas piezas comparten el mismo defecto de diseño ya diagnosticado por Nivel Tres K.3: un piso eliminatorio (sustento/estructura) diluido en un promedio con factores de prioridad. K.2 es, en palabras del propio documento fuente, "el hallazgo individual más valioso de los tres documentos: una compuerta que promedia no es una compuerta".

**Qué se agregó:**

- **K.1 — Selección de Ángulo** (`Pluma\Redaccion\SelectorAngulo`/`CandidatoTesis`): el piso de sustento (`UMBRAL_SUSTENTO_MINIMO = 40.0`) ya existía y filtraba candidatos antes de competir. El gap real era `CandidatoTesis::puntuacionTotal()`: seguía siendo la media simple de las 4 puntuaciones, incluido el sustento ya gateado — diluyéndolo de nuevo. Ahora es la media ponderada de los tres factores de PRIORIDAD únicamente: originalidad 0.40, compatibilidad con la línea editorial 0.35, potencial de conversación 0.25.
- **K.2 — Compuerta de Calidad** (`Pluma\Compuertas\CompuertaCalidad`/`DiagnosticoCalidad`): antes, sustento aportaba 25 puntos y estructura 20 puntos a una suma de 100 — sustento ya vetaba por separado vía `aprobada()`, pero estructura NO vetaba nunca, solo restaba puntos. Ahora ambos son pisos binarios (`elegible = sustentoAprobado && estructuraCompleta`); sin ambos, `puntuacionTotal = 0` sin excepción. Solo tras superarlos se ponderan proporción interpretativa (0.40), legibilidad (0.35) y presencia de voz (0.25).
- **`DiagnosticoCalidad`** gana el campo `estructuraCompleta: bool`; `desdeArray()` lee `?? true` para diagnósticos ya persistidos antes de este cambio (piezas ya publicadas no se reevalúan retroactivamente).
- **`docs/puntuaciones.md`**: las filas "Selección de Ángulo" y "Compuerta de Calidad" pasan de `PENDIENTE DE RETROFIT` a retrofitadas.

**Sin cambios de esquema, sin cambios de contrato REST/panel** — esta porción es 100% backend interno a `Compuertas`/`Redaccion`.

**Compatibilidad retroactiva deliberada**: `DiagnosticoCalidad::desdeArray()` asume `estructuraCompleta = true` cuando la clave no existe en el JSON persistido — evita que la lectura de un diagnóstico histórico (de una pieza ya publicada bajo las reglas antiguas) rompa por falta de una clave que no existía cuando se guardó.

### Evidencia de gates — Porción 1

| Gate | Resultado |
|---|---|
| PHPCS | 0 errores |
| PHPStan L8 | 0 errores |
| `composer test:unit` | 384/384 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 160/160 (2 skipped esperados) |
| `npx vitest run` | 87/87 (sin cambios — porción 100% backend) |
| `npx tsc --noEmit` / `npm run build` | sin cambios de panel esperados |
| `npx playwright test tests/e2e/salud.spec.ts` | 2/2 |
