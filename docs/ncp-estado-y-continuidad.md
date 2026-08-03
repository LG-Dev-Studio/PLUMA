# Estado y continuidad — Núcleo Cognitivo Propio (NCP)

**Documento vivo** (a diferencia de los ADR en `docs/decisiones/`, que son inmutables): se actualiza en cada porción cerrada. Su propósito es que cualquier agente o desarrollador — incluida una sesión nueva de Claude Code sin memoria de esta conversación — pueda continuar el trabajo de NCP exactamente donde quedó, sin releer el historial completo de `git log` ni adivinar decisiones ya tomadas.

**Última actualización**: 2026-08-03, al cerrar NCP-3 Porción 3 (reordenamiento de hechos por relevancia vía RRK).

---

## 0. Cómo usar este documento (léelo primero, en este orden)

1. Lee `CLAUDE.md` completo (ley de ingeniería del proyecto) si no lo has leído en esta sesión — es innegociable, no un contexto opcional.
2. Lee `docs/CEREBRO_PLUMA_v2.md` completo (Partes 0-7) — es el canon de producto de esta línea de trabajo específica (NCP), análogo a como `docs/PLUMA_Engine_Libro_de_Arquitectura.md` es el canon del resto del producto. Presta especial atención a la Parte 5 (restricciones de código y de manera de pensar) y a la Parte 7 (protocolo de activación: investigación obligatoria con fuentes antes de código).
3. Lee este documento completo (es corto) para saber exactamente qué está cerrado, qué está bloqueado, y qué decisión está pendiente para seguir.
4. Lee los ADR listados en la sección 3 en orden — cada uno es la memoria completa de por qué se decidió lo que se decidió, con fuentes cuando aplica.
5. Antes de escribir código nuevo: sigue el Protocolo Mission Lock de `CLAUDE.md` (inventario PLAN GUARDIAN, Plan Mode, aprobación explícita del propietario antes de codificar). Esto se ha seguido sin excepción en todo el trabajo de NCP hasta ahora — no te saltes el patrón.

---

## 1. Qué es NCP, en una frase

`docs/CEREBRO_PLUMA_v2.md` es el encargo de un "cerebro híbrido": reducir la dependencia del producto de APIs de pago de IA generativa, sustituyendo lo que sea posible por PHP puro (Plano 0) o por modelos pequeños ONNX ejecutados en CPU (Plano 1), manteniendo la generación real (Plano 2) como acelerador opcional, nunca como única vía. Se divide en 6 fases (NCP-1 a NCP-6, tabla completa en `docs/CEREBRO_PLUMA_v2.md` Parte 6.2); cada fase se ejecuta en "porciones" pequeñas con gates completos entre cada una — mismo patrón que las Etapas del roadmap principal (`docs/PLAN-MAESTRO-EVOLUCION.md`), pero es una línea de trabajo paralela y distinta, no sustituye a las Etapas.

---

## 2. Estado exacto por fase y porción

### NCP-1 · Recorte — CASI CERRADA (2 de 5 porciones bloqueadas, no fallidas)

| Porción | Contenido | Estado | ADR | Commit |
|---|---|---|---|---|
| 1 | Instrumento de medición de llamadas al modelo (tabla `pluma_llamadas_modelo`, origen cron/panel/visitante) | **CERRADA** | `ADR 0010` | `fea4ac3` |
| 2 | Auditoría de datos reales de la instrumentación de la Porción 1 | **BLOQUEADA** — necesita semanas de datos de producción acumulados, no hay atajo | — | — |
| 3 | Capa `Pluma\Idioma` + `PerfilIdioma` + activación real de `localeEditorial` en el panel | **CERRADA** | `ADR 0012` | `e35a4c0` |
| 4 | Sonda de Capacidades (`SensorCapacidades`, `ResolutorPerfilEntorno`, `AlmacenPerfilEntorno`, cerebro remoto T3 con credenciales) | **CERRADA** | `ADR 0013` | `4ab2974` |
| 5 | "El recorte" real (reasignar llamadas generativas identificadas como innecesarias a Plano 0) | **BLOQUEADA** — depende de los datos de las Porciones 1-2 | — | — |

