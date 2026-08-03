# ADR 0020 — NCP-2 Porción 6: NLI y RRK vía T3 — corrección de 2 candidatos de ADR 0014

- **Fecha**: 2026-08-03
- **Estado**: Aceptada — construido y verificado con evidencia real; corrige 2 candidatos de `ADR 0014`
- **Contexto**: `ADR 0014` §2 (candidatos por rol), `ADR 0016` (patrón T3/ENC), `ADR 0019` (registro de modelos)

## Decisión

Se construyen `Pluma\Proveedores\ProveedorNliCerebroRemoto` y `Pluma\Proveedores\ProveedorRerankCerebroRemoto`, primeras implementaciones reales de los roles **NLI** y **RRK** del canon, sobre el transporte T3 (mismo patrón que `ProveedorEmbeddingsCerebroRemoto`, `ADR 0016`). El propietario decidió construir ambos roles en una sola porción, asumiendo el coste de infraestructura de verificar 2 servicios TEI reales con modelos distintos.

## Hallazgo real: los candidatos originales de `ADR 0014` para NLI y RRK no eran directamente compatibles con TEI

`ADR 0014` investigó candidatos por *licencia y ONNX*, pero no verificó su compatibilidad con la *arquitectura que TEI realmente soporta para clasificación/reranking* (T3 no tenía protocolo fijado todavía en esa investigación). Esta porción cierra ese hueco con verificación real.

### NLI: `MoritzLaurer/mDeBERTa-v3-base-xnli-multilingual-nli-2mil7` descartado con evidencia

El README oficial de TEI (`github.com/huggingface/text-embeddings-inference`) declara explícitamente que la clasificación de secuencias solo soporta las arquitecturas **CamemBERT, XLM-RoBERTa, GTE, ModernBERT y RoBERTa**. El candidato de `ADR 0014` usa **DeBERTa-v3** — no está en esa lista. Sin arquitectura soportada, TEI no puede servir el modelo para `/predict`, sin importar que tenga ONNX.

**Candidato de reemplazo, investigado y verificado en esta sesión**: `MoritzLaurer/xlm-v-base-mnli-xnli`:
- Licencia MIT (ficha del modelo).
- `config.json` confirma `XLMRobertaForSequenceClassification` — arquitectura sí soportada por TEI.
- ONNX real confirmado: `onnx/model.onnx` (314kB) + `onnx/model.onnx_data` (3.11GB).
- Entrenado en `multi_nli` + `xnli`, 16 idiomas.
- Mismo autor y mismo mecanismo dual NLI/CLS-zero-shot que `ADR 0014` ya destacaba para el candidato original — la sustitución conserva la intención, corrige solo la arquitectura.

### RRK: ninguno de los 2 candidatos originales resultó directamente verificable

- `cross-encoder/ms-marco-MiniLM-L6-v2` (ONNX listo, `ADR 0014`): su arquitectura (MiniLM) no aparece en la tabla oficial de TEI de modelos de reranking soportados. No se intentó en vivo por este motivo.
- `BAAI/bge-reranker-v2-m3` (multilingüe, `ADR 0014`): confirmado de nuevo en esta sesión que su repositorio no tiene carpeta `onnx/` — exige re-exportar en casa, fuera de alcance de una porción de verificación.

**Candidato de reemplazo, investigado y verificado en esta sesión**: `BAAI/bge-reranker-base`:
- Licencia MIT ("uso comercial gratuito", ficha del modelo).
- Arquitectura XLM-RoBERTa (confirmada).
- **Listado oficialmente en la tabla de reranking soportado de TEI** (junto a `bge-reranker-large`, GTE, ModernBert).
- ONNX real confirmado: `onnx/model.onnx` (~1.1GB).
- **Limitación real declarada**: su propia ficha lo describe como "Chinese and English" — no la cobertura multilingüe completa que `bge-reranker-v2-m3` habría dado. Se declara honestamente, no se presenta como multilingüe.

## Evidencia — verificación real end-to-end (dos contenedores Docker temporales, destruidos al terminar)

### NLI — `MoritzLaurer/xlm-v-base-mnli-xnli`

```
$ docker run -d -p 8091:80 ghcr.io/huggingface/text-embeddings-inference:cpu-1.8 \
    --model-id=MoritzLaurer/xlm-v-base-mnli-xnli --api-key=clave-de-prueba-spike
# descarga real de 3.1GB, servicio "Ready"

$ curl -X POST http://localhost:8091/predict -d '{"inputs": "El equipo gano el partido por goleada.</s></s>El equipo perdio el partido."}'
[{"score":0.9983525,"label":"contradiction"},{"score":0.0013831706,"label":"entailment"},{"score":0.00026430064,"label":"neutral"}]

$ curl -X POST http://localhost:8091/predict -d '{"inputs": "El gobierno anuncio nuevas medidas economicas.</s></s>Este texto habla de politica y economia."}'
[{"score":0.87229615,"label":"neutral"},{"score":0.12731992,"label":"entailment"},{"score":0.00038389192,"label":"contradiction"}]
```

