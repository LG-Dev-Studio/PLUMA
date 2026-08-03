# Estado y continuidad — Núcleo Cognitivo Propio (NCP)

**Documento vivo** (a diferencia de los ADR en `docs/decisiones/`, que son inmutables): se actualiza en cada porción cerrada. Su propósito es que cualquier agente o desarrollador — incluida una sesión nueva de Claude Code sin memoria de esta conversación — pueda continuar el trabajo de NCP exactamente donde quedó, sin releer el historial completo de `git log` ni adivinar decisiones ya tomadas.

**Última actualización**: 2026-07-31, al cerrar NCP-2 Porción 2.

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
| 3+ | Sin definir | **ABIERTA — decisión pendiente del propietario**, ver §5 | — | — |

---

## 3. Índice completo de ADR relevantes para NCP (leer en este orden si se retoma el trabajo)

- `docs/decisiones/0010-ncp1-recorte-enmienda-de-fase.md` — por qué NCP-1 existe, auditoría inicial de las 21 llamadas reales al proveedor de lenguaje.
- `docs/decisiones/0012-capa-idioma-y-locale-editorial-activo.md` — `Pluma\Idioma`, `PerfilIdioma`, por qué el Plano 1 todavía no existe y qué NO se le puede prometer.
- `docs/decisiones/0013-sonda-de-capacidades-medicion-sin-enrutamiento.md` — la Sonda mide, no enruta; T4 excluido del enum server-side; por qué el cerebro remoto nunca se prueba en vivo desde el tick.
- `docs/decisiones/0014-ncp2-investigacion-transportes-modelos-y-distribucion.md` — **el documento de referencia técnica más denso**: tabla completa de modelos cualificados/descalificados por rol, con licencia y fuente.
- `docs/decisiones/0015-ncp2-porcion-1-veredicto-spike-embeddings-locales.md` — por qué T1/FFI queda descartado, con evidencia de comandos reales ejecutados.
- `docs/decisiones/0016-ncp2-porcion-2-enc-via-t3-veredicto.md` — el protocolo real de T3 para embeddings, con evidencia de un servicio real verificado.

---

## 4. Decisiones de arquitectura vivas — qué NO tocar sin decisión explícita nueva

Estas reglas ya están decididas y documentadas; un continuador **no debe deshacerlas por su cuenta** ni "mejorarlas" sin plantear la pregunta al propietario primero:

1. **`Pluma\Proveedores\EmbeddingsInterface::class` sigue vinculado a `ProveedorOpenRouter`** en `src/Kernel/Nucleo.php` (~línea 464). `ProveedorEmbeddingsCerebroRemoto` existe, está registrado en el contenedor de DI, tiene tests, y **no se usa por defecto**. Los 2 consumidores reales (`VerificadorRegresionVoz`, umbral `0.70`; `VerificadorTrazabilidadDeterminista`, umbral `0.75`) siguen usando la distribución de similitud de OpenRouter. Cambiar esto exige recalibrar ambos umbrales explícitamente — nunca un efecto colateral de otra tarea.
2. **Ninguna capa de `Pluma\Compuertas` conoce ni depende de `TransportePlano1`/`PerfilEntorno`/la Sonda de Capacidades.** El enrutamiento real (qué plano atiende qué operación, restricción del modo Autónomo cuando falta Plano 1) es exclusivamente NCP-4 · Enrutador — una fase que todavía no ha empezado. No conectar nada de esto a `ModoOperacion`/`GestorDegradacion`/`EvaluadorCompuertas` sin que sea, otra vez, una decisión explícita y documentada.
3. **`TransportePlano1` no incluye T4 (navegador/WASM)** — decisión deliberada (`ADR 0013`), no un olvido. Si se necesita en el futuro (probablemente para trabajo interactivo del panel, nunca para el cron), es una extensión nueva con su propia justificación, no un "arreglo" de un enum incompleto.
4. **`ProveedorCerebroRemoto::probar()` y el refresco de `SensorCapacidades` nunca deben empezar a compartir la misma llamada de red.** El diseño depende de que `ultimaPruebaOk()` sea siempre una lectura cacheada — si alguna vez se tienta "simplificar" haciendo que el sensor pruebe en vivo, se reintroduce exactamente el riesgo que `ADR 0013` resolvió (llamadas de red dentro de la sección no presupuestada del tick del Orquestador).
5. **`SensorCapacidades`, `ProveedorCerebroRemoto` y `ProveedorEmbeddingsCerebroRemoto` son `final class` sin interfaz propia** (a diferencia de `AlmacenPerfilEntorno`, que sí tiene `AlmacenPerfilEntornoInterface` porque tiene múltiples consumidores). Si una clase `final` necesita mockearse en un test nuevo, la solución establecida en este proyecto es construir una instancia real controlada vía `get_option`/colaboradores inyectados (interfaces reales), **nunca** intentar `Mockery::mock()`/`createMock()` sobre una clase `final` — PHP no lo permite, y ya ha costado una ronda de correcciones en esta sesión dos veces (ver `tests/Unit/Kernel/SensorCapacidadesTest.php` y `tests/Unit/Proveedores/ProveedorEmbeddingsCerebroRemotoTest.php` como los dos ejemplos ya resueltos de este patrón).
6. **El modelo de referencia verificado es `intfloat/multilingual-e5-small` (MIT)**, servido vía Hugging Face Text Embeddings Inference. Cualquier cambio de modelo de referencia debe volver a pasar por la tabla de licencias de `ADR 0014` — no asumir que otro modelo de la misma familia tiene la misma licencia (ver los casos ya descalificados en esa tabla: varios modelos con apariencia similar tienen licencias no comerciales o ambiguas).

