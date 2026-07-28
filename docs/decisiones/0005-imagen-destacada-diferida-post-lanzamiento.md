# ADR 0005 — Imagen destacada (D.1-D.3 + N.2 + G.2) diferida a una versión posterior al lanzamiento

- **Fecha**: 2026-07-27
- **Estado**: Aceptada
- **Contexto**: `docs/PLAN-MAESTRO-EVOLUCION.md` — Etapa 8, Porción 8 · `docs/PLUMA_Engine_Nivel_Dos.md` Capítulo D (D.1-D.3) · deuda relacionada `PLUMA-E3-2`

## Decisión

**Toda la Porción 8 de la Etapa 8 (imagen destacada: compuerta de originalidad visual, tarjeta editorial, fallback, derechos de personalidad, `SatiricalArticle`) queda diferida explícitamente a una versión posterior al lanzamiento comercial de PLUMA Engine.** Decisión del propietario, tomada al abrir la Porción 8.

- **D.1** (generación con IA vs. selección de banco de stock) — **diferido**: PLUMA no tiene hoy ningún proveedor de generación ni de banco de imágenes con licencia integrado. Elegir uno implica costo recurrente por imagen y términos de licencia comercial que el propietario decidió no resolver todavía bajo presión de continuar la Etapa 8.
- **D.2** (Compuerta de Originalidad Visual: lista de bloqueo de marca/IP/artista vivo, registro de procedencia, consistencia visual por periodista) — **diferido**: depende por completo de que exista un proveedor de generación real contra el cual evaluar el prompt.
- **D.3** (tarjeta editorial tipográfica como fallback determinista, sin proveedor externo) — **diferido junto con el resto**, aunque es la pieza de menor riesgo técnico/legal de las tres (no depende de proveedor externo); se retoma junto con D.1/D.2 para no fragmentar la porción en un momento donde ya se decidió posponerla completa.
- **N.2** (derechos de personalidad en imagen) y **G.2** (`SatiricalArticle` en schema.org) — **diferidos** porque ambos son extensiones directas de la Compuerta de Originalidad Visual (D.2) que no existe todavía.

Documentación completa de la funcionalidad diferida (qué es, por qué importa, diseño de referencia cuando se retome) en `docs/funcionalidad-imagen-destacada-d1-d3.md`.

## Fundamento

- `CLAUDE.md` (Santo Grial §4, "cero invención"): elegir un proveedor de generación/banco de imágenes sin verificar su API oficial, su política de uso comercial y su modelo de costo real no es una decisión de ingeniería que se pueda tomar por defecto — es una decisión de producto con implicación de costo recurrente y riesgo legal (reproducción de estilo/marca/persona real), exactamente la misma clase de decisión que ya bloqueó `PLUMA-E8-1` (proveedor de búsqueda web).
- El propio texto fuente (D.1) ya señala que esta es "una de las decisiones de producto más consecuentes de todo el sistema" — no un detalle de implementación — y que el Libro original la trata con una sola frase, sin comparar las implicaciones reales.
- D.3 (el fallback) por sí solo no cambia el balance: sin D.1/D.2 construidos, el fallback tipográfico se activaría siempre (todas las piezas caerían en `RETENIDA: sin_activo_visual` en vez de tener imagen), lo cual no es el comportamiento que el propietario quiere entregar en esta etapa.

## Consecuencias

- `docs/deuda.md`: nueva fila registrando el diferimiento explícito de la Porción 8 completa (D.1-D.3 + N.2 + G.2), enlazada a este ADR y a `PLUMA-E3-2` (deuda preexistente desde la Etapa 3 que esta porción iba a pagar).
- El roadmap de la Etapa 8 continúa con la Porción 9 (Legitimidad del insumo, G.1) sin bloqueo — ninguna porción posterior depende de la imagen destacada.
- Cuando se retome, la decisión de proveedor de imagen y la decisión de proveedor de búsqueda web (`PLUMA-E8-1`) son independientes entre sí — no comparten mecanismo, a diferencia de `PLUMA-E8-6` (réplica dirigida), que sí compartía proveedor con `PLUMA-E8-1`.
