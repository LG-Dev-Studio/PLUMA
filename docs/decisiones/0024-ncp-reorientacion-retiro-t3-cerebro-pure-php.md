# ADR 0024 — Reorientación del NCP: retiro del cerebro remoto (T3), NLI y RRK 100% PHP puro

- **Fecha**: 2026-08-03
- **Estado**: Aceptada — construida, verificada, en producción
- **Contexto**: `docs/CEREBRO_PLUMA_v2.md` (Partes 1.3, 3.1, 3.2 quedan corregidas por este ADR), `ADR 0013`–`ADR 0023` (toda la línea T3), decisión explícita del propietario tras revisar el trade-off de calidad

## Decisión

El propietario aclaró que la intención original del NCP nunca fue un "cerebro remoto" — un servicio HTTP aparte que el cliente o un tercero debe operar — sino un **cerebro cognitivo propio del plugin, embebido, funcional en cualquier hosting sin ninguna pieza de infraestructura externa**. La API de pago (OpenRouter/Anthropic/OpenAI/DeepSeek/…) es y sigue siendo un acelerador opcional, exclusivamente para la **redacción** (Plano 2) — nunca un requisito para verificar.

Se retira **por completo** T3 (cerebro remoto) y todo lo construido sobre él en NCP-2 Porciones 2/6 y NCP-3 Porciones 1–3. NLI y RRK pasan a ser capacidades **PHP puro, siempre disponibles, sin configuración, sin transporte que sondear**.

## Investigación previa (con fuentes, obligatoria antes de nombrar — Santo Grial §4)

### 1. ONNX embebido en PHP (T1, vía FFI) no es viable de forma confiable en hosting compartido

