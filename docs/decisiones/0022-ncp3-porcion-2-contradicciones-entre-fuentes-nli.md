# ADR 0022 — NCP-3 Porción 2: contradicciones entre fuentes vía NLI (N2-B.2)

- **Fecha**: 2026-08-03
- **Estado**: Aceptada — construida, conectada a producción, verificada
- **Contexto**: `docs/CEREBRO_PLUMA_v2.md` §0.1 punto 2, `ADR 0020` (NLI/RRK vía T3), `ADR 0021` (Porción 1 de NCP-3, mismo patrón)

## Decisión

Se construye `Pluma\Investigacion\DetectorContradiccionesNli`, segundo consumidor real de `ProveedorNliCerebroRemoto`. El canon (§0.1 punto 2): *"N2-B.2 clasifica contradicciones entre fuentes con reglas. La contradicción entre dos extractos ES la etiqueta CONTRADICTION del mismo modelo NLI."*

Compara cada par de hechos del expediente (`C(n,2)` comparaciones) vía `ProveedorNliCerebroRemoto::inferir()`; los pares con etiqueta `Contradiccion` por encima de un umbral configurable (`pluma_umbral_contradiccion_nli_fuentes`, fábrica `0.5`, opción independiente de la del Corrector Interno) se señalan en la petición generativa de `Pluma\Investigacion\ResolutorDisputas::resolver()` — mismo principio "prioriza, nunca sustituye" de `ADR 0021`. NLI por sí solo no distingue `TipoContradiccion` (`Cifra`/`Atribucion`/`Ocurrencia`), así que la llamada generativa se mantiene para la clasificación de subtipo; el canon pide "sin una sola llamada generativa" como visión final, pero eliminarla del todo exigiría un clasificador de subtipo que no existe hoy — inventarlo violaría cero-invención.

## Consecuencia de blast radius — declarada, no una decisión nueva

`ResolutorDisputas` está cableado directamente en `Pluma\Pipeline\Orquestador::procesarPieza()` — esta porción extiende la dependencia dura de T3 (ya confirmada obligatoria en `ADR 0021` para el Corrector Interno) también al paso de **investigación** del pipeline. Es la misma decisión ya tomada, aplicada de forma consistente al siguiente consumidor real de NLI — no se volvió a preguntar porque es la aplicación directa de una decisión ya confirmada, no una nueva.

## Hallazgos reales durante la verificación (ninguno introducido por esta porción)

1. **`RedactorConFallbackMecanicoTest`, `RedactorSinteticoTest` (2 archivos de la Porción 1), `OrquestadorTest` y 2 archivos de `tests/Invariantes/`** construían `ResolutorDisputas` directamente — corregidos tras búsqueda exhaustiva (`grep -r "new ResolutorDisputas("`), aplicando la lección explícita de `ADR 0021`.
2. **`Pluma\Tests\Unit\Kernel\CifradoTest` falla de forma preexistente** (5 errores "Constant AUTH_KEY already defined" + 1 fallo de aserción) cuando corre como parte de la suite completa — sus tests llevan `@runInSeparateProcess`, pero el aislamiento de proceso no se comporta como se espera en este entorno (Docker Desktop en Windows), dejando que constantes definidas por otros tests contaminen el proceso "aislado". **Confirmado con `git stash` contra el commit base**: el mismo fallo ya existía antes de cualquier cambio de esta porción — no introducido aquí. Registrado como deuda nueva (`PLUMA-E9-30`).
3. **`tests/Invariantes/EscasezHonestaInvarianteTest`/`RegistroDiagnosticoCompuertasInvarianteTest`** siguen fallando por el `ArgumentCountError` de `Orquestador` ya registrado en `ADR 0021`/`PLUMA-E9-29` — confirmado de nuevo que es ajeno a esta porción (el 2º argumento de `ResolutorDisputas` que esta porción añade se aplicó correctamente en ambos archivos; el fallo real está en la construcción de `Orquestador`, no tocada aquí).

## Verificación

- Gates: PHPCS 0, PHPStan nivel 8 0.
- PHPUnit `--testsuite=Unit`: 745 tests, **739 en verde** — los 5 errores + 1 fallo restantes son exclusivamente `CifradoTest`, confirmados preexistentes y ajenos (punto 2 arriba). Ningún test nuevo ni modificado por esta porción falla.
- PHPUnit `--testsuite=Invariantes`: 30 tests, 2 errores — los mismos 2 preexistentes de `ADR 0021`/`PLUMA-E9-29`, confirmados de nuevo ajenos a esta porción.
- No hace falta verificación real contra un servicio TEI — el protocolo de `ProveedorNliCerebroRemoto` ya está verificado en `ADR 0020`; esta porción es integración de código ya verificado (mismo criterio que `ADR 0021`).

## Consecuencias

- Segundo consumidor real de NLI en producción — ambas piezas construidas de NCP-3 usan el mismo patrón "capa determinista prioriza, LLM decide".
- T3 obligatorio ahora cubre investigación Y corrección — cualquier instalación sin T3 configurado no puede procesar ninguna Pieza con expediente de 2+ hechos.
- Dos piezas de deuda preexistentes quedan documentadas con evidencia real (`PLUMA-E9-29` reconfirmada, `PLUMA-E9-30` nueva) — ninguna bloquea el cierre de esta porción, ninguna fue introducida por ella.