**Nota aparte, ya cerrada antes de NCP-1**: el retiro completo de la función de comentarios (`ADR 0011`, commit `da8e03b`) — decisión del propietario, no parte de NCP pero ocurrió en la misma ventana de trabajo, mencionado aquí solo por continuidad cronológica del `git log`.

Las Porciones 2 y 5 **no se pueden forzar** — no hay investigación ni ingeniería que sustituya la necesidad de datos reales de uso en producción. Si en el futuro se retoman, lo primero es comprobar cuánto tiempo lleva el sitio real en producción y si `pluma_llamadas_modelo` tiene ya una muestra representativa (semanas, no días).

### NCP-2 · Semántico — EN CURSO, 2 porciones cerradas, sin porción 3 planeada todavía

Investigación previa obligatoria (canon Parte 7, punto 3) ya hecha y documentada en `ADR 0014` — 4 rondas de sub-agentes con fuentes citadas:
1. Transportes T1-T4 desde PHP (viabilidad real por tipo de hosting).
2. Candidatos de modelo con licencia comercial verificada, para los 8 roles del canon (ENC, NER, SEG, LID, NLI, RRK, CLS, TOX).
3. Segmentación para escrituras sin espacios (CJK/Thai) y RTL (Árabe/Hebreo).
4. Reglas de distribución (Envato/venta directa) para plugins que descargan binarios o llaman a servicios externos.

| Porción | Contenido | Estado | ADR | Commit |
|---|---|---|---|---|
| 1 | Spike: ¿puede `transformers-php` (FFI) tokenizar+ejecutar `multilingual-e5-small` dentro del propio entorno de desarrollo? | **FALLIDA CON EVIDENCIA REAL** — el contenedor `cli` de wp-env no tiene FFI compilado (`class_exists('FFI') === false`). T1 descartado como transporte por defecto para ENC. | `ADR 0015` | `0122c97` |
| 2 | `ProveedorEmbeddingsCerebroRemoto` — ENC vía T3 (cerebro remoto), protocolo real de Hugging Face TEI (`POST /embed`), verificado contra un servicio TEI real en Docker | **CERRADA**, código en producción (no vinculado por defecto — ver §4) | `ADR 0016` | `420641d` |
| 3 | Herramienta de calibración de embeddings (`tools/calibracion-embeddings/`) — mide distribución de similitud del proveedor T3 contra fixtures de desarrollo (voz + trazabilidad, este último nuevo), sin recalibrar producción | **CERRADA** — mecanismo listo; calibración de **producción** sigue bloqueada por falta de corpus real de Piloto (mismo estatus que NCP-1 Porciones 2/5) | `ADR 0017` | (pendiente de commit) |
| 4 | Rol SEG — `Pluma\Idioma\SegmentadorOraciones`, segmentación de oraciones vía ICU (`IntlBreakIterator`) con fallback determinista en PHP puro | **CERRADA** — registrada en el contenedor de DI, sin consumidor real todavía | `ADR 0018` | (pendiente de commit) |
| 5 | Registro de modelos formal — `Pluma\Proveedores\RegistroModelos`/`ModeloRegistrado`/`RolModelo`, consolida `ProveedorEmbeddingsCerebroRemoto::MODELO_REFERENCIA` (retirada) | **CERRADA** — 3 entradas reales hoy (ENC, NLI, RRK) | `ADR 0019` | (pendiente de commit) |
| 6 | NLI y RRK vía T3 — `Pluma\Proveedores\ProveedorNliCerebroRemoto`/`ProveedorRerankCerebroRemoto`, corrige 2 candidatos de `ADR 0014` incompatibles con TEI | **CERRADA** — última de las 3 porciones del orden decidido 2026-08-03: (b) SEG → (c) Registro → (a) NLI/RRK | `ADR 0020` | (pendiente de commit) |

