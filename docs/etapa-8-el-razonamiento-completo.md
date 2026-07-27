# Etapa 8 — El razonamiento completo (retrofit de mecanismo, TIER 1)

**Estado: EN CURSO.** Porción 1 completa (1a + 1b). Roadmap completo de 10 porciones en `C:\Users\PCMASTER-2\.claude\plans\eager-fluttering-widget.md` (aprobado); las Porciones 2-10 abren su propio Mission Lock cuando llegue su turno.

## Objetivo y criterio de salida (`docs/PLAN-MAESTRO-EVOLUCION.md` §6)

> Etapa TIER 1: hace que el pipeline enviado (Etapas 0-7, todas cerradas y en producción) efectivamente razone — no solo que publique bajo compuertas correctas (eso ya lo garantiza la Etapa 7). Se entrega "por porciones verticales por módulo".

**Hallazgo de diseño confirmado al abrir la Etapa** (verificado con WebFetch contra `https://openrouter.ai/docs/api-reference/embeddings`): A.5 (corpus de regresión de voz, Porción 1b) y J.3 (verificación determinista de trazabilidad, Porción 1b) comparten infraestructura de embeddings. OpenRouter — el mismo proveedor que ya usa el plugin — expone un endpoint real `POST /api/v1/embeddings`; no hace falta un segundo proveedor externo.

## Porción 1a — Cerebro editorial: plantilla versionada, anclas de 3 tramos y matriz de diales (Nivel Dos A.2-A.4)

**División de la Porción 1 original del plan** (First Principles, LG Labs): agrupar A.2-A.6+J.3 en un solo commit era una convención del documento fuente, no una dependencia real — las únicas piezas genuinamente acopladas son A.5 y J.3 (comparten infraestructura de embeddings). Se dividió en:

- **1a** (esta porción): "cómo se compilan las directrices" — infraestructura de embeddings + A.2 + A.3 + A.4.
- **1b** (siguiente): "infraestructura de verificación/testing" — A.5 + A.6 + J.3.

**Qué se agregó:**

- **Infraestructura de embeddings compartida** (`Pluma\Proveedores`): `EmbeddingsInterface::embed(string $texto): array` (contrato puro). `ProveedorOpenRouter` la implementa además de `LenguajeInterface` — misma cuenta, misma llave cifrada, mismo circuit breaker, mismo presupuesto diario (`PresupuestoLenguaje`, compartido: el coste marginal de embeddings es ~100x menor que chat completions, no justifica un presupuesto separado). Nueva opción `pluma_modelo_embeddings` (`EnrutadorModelos::modeloEmbeddings()`), default de fábrica `openai/text-embedding-3-small` — modelo de ejemplo citado literalmente en la documentación oficial de OpenRouter. `Pluma\Proveedores\SimilitudVectorial::coseno()`: función pura, sin dependencias, defensiva ante vectores vacíos/de longitud distinta/de norma cero.
- **A.2 — Plantilla de Prompt versionada**: `Pluma\Redaccion\PlantillaPrompt` (`final readonly class`) separa lo que ningún dial de periodista puede tocar (`seccionesFijas`: identidad, línea editorial, regla de oro contra la alucinación, vocabulario prohibido global+propio, bloqueo de sátira por sistema cuando aplica) de la traducción dial→directriz (`seccionesParametrizadas`). `CompiladorDirectrices::compilar()` sigue devolviendo el mismo `string` de siempre (cero cambio de contrato para `RedactorSintetico`); internamente delega en `compilarPlantilla()->ensamblar()`.
- **A.3 — Anclas de 3 tramos**: `CompiladorDirectrices::ancla()` deja de tener 2 extremos (bajo/alto, frase de 3-8 palabras) y pasa a 3 tramos continuos `[0,33) / [33,67) / [67,100]`, cada uno con una directriz corta **más un párrafo ancla real** (ejemplo de prosa en ese registro, copy editorial original, no una afirmación técnica que requiera verificación externa) — congelados en `src/Redaccion/references/anclas-diales.php`, cargados una sola vez por request (caché estática).
- **A.4 — Matriz de Combinación de Diales**: `Pluma\Redaccion\MatrizCombinacionDiales::directrices()` implementa literalmente las 4 combinaciones documentadas (no 28 pares a priori): humor+agudeza crítica altos (la agudeza ataca argumentos, jamás a la persona), vehemencia+empatía altas (orden de prioridad explícito en el mismo párrafo), sátira+densidad de datos alta (todo dato citado en pasaje satírico debe ser verificable igual que en uno serio), formalidad baja+vehemencia alta (estructura argumental obligatoria incluso en registro coloquial).

