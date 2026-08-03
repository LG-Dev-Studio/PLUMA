# TEI local — servicio de referencia para desarrollo (NCP-2)

Herramienta de desarrollo, **no forma parte del producto**: no se empaqueta en el ZIP del plugin, no lo orquesta `@wordpress/env`. Levanta un servicio real de [Hugging Face Text Embeddings Inference](https://github.com/huggingface/text-embeddings-inference) sirviendo `intfloat/multilingual-e5-small` (MIT, `ADR 0014`), usado para verificar `Pluma\Proveedores\ProveedorEmbeddingsCerebroRemoto` contra un cerebro remoto real (T3, `ADR 0016`).

## Uso

```bash
docker compose -f tools/tei-local/docker-compose.yml up -d
curl http://localhost:8089/health
curl -X POST http://localhost:8089/embed \
  -H "Authorization: Bearer clave-de-prueba-local" \
  -H "Content-Type: application/json" \
  -d '{"inputs": "query: el gato duerme"}'
```

Desde el contenedor `cli` de wp-env, la red Docker `pluma-tei-local` es accesible por su nombre de host de servicio `tei` si se conecta a la misma red externa, o por `host.docker.internal:8089` en Docker Desktop (Windows/Mac).

## Apagar

```bash
docker compose -f tools/tei-local/docker-compose.yml down
```

`--api-key` es una clave de prueba fija, solo para este servicio local — nunca usar en producción real.
