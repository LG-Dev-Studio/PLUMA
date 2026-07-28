# Etapa 8 — El razonamiento completo (retrofit de mecanismo, TIER 1)

**Estado: EN CURSO.** Porciones 1 (1a+1b), 2, 3, 4, 5 (diferida, ADR 0004), 6, 7 (7a+7b+7c), 8 (diferida, ADR 0005 + alternativa del propietario, ADR 0006) y 9 completas. Roadmap completo de 10 porciones en `C:\Users\PCMASTER-2\.claude\plans\eager-fluttering-widget.md` (aprobado); la Porción 10 abre su propio Mission Lock a continuación.

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

## Porción 3 — Aritmética del Radar y asignación (Nivel Dos C.1-C.3)

**Decisiones de propietario tomadas al abrir esta porción** (Mission Lock): (1) no se integra Search Console como proxy de hueco competitivo en C.1 — evita inventar un mapeo que el texto fuente no pide literalmente; hueco y vida útil quedan diferidos (ya cubiertos por `PLUMA-E1-1`). `docs/puntuaciones.md` ya traía las dos tablas objetivo pre-registradas con los pesos exactos — esta porción implementa exactamente esas filas.

**Qué se agregó:**

- **C.1 — `Pluma\Sensores\PuntuacionOportunidad`**: afinidad con la línea editorial pasa de sumando a **puerta binaria** (`elegible = afinidad >= umbral_afinidad_minima`, opción `pluma_umbral_afinidad_minima`, default 15/100) — antes, una tendencia con afinidad cero (un evento deportivo en un sitio de tecnología) podía alcanzar puntuación alta si velocidad/hueco/vida útil eran altos, porque `0×0.30` seguía dejando 70 puntos de los otros factores. Reponderación honesta de los dos factores reales disponibles (velocidad 0.40, afinidad residual 0.15) — techo de `total` ahora es **55/100**, no 100, mientras hueco competitivo (0.25) y vida útil (0.20) sigan sin construir. No se añadieron parámetros `hueco`/`vidaUtil` con default 0.0: pasar un 0.0 como si fuera un dato medido sería el placeholder que CLAUDE.md prohíbe.
- **C.2+C.3 — `Pluma\Redaccion\AsignadorPeriodista`**: nuevo piso de dominio (`umbral_dominio_minimo_periodista`, opción `pluma_umbral_dominio_minimo_periodista`, default 40/100 — rechaza el ejemplo literal del propio texto fuente, dominio 1/5=20/100) — si ningún candidato lo supera, nueva `Pluma\Redaccion\NingunPeriodistaIdoneoException` (sibling de `DecisionEditorialException`, no subclase, para no caer en catches existentes) en vez de asignar "al menos malo". Nueva cascada de desempate cuando el primero y el segundo candidato están dentro de un margen configurable (`pluma_margen_empate_asignacion`, default 5/100): balance de carga → historial con la historia específica → `AzarInterface` con semilla — nunca "el primero del array" (el bug de desempate más común, que el código anterior sí tenía con su comparación `>` estricta). "Historia específica" (C.2 punto 2) reutiliza `Pieza::$piezaOriginalId` y `RepositorioPiezasInterface::obtenerPorId()` — ya modela exactamente "quién empezó esta historia", cero infraestructura nueva.
- **Estado `SIN_PERIODISTA_IDONEO`**: nuevo `EstadoPieza::SinPeriodistaIdoneo`, lateral desde `EnRedaccion` (sin migración — `pluma_piezas.estado` es `VARCHAR(30)`), reanudable a `EnRedaccion` o descartable. `ResultadoRedaccion` gana dos campos opcionales (`sinPeriodistaIdoneo`, `motivoSinPeriodistaIdoneo`) — los 5 call sites existentes no cambian. `RedactorConFallbackMecanico` captura la nueva excepción y devuelve el resultado sin pasar por el fallback mecánico (C.3 prohíbe escribir cualquier borrador). `Orquestador::procesarRedaccionYBorrador()` gana una rama nueva, antes del chequeo de `retenida`. Notificación por correo (`Pluma\Admin\NotificadorSinPeriodistaIdoneo`, copia exacta del patrón de `NotificadorRevision`, enganchada al evento genérico `pluma/pieza_sin_periodista_idoneo` que `Transicionador` ya dispara). Superficie en panel: nueva etiqueta en `PantallaPanel` y nuevo bucket de alerta en `RestPortada`.
- **Compatibilidad**: `AsignadorPeriodista` gana un constructor (`AzarInterface`) — 10 call sites de test reparados. `DecisionEditorial::decidir()` gana un parámetro final opcional `?int $piezaOriginalId` — sin romper llamadas existentes.

**Gap descubierto, no de esta porción**: el `decaimiento_temporal` de B.3 (Porción 2) y el consumo del gate `elegible`/`total` de C.1 en `Orquestador` quedan diferidos — ver `docs/deuda.md` (`PLUMA-E8-2`, `PLUMA-E8-5`).