**Sin cambios de esquema, sin endpoints REST, sin cambios de panel** — 100% backend interno a `Proveedores`/`Redaccion`. `CompiladorDirectrices` no ganó dependencias de constructor (sigue construyéndose `new CompiladorDirectrices()` en `Nucleo.php`/`GeneradorVistaPrevia.php`, sin cambios en esos call sites).

**Compatibilidad**: `compilar()` mantiene exactamente su firma pública; los tests existentes de `CompiladorDirectricesTest` pasan sin modificación de expectativas (solo se reordenan internamente las secciones fijas antes que las parametrizadas, el contenido de cada línea no cambió salvo el propio rediseño de `ancla()`).

### Evidencia de gates — Porción 1a

| Gate | Resultado |
|---|---|
| PHPCS | 0 errores |
| PHPStan L8 | 0 errores |
| `composer test:unit` | 422/422 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 168/168 (2 skipped esperados) |
| `npx vitest run` | 91/91 (sin cambios — porción 100% backend) |
| `npx tsc --noEmit` | limpio |
| `npm run build` / Playwright | sin cambios de panel — no aplica a esta porción |

## Porción 1b — Prioridad de corrección, verificación determinista de trazabilidad y corpus de regresión de voz (Nivel Dos A.5-A.6 + Nivel Tres J.3)

**Qué se agregó:**

- **A.6 — Prioridad de corrección**: `PuntoCorrector::ordenDeReparacion()` fija el orden de REPARACIÓN (Hechos → MatrizYLineasRojas → SolapamientoNGrama → ProporcionInterpretativa → Voz → TitularHonesto) — distinto del orden de VERIFICACIÓN en que `CorrectorInterno::revisar()` evalúa y devuelve los 6 puntos (el orden de declaración del enum, Cap. 5.6). `RedactorSintetico::redactarPasada()` reordena los puntos reprobados según esta prioridad antes de instruir la siguiente pasada — antes se concatenaban en el orden de enumeración, "orden de verificación, no de reparación", exactamente el defecto que A.6 señala.
- **J.3 — Capa determinista de verificación de trazabilidad**: `Pluma\Redaccion\SegmentadorUnidadesFactuales` (segmentación determinista de oraciones, sin proveedor de lenguaje, con guarda de abreviaturas comunes y números decimales para no partir "Dr." o "4.2%" en dos unidades) + `Pluma\Redaccion\VerificadorTrazabilidadDeterminista` (para cada unidad factual, `embed()` + `SimilitudVectorial::coseno()` contra cada extracto de `HechoFuente` del expediente; unidad bajo el umbral configurable `pluma_umbral_similitud_trazabilidad`, default 0.75, se marca `SIN_RESPALDO_APARENTE`). `CorrectorInterno` gana esta capa como paso previo a su llamada LLM del punto "hechos": las unidades sin respaldo aparente se señalan explícitamente en la petición al proveedor — prioriza y abarata la evaluación semántica real, nunca la sustituye (los embeddings dan falsos positivos ante paráfrasis legítima, GOVERNANCE §2.4). Cambio de constructor en `CorrectorInterno` (nueva dependencia `VerificadorTrazabilidadDeterminista`) reparado en los 6 call sites existentes: `Nucleo.php` (nuevo registro DI de `SegmentadorUnidadesFactuales` + `VerificadorTrazabilidadDeterminista`) y 5 archivos de test, con un nuevo doble `Pluma\Tests\Unit\Dobles\EmbeddingsFalso` (vector constante por defecto — ningún test ajeno a J.3 ve una alerta de trazabilidad inesperada).
- **A.5 — Corpus de regresión de voz**: `Pluma\Redaccion\VerificadorRegresionVoz` (verificación 2 de 3, deriva semántica: similitud promedio de una muestra nueva contra el corpus de referencia de un periodista, vía embeddings; deriva excesiva bajo el umbral configurable `pluma_umbral_similitud_regresion_voz`, default 0.70) + `tests/Fixtures/corpus-voz.php` (corpus **mínimo de desarrollo**: 2-3 piezas por cada uno de los 3 periodistas sembrados de `PlantillasSiembra`, copy editorial original — no las 15-20 piezas curadas que exige la versión madura de A.5; CLAUDE.md prohíbe presentar datos inventados como reales, así que se declara explícitamente por lo que es, ampliable con piezas reales en Piloto) + `tests/Unit/Redaccion/CorpusVozFixturesTest.php` (verificación 1 de 3, presencia estructural: reutiliza `VerificadorVoz` tal cual, corre en cada `composer test:unit`). La verificación 3 de 3 (discriminación a ciegas) y la ejecución de la verificación 2 contra un proveedor real (GOVERNANCE §4.4 prohíbe que un test Unit llame a una API real) quedan documentadas como protocolo manual en `docs/protocolo-corpus-voz.md` — simularlas con código sería inventar una verificación falsa.