### NCP-3 · Verificación — EN CURSO, 3 porciones cerradas

Primera fase de NCP donde capacidades de NCP-2 se conectan a consumidores reales de producción (hasta entonces todo NCP-2 quedaba deliberadamente aislado, sin consumidor, per el principio "cero enforcement prematuro" de toda la sesión).

| Porción | Contenido | Estado | ADR | Commit |
|---|---|---|---|---|
| 1 | `Pluma\Redaccion\VerificadorContradiccionNli` — detección de contradicciones unidad↔hecho vía NLI real, conectada a `CorrectorInterno` como alerta adicional para el punto "hechos" (N3-J.3, "de similitud a implicación") | **CERRADA** — primer consumidor real de `ProveedorNliCerebroRemoto`. **T3 pasa a ser obligatorio** para todo el pipeline de redacción (decisión confirmada explícitamente por el propietario, ver §4) | `ADR 0021` | (pendiente de commit) |
| 2 | `Pluma\Investigacion\DetectorContradiccionesNli` — detección de contradicciones entre pares de hechos del expediente vía NLI real, conectada a `ResolutorDisputas` como alerta adicional (N2-B.2, "la contradicción entre dos extractos ES la etiqueta CONTRADICTION") | **CERRADA** — segundo consumidor real de `ProveedorNliCerebroRemoto`. T3 obligatorio ahora cubre también el paso de investigación (misma decisión de `ADR 0021`, aplicada de forma consistente) | `ADR 0022` | (pendiente de commit) |
| 3 | `Pluma\Investigacion\OrdenadorHechosPorRelevancia` — reordena (nunca excluye) los hechos del expediente por relevancia vía RRK real, conectado en `Orquestador::procesarInvestigacion()` | **CERRADA** — primer consumidor real de `ProveedorRerankCerebroRemoto`. **Degrada con gracia** (no propaga fallos de T3) — primera pieza de NCP-3 donde T3 sigue opcional | `ADR 0023` | (pendiente de commit) |

---

## 3. Índice completo de ADR relevantes para NCP (leer en este orden si se retoma el trabajo)

- `docs/decisiones/0010-ncp1-recorte-enmienda-de-fase.md` — por qué NCP-1 existe, auditoría inicial de las 21 llamadas reales al proveedor de lenguaje.
- `docs/decisiones/0012-capa-idioma-y-locale-editorial-activo.md` — `Pluma\Idioma`, `PerfilIdioma`, por qué el Plano 1 todavía no existe y qué NO se le puede prometer.
- `docs/decisiones/0013-sonda-de-capacidades-medicion-sin-enrutamiento.md` — la Sonda mide, no enruta; T4 excluido del enum server-side; por qué el cerebro remoto nunca se prueba en vivo desde el tick.
- `docs/decisiones/0014-ncp2-investigacion-transportes-modelos-y-distribucion.md` — **el documento de referencia técnica más denso**: tabla completa de modelos cualificados/descalificados por rol, con licencia y fuente.
- `docs/decisiones/0015-ncp2-porcion-1-veredicto-spike-embeddings-locales.md` — por qué T1/FFI queda descartado, con evidencia de comandos reales ejecutados.
- `docs/decisiones/0016-ncp2-porcion-2-enc-via-t3-veredicto.md` — el protocolo real de T3 para embeddings, con evidencia de un servicio real verificado.
- `docs/decisiones/0017-ncp2-herramienta-calibracion-embeddings.md` — la herramienta de calibración construida para la opción (d) de §5, con evidencia real de una corrida contra TEI local y el hallazgo honesto del solapamiento intra/inter en voz.
- `docs/decisiones/0018-ncp2-porcion-4-seg-segmentacion-icu.md` — rol SEG (segmentación de oraciones), ICU con fallback, y el hallazgo real de que ICU no protege abreviaturas editoriales por defecto.
- `docs/decisiones/0019-ncp2-porcion-5-registro-modelos-formal.md` — el registro de modelos formal, con su única entrada real hoy (ENC) y por qué el campo checksum es honestamente nulo para T3.
- `docs/decisiones/0020-ncp2-porcion-6-nli-rrk-via-t3.md` — NLI y RRK vía T3, con la corrección real de 2 candidatos de `ADR 0014` (arquitecturas incompatibles con TEI) y evidencia de los reemplazos verificados.
- `docs/decisiones/0021-ncp3-porcion-1-contradicciones-via-nli.md` — primer consumidor real de NLI, la decisión de T3 obligatorio confirmada por el propietario, y un bug real de descubrimiento de tests en PHPUnit + Docker-en-Windows encontrado y resuelto durante la verificación.
- `docs/decisiones/0022-ncp3-porcion-2-contradicciones-entre-fuentes-nli.md` — segundo consumidor real de NLI (contradicciones entre fuentes, N2-B.2), extiende T3 obligatorio a investigación, y confirma con `git stash` que 2 fallos de test adicionales (`CifradoTest`, `Orquestador`) son preexistentes y ajenos.
- `docs/decisiones/0023-ncp3-porcion-3-orden-hechos-por-relevancia-rrk.md` — primer consumidor real de RRK (reordenamiento de hechos), y el primer caso de NCP-3 donde T3 se deja explícitamente opcional (degradación con gracia, no propagación de fallo).