**Sin cambios de esquema** — todo vive en opciones de WordPress y en el JSON/VARCHAR ya persistidos.

### Evidencia de gates — Porción 3

| Gate | Resultado |
|---|---|
| PHPCS | 0 errores |
| PHPStan L8 | 0 errores |
| `composer test:unit` | 492/492 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 168/168 (2 skipped esperados) |
| `npx vitest run` | 92/92 (esta porción SÍ toca panel — `PantallaPortada.tsx`, `BarraEstado`/`Aplicacion` fixtures reparadas) |
| `npx tsc --noEmit` | limpio |
| `npm run build` | build de producción real (`vite build`), sin errores |
| `npx playwright test tests/e2e/salud.spec.ts` | 2/2 — panel real cargado contra wp-env con los cambios de `PantallaPanel.php` presentes |

## Porción 4 — Prueba de Falseabilidad (Nivel Tres O.1)

**Diagnóstico del texto fuente**: el Paso 3 del Algoritmo de Decisión Editorial selecciona la tesis ganadora por puntuación y pasa directamente al Paso 4. El Libro exige "contraargumento reconocido y respondido" dentro del esqueleto de redacción, pero eso lo escribe el mismo periodista que ya defiende la tesis — con el incentivo de defenderla bien, no de encontrarle el fallo real. O.1 añade una Fase 3.5: una pasada adversarial acotada, con instrucción explícita de construir el caso más fuerte posible EN CONTRA de la tesis exacta, usando solo el expediente.

**Qué se agregó:**

- **`Pluma\Proveedores\PropositoLenguaje::Falsear`** (nuevo caso del enum): económico, temperatura 0.2 — mismo tratamiento que `Corregir` (evalúa, no crea).
- **`Pluma\Redaccion\VerificadorFalseabilidad`**: una llamada `PropositoLenguaje::Falsear` con directrices explícitamente adversariales ("eres un fiscal, no un abogado defensor"); devuelve `Pluma\Redaccion\ResultadoFalseabilidad` (`casoEnContra: string`, `fuerzaSustento: float` 0-100, misma escala que `CandidatoTesis::$puntuacionSustento` para que sean comparables). Umbral de retorno configurable (`umbralRegreso()`, opción `pluma_umbral_regreso_falseabilidad`, default 75/100).
- **`DecisionEditorial::decidir()`** orquesta la Fase 3.5 en un bucle entre el Paso 3 y el Paso 4:
  1. Si `fuerzaSustento >= umbralRetorno`: la tesis se descarta del array de candidatos y `SelectorAngulo::elegirGanadora()` reevalúa entre los restantes — el bucle repite la Prueba de Falseabilidad sobre el nuevo ganador. Si no queda ningún candidato, `DecisionEditorialException` (nunca "la menos derrotada").
  2. Si `fuerzaSustento >= puntuacionSustento` de la tesis (comparable, sin llegar al umbral) pero no la derrota: `tensionFalseabilidad` se registra y se pasa a `GeneradorEsqueleto::generar()` como nuevo parámetro opcional trailing.
  3. Si es menor: sin efecto, flujo normal.
- **`Pluma\Redaccion\FichaDecisionEditorial`** gana el campo opcional `tensionFalseabilidad` (trailing, con default `null` — compatible con JSON ya persistido).
- **`GeneradorEsqueleto::generar()`** gana un parámetro opcional trailing `?string $casoEnContraARespetar` — cuando está presente, añade una directriz explícita: el `contraargumentoReconocido` DEBE incorporar ese caso específico con el mismo peso argumental que la tesis, no como concesión menor.
- **Compatibilidad**: `DecisionEditorial` gana una nueva dependencia (`VerificadorFalseabilidad`) — 3 call sites de test reparados, más 2 secuencias de `ProveedorLenguajeSecuencial` que necesitaron una respuesta nueva insertada en el orden correcto (la Fase 3.5 consume una llamada del proveedor entre "candidatos" y "esqueleto").

**Fila registrada en `docs/puntuaciones.md` §5 antes de implementar** (GOVERNANCE §1.6: "ninguna puntuación nueva se acepta en el código sin su fila en este registro") — clasificada como "umbral de retorno", consistente con la clasificación que N4-I.2 ya le dio a esta pieza.

**Sin cambios de esquema, sin endpoints REST, sin cambios de panel** — 100% backend interno a `Redaccion`/`Proveedores`.

### Evidencia de gates — Porción 4

| Gate | Resultado |
|---|---|
| PHPCS | 0 errores |
| PHPStan L8 | 0 errores |
| `composer test:unit` | 502/502 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 168/168 (2 skipped esperados) |
| `npx vitest run` / `tsc` / `build` / Playwright | sin cambios de panel — no aplica a esta porción |

## Porción 5 — Réplica dirigida (M.2 Nivel 2-3) — DIFERIDA A VERSIÓN POSTERIOR AL LANZAMIENTO