---

## 5. Qué sigue — decisión abierta, sin resolver todavía

No hay una porción 3 de NCP-2 planeada. Las opciones reales sobre la mesa, ninguna descartada:

- **(a) Reutilizar el mismo patrón de transporte T3 para otro rol** — el candidato más natural es **NLI** (`MoritzLaurer/mDeBERTa-v3-base-xnli-multilingual-nli-2mil7`, MIT, cualificado en `ADR 0014`) o **RRK** (reranking), porque TEI (el mismo servicio ya verificado) sirve ambos además de embeddings (`POST /rerank`, `POST /predict` — endpoints ya documentados en la investigación de `ADR 0014`, no verificados en vivo todavía). Sería la porción de menor riesgo/mayor reutilización: mismo servicio, mismo patrón de credenciales, protocolo distinto mismo pero mismo ya documentado.
- **(b) Empezar por SEG** (segmentación) — la opción de menor riesgo posible: no necesita ningún modelo ONNX, se resuelve con `ext-intl`/`IntlBreakIterator` en PHP puro (`ADR 0014`), y es exactamente el campo "Segmentador por escritura" que `Pluma\Idioma\PerfilIdioma` dejó fuera a propósito en NCP-1 Porción 3. Esta opción ya se le ofreció al propietario antes de elegir ENC — sigue disponible.
- **(c) Construir el registro de modelos formal** (`rol → artefacto, versión, licencia, idioma, checksum, procedencia`, exigido por el canon §5.1.5) — todavía no existe como estructura general; `ProveedorEmbeddingsCerebroRemoto::MODELO_REFERENCIA` es solo una constante informativa, no un registro real. Podría valer la pena construirlo formalmente antes de añadir un tercer/cuarto rol, para no repetir el patrón de "constante suelta por clase".
- **(d) Decidir si se recalibra `EmbeddingsInterface` hacia el proveedor local** para los 2 consumidores reales — ahorraría coste real de OpenRouter, pero exige medir la nueva distribución de similitud de `multilingual-e5-small` contra el corpus real de voz/trazabilidad del cliente antes de fijar nuevos umbrales (no se puede adivinar `0.70`/`0.75` equivalentes sin datos reales — mismo principio que bloquea NCP-1 Porciones 2 y 5).
- **(e) Pausar NCP y volver al roadmap principal** — Etapa 9 ya cerró (`docs/etapa-9-el-medio-real.md`); Etapa 10 (`docs/PLAN-MAESTRO-EVOLUCION.md` §6) es lo siguiente en ese roadmap si el propietario prefiere dejar NCP en este punto y no retomarlo de inmediato.