---

## 4. Decisiones de arquitectura vivas — qué NO tocar sin decisión explícita nueva

Estas reglas ya están decididas y documentadas; un continuador **no debe deshacerlas por su cuenta** ni "mejorarlas" sin plantear la pregunta al propietario primero:

1. **`Pluma\Proveedores\EmbeddingsInterface::class` sigue vinculado a `ProveedorOpenRouter`** en `src/Kernel/Nucleo.php` (~línea 464). `ProveedorEmbeddingsCerebroRemoto` existe, está registrado en el contenedor de DI, tiene tests, y **no se usa por defecto**. Los 2 consumidores reales (`VerificadorRegresionVoz`, umbral `0.70`; `VerificadorTrazabilidadDeterminista`, umbral `0.75`) siguen usando la distribución de similitud de OpenRouter. Cambiar esto exige recalibrar ambos umbrales explícitamente — nunca un efecto colateral de otra tarea.
2. **Ninguna capa de `Pluma\Compuertas` conoce ni depende de `TransportePlano1`/`PerfilEntorno`/la Sonda de Capacidades.** El enrutamiento real (qué plano atiende qué operación, restricción del modo Autónomo cuando falta Plano 1) es exclusivamente NCP-4 · Enrutador — una fase que todavía no ha empezado. No conectar nada de esto a `ModoOperacion`/`GestorDegradacion`/`EvaluadorCompuertas` sin que sea, otra vez, una decisión explícita y documentada.
3. **`TransportePlano1` no incluye T4 (navegador/WASM)** — decisión deliberada (`ADR 0013`), no un olvido. Si se necesita en el futuro (probablemente para trabajo interactivo del panel, nunca para el cron), es una extensión nueva con su propia justificación, no un "arreglo" de un enum incompleto.
4. **`ProveedorCerebroRemoto::probar()` y el refresco de `SensorCapacidades` nunca deben empezar a compartir la misma llamada de red.** El diseño depende de que `ultimaPruebaOk()` sea siempre una lectura cacheada — si alguna vez se tienta "simplificar" haciendo que el sensor pruebe en vivo, se reintroduce exactamente el riesgo que `ADR 0013` resolvió (llamadas de red dentro de la sección no presupuestada del tick del Orquestador).
5. **`SensorCapacidades`, `ProveedorCerebroRemoto` y `ProveedorEmbeddingsCerebroRemoto` son `final class` sin interfaz propia** (a diferencia de `AlmacenPerfilEntorno`, que sí tiene `AlmacenPerfilEntornoInterface` porque tiene múltiples consumidores). Si una clase `final` necesita mockearse en un test nuevo, la solución establecida en este proyecto es construir una instancia real controlada vía `get_option`/colaboradores inyectados (interfaces reales), **nunca** intentar `Mockery::mock()`/`createMock()` sobre una clase `final` — PHP no lo permite, y ya ha costado una ronda de correcciones en esta sesión dos veces (ver `tests/Unit/Kernel/SensorCapacidadesTest.php` y `tests/Unit/Proveedores/ProveedorEmbeddingsCerebroRemotoTest.php` como los dos ejemplos ya resueltos de este patrón).
6. **T3 (cerebro remoto) es OBLIGATORIO desde NCP-3 Porción 1 (`ADR 0021`) — ya no es infraestructura opcional.** `Pluma\Redaccion\VerificadorContradiccionNli` es dependencia dura de `CorrectorInterno`; sin T3 configurado, `CorrectorInterno::revisar()` lanza `ProveedorLenguajeException` sin capturar y **ninguna Pieza puede corregirse/publicarse**. Esto fue presentado explícitamente al propietario (dos veces, con la alternativa de fallo silencioso que habría mantenido T3 opcional) y **confirmado a propósito**, no es un descuido — no lo trates como un bug a "arreglar" volviendo a hacer T3 opcional sin plantear la pregunta de nuevo.
7. **`phpunit.xml.dist` lista las subcarpetas de `tests/Unit/` explícitamente, una por `<directory>`, en vez de un único `<directory>tests/Unit</directory>` recursivo.** Descubierto en `ADR 0021`: el escaneo recursivo único empezó a perder tests silenciosamente (sin error, "OK" con un conteo menor) al cruzar cierto tamaño del árbol en este entorno (Docker-en-Windows). Cualquier subcarpeta nueva bajo `tests/Unit/` debe añadirse a esa lista explícita — si no, sus tests podrían no ejecutarse nunca sin que ningún gate lo detecte.
6. **El modelo de referencia verificado es `intfloat/multilingual-e5-small` (MIT)**, servido vía Hugging Face Text Embeddings Inference. Cualquier cambio de modelo de referencia debe volver a pasar por la tabla de licencias de `ADR 0014` — no asumir que otro modelo de la misma familia tiene la misma licencia (ver los casos ya descalificados en esa tabla: varios modelos con apariencia similar tienen licencias no comerciales o ambiguas).

