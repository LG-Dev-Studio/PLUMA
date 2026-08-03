# ADR 0016 — NCP-2 Porción 2: ENC vía cerebro remoto (T3), verificado con un servicio real

- **Fecha**: 2026-07-31
- **Estado**: Aceptada — verificación real con evidencia, código en producción (no vinculado por defecto)
- **Contexto**: `ADR 0015` (T1/FFI descartado con evidencia), `ADR 0014` (investigación de transportes/modelos), `ADR 0013` (Sonda de Capacidades, `ProveedorCerebroRemoto`)

## Decisión

Se construye `Pluma\Proveedores\ProveedorEmbeddingsCerebroRemoto`, primera implementación real y verificada de `EmbeddingsInterface` sobre el transporte T3 (cerebro remoto). El protocolo (`POST {url}/embed`, `{"inputs": "texto"}`, respuesta `[[float,...]]`) se verificó contra un servicio real — no simulado — de Hugging Face Text Embeddings Inference (TEI) sirviendo `intfloat/multilingual-e5-small` (MIT, `ADR 0014`).

**No se vincula por defecto** a `EmbeddingsInterface::class` en `Nucleo.php`: los 2 consumidores reales (`VerificadorRegresionVoz`, `VerificadorTrazabilidadDeterminista`) siguen usando `ProveedorOpenRouter`, cuyos umbrales (`0.70`/`0.75`) están calibrados contra esa distribución de similitud, no contra la de `multilingual-e5-small`.

## Evidencia — verificación end-to-end real

### Servicio de referencia

`docker compose -f tools/tei-local/docker-compose.yml up` — imagen `ghcr.io/huggingface/text-embeddings-inference:cpu-1.8`, modelo `intfloat/multilingual-e5-small`.

**Hallazgo real durante el levantamiento**: la imagen `cpu-1.5` (la más reciente disponible al momento de escribir el plan) falla de forma reproducible al descargar los artefactos del modelo con `Error: Could not download model artifacts / builder error: relative URL without a base`. Confirmado como bug conocido de la propia librería `hf-hub` (issue [huggingface/text-embeddings-inference#527](https://github.com/huggingface/text-embeddings-inference/issues/527), cerrado, "1.7.0 is out, and works perfectly on our end"; comentario adicional confirma que `cpu-1.5` concretamente lo reproduce). Se cambió a `cpu-1.8` y el arranque completó sin error — evidencia real, no una suposición sobre versiones.

Log real de arranque exitoso (extracto):
```
Downloading `1_Pooling/config.json`
Downloading `sentence_bert_config.json`
Downloading `config_sentence_transformers.json`
Downloading `config.json`
Downloading `tokenizer.json`
Model artifacts downloaded in 4.064905935s
Downloading `onnx/model.onnx`
Downloading `onnx/model.onnx_data`
Model ONNX weights downloaded in 47.951764363s
Starting HTTP server: 0.0.0.0:80
Ready
```

### Llamada real al endpoint `/embed`

```
$ curl -X POST http://localhost:8089/embed \
    -H "Authorization: Bearer clave-de-prueba-local" \
    -H "Content-Type: application/json" \
    -d '{"inputs": "query: el gato duerme"}'
[[0.044124927,0.013998146,-0.038682513,...]]

$ curl -X POST http://localhost:8089/embed -H "Content-Type: application/json" -d '{"inputs":"x"}'
HTTP 401   ← sin Authorization, correctamente rechazado
```

### Prueba de cordura semántica real (script Node.js, `sanity-check.cjs`, ejecutado contra el servicio real)

```
Dimensión del vector: 384
Tiempo por llamada (ms): 64, 36, 38, 24

Coseno(es "el gato duerme", en "the cat is sleeping")            = 0.9072
Coseno(es "el gato duerme", es "el mercado bursátil cayó hoy")   = 0.7854
Coseno(es "el gato duerme", ja "猫が眠っています" [mismo significado]) = 0.9007

PRUEBA DE CORDURA SEMÁNTICA: PASA
```

Las dos frases con el mismo significado en idiomas distintos (es/en, es/ja) puntúan más alto que la frase de tema no relacionado, confirmando alineación cross-lingüe real — no solo un vector mecánicamente válido, sino semánticamente coherente. Ningún `NaN` en ningún vector. Latencia real 24-64ms por llamada — muy por debajo de cualquier presupuesto de tiempo del tick del Orquestador.

## Fundamento — decisiones de diseño

- **El protocolo de T3 para ENC queda fijado por primera vez** (`ADR 0013` lo dejaba "provisional, sin contrato real todavía"): `POST /embed`, `{"inputs": string}`, `[[float,...]]`. Verificado contra la documentación oficial de TEI (README + `docs/openapi.json`) y contra un servicio real, no inventado.
- **`ProveedorEmbeddingsCerebroRemoto` no conoce el modelo detrás del cerebro remoto**: no añade el prefijo `"query: "`/`"passage: "` que `multilingual-e5-small` exige — eso es responsabilidad de quien construya el texto de entrada. Mezclar esa lógica en el transporte violaría "ninguna capa editorial sabe qué plano la atendió" (`CLAUDE.md` § Contrato del Proveedor de Lenguaje): el transporte no debe saber qué modelo hay al otro lado.
- **`ProveedorCerebroRemoto::credenciales()`** centraliza la lectura/descifrado de URL+token en un único punto, reutilizado tanto por la Sonda de Capacidades (salud/health-check genérico) como por este primer consumidor real — una sola fuente de verdad, sin duplicar la lógica de descifrado.
- **Registro del modelo como metadato, no como artefacto con checksum**: a diferencia de T1 (que habría descargado y ejecutado el binario localmente), T3 no descarga nada — el modelo vive en el servicio remoto, fuera del control directo de PLUMA. `ProveedorEmbeddingsCerebroRemoto::MODELO_REFERENCIA` documenta qué modelo se verificó, sin pretender un registro de checksums que no aplica a este transporte.
- **`tools/tei-local/`** es una herramienta de desarrollo, no un componente del producto — no la orquesta `@wordpress/env`, no se empaqueta en el ZIP del cliente. Documentado explícitamente en su propio `README.md`.

## Consecuencias

- **Confirma la elección de T3 sobre T1** (`ADR 0015`): el mismo problema de tokenización que hundió el spike de FFI queda resuelto de raíz aquí, porque TEI hace tokenización + inferencia + *pooling* en Rust, del lado del servicio — PHP nunca toca SentencePiece/Unigram.
- **Ningún consumidor real cambia de comportamiento**: `EmbeddingsInterface::class` sigue vinculado a `ProveedorOpenRouter` en `Nucleo.php`. `ProveedorEmbeddingsCerebroRemoto` queda disponible en el contenedor de DI para consumo futuro explícito.
- **Próxima decisión, no resuelta aquí**: si una porción futura recalibra los umbrales de `VerificadorRegresionVoz`/`VerificadorTrazabilidadDeterminista` para adoptar este proveedor como el real (ahorro de coste real, dado que TEI autohospedado no tiene coste marginal por llamada más allá de la infraestructura), o si el mismo patrón de transporte T3 se reutiliza primero para otro rol (NLI es el siguiente candidato natural, mismo `ADR 0014`, ya que también tiene un servicio TEI-compatible — TEI sirve reranking/clasificación además de embeddings).
