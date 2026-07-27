# ADR 0004 — Réplica dirigida (M.2 Nivel 2-3) diferida a una versión posterior al lanzamiento

- **Fecha**: 2026-07-27
- **Estado**: Aceptada
- **Contexto**: `docs/PLAN-MAESTRO-EVOLUCION.md` — Etapa 8, Porción 5 · Nivel Tres, Capítulo M (`docs/PLUMA_Engine_Nivel_Tres.md` M.2-M.3) · deuda relacionada `PLUMA-E8-1`

## Decisión

**Nivel 2 (búsqueda dirigida) y Nivel 3 (contacto directo automatizado) del derecho de réplica previa (M.2) quedan diferidos explícitamente a una versión posterior al lanzamiento comercial de PLUMA Engine.** Decisión del propietario, tomada al abrir la Porción 5 de la Etapa 8.

- **Nivel 1** (verificación de postura ya existente en el expediente) — **ya construido**, Etapa 7 Porción 2 (M.1+N.1): `CompuertaRiesgo`/`DiagnosticoRiesgo` ya detecta `posturaSenaladoAusente` y lo trata como motivo de retención independiente. Sin cambios, sigue en producción.
- **Nivel 2** (búsqueda dirigida adicional cuando ninguna fuente recolectada trae la postura) — **diferido**. Depende literalmente del mismo mecanismo de búsqueda web acotada que B.2 (Etapa 8, Porción 2) ya dejó diferido como `PLUMA-E8-1`: PLUMA no tiene hoy ningún proveedor de búsqueda web (solo Google Trends para tendencias y OpenRouter para lenguaje). Construir el Nivel 2 sin ese proveedor obligaría a inventar o simular una búsqueda real, lo que viola "cero invención" (`CLAUDE.md`).
- **Nivel 3** (contacto directo automatizado con ventana de espera) — **diferido**, con dependencias propias más allá del proveedor de búsqueda: un canal de contacto verificado por entidad, una cola de aprobación humana con un clic, y una ventana de espera mínima integrada al grafo de estados (`EN_REVISION`). Nada de esto existe hoy.

Documentación completa de la funcionalidad diferida (qué es, por qué importa, diseño exacto cuando se retome) en `docs/funcionalidad-replica-dirigida-m2.md` — para que retomar esta pieza en una versión futura no requiera re-derivar el contexto desde cero.

## Fundamento

- El propio texto fuente (M.2 Nivel 2) dice explícitamente: "el mismo patrón de presupuesto acotado que B.2 ya estableció" — es decir, M.2 Nivel 2 fue diseñado por el documento fuente asumiendo que B.2 ya estaría resuelto. Como B.2 se diferió en la Porción 2 (mismo motivo raíz: sin proveedor de búsqueda), M.2 Nivel 2 hereda el mismo bloqueo, no es un problema nuevo.
- M.3 (el propio Capítulo M) ya reconoce que el Nivel 3 es "estructuralmente incompatible... con el objetivo de tendencia→publicación < 90 minutos" para las piezas donde aplica — es una categoría de pieza donde el rigor debe ganarle a la velocidad, coherente con la degradación por sensibilidad ya existente (Cap. 8.2). Esto refuerza que no es una pieza que deba apresurarse para el lanzamiento.
- El Nivel 1 (ya construido) cubre el caso más común y de menor coste: cuando la postura del señalado YA está en alguna de las 4-8 coberturas recolectadas, la Compuerta de Riesgo ya la exige. El Nivel 2/3 solo se activa cuando ninguna fuente la trae — una fracción menor de piezas, no el camino crítico del producto.

## Consecuencias

- `docs/deuda.md`: nueva fila registrando el diferimiento explícito de la Porción 5 completa (Nivel 2+3), enlazada a `PLUMA-E8-1` y a este ADR.
- Cuando se elija y verifique un proveedor de búsqueda real (la misma decisión pendiente que desbloquea `PLUMA-E8-1`), Nivel 2 se retoma primero — Nivel 3 requiere trabajo adicional propio (canal de contacto, cola de aprobación, ventana de espera) y puede secuenciarse después.
- El roadmap de la Etapa 8 continúa con la Porción 6 (Grafo y memoria, E.1+E.2) sin bloqueo — ninguna porción posterior depende de M.2 Nivel 2/3.