---

## 5. Qué sigue — decisión abierta, sin resolver todavía

No hay una porción 3 de NCP-2 planeada. Las opciones reales sobre la mesa, ninguna descartada:

- **(a) Reutilizar el mismo patrón de transporte T3 para otro rol** — **RESUELTA 2026-08-03 (NCP-2 Porción 6, `ADR 0020`)**: `Pluma\Proveedores\ProveedorNliCerebroRemoto` y `Pluma\Proveedores\ProveedorRerankCerebroRemoto` construidos y verificados. El candidato NLI original de `ADR 0014` (mDeBERTa-v3) resultó incompatible con TEI (arquitectura no soportada) — reemplazado por `MoritzLaurer/xlm-v-base-mnli-xnli` (XLM-RoBERTa, verificado). Ninguno de los 2 candidatos RRK originales resultó directamente verificable — reemplazado por `BAAI/bge-reranker-base` (verificado, pero solo chino/inglés). Ver `ADR 0020` para la investigación completa.
- **(b) Empezar por SEG** (segmentación) — la opción de menor riesgo posible: no necesita ningún modelo ONNX, se resuelve con `ext-intl`/`IntlBreakIterator` en PHP puro (`ADR 0014`), y es exactamente el campo "Segmentador por escritura" que `Pluma\Idioma\PerfilIdioma` dejó fuera a propósito en NCP-1 Porción 3. Esta opción ya se le ofreció al propietario antes de elegir ENC — sigue disponible.
- **(c) Construir el registro de modelos formal** — **RESUELTA 2026-08-03 (NCP-2 Porción 5, `ADR 0019`)**: `Pluma\Proveedores\RegistroModelos`/`ModeloRegistrado`/`RolModelo` construidos, con 1 entrada real (ENC). `ProveedorEmbeddingsCerebroRemoto::MODELO_REFERENCIA` retirada, consolidada en el registro.
- **(d) Decidir si se recalibra `EmbeddingsInterface` hacia el proveedor local** para los 2 consumidores reales — ahorraría coste real de OpenRouter, pero exige medir la nueva distribución de similitud de `multilingual-e5-small` contra el corpus real de voz/trazabilidad del cliente antes de fijar nuevos umbrales (no se puede adivinar `0.70`/`0.75` equivalentes sin datos reales — mismo principio que bloquea NCP-1 Porciones 2 y 5). **Actualización 2026-08-03 (NCP-2 Porción 3, `ADR 0017`)**: el mecanismo de calibración ya existe (`tools/calibracion-embeddings/`) y se verificó contra un corpus mínimo de desarrollo — reveló separación clara en trazabilidad pero solapamiento notable en voz (ver `ADR 0017` para la lectura honesta y sus causas posibles sin confirmar). Sigue bloqueada para producción por falta de corpus real de Piloto; cuando exista, esta opción ya no exige construir el mecanismo desde cero.
- **(e) Pausar NCP y volver al roadmap principal** — Etapa 9 ya cerró (`docs/etapa-9-el-medio-real.md`); Etapa 10 (`docs/PLAN-MAESTRO-EVOLUCION.md` §6) es lo siguiente en ese roadmap si el propietario prefiere dejar NCP en este punto y no retomarlo de inmediato.