Ambos resultados son correctos: la contradicción directa se detecta con confianza altísima (0.998); el segundo caso (relación de tema, no entailment lógico estricto) puntúa "neutral" más alto que "entailment" — comportamiento NLI real, no coincidencia de palabras clave.

### RRK — `BAAI/bge-reranker-base`

```
$ docker run -d -p 8090:80 ghcr.io/huggingface/text-embeddings-inference:cpu-1.8 \
    --model-id=BAAI/bge-reranker-base --api-key=clave-de-prueba-spike
# descarga real de 1.1GB, servicio "Ready"

$ curl -X POST http://localhost:8090/rerank -d '{"query": "Cual es la capital de Francia", "texts": ["Paris es la capital de Francia.", "El clima en Madrid es calido en verano.", "Francia es un pais de Europa occidental."]}'
[{"index":0,"score":0.43778288},{"index":2,"score":0.08668595},{"index":1,"score":0.017106343}]
```

Orden correcto: la respuesta directa (índice 0) puntúa más alto que la relación temática (índice 2), que a su vez puntúa más alto que la frase sin relación (índice 1).

## Diseño

Mismo patrón exacto que `ProveedorEmbeddingsCerebroRemoto`: clases `final`, sin interfaz, dependen de `ProveedorCerebroRemoto` para credenciales, lanzan `ProveedorLenguajeException` en cualquier fallo — nunca un valor por defecto silencioso.

- `Pluma\Proveedores\EtiquetaNli` (enum: `Entailment`, `Neutral`, `Contradiccion`) + `Pluma\Proveedores\ResultadoNli` (DTO) + `Pluma\Proveedores\ProveedorNliCerebroRemoto::inferir(premisa, hipotesis): list<ResultadoNli>` — `POST /predict`, `{"inputs": "premisa</s></s>hipotesis"}`. Si la API devuelve una etiqueta que no calza con `EtiquetaNli`, lanza excepción — nunca la ignora en silencio.
- `Pluma\Proveedores\ResultadoRerank` (DTO) + `Pluma\Proveedores\ProveedorRerankCerebroRemoto::reordenar(consulta, textos): list<ResultadoRerank>` — `POST /rerank`, `{"query", "texts", "raw_scores": false}`.
- `RegistroModelos` gana las 2 entradas reales (NLI, RRK) — checksum `null` con motivo T3, mismo patrón que ENC.

## Qué NO hace esta porción

- **Ambos proveedores reutilizan el mismo `pluma_cerebro_remoto_url`/token único** que ya usa ENC — la misma limitación implícita en `ADR 0016`: un solo proceso TEI sirve un solo modelo, así que en la práctica hoy solo se puede verificar un rol a la vez apuntando manualmente la URL al servicio correspondiente (de ahí los 2 contenedores temporales separados de esta verificación). **Cómo un solo "cerebro remoto" configurado por el cliente sirva simultáneamente ENC+SEG+NLI+RRK en producción (¿gateway del vendedor enrutando por path? ¿URLs separadas por rol?) es una decisión de NCP-4 · Enrutador**, no de esta porción.
- **No construye ningún rol CLS** — `xlm-v-base-mnli-xnli` también sirve zero-shot classification con el mismo mecanismo NLI, pero no se pidió en esta porción.
- **No conecta ninguno de los dos proveedores a un consumidor real** — `verificar_trazabilidad`/detección de contradicciones (NCP-3) no existe todavía como pipeline. Ambos quedan disponibles en el contenedor de DI, sin consumidor.

## Verificación

- Gates: PHPCS 0, PHPStan nivel 8 0, PHPUnit Unit 732/732 en verde (719 + 13 tests nuevos: 6 de `ProveedorNliCerebroRemotoTest`, 5 de `ProveedorRerankCerebroRemotoTest`, 2 de `RegistroModelosTest`).
- Verificación real end-to-end contra ambos servicios TEI capturada arriba — ambos contenedores destruidos (`docker rm -f`) al terminar, no queda infraestructura corriendo.

## Consecuencias

- El canon tiene ahora 4 de sus 8 roles con implementación real: ENC, SEG, NLI, RRK. NER, LID, CLS, TOX siguen sin construir.
- `ADR 0014` queda corregido en la práctica por este ADR para los candidatos de NLI y RRK — un continuador que retome esos roles debe leer este ADR, no solo `ADR 0014`, antes de asumir que sus candidatos originales son viables con T3.
- Cierra la secuencia de 3 porciones decidida el 2026-08-03 ((b) SEG → (c) Registro → (a) NLI/RRK). La siguiente decisión abierta en `docs/ncp-estado-y-continuidad.md` §5 es (e) pausar NCP → Etapa 10, salvo que el propietario abra una línea nueva.
