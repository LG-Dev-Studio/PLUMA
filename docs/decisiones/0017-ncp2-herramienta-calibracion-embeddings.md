# ADR 0017 — NCP-2: herramienta de calibración de embeddings, sin recalibrar producción

- **Fecha**: 2026-08-03
- **Estado**: Aceptada — herramienta construida y verificada con evidencia real; ninguna recalibración de producción decidida
- **Contexto**: `ADR 0016` (`ProveedorEmbeddingsCerebroRemoto`, T3), `docs/ncp-estado-y-continuidad.md` §5(d)

## Decisión

El propietario eligió la opción (d) de `docs/ncp-estado-y-continuidad.md` §5: evaluar si `ProveedorEmbeddingsCerebroRemoto` puede sustituir a `ProveedorOpenRouter` como `EmbeddingsInterface` de producción para `VerificadorRegresionVoz` (umbral `0.70`) y `VerificadorTrazabilidadDeterminista` (umbral `0.75`).

Al investigar antes de escribir código (canon `docs/CEREBRO_PLUMA_v2.md` Parte 7), se confirmó que el requisito literal de (d) — medir contra "el corpus real de voz/trazabilidad del cliente" — no se puede cumplir hoy: `docs/protocolo-corpus-voz.md` declara `tests/Fixtures/corpus-voz.php` explícitamente como **corpus mínimo de desarrollo** (no el corpus curado de Piloto), y no existía ningún fixture equivalente para trazabilidad (`VerificadorTrazabilidadDeterministaTest.php` solo usaba dobles sintéticos `EmbeddingsFalso`). Mismo tipo de bloqueo que ya frenó NCP-1 Porciones 2 y 5 — no hay atajo de ingeniería para datos reales que no existen.

Presentada esta disyuntiva, el propietario eligió **construir el mecanismo de calibración ahora, sin fijar ni recalibrar ningún umbral de producción**, dejándolo listo para cuando exista corpus real de Piloto. Esta porción entrega:

1. `tools/calibracion-embeddings/calibrar.php` — script de desarrollo (`wp eval-file`, fuera del producto) que mide 4 distribuciones de similitud coseno usando `ProveedorEmbeddingsCerebroRemoto` real contra un servicio TEI real, reutilizando código de producción (`VerificadorRegresionVoz::similitudPromedioConCorpus()`, `SimilitudVectorial::coseno()`) sin reimplementarlo.
2. `tests/Fixtures/corpus-trazabilidad.php` — nuevo fixture mínimo de desarrollo (4 casos hecho/unidad-respaldada/unidad-sin-respaldo), mismo patrón de honestidad que `corpus-voz.php`.
3. `tests/Unit/Redaccion/CorpusTrazabilidadFixturesTest.php` — verificación estructural del fixture, sin red real.

**No cambia**: `VerificadorRegresionVoz::UMBRAL_DEFECTO`, `VerificadorTrazabilidadDeterminista::UMBRAL_DEFECTO`, ni el binding de `EmbeddingsInterface::class` en `Nucleo.php` (sigue en `ProveedorOpenRouter`).

## Evidencia — corrida real contra TEI local

Contenedor `pluma-tei-local` (`tools/tei-local/docker-compose.yml`, `intfloat/multilingual-e5-small`) levantado, `GET /health` → `200`. Cerebro remoto del plugin apuntado a `http://host.docker.internal:8089` (`tools/calibracion-embeddings/configurar-cerebro-remoto-dev.php`, clave de prueba local). Ejecución real de `calibrar.php` vía `wp eval-file` dentro del contenedor `cli` de wp-env:

```
== Calibración de embeddings — Pluma\Proveedores\ProveedorEmbeddingsCerebroRemoto ==
Corpus de DESARROLLO (no producción)

-- Voz (VerificadorRegresionVoz::similitudPromedioConCorpus, umbral de fábrica actual: 0.70) --
Intra-periodista (voz genuina)   n=12  min=0.7827  mediana=0.8445  media=0.8351  max=0.8659
Inter-periodista (voz ajena)     n=36  min=0.7751  mediana=0.8161  media=0.8127  max=0.8550

-- Trazabilidad (coseno hecho vs unidad, umbral de fábrica actual: 0.75) --
Con respaldo real                n=4   min=0.9507  mediana=0.9643  media=0.9630  max=0.9726
Sin respaldo real                n=4   min=0.7817  mediana=0.7947  media=0.8038  max=0.8440
```

## Lectura honesta de los números — sin conclusión de producción

- **Trazabilidad**: separación clara entre "con respaldo" (0.95-0.97) y "sin respaldo" (0.78-0.84) en este corpus mínimo — un hueco amplio, muy por encima del umbral de fábrica actual (`0.75`). Señal alentadora, pero de solo 4 casos inventados para esta herramienta — no sustituye el corpus real de expedientes.
- **Voz**: la distribución intra-periodista (0.78-0.87) y la inter-periodista (0.775-0.855) **se solapan de forma notable** en este corpus — a diferencia de lo que uno esperaría de un umbral bien calibrado (como el `0.70` actual, calibrado contra la distribución de `ProveedorOpenRouter`, no la de `multilingual-e5-small`). Esto NO se interpreta aquí como "el modelo es peor" ni como "el modelo es viable" — puede deberse igual de bien a: (a) el corpus mínimo de 3 periodistas × 3 piezas es demasiado pequeño y temáticamente similar entre sí para separar voz de tema, (b) `multilingual-e5-small` sin el prefijo `"query: "`/`"passage: "` que su ficha recomienda (deliberadamente no añadido por `ProveedorEmbeddingsCerebroRemoto`, ver `ADR 0016`) pondera más el tema que el estilo, o (c) una combinación de ambas. Investigar cuál es la causa real, con un corpus mayor, es trabajo de una porción futura si se decide seguir por (d) — **no una conclusión de esta porción**.

**Ninguno de estos números fija ni sugiere un umbral de producción.** Están aquí como la evidencia real que exige `CLAUDE.md` "cero invención" — lo que se midió, con qué corpus, y sus límites — no como un veredicto sobre si recalibrar.

## Consecuencias

- El mecanismo de calibración queda disponible y verificado para cuando exista corpus real de Piloto — la próxima vez que se retome la opción (d), no hace falta reconstruirlo, solo sustituir los fixtures de desarrollo por datos reales curados.
- El hallazgo del solapamiento en voz es información nueva y real que una porción futura de (d) deberá investigar (tamaño de corpus, prefijos e5) antes de poder siquiera proponer un umbral candidato — se registra aquí para no perderlo, no se actúa sobre él todavía.
- `docs/ncp-estado-y-continuidad.md` se actualiza para reflejar que (d) tiene su mecanismo listo pero sigue bloqueada para calibración de producción real, mismo estatus que NCP-1 Porciones 2/5.