La directiva `ffi.enable` de PHP tiene tres valores (`true`/`false`/`preload`) y su **default es `"preload"`**, que deshabilita FFI en peticiones web normales salvo que el hosting configure `opcache.preload` a nivel de servidor — algo que ningún plugin puede activar por sí mismo ([manual oficial de PHP](https://www.php.net/manual/en/ffi.configuration.php)). La alternativa sin FFI (`exec`/`proc_open`) está deshabilitada por política de seguridad en prácticamente todo hosting cPanel compartido multi-cliente. No es una limitación de ingeniería propia: es una política de seguridad del hosting que ningún plugin de terceros puede evitar.

### 2. Candidato viable: Rubix ML

[Rubix ML](https://rubixml.com/) — machine learning **puro PHP**, código MIT (solo su documentación es CC BY-NC, irrelevante: no se redistribuye). Único requisito obligatorio: PHP 7.4+. Confirmado con `composer licenses --no-dev` tras instalarlo: `rubix/ml` MIT, dependencias transitivas todas MIT salvo `joomla/string` (GPL-2.0-or-later — compatible por diseño, WordPress y sus plugins ya son GPL-2.0-or-later).

### 3. RRK no necesita ningún modelo entrenado

TF-IDF + similitud de coseno es una técnica léxica clásica. El propio canon (`CEREBRO_PLUMA_v2.md` §1.2, Plano 0) ya lista "BM25/TF-IDF sobre el archivo propio" como léxico puro. Cero dependencia de terceros, cero entrenamiento.

### 4. NLI sí necesita un clasificador entrenado — investigación de licencias de datasets

No existe ningún modelo NLI "pre-entrenado listo para PHP puro". Candidatos de dataset investigados:

| Dataset | Licencia | Veredicto |
|---|---|---|
| XNLI | **CC BY-NC 4.0** | Descalificado — no comercial |
| SNLI | CC-BY-SA 4.0 | Complicado para producto cerrado (cláusula de compartir-igual) |
| MultiNLI | Mayormente permisivo (OANC/CC-BY/MIT) | Solo inglés — `PerfilIdioma` hoy solo cubre `es-ES` |
| **[InferES](https://huggingface.co/datasets/venelin/inferes)** (Kovatchev & Taulé, 2022) | **CC-BY-4.0** | **Elegido** — español peninsular, 8.056 ejemplos (6.444 train / 1.612 test), entailment/neutral/contradiction, foco en ejemplos basados en negación |

## Diseño construido

### Interfaces nuevas (mismo patrón que `EmbeddingsInterface`)

`Pluma\Proveedores\NliInterface` y `Pluma\Proveedores\RerankInterface` desacoplan a los consumidores del proveedor concreto. Reutilizan los DTOs ya existentes y correctos (`EtiquetaNli`, `ResultadoNli`, `ResultadoRerank` — son forma del dominio, no del transporte).

### RRK — `Pluma\Proveedores\ProveedorRerankLexico`

TF-IDF + coseno, PHP puro, sin dependencia de terceros, sin entrenamiento, siempre disponible. IDF calculado sobre el propio lote de textos de cada llamada (técnica correcta para reordenar un conjunto pequeño de extractos, no para recuperación en archivo grande).

### NLI — `Pluma\Proveedores\CaracteristicasNli` + `tools/entrenamiento-nli/` + `Pluma\Proveedores\ProveedorNliEntrenado`

`CaracteristicasNli` es la ÚNICA fuente de verdad de extracción de características, compartida entre entrenamiento y runtime (si se desincronizan, el modelo deja de significar lo que el proveedor cree). Vector: `2 × |vocabulario|` frecuencias de término (premisa, hipótesis) + 4 escalares (similitud de coseno, solapamiento de Jaccard, diferencia de negación, razón de longitud) — mismo espíritu que la línea base léxica de Bowman et al. 2015 (SNLI): vectores de bolsa de palabras más señales de su relación.

`tools/entrenamiento-nli/entrenar.php` (herramienta de desarrollo, PHP puro sin WordPress — corre vía `php` directo sobre el autoload de Composer, no vía `wp eval-file`, para evitar el problema del huevo y la gallina: el contenedor de DI exige `NliInterface` ya vinculado a un modelo que el script todavía no ha producido la primera vez):

1. Carga `dataset/train.csv` / `dataset/test.csv` (InferES, descargado a mano, gitignored por tamaño).
2. Construye vocabulario (800 palabras más frecuentes por frecuencia documental ≥3, **solo desde entrenamiento** — el split de prueba nunca contamina el vocabulario).
3. Entrena y compara 3 candidatos reales sobre el split de prueba REAL (1.612 ejemplos, nunca vistos en vocabulario/entrenamiento):

| Candidato | Exactitud real | Nota |
|---|---|---|
| GaussianNB | 35,7% | Apenas sobre el azar (33,3% para 3 clases) |
| ClassificationTree | 39,6%–48,5% (inestable entre corridas — desempate interno por aleatoriedad) | Colapsa `entailment` (recall 2–10%) |
| **RandomForest** (50 árboles, bootstrap balanceado) | **49,8%** | Ganador real — estabiliza la varianza de un árbol único vía bagging |

Desglose del ganador por clase (test, 1.612 ejemplos):

| Clase | Precisión | Recall | F1 | Soporte |
|---|---|---|---|---|
| `contradiction` | 0,4528 | 0,4916 | 0,4714 | 537 |
| `neutral` | 0,5299 | 0,8215 | 0,6442 | 594 |
| `entailment` | 0,4722 | 0,1060 | 0,1732 | 481 |

4. Persiste el modelo ganador (`Rubix\ML\PersistentModel` + `Filesystem` + serializador `RBX`) en `recursos/modelos/nli-es.rbx` (~98 KB) + el vocabulario en `nli-es-vocab.json` (~15 KB). **Total ~114 KB** — órdenes de magnitud menor que cualquier modelo ONNX (cientos de MB a GB): viaja **dentro del propio plugin**, sin botón de descarga, decisión confirmada empíricamente, no asumida de antemano.

`ProveedorNliEntrenado implements NliInterface` carga el artefacto (perezoso, cacheado por instancia) desde `PLUMA_ENGINE_DIR . 'recursos/modelos/'`, extrae características con `CaracteristicasNli`, llama `$modelo->proba()` (RandomForest implementa `Probabilistic`), mapea las 3 clases a `EtiquetaNli`.

### Calidad — honestidad explícita

~50% de exactitud global es una mejora real sobre el azar (33%), pero muy por debajo de un transformer NLI real (el candidato ONNX retirado, `xlm-v-base-mnli-xnli`, no fue medido en producción pero su arquitectura típicamente supera 80-90% en benchmarks NLI estándar). `entailment` es la clase más débil (recall 10,6%) — el clasificador es notablemente mejor detectando `contradiction`/`neutral` que `entailment`. Para el uso real (`VerificadorContradiccionNli`/`DetectorContradiccionesNli` solo comprueban si la etiqueta principal es `Contradiccion`), la métrica relevante es la de esa clase específica (F1 0,4714), no la exactitud global de 3 clases. Este es el trade-off de calidad que el propietario aceptó explícitamente al elegir "solo pure-PHP, sin ONNX, en todos los entornos por igual".

### Retirado

- `Pluma\Proveedores\ProveedorCerebroRemoto`, `ProveedorEmbeddingsCerebroRemoto`, `ProveedorNliCerebroRemoto`, `ProveedorRerankCerebroRemoto` — las 4 clases T3.
- Endpoints REST `/motor/cerebro-remoto` (guardar/borrar/probar) en `RestSalaMaquinas` — el resto del controlador (bitácora, llave OpenRouter, presupuesto, sonda, diagnóstico) no se toca.
- Bloque de UI del panel `BloqueCerebroRemoto.tsx` + su test, y su integración en `PantallaSalaMaquinas.tsx`.
- El hecho `cerebroRemotoConfigurado` de `HechosEntorno`/`SensorCapacidades`/`ResolutorPerfilEntorno`/`PerfilEntorno`/`AlmacenPerfilEntorno` — deja de tener sentido (NLI/RRK ya no dependen de configuración). `SensorCapacidades` pierde su dependencia de `ProveedorCerebroRemoto`. `ResolutorPerfilEntorno` sigue midiendo T1/T2 (FFI, proceso hijo) como medición prospectiva para un futuro rol que sí necesite ONNX embebido — eso no cambia, solo T3 desaparece de la matriz.
- Las 3 entradas T3 de `RegistroModelos::todos()`, reemplazadas por 2 entradas reales pure-PHP (NLI con checksum real por primera vez; RRK sin modelo, técnica pura).
- La decisión "T3 obligatorio" de `ADR 0021`/`ADR 0022` queda **revertida de facto**: ya no hay nada que configurar, así que NLI nunca falla por falta de credenciales. Se documenta el porqué aquí, sin borrar la decisión original del historial — fue correcta dado lo que se sabía entonces.

### Qué NO se tocó (confirmado sin impacto real)

`EmbeddingsInterface`, `ProveedorOpenRouter`, `EmbeddingsInstrumentado`, `VerificadorRegresionVoz`, `VerificadorTrazabilidadDeterminista` — ya estaban ligados a `EmbeddingsInterface` → `ProveedorOpenRouter` (API de pago), nunca a T3 (`ProveedorEmbeddingsCerebroRemoto` estaba registrado en el contenedor pero sin ningún consumidor real, confirmado por búsqueda exhaustiva antes de retirarlo). El Plano 2 generativo (redacción) no cambia en absoluto.

## Bug real encontrado y corregido de paso

`bin/build-zip` (Etapa 6, nunca ejercido de punta a punta con múltiples dependencias hasta esta porción): la línea `mv "$plugin_dir/build-scoped/vendor" "$plugin_dir/vendor"` asumía que PHP-Scoper anida su salida bajo `build-scoped/vendor/`. En realidad, dado que `scoper.inc.php` escanea con `Finder->in('vendor')`, las rutas de salida ya son relativas a `vendor/` — `build-scoped/` ES el nuevo `vendor/` directamente. Corregido a `mv "$plugin_dir/build-scoped" "$plugin_dir/vendor"`. Verificado con una corrida real completa (composer install + php-scoper con 1.638 archivos prefijados + verificación de reproducibilidad con huella agregada) — pasó. El paso final de compresión ZIP no se verificó en este contenedor de desarrollo (falta el binario `zip`, sin permiso para instalarlo) — no relacionado con el código de esta porción.

## Verificación

- Gates: `phpcs` 0 errores/0 advertencias, `phpstan` nivel 8 0 errores.
- `phpunit --testsuite=Unit`: **740 tests, 740 en verde** (0 fallos — incluye `CaracteristicasNliTest`, `ProveedorNliEntrenadoTest` contra el artefacto REAL entrenado, `ProveedorRerankLexicoTest`, y todos los consumidores reajustados a las nuevas interfaces).
- `phpunit --testsuite=Invariantes`: 30 tests, los mismos 2 errores preexistentes de `PLUMA-E9-29` (`Orquestador::__construct()` desincronizado en 2 tests, confirmado ajeno a esta porción por `git log`).
- `npm test` (panel): 24 archivos, 137 tests en verde.
- `npm run build` (panel): compila sin errores de TypeScript.
- `composer test:integration --filter RestSalaMaquinasTest`: **13 tests, 13 en verde** contra WordPress real (confirma que la Sala de Máquinas, el contenedor de DI completo, y el retiro de los endpoints T3 funcionan de punta a punta, no solo en Unit).
- `wp option get siteurl` (arranque real del plugin vía `Nucleo::arrancar()`): sin error fatal — confirma que `NliInterface`/`RerankInterface` resuelven correctamente en el contenedor de producción.
- `tools/entrenamiento-nli/prueba-manual.php`: ejecutado contra el artefacto real — resultados plausibles (p.ej. "El alcalde renunció" vs "El alcalde no renunció" puntúa `contradiction` más alto que las otras dos etiquetas).
- `bin/build-zip`: composer install + PHP-Scoper (1.638 archivos) + verificación de reproducibilidad, todo real, todo en verde.

## Consecuencias

- El objetivo original del propietario ("cerebro cognitivo propio del plugin, sin ninguna pieza de infraestructura externa") se cumple de verdad para NLI y RRK: cero configuración, cero red, cero FFI, funciona en cualquier hosting compartido sin excepción.
- Calidad medible y honesta: ~50% de exactitud NLI (mejor que el azar, peor que un transformer) — deuda registrada en `docs/deuda.md` para mejora futura (vocabulario más grande, más características, más árboles) si la calidad en Piloto resulta insuficiente.
- `RegistroModelos` tiene, por primera vez, una entrada con `checksum` real (no `null` con motivo T3) — el artefacto vive dentro del plugin, es verificable de verdad.
- `docs/CEREBRO_PLUMA_v2.md` Partes 1.3/3.1/3.2 quedan corregidas por este ADR — no se reescribe el documento entero en esta porción (deuda de documentación declarada, nota añadida al inicio del documento).
- NCP-3 Porciones 1–3 (`ADR 0021`/`ADR 0022`/`ADR 0023`) permanecen como registro histórico correcto de lo que se investigó y decidió con la información disponible entonces — no se borran ni reescriben.
