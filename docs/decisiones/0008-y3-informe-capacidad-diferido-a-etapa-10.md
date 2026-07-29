# ADR 0008 — Y.3 (informe de asignación de capacidad) diferido a la Etapa 10, junto con R

- **Fecha**: 2026-07-29
- **Estado**: Aceptada
- **Contexto**: `docs/PLUMA_Engine_Nivel_Cuatro.md` — Capítulo Y.3 · `docs/PLAN-MAESTRO-EVOLUCION.md` línea 141 (asignación de R a Etapa 10) · Etapa 9, Porción 5

## Decisión

**Y.3 ("de la economía unitaria... a la decisión de crecimiento", el informe de asignación de capacidad por vertical/periodista) queda diferido a la Etapa 10 — El Espejo (Homeostasis a escala), junto con R.** Decisión del propietario, tomada al abrir la Porción 5 de la Etapa 9 (2026-07-29).

## Conflicto que motivó la decisión

Y.3 (Nivel Cuatro) asume literalmente que "R calcula coste y valor por pieza" ya existe, y que Y.3 solo añade la capa de decisión trimestral encima de ese dato. Verificado contra el código real: **R no existe en ningún lugar de `src/`** — ni el coste por pieza (tokens × precio por propósito) ni el valor esperado (sesiones históricas × ingreso estimado) están calculados ni persistidos (`bitacora_motor` no tiene columna de coste, confirmado). Más importante: `docs/PLAN-MAESTRO-EVOLUCION.md` (línea 141) ya asigna explícitamente la construcción de R a la **Etapa 10 — Homeostasis a escala**, con la nota "Señal direccional al editor, jamás corte automático (P4)" — una decisión de planificación tomada antes de que existiera el Nivel Cuatro.

Construir Y.3 tal como el texto lo describe exigiría adelantar R completo fuera de la etapa donde el propio plan maestro del proyecto ya lo situó — invención de alcance no autorizada, exactamente el tipo de decisión que el `/goal` de esta sesión exige escalar en vez de resolver en silencio.

## Fundamento

- R y Y.3 son la misma pieza de trabajo en dos documentos distintos (Nivel Tres §R, Nivel Cuatro §Y.3) — construir Y.3 sin R sería fabricar un "informe de asignación de capacidad" sobre datos inventados, justo lo que CLAUDE.md prohíbe ("cero invención").
- El plan maestro ya reconoce que R pertenece a una etapa posterior, dedicada específicamente a la homeostasis del sistema a escala — mezclarlo dentro de la Porción 5 de la Etapa 9 fragmentaría esa etapa futura sin necesidad.
- Las demás piezas de la Porción 5 (X.2-X.4, Y.2, Z) no dependen de R y se construyen sin bloqueo.

## Consecuencias

- `docs/deuda.md`: nueva fila registrando a Y.3 como diferido, enlazada a este ADR y a la fila ya existente de `PLAN-MAESTRO-EVOLUCION.md` que asigna R a la Etapa 10.
- Cuando se abra la Etapa 10, R se construye primero (coste por pieza en la bitácora del motor, valor esperado por pieza) y Y.3 se retoma inmediatamente después, reutilizando ese dato — mismo orden que el propio Nivel Cuatro asume.
- La Porción 5 de la Etapa 9 continúa con X.2, X.3, X.4, Y.2 y Z, ninguna de las cuales depende de R.