**Decisión explícita del propietario (`ADR 0004`, 2026-07-27)**: Nivel 2 (búsqueda dirigida) depende literalmente del mismo mecanismo de búsqueda web que `PLUMA-E8-1` (B.2, Porción 2) ya dejó diferido — PLUMA no tiene hoy ningún proveedor de búsqueda web. Nivel 3 (contacto directo automatizado) tiene dependencias propias adicionales (canal de contacto verificado, cola de aprobación humana, ventana de espera temporizada en el grafo de estados) que tampoco existen. El propietario decidió diferir la Porción 5 completa a una versión posterior al lanzamiento, no solo bloquearla técnicamente.

El **Nivel 1** (verificación de postura ya existente en el expediente) ya está en producción desde la Etapa 7, Porción 2 (M.1+N.1) — no se ve afectado por este diferimiento.

Documentación completa de la funcionalidad diferida — qué es, por qué importa, diseño exacto para cuando se retome — en `docs/funcionalidad-replica-dirigida-m2.md`. Deuda registrada como `PLUMA-E8-6`.

## Porción 6 — Grafo y memoria (Nivel Dos E.1 + E.2)

**E.1 — estado lateral `SinPeriodistaIdoneo`**: ya construido en la Porción 3 (junto a C.3), sin cambios en esta porción. `RETENIDA: sin_activo_visual` sigue siendo un *motivo*, no un estado nuevo — comparte pieza con D.3 (Porción 8, imagen destacada), fuera de alcance aquí.

**E.2 — el problema de la memoria del periodista jubilado**: 5.8 del Libro dice que jubilar a un periodista significa "sus piezas quedan, deja de recibir asignaciones" — resuelve el problema de datos, no el editorial. La Memoria Editorial (5.4) es por periodista; un periodista nuevo (o uno que nunca cubrió un tema) no tenía forma de saber que el sitio, como voz colectiva, ya se pronunció sobre ese tema a través de alguien que ya no firma. Resultado sin corrección: el sitio puede contradecirse a sí mismo con total naturalidad.

**Qué se agregó:**

- **Esquema `0.14.0`**: nuevo índice `KEY tema (tema(100))` en solitario sobre `pluma_memoria_editorial`. El índice compuesto existente `periodista_tema (periodista_id, tema(100))` tiene `periodista_id` como columna líder — por la regla del prefijo izquierdo de MySQL, no sirve para la consulta "todas las posturas sobre este tema, de cualquier periodista" que la memoria colectiva necesita. Reversa registrada (`0.14.0->0.13.0`: `DROP INDEX tema`), probada con datos reales sembrados en el shape anterior (`tests/Integration/MigracionA0140ConDatosRealesTest.php`, mismo patrón que `MigracionA0130ConDatosRealesTest`).
- **`Pluma\Datos\RepositorioMemoriaEditorialInterface::obtenerPosturasColectivasPorTema( string $tema, int $limite = 20 )`**: filtra solo por `tipo = Postura` y `tema` — sin `periodista_id` — a diferencia de `obtenerPosturasPorTema()`. Implementado en `RepositorioMemoriaEditorial` con el mismo idioma de `$wpdb->prepare()` ya usado en el resto del repositorio.
- **`Pluma\Redaccion\PosturaColectiva`** (DTO nuevo, `final readonly`): empareja una `EntradaMemoria` con `periodistaNombre` y `periodistaActivo` — la atribución que el texto exige resolver antes de que la postura llegue al material del proveedor de lenguaje.
- **`DecisionEditorial::decidir()`**: tras la consulta de memoria individual ya existente, consulta `obtenerPosturasColectivasPorTema( $clasificacion->tema )`, excluye la entrada que pertenece al propio periodista asignado (ya cubierta por la memoria individual — evita duplicar el mismo hecho con dos atribuciones distintas), y resuelve cada autor restante vía `RepositorioPeriodistasInterface::obtenerPorId()`. Un registro huérfano (periodista eliminado, `obtenerPorId()` devuelve `null`) se descarta sin inventar atribución — cero invención también aplica a un dato de identidad que ya no existe.
- **`SelectorAngulo::generarCandidatos()`** gana un quinto parámetro `array $posturasColectivas = array()` (trailing, con default — los call sites existentes no cambian). Nueva directriz explícita: una postura colectiva que contradiga un candidato exige el mismo reconocimiento obligatorio ya vigente para la memoria individual, con la atribución exacta que corresponda:
  - Periodista aún **activo**: atribución individual por nombre — "un colega de esta redacción, {nombre}, sostuvo...".
  - Periodista **jubilado**: atribución de sitio, no de individuo — "esta redacción sostuvo antes (a través de un periodista que ya no forma parte del banco)..." — el cambio de forma que el propio texto fuente exige literalmente.

**Sin endpoints REST nuevos, sin cambios de panel** — 100% backend en `Datos`/`Redaccion`, con un cambio de esquema real (el primero de la Etapa 8).

### Evidencia de gates — Porción 6

| Gate | Resultado |
|---|---|
| PHPCS | 0 errores |
| PHPStan L8 | 0 errores |
| `composer test:unit` | 507/507 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 171/171 (2 skipped esperados) — incluye migración real 0.13.0→0.14.0→0.13.0 con datos sembrados |
| `npx vitest run` / `tsc` / `build` / Playwright | sin cambios de panel — no aplica a esta porción |