**Un continuador debe presentar estas opciones al propietario (o preguntar directamente) antes de elegir una — no decidir por su cuenta cuál sigue.** Esto es exactamente lo que se ha hecho en cada bifurcación de esta sesión (ver el patrón repetido de `AskUserQuestion` antes de cada porción nueva en el historial de commits).

---

## 6. Herramientas de desarrollo activas (no confundir con el producto)

- **`tools/tei-local/`** (`docker-compose.yml` + `README.md`): levanta un servicio real de Hugging Face Text Embeddings Inference sirviendo `multilingual-e5-small`, usado para verificar `ProveedorEmbeddingsCerebroRemoto`. **No forma parte del plugin** — no lo orquesta `@wordpress/env`, no se empaqueta en el ZIP de distribución.
  - Estado actual: el contenedor (`pluma-tei-local`) y su red Docker se apagaron y eliminaron (`docker compose down`) al cerrar la Porción 2. El volumen con los pesos del modelo ya descargados (`tei-local_tei-data`, ~500MB) **sigue existiendo** en el Docker local de esta máquina — es inofensivo (solo acelera el siguiente `docker compose up` evitando re-descargar el modelo), pero se puede borrar con `docker volume rm tei-local_tei-data` si hace falta liberar espacio.
  - **Bug real ya encontrado y resuelto** si se vuelve a usar: la imagen `ghcr.io/huggingface/text-embeddings-inference:cpu-1.5` falla de forma reproducible al descargar artefactos del modelo (`relative URL without a base` — bug conocido, issue upstream `huggingface/text-embeddings-inference#527`). Usar `cpu-1.8` o más reciente (ya es el tag fijado en el `docker-compose.yml` committeado).
  - Nuevamente apagado (`docker compose down`) al cerrar la Porción 3 — mismo criterio que al cerrar la Porción 2.
- **`tools/calibracion-embeddings/`** (`calibrar.php`, `configurar-cerebro-remoto-dev.php`, `README.md`, NCP-2 Porción 3, `ADR 0017`): mide la distribución de similitud coseno de `ProveedorEmbeddingsCerebroRemoto` contra los fixtures de desarrollo de voz/trazabilidad, vía `wp eval-file`. **No forma parte del plugin.** No fija ni sugiere umbrales de producción — ver `README.md` de ese directorio y `ADR 0017` antes de interpretar su salida.