**Sin cambios de esquema, sin endpoints REST, sin cambios de panel** — 100% backend interno a `Redaccion`/`Kernel`.

### Evidencia de gates — Porción 1b

| Gate | Resultado |
|---|---|
| PHPCS | 0 errores |
| PHPStan L8 | 0 errores |
| `composer test:unit` | 442/442 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 168/168 (2 skipped esperados) |
| `npx vitest run` | 91/91 (sin cambios — porción 100% backend) |
| `npx tsc --noEmit` | limpio |
| `npm run build` / Playwright | sin cambios de panel — no aplica a esta porción |

**Cierra la Porción 1 completa de la Etapa 8 (A.2-A.6 + J.3).**

## Porción 2 — Investigador máquina (Nivel Dos B.1-B.4 + Nivel Tres O.2 + L.1-L.2)

**Decisiones de propietario tomadas al abrir esta porción** (Mission Lock): tres piezas del texto fuente dependen de infraestructura que no existe hoy — se decidió, en los tres casos, implementar lo no bloqueado y registrar el resto como deuda explícita en vez de inventar una solución o expandir el alcance sin límite:

1. **B.2 punto 2** (buscar una tercera fuente independiente para contradicciones de ocurrencia) requiere un proveedor de búsqueda web — PLUMA no tiene ninguno hoy (solo Google Trends y OpenRouter). Diferido, `PLUMA-E8-1`.
2. **B.3** (`decaimiento_temporal`) depende de la clasificación de vida útil de la tendencia (relámpago/ola/marea) — no existe en ningún lugar del código (`PLUMA-E1-1`). Se implementó el resto de la fórmula; el decaimiento queda fijo en `1.0`, `PLUMA-E8-2`.

**Qué se agregó:**