## Porción 7a — Modo respeto: clasificación de gravedad y máquina de estados (Nivel Dos F.1-F.2)

**Diagnóstico del texto fuente (F.1)**: el Radar clasificaba gravedad únicamente de forma implícita (3.3) y, en el código real, ese eje solo existía mucho más tarde como `ClasificacionNoticia::$gravedad` — calculado *después* de asignar periodista, inútil para un disparador que necesita actuar sobre tendencias crudas, antes de que exista ninguna Pieza. F.2 exige un disparador de dos niveles: automático (2+ tendencias de gravedad máxima, ventana corta, mismo campo temático o geográfico) y manual (un clic del editor). Dado que F.3 (efectos del modo respeto sobre el resto del pipeline) es una superficie propia y grande, esta porción se dividió en 7a (esta) y 7b — decisión de ejecución confirmada con el propietario al abrir la porción.

**Qué se agregó:**

- **Esquema `0.15.0`**: `pluma_tendencias` gana `gravedad TINYINT UNSIGNED NULL`, `campo_tematico VARCHAR(191) NULL`, `campo_geografico VARCHAR(191) NULL`, con un índice propio en `gravedad` — deliberadamente NO compuesto con `detectada_en` (`(gravedad, detectada_en)` disparaba `Duplicate key name` en ciclos repetidos de migración contra wp-env real, un límite conocido de `dbDelta` con claves multi-columna). Tabla nueva `pluma_modo_respeto`: registro histórico append-only (`id`, `activado_en`, `activado_por`, `motivo`, `duracion_minima_horas`, `desactivado_en`) — el estado actual es siempre la fila más reciente sin `desactivado_en`; `duracion_minima_horas` congela, en la propia activación, el piso vigente en ese momento, para que un cambio posterior de configuración no reabra ni acorte una ventana ya en curso.
- **`Pluma\Compuertas\ClasificadorGravedadTendencia`**: una llamada `PropositoLenguaje::Clasificar` (económica, mismo tratamiento que `ClasificadorNoticia`) sobre la tendencia cruda (término + artículos relacionados), devuelve `Pluma\Compuertas\GravedadTendencia` (`gravedad: int 0-100`, `campoTematico: string`, `campoGeografico: ?string`).
- **`Pluma\Datos\RepositorioModoRespeto`** (único punto con `$wpdb` para la tabla nueva) + `RepositorioTendencias::actualizarGravedad()`/`obtenerGravedadMaximaRecientes()`.
- **`Pluma\Compuertas\GestorModoRespeto`**: `evaluarDisparadorAutomatico()` agrupa las tendencias de gravedad máxima recientes por `campoTematico` y por `campoGeografico`, y activa si algún grupo tiene 2+ tendencias distintas; `activarManualmente()` es idempotente (reactivar un modo ya activo no reinicia la ventana de duración mínima); `desactivar()` lanza `ModoRespetoAunNoDesactivableException` si el piso configurado (`pluma_modo_respeto_duracion_minima_horas`, default 6h, piso de fábrica no editable a la baja 1h) no se cumplió.
- **`Orquestador`**: clasifica gravedad justo después de guardar cada tendencia nueva, y evalúa el disparador automático una vez por tick — ambos pasos envueltos en manejo de errores propio (`evaluarModoRespeto()`, mismo patrón que la resiliencia ante un Sensor caído): un fallo aquí se registra y el resto del pipeline sigue.
- **`Pluma\Admin\RestModoRespeto`** (`GET`/`POST /pluma/v1/motor/modo-respeto{,/activar,/desactivar}`, capacidad `pluma_configurar_motor`) + **`BloqueModoRespeto.tsx`** en la Sala de Máquinas: estado actual, quién/cuándo activó, motivo, piso de desactivación, botones activar/desactivar.

**Hallazgo de robustez descubierto y corregido durante esta porción** (no una feature de F.1-F.2, un defecto real de infraestructura): verificado contra wp-env real que ciclos repetidos de `ADD`/`DROP COLUMN` sobre la misma tabla, dentro de la misma sesión de MySQL/MariaDB, acumulan indefinidamente los restos internos de columnas que el algoritmo `INSTANT` conserva hasta la siguiente reconstrucción real de la tabla — eventualmente dispara `ERROR 1118 (Row size too large)` aunque las columnas vivas estén muy por debajo del límite real (reproducido de forma aislada y confirmado que `ALTER TABLE ... FORCE` lo resuelve). `Migrador::migrar()` ahora reconstruye cada tabla que `dbDelta` reporta haber modificado — condicional al resultado real de `dbDelta`, no incondicional, para no pagar el costo en el caso común (no-op). Beneficia a todo el ciclo de vida del plugin, no solo a esta porción.

**F.3 (forzar tono de Tragedia en todo el sitio, pausar cola con jitter recalculado, piso de duración) queda para la Porción 7b.**

