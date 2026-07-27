# Etapa 8 — El razonamiento completo (retrofit de mecanismo, TIER 1)

**Estado: EN CURSO.** Porción 1a cerrada. Roadmap completo de 10 porciones en `C:\Users\PCMASTER-2\.claude\plans\eager-fluttering-widget.md` (aprobado); las Porciones 2-10 abren su propio Mission Lock cuando llegue su turno.

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

## Porciones 1b-10

Pendientes. Alcance fundamentado y fuente ya verificada literalmente en el plan aprobado (`C:\Users\PCMASTER-2\.claude\plans\eager-fluttering-widget.md`); cada una abre su propio Mission Lock al comenzar.