**Un continuador debe presentar estas opciones al propietario (o preguntar directamente) antes de elegir una — no decidir por su cuenta cuál sigue.** Esto es exactamente lo que se ha hecho en cada bifurcación de esta sesión (ver el patrón repetido de `AskUserQuestion` antes de cada porción nueva en el historial de commits).

---

## 6. Herramientas de desarrollo activas (no confundir con el producto)

- **`tools/tei-local/`** (`docker-compose.yml` + `README.md`): levanta un servicio real de Hugging Face Text Embeddings Inference sirviendo `multilingual-e5-small`, usado para verificar `ProveedorEmbeddingsCerebroRemoto`. **No forma parte del plugin** — no lo orquesta `@wordpress/env`, no se empaqueta en el ZIP de distribución.
  - Estado actual: el contenedor (`pluma-tei-local`) y su red Docker se apagaron y eliminaron (`docker compose down`) al cerrar la Porción 2. El volumen con los pesos del modelo ya descargados (`tei-local_tei-data`, ~500MB) **sigue existiendo** en el Docker local de esta máquina — es inofensivo (solo acelera el siguiente `docker compose up` evitando re-descargar el modelo), pero se puede borrar con `docker volume rm tei-local_tei-data` si hace falta liberar espacio.
  - **Bug real ya encontrado y resuelto** si se vuelve a usar: la imagen `ghcr.io/huggingface/text-embeddings-inference:cpu-1.5` falla de forma reproducible al descargar artefactos del modelo (`relative URL without a base` — bug conocido, issue upstream `huggingface/text-embeddings-inference#527`). Usar `cpu-1.8` o más reciente (ya es el tag fijado en el `docker-compose.yml` committeado).

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

**Estado conocido al momento de escribir este documento** (commit `420641d`): PHPCS 0 errores, PHPStan nivel 8 0 errores, PHPUnit Unit 704 tests en verde, PHPUnit Integración 296 tests en verde (última corrida completa fue durante NCP-1 Porción 4 — la Porción 2 de NCP-2 solo añadió tests Unit nuevos, no tocó nada que la suite de Integración cubra, pero si un continuador quiere evidencia fresca de Integración tras más cambios, debe volver a correrla completa, no asumir que sigue en verde indefinidamente).

Si algún gate falla al retomar el trabajo, **no es necesariamente un problema de esta sesión** — confirma primero si el fallo es preexistente (`git stash` + repetir el gate en el commit anterior) antes de asumir que el código nuevo lo rompió.

---

## 8. Disciplina de proceso a mantener (resumen de lo ya aplicado, para que no se pierda)

- **Protocolo Mission Lock** (`CLAUDE.md`): cada porción, sin excepción, pasó por Plan Mode (investigación → diseño → `AskUserQuestion` para decisiones de alcance → plan escrito → `ExitPlanMode` con aprobación explícita) antes de escribir código de producción.
- **Cero invención**: toda afirmación técnica sobre modelos, licencias, runtimes o protocolos de terceros se verificó con fuente citada (búsquedas web, fichas de Hugging Face, documentación oficial, o — en el caso del spike de la Porción 1 y la verificación de la Porción 2 — ejecución real contra el entorno/servicio real, nunca simulada).
- **Cero enforcement prematuro**: cada capacidad nueva (Sonda, proveedor de embeddings local) se construyó y verificó de forma aislada, sin conectarla a ningún consumidor real hasta que exista una decisión explícita de hacerlo — mismo patrón en las 4 porciones de NCP-1 y las 2 de NCP-2 hasta ahora.
- **ADR por cada decisión de arquitectura o hallazgo de investigación**, nunca resuelto solo en el código o en un mensaje de chat que se pierde.
- **Verificación real antes de declarar éxito**: navegador visible para cambios de panel, contenedor Docker real para servicios externos, nunca "debería funcionar" sin haberlo ejecutado.