### Evidencia de gates — Porción 7a

| Gate | Resultado |
|---|---|
| PHPCS | 0 errores |
| PHPStan L8 | 0 errores |
| `composer test:unit` | 522/522 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 178/178 (2 skipped esperados) — incluye migración real 0.14.0→0.15.0→0.14.0 con datos sembrados |
| `npx vitest run` | 95/95 |
| `npx tsc --noEmit` | limpio |
| `npm run build` | real, verificado |
| `npx playwright test tests/e2e/salud.spec.ts` | 2/2 |

## Porción 7b — Modo respeto: forzar tono de Tragedia en todo el sitio (Nivel Dos F.3, primera mitad)

**Diagnóstico del texto fuente (F.3)**: "no solo se bloquea sátira en la pieza sobre el evento grave: se degrada el registro de TODO lo que el sitio publique mientras el modo esté activo, incluyendo piezas de verticales no relacionados — el contexto del sitio entero es lo que un lector percibe, no el tema pieza por pieza." Dado el tamaño de F.3 completo (forzar tono + pausar cola con jitter + piso de duración ya construido en 7a), esta porción se dividió en 7b (forzar tono, esta) y 7c (pausar/reactivar la cola) — decisión de ejecución, mismo criterio que la Porción 1a/1b y 7a/7b.

**Qué se agregó:**

- **`Pluma\Redaccion\DecisionEditorial`** gana la dependencia `Pluma\Compuertas\GestorModoRespeto` (10º parámetro del constructor). En el Paso 4 (arquitectura de la pieza), la búsqueda de la fila de tono cambia de:
  ```php
  $filaMatriz = $periodista->conductaActual->matrizTonos->paraTipo( $clasificacion->tipoNoticia );
  ```
  a:
  ```php
  $tipoNoticiaParaTono = $this->gestorModoRespeto->estadoActual()->activo ? TipoNoticia::Tragedia : $clasificacion->tipoNoticia;
  $filaMatriz = $periodista->conductaActual->matrizTonos->paraTipo( $tipoNoticiaParaTono );
  ```
  Reutiliza `MatrizTonos::filaSistemaTragedia()` (Etapa 2, Libro Cap. 5.3) — la fila de sistema ya fuerza `Tono::InformativoEmpatico`/`Tono::Analitico`/sátira bloqueada para CUALQUIER periodista, sin excepción; F.3 solo necesitaba una forma de dirigir CUALQUIER pieza hacia esa fila cuando el modo respeto está activo, sin importar su vertical o clasificación real. Cero lógica nueva de tono — el punto de apalancamiento correcto ya existía.
  - **Deliberado**: `$clasificacion` (la real, con su `tipoNoticia` genuino) se sigue guardando sin tocar en la `FichaDecisionEditorial` — solo la variable local usada para la búsqueda de tono se fuerza. Esto preserva trazabilidad completa: después de desactivar el modo respeto, sigue siendo posible ver qué pieza se escribió bajo tono forzado y cuál era su clasificación original, sin necesidad de un campo nuevo ni de reescribir la Ficha.
- **Compatibilidad**: `DecisionEditorial` gana una nueva dependencia — 8 call sites de test reparados (`Nucleo.php` en producción, 4 en `DecisionEditorialTest.php`, 3 en `RedactorConFallbackMecanicoTest.php`), todos con un doble `GestorModoRespeto` inactivo por defecto (mismo patrón que otros dobles: repositorio mockeado devolviendo `EstadoModoRespeto::inactivo()`).

**Qué NO se tocó en esta porción**: la cola de publicación (`pluma_cola_publicacion`) sigue programándose y publicándose exactamente igual con el modo respeto activo — pausar las ranuras `Programada` no relacionadas con el evento, y reactivarlas con jitter recalculado al desactivar, es la Porción 7c. Sin cambios de esquema, sin endpoints REST, sin cambios de panel en esta porción — 100% backend en `Redaccion`.

### Evidencia de gates — Porción 7b

| Gate | Resultado |
|---|---|
| PHPCS | 0 errores |
| PHPStan L8 | 0 errores |
| `composer test:unit` | 524/524 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 178/178 (2 skipped esperados) |
| `npx vitest run` / `tsc` / `build` / Playwright | sin cambios de panel — no aplica a esta porción |

## Porción 7c — Modo respeto: pausar y reactivar la cola de publicación (Nivel Dos F.3, cierra F.3 completo)

**Diagnóstico del texto fuente (F.3, último efecto pendiente)**: "Piezas ya PROGRAMADA que no sean sobre el evento en cuestión: pausadas, no descartadas — la cola se reactiva completa al desactivar el modo, con el jitter de horario recalculado desde cero (publicar seis piezas de golpe al reactivar delataría automatización tan claramente como publicarlas exactamente en punto)."