- **B.1+B.2 — Algoritmo de Resolución de Disputas**: `Pluma\Investigacion\TipoContradiccion` (`Cifra`/`Atribucion`/`Ocurrencia`) + `Pluma\Investigacion\ResolutorDisputas` — una llamada `PropositoLenguaje::Clasificar` sobre todos los hechos del expediente identifica pares contradictorios y su tipo. `Cifra`/`Atribucion` no mutan nada (`InvestigadorMecanico` ya nunca fusiona hechos distintos, así que ambas versiones ya conviven separadas). `Ocurrencia` marca ambos hechos `NivelVerificacion::Disputado` — el enum ya existía desde Etapa 1 pero **ningún código lo asignaba nunca** hasta ahora.
- **B.3 — Jerarquía de fuentes con decaimiento**: `Pluma\Investigacion\NivelFuente` (A=1.0/B=0.6/C=0.15) + `ClasificadorNivelFuente` (opciones `pluma_fuentes_nivel_a`/`pluma_fuentes_nivel_b`, listas editables por el cliente, todo lo no listado es C) + `CalculadoraPesoEfectivo` (`nivel_fuente_base × decaimiento_temporal(=1.0) × factor_independencia`). `factor_independencia` — detección de cadena de citación — vía solapamiento de n-gramas de 8 palabras entre extractos de fuentes distintas (mismo principio que `VerificadorNGramas` de `Redaccion`, deliberadamente no compartido entre capas). Clase construida y testeada, **no registrada todavía en `Nucleo.php`** porque ningún flujo real la invoca — mismo tratamiento que `VerificadorIndependenciaEpistemica` de la Etapa 7.
- **B.4+O.2 — Detección de hueco con relevancia causal**: `Pluma\Investigacion\DimensionEncuadre` (económica/humana/política/técnica/histórica/legal) + `Pluma\Investigacion\DetectorHuecos` — una llamada de clasificación evalúa las 6 dimensiones contra los hechos del expediente en tres preguntas (cubierta / datos disponibles / relevancia causal con los actores concretos de la tendencia); solo las dimensiones que fallan la primera y pasan las otras dos entran a la nueva propiedad `Expediente::$huecosDetectados`.
- **L.1 — Verificación de procedencia de la declaración**: `Pluma\Investigacion\EstadoProcedenciaDeclaracion` (`NoAplica`/`VerificadaCanalOficial`/`NoVerificada`) + `Pluma\Investigacion\VerificadorProcedenciaDeclaracion` — heurística determinista (comillas o verbo de atribución + lista configurable `pluma_canales_oficiales`), sin proveedor de lenguaje. Nueva propiedad `HechoFuente::$procedenciaDeclaracion`, poblada por `InvestigadorMecanico` para cada artículo (nueva dependencia en su constructor).
- **L.2 — Corroboración audiovisual (solo modelo de datos)**: `Pluma\Investigacion\EstadoCorroboracionAudiovisual` + nueva propiedad `HechoFuente::$corroboracionAudiovisual`, default `NoAplica`. La heurística real queda bloqueada: el Sensor/Radar no clasifica si una tendencia origen es audiovisual — sin esa señal, `InvestigadorMecanico` no puede decidir cuándo aplicar el chequeo. Registrado como `PLUMA-E8-3`, actualiza `PLUMA-EV-4`.
- **Wiring**: `Orquestador::procesarInvestigacion()` encadena `ResolutorDisputas::resolver()` y `DetectorHuecos::detectar()` sobre el expediente que ya construyó `InvestigadorMecanico`, antes de persistirlo — enriquecen, no reemplazan. Cambios de constructor en `InvestigadorMecanico` (nueva dependencia `VerificadorProcedenciaDeclaracion`) y `Orquestador` (nuevas dependencias `ResolutorDisputas`/`DetectorHuecos`) reparados en todos los call sites (`Nucleo.php` + 4 archivos de test).
- **Compatibilidad**: `HechoFuente` y `Expediente` ganan sus campos nuevos como parámetros opcionales con default (`NoAplica`/lista vacía) — con 23 y 24 call sites de `new HechoFuente(...)`/`new Expediente(...)` respectivamente en la base de tests, una reparación exhaustiva habría sido pura fricción sin valor; los defaults son semánticamente correctos (la mayoría de hechos no son declaraciones atribuidas ni de origen audiovisual, la mayoría de expedientes no tienen huecos). `desdeArray()` de ambos tolera JSON persistido antes de esta porción sin las claves nuevas.
- **Gap descubierto, no de esta porción**: durante el Mission Lock se confirmó que ningún lugar del código asigna nunca `NivelVerificacion::Verificado` — la triangulación real "2+ fuentes independientes" del Libro Cap. 4.3 nunca se implementó desde la Etapa 1. B.1-B.4/L.1-L.2 asumen esa base construida y solo la refinan; construirla requiere comparación semántica entre hechos (embeddings, ya disponibles desde la Porción 1) y está fuera del alcance literal de esta porción. Registrado como `PLUMA-E8-4`.

**Sin cambios de esquema** — `NivelVerificacion::Disputado` y las nuevas propiedades de `HechoFuente`/`Expediente` viven en el JSON del expediente ya persistido, ninguna tabla ni columna nueva.

### Evidencia de gates — Porción 2

| Gate | Resultado |
|---|---|
| PHPCS | 0 errores |
| PHPStan L8 | 0 errores |
| `composer test:unit` | 478/478 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 168/168 (2 skipped esperados) |
| `npx vitest run` | 91/91 (sin cambios — porción 100% backend) |
| `npx tsc --noEmit` | limpio |
| `npm run build` / Playwright | sin cambios de panel — no aplica a esta porción |

## Porciones 3-10

Pendientes. Alcance fundamentado y fuente ya verificada literalmente en el plan aprobado (`C:\Users\PCMASTER-2\.claude\plans\eager-fluttering-widget.md`); cada una abre su propio Mission Lock al comenzar.