---

## 7. Verificación de que el estado comiteado está sano (repetir si hay dudas)

Todo lo listado en la sección 2 como "CERRADA" pasó, en el momento de su commit, los siguientes gates (mismo patrón exigido por `CLAUDE.md` § Delivery Guardian en cada porción):

```bash
# Gates PHP (contenedor cli de wp-env)
npx wp-env run cli --env-cwd=wp-content/plugins/PLUMA -- vendor/bin/phpcs
npx wp-env run cli --env-cwd=wp-content/plugins/PLUMA -- vendor/bin/phpstan analyse --memory-limit=1536M
npx wp-env run cli --env-cwd=wp-content/plugins/PLUMA -- vendor/bin/phpunit --testsuite=Unit

# Gates de integración real (contenedor tests-cli, ~11-12 min)
npx wp-env run tests-cli --env-cwd=wp-content/plugins/PLUMA -- php vendor/bin/phpunit -c phpunit-integration.xml.dist

# Gates JS (panel React)
npx tsc --noEmit
npx vitest run
npm run build
```

**Estado conocido al momento de escribir este documento** (tras NCP-3 Porción 3): PHPCS 0 errores, PHPStan nivel 8 0 errores, PHPUnit Unit 753 tests, **747 en verde** — los 5 errores + 1 fallo restantes son exclusivamente `Pluma\Tests\Unit\Kernel\CifradoTest` (`@runInSeparateProcess` no aísla correctamente en este entorno), **preexistente y confirmado ajeno con `git stash`** (`PLUMA-E9-30` en `docs/deuda.md`) — pasa limpio (6/6) cuando se ejecuta el archivo solo. PHPUnit Invariantes: 30 tests, 2 errores **preexistentes y confirmados ajenos a NCP** (`Pluma\Pipeline\Orquestador::__construct()`, `PLUMA-E9-29`, ahora "32 vs 33" — mismo origen, mecánicamente actualizado) — los archivos de Invariantes que NCP-3 sí toca pasan limpios. PHPUnit Integración 296 tests en verde (última corrida completa fue durante NCP-1 Porción 4 — ninguna porción de NCP-2/NCP-3 tocó nada que la suite de Integración cubra, pero si un continuador quiere evidencia fresca de Integración tras más cambios, debe volver a correrla completa, no asumir que sigue en verde indefinidamente).

Si algún gate falla al retomar el trabajo, **no es necesariamente un problema de esta sesión** — confirma primero si el fallo es preexistente (`git stash` + repetir el gate en el commit anterior) antes de asumir que el código nuevo lo rompió.

---

## 8. Disciplina de proceso a mantener (resumen de lo ya aplicado, para que no se pierda)

- **Protocolo Mission Lock** (`CLAUDE.md`): cada porción, sin excepción, pasó por Plan Mode (investigación → diseño → `AskUserQuestion` para decisiones de alcance → plan escrito → `ExitPlanMode` con aprobación explícita) antes de escribir código de producción.
- **Cero invención**: toda afirmación técnica sobre modelos, licencias, runtimes o protocolos de terceros se verificó con fuente citada (búsquedas web, fichas de Hugging Face, documentación oficial, o — en el caso del spike de la Porción 1 y la verificación de la Porción 2 — ejecución real contra el entorno/servicio real, nunca simulada).
- **Cero enforcement prematuro**: cada capacidad nueva (Sonda, proveedor de embeddings local) se construyó y verificó de forma aislada, sin conectarla a ningún consumidor real hasta que exista una decisión explícita de hacerlo — mismo patrón en las 4 porciones de NCP-1 y las 2 de NCP-2 hasta ahora.
- **ADR por cada decisión de arquitectura o hallazgo de investigación**, nunca resuelto solo en el código o en un mensaje de chat que se pierde.
- **Verificación real antes de declarar éxito**: navegador visible para cambios de panel, contenedor Docker real para servicios externos, nunca "debería funcionar" sin haberlo ejecutado.