**Decisión de alcance tomada al abrir esta porción**: el texto fuente no da un mecanismo para determinar "qué ranuras son sobre el evento en cuestión" — inventar un matching heurístico (por ejemplo, comparar el `vertical`/tema de la Pieza contra el campo temático/geográfico que disparó el modo respeto) violaría "cero invención" (CLAUDE.md): sería una regla de negocio no pedida por el texto fuente, con un modo de fallo real (falsos negativos que dejan pasar justo la pieza más sensible). Dado que una pieza *sobre* el evento que acaba de disparar el modo respeto casi nunca está ya en `Programada` en el momento de la activación (tiene que atravesar investigación→redacción→compuertas primero, y F.3 ya fuerza su tono a Tragedia desde la Porción 7b), la simplificación **pausar todas las ranuras `Programada` sin excepción** es defendible y segura por defecto — documentada aquí explícitamente como decisión de alcance, no como omisión silenciosa.

**Qué se agregó:**

- **`Pluma\Pipeline\EstadoColaPublicacion`** gana el caso `Pausada` — lateral y temporal, nunca un estado final; ninguna ranura pausada se descarta jamás por este mecanismo.
- **`Pluma\Datos\RepositorioColaPublicacionInterface`** gana `pausarProgramadas()` (`UPDATE` masivo `programada → pausada`, devuelve cuántas), `obtenerPausadas()`, y `reprogramar( int $id, DateTimeImmutable $nuevaHora )` (`pausada → programada` con hora nueva). `obtenerVencidas()` no necesitó cambios — ya filtraba `estado = programada`, así que deja de ver las pausadas automáticamente.
- **`Pluma\Pipeline\ProgramadorCadencia::rejitter()`** (nuevo método): conserva la franja horaria ya asignada a la ranura (misma hora, minutos a cero) y redibuja solo el jitter dentro de esa hora — deliberadamente NO rederiva cuota/ventanas/separación mínima para todo el lote reactivado a la vez (evitaría el problema de recomputar "quién cabía ese día" contra un conjunto de ranuras que ya estaban comprometidas antes de pausar); simplificación análoga a la ya aceptada y documentada en `siguienteRanura()` sobre el reparto proporcional por peso.
- **`Pluma\Compuertas\GestorModoRespeto`**: al activarse (automático o manual), llama `colaPublicacion->pausarProgramadas()`; al desactivarse (una vez cumplido el piso de duración mínima ya construido en 7a), recorre `obtenerPausadas()` y llama `reprogramar()` con la hora que devuelve `rejitter()` para cada una. Gana tres dependencias nuevas (`RepositorioColaPublicacionInterface`, `ProgramadorCadencia`, `LectorConfiguracionCadencia`) — 8 call sites de test reparados (`Nucleo.php` + 7 en tests).

**Cierra el Nivel Dos F.1-F.3 completo**: disparador de dos niveles (7a), forzar tono de Tragedia en todo el sitio (7b), pausar/reactivar la cola con jitter recalculado (7c), piso de duración mínima no editable a la baja (7a). Sin cambios de esquema en esta porción (`pausada` cabe en la columna `estado VARCHAR(20)` ya existente), sin endpoints REST nuevos, sin cambios de panel.

### Evidencia de gates — Porción 7c

| Gate | Resultado |
|---|---|
| PHPCS | 0 errores |
| PHPStan L8 | 0 errores |
| `composer test:unit` | 529/529 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 180/180 (2 skipped esperados) |
| `npx vitest run` / `tsc` / `build` / Playwright | sin cambios de panel — no aplica a esta porción |

## Porción 8 — Imagen destacada (D.1-D.3 + N.2 + G.2) — DIFERIDA A VERSIÓN POSTERIOR AL LANZAMIENTO

**Decisión explícita del propietario (`ADR 0005`, 2026-07-27)**: PLUMA no tiene hoy ningún proveedor de generación de imágenes ni de banco de stock con licencia integrado — elegir uno implica costo recurrente y términos de licencia comercial que el propietario decidió no resolver bajo presión de continuar la Etapa 8, el mismo tratamiento ya dado al proveedor de búsqueda web (`PLUMA-E8-1`) y a la réplica dirigida (`PLUMA-E8-6`/`ADR 0004`).

Se difiere la Porción 8 completa: D.1 (bifurcación generación IA vs. banco de stock), D.2 (Compuerta de Originalidad Visual — lista de bloqueo de marca/IP/artista vivo, registro de procedencia, consistencia visual por periodista), D.3 (fallback de tarjeta editorial tipográfica — técnicamente independiente de proveedor externo, pero diferido junto con el resto para no fragmentar la porción), N.2 (derechos de personalidad, extensión directa de D.2) y G.2 (`SatiricalArticle` en schema.org, agrupado en el plan por pertenecer al mismo módulo `Seo`).

Documentación completa de la funcionalidad diferida — qué es, por qué importa, diseño de referencia para cuando se retome — en `docs/funcionalidad-imagen-destacada-d1-d3.md`. Deuda registrada como `PLUMA-E8-7`, paga parcialmente `PLUMA-E3-2` (deuda preexistente desde la Etapa 3).

Ninguna porción posterior de la Etapa 8 depende de la imagen destacada — el roadmap continúa sin bloqueo con la Porción 9.

