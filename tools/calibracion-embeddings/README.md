# Calibración de embeddings — herramienta de desarrollo (NCP-2)

Herramienta de desarrollo, **no forma parte del producto**: no se empaqueta en el ZIP del plugin, no la orquesta `@wordpress/env`. Mide la forma de la distribución de similitud coseno de `Pluma\Proveedores\ProveedorEmbeddingsCerebroRemoto` (T3, `ADR 0016`) contra los fixtures de desarrollo de voz (`tests/Fixtures/corpus-voz.php`) y trazabilidad (`tests/Fixtures/corpus-trazabilidad.php`), reutilizando código de producción real (`VerificadorRegresionVoz::similitudPromedioConCorpus()`, `SimilitudVectorial::coseno()`) — nunca lo reimplementa.

## Qué NO es esta herramienta

**No es una calibración de producción.** Los fixtures que usa son corpus mínimos de desarrollo, no el corpus real de piezas/expedientes del cliente. El script:

- No cambia `VerificadorRegresionVoz::UMBRAL_DEFECTO` (`0.70`) ni `VerificadorTrazabilidadDeterminista::UMBRAL_DEFECTO` (`0.75`).
- No reconecta `EmbeddingsInterface::class` en `Nucleo.php` (sigue en `ProveedorOpenRouter`).
- `calibrar.php` no escribe ninguna opción de WordPress (solo lee y mide) — el único script de este directorio que escribe opciones es `configurar-cerebro-remoto-dev.php`, y solo para apuntar al TEI local de desarrollo.

Ver `docs/decisiones/0017-ncp2-herramienta-calibracion-embeddings.md` y `docs/ncp-estado-y-continuidad.md` §5(d) para la decisión de por qué existe esta herramienta sin recalibrar nada todavía.

## Requisitos

1. Un servicio de embeddings real accesible por T3 — para desarrollo, el TEI local de `tools/tei-local/` (`docker compose -f tools/tei-local/docker-compose.yml up -d`).
2. Un cerebro remoto configurado en las opciones del plugin apuntando a ese servicio (mismas opciones que usa `ProveedorCerebroRemoto`: `pluma_cerebro_remoto_url`, `pluma_cerebro_remoto_token_cifrado`) — guardado una vez desde el panel de Sala de Máquinas, o con el script de conveniencia de este directorio (paso 2 abajo).

## Uso

```bash
# 1. Levantar el TEI local (si no está ya arriba)
docker compose -f tools/tei-local/docker-compose.yml up -d

# 2. Apuntar el cerebro remoto del plugin al TEI local (solo desarrollo)
npx wp-env run cli --env-cwd=wp-content/plugins/PLUMA -- wp eval-file tools/calibracion-embeddings/configurar-cerebro-remoto-dev.php

# 3. Correr la calibración
npx wp-env run cli --env-cwd=wp-content/plugins/PLUMA -- wp eval-file tools/calibracion-embeddings/calibrar.php
```

El script imprime a stdout, para cada distribución medida, `n`/mínimo/mediana/media/máximo:

- **Voz**: similitud intra-periodista (leave-one-out contra el resto de su propio corpus — proxy de "voz genuina") vs inter-periodista (contra el corpus de otro periodista — proxy de "voz ajena").
- **Trazabilidad**: similitud coseno hecho↔unidad cuando la unidad sí está respaldada por el hecho vs cuando no tiene relación.

No persiste ningún archivo — la salida es evidencia para capturar manualmente en un ADR o en la bitácora de una porción futura que decida recalibrar con datos reales.