## Imagen destacada por autoridad de fuente — alternativa del propietario a la Porción 8 (`ADR 0006`)

Con la Porción 8 original ya diferida (`ADR 0005`), el propietario pidió un mecanismo distinto para la misma necesidad de producto — transferir la imagen del sitio fuente de mayor autoridad en vez de generar/comprar una:

> "ahora quiero que el sistema transfiera la imagen del sitio donde se cogieron las noticias y la suba con la noticia nuestra, la calificación de la portada dependerá de cuál fuente tiene mayor autoridad"

Esto es una **desviación deliberada** del principio establecido desde la Etapa 1 en `Pluma\Investigacion\HechoFuente` ("citar y enlazar, jamás reproducir") — el agente señaló el riesgo legal (posible infracción de derechos de autor incluso citando la fuente) antes de construir nada; el propietario, informado del riesgo, decidió proceder. Registro completo del razonamiento, la mitigación y la arquitectura en `ADR 0006`.

**Qué se construyó:**

- **`Pluma\Proveedores\ExtractorImagenFuenteInterface`/`ExtractorImagenFuente`** (nuevo): descarga la página del artículo fuente (`wp_remote_get`, timeout 8s), extrae `og:image`/`twitter:image` por regex, valida tanto la URL del artículo como la de la imagen con `Pluma\Proveedores\ValidadorUrl::esSegura()` (anti-SSRF, ya existente desde la Etapa 1) antes de usar ninguna.
- **`Pluma\Investigacion\SelectorImagenPorAutoridad`** (nuevo): ordena los hechos del expediente por `NivelFuente::pesoBase()` descendente (A > B > C, `Pluma\Investigacion\ClasificadorNivelFuente`, construido en la Etapa 8 Porción 2 pero nunca antes registrado en `Nucleo.php` — se registra por primera vez en esta porción), prueba cada host una sola vez hasta encontrar imagen.
- **`Pluma\Publicacion\ModoImagenDestacada`** (enum: `Ninguna`/`Enlazada`/`Descargada`) + **`Pluma\Publicacion\AsignadorImagenDestacada`**: modo `Enlazada` incrusta `<img>` apuntando a la URL original (nunca copia el archivo); modo `Descargada` usa `media_sideload_image()` + `set_post_thumbnail()` para una copia real en la biblioteca de medios del cliente. **`Ninguna` es el valor de fábrica** — ningún cliente queda expuesto al riesgo sin activarlo. Crédito a la fuente configurable de forma independiente del modo (visible por defecto), con aviso legal persistente en el propio texto del panel: ocultar el crédito no reduce el riesgo, y el texto lo dice explícitamente.
- **`Orquestador::procesarRedaccionYBorrador()`**: la asignación de imagen es mejor esfuerzo, envuelta en su propio `try/catch` que registra en `$errores` sin bloquear la Pieza — mismo patrón de resiliencia que `evaluarModoRespeto()` (Porción 7).
- **Panel**: nueva pestaña `imagenDestacada` en la Sala de Máquinas (`BloqueImagenDestacada.tsx`) — selector de modo, interruptor de crédito visible, aviso legal permanente (`role="alert"`).
- **`Pluma\Admin\RestImagenDestacada`**: `GET`/`POST /pluma/v1/motor/imagen-destacada`, capacidad `pluma_configurar_motor`.

**Cobertura de test y una limitación de herramientas documentada**: el modo `Descargada` no se pudo probar en la suite Unit — `vendor/php-stubs/wordpress-stubs` ya declara `media_sideload_image()` como función global real (para PHPStan), lo que bloquea a Brain\Monkey/Patchwork de interceptarla (solo puede shimear funciones genuinamente indefinidas). Se movió esa cobertura a `tests/Integration/AsignadorImagenDestacadaTest.php` contra WordPress real, donde la función existe de verdad. Esa misma suite de integración usa `pre_http_request` (no red real, GOVERNANCE §4.4) con una IP literal como host de prueba (`8.8.8.8`) en vez de un dominio `*.example.com` — `ValidadorUrl::esSegura()` hace una resolución DNS real (`gethostbyname()`) cuando el host no es ya una IP, así que un dominio de prueba forzaría tráfico de red real durante los tests.

Paga parcialmente `PLUMA-E3-2` (la necesidad de producto queda cubierta; la necesidad literal del Libro — imagen con licencia propia — sigue abierta). Nueva deuda registrada: `PLUMA-E8-8` (sin circuit breaker en `ExtractorImagenFuente`; ninguna verificación automática de licencia es posible).

### Evidencia de gates

| Gate | Resultado |
|---|---|
| PHPCS | 0 errores |
| PHPStan L8 | 0 errores |
| `composer test:unit` | 546/546 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 187/187 (2 skipped esperados) |
| `npx vitest run` | 95/95 |
| `npx tsc --noEmit` | 0 errores |
| `npm run build` | build de producción real, verificado |
| `npx playwright test tests/e2e/salud.spec.ts` | 2/2 |

## Porción 9 — Legitimidad del insumo (Nivel Dos G.1)

**Texto fuente** (`docs/PLUMA_Engine_Nivel_Dos.md`, Cap. G.1): "antes de que una tendencia entre a la cola editorial... necesita una verificación de naturalidad de la señal — patrones de crecimiento consistentes con difusión orgánica... versus... amplificación coordinada (pico sincronizado desde cuentas o fuentes con poca huella previa, concentración geográfica o de red anómala)". El propio texto admite el límite: "heurística mínima defendible (concentración de fuente, novedad de las cuentas de origen **donde el sensor lo exponga**)".

**Límite de datos verificado antes de escribir código**: `Pluma\Proveedores\TendenciaCruda`/`Pluma\Sensores\TendenciaDetectada` solo exponen, por artículo relacionado, `titulo`/`url`/`fuente` (nombre de fuente en texto libre, tal como lo reporta el feed de Google Trends) — ningún timestamp por artículo, ninguna geografía, ninguna señal de novedad de cuenta (mismo límite ya formalizado para L.2 en `PLUMA-E8-3`). Se implementa únicamente la pieza de G.1 que el dato actual permite sin inventar precisión que no existe: **concentración de fuente**.

**Qué se construyó:**

- **`Pluma\Sensores\EvaluadorLegitimidadInsumo`** (nuevo): calcula `diversidadFuente = fuentesUnicas / totalArticulos` sobre los artículos relacionados de una tendencia. Con menos artículos que el umbral configurado (`pluma_legitimidad_articulos_minimo`, default 3) la muestra se declara insuficiente y la tendencia se trata como legítima — nunca se declara ilegítima una tendencia por tener pocos artículos, podría ser cobertura temprana de un evento real. Con muestra suficiente, `diversidad < pluma_legitimidad_diversidad_minima` (default 0.34, ~1 de cada 3 artículos de fuente distinta) marca la tendencia como sospechosa.
- **`Pluma\Sensores\DiagnosticoLegitimidadInsumo`** (nuevo, DTO readonly): resultado con `totalArticulos`, `fuentesUnicas`, `diversidadFuente`, `legitimo`, `motivo`.
- **`EstadoTendencia::SospechaDeManipulacion`** (nuevo caso): mismo tratamiento que `Vigilada` — reversible por el editor con "Cubrir ahora" desde la Sala de Tendencias, nunca un bloqueo silencioso permanente (la heurística es imperfecta por diseño).
- **`RepositorioTendencias::guardarConSospechaLegitimidad()`** (nuevo, espejo de `guardarComoPosibleActualizacion`): persiste la tendencia con el diagnóstico (columnas nuevas `diversidad_fuente`/`motivo_legitimidad`) sin crear Pieza.
- **`Orquestador::detectarTendencias()`**: nueva evaluación entre la deduplicación por huella semántica y `tendencias->guardar()` — si `!legitimo`, guarda con sospecha y `continue` sin crear Pieza. Sin `try/catch`: el evaluador no hace I/O, no puede fallar de forma transitoria (igual que `ComparadorHistorias`).
- **Esquema `0.15.0 → 0.16.0`**: `pluma_tendencias` gana `diversidad_fuente DECIMAL(4,2) NULL` y `motivo_legitimidad VARCHAR(500) NULL` — auditoría y calibración futura del umbral, honesto per `PLUMA-EV-3`. Reversa registrada.
- **Panel**: nueva insignia "Sospecha de manipulación" en la Sala de Tendencias (`PantallaTendencias.tsx`), nuevo contador `sospechaManipulacion` en el Informe Editorial semanal (`RestInformesEditoriales`/`PantallaInformes.tsx`). Verificado contra el código real que `EstadoTendencia` no se renderiza genéricamente (cada caso es texto/JSX hardcodeado) — el panel SÍ requería estos cuatro archivos, no era gratis. Cero rutas REST nuevas: `GestorSalaTendencias::cubrirAhora()` ya es genérico por estado (comprueba si la Pieza es `null`/`Descartada`, y aquí siempre es `null` porque nunca se creó).

Paga parcialmente `PLUMA-EV-4` (concentración de fuente); novedad de cuentas de origen y concentración geográfica/de red siguen bloqueadas por el mismo límite de Sensor que `PLUMA-E8-3`, documentado explícitamente en el docblock de `EvaluadorLegitimidadInsumo`.

### Evidencia de gates — Porción 9

| Gate | Resultado |
|---|---|
| PHPCS | 0 errores |
| PHPStan L8 | 0 errores |
| `composer test:unit` | 554/554 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real) | 188/188 (2 skipped esperados) |
| `npx vitest run` | 96/96 |
| `npx tsc --noEmit` | 0 errores |
| `npm run build` | build de producción real, verificado |
| `npx playwright test tests/e2e/salud.spec.ts` | 2/2 |

## Porción 10

Pendiente. Alcance fundamentado y fuente ya verificada literalmente en el plan aprobado (`C:\Users\PCMASTER-2\.claude\plans\eager-fluttering-widget.md`); abre su propio Mission Lock a continuación.
