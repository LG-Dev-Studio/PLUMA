# Registro de Puntuaciones Compuestas — PLUMA Engine

Regla: `GOVERNANCE.md §1.6`. Toda puntuación compuesta del sistema declara, por factor, una tabla de tres columnas: **(1)** ¿piso eliminatorio o contribuyente ponderado? **(2)** si es piso, umbral + qué pasa debajo; **(3)** ¿piso de fábrica no editable a la baja, o configurable?

Un factor de elegibilidad (piso) nunca se promedia con factores de prioridad — actúa como puerta binaria previa a la ponderación. Ninguna puntuación nueva se acepta en el código sin su fila en este registro.

> Estado: las cuatro puntuaciones existentes se declaran aquí según el canon (Nivel Dos C.1–C.3, Nivel Tres K.1–K.2). Las que aún no aplican el piso en el código enviado quedan marcadas **PENDIENTE DE RETROFIT** con su Etapa de pago del `PLAN-MAESTRO-EVOLUCION.md`.

---

## 1. Puntuación de Oportunidad del Radar
*Origen: Libro 3.3 · corrección: Nivel Dos C.1 · vive en `Pluma\Sensores`.*

| Factor | ¿Piso o contribuyente? | Umbral / qué pasa debajo | ¿Piso de fábrica? |
|---|---|---|---|
| Afinidad con la línea editorial | **Piso (puerta binaria)** | `afinidad ≥ umbral_afinidad_minima` (ej. 15/100); si no, `puntuacion = 0` → no entra a la cola | Default de fábrica configurable; revisión a 90 días |
| Velocidad | Contribuyente (0.40) | — | — |
| Hueco competitivo | Contribuyente (0.25) | — | — |
| Vida útil | Contribuyente (0.20) | — | — |
| Afinidad normalizada (residual) | Contribuyente (0.15) | — | — |

Estado en código: **PENDIENTE DE RETROFIT** — Etapa 8 (C.1). Deuda relacionada: `PLUMA-E1-1` (hueco competitivo y vida útil no integrados).

## 2. Asignación de Periodista
*Origen: Libro 5.5 Paso 2 · corrección: Nivel Dos C.2–C.3 · vive en `Pluma\Redaccion\AsignadorPeriodista`.*

| Factor | ¿Piso o contribuyente? | Umbral / qué pasa debajo | ¿Piso de fábrica? |
|---|---|---|---|
| Dominio del vertical | **Piso** | Si ningún periodista supera `umbral_dominio_minimo` → estado `SIN_PERIODISTA_IDONEO` (no se asigna "al menos malo") | Default de fábrica configurable |
| Afinidad / carga / historial | Contribuyentes + reglas de desempate | Desempate: carga → historial con la historia → `AzarInterface` con semilla | — |

Estado en código: **PENDIENTE DE RETROFIT** — Etapa 8 (C.2–C.3). Hoy el `AsignadorPeriodista` usa heurístico léxico sin piso (deuda `PLUMA-E2-2`).

## 3. Selección de Ángulo (tesis)
*Origen: Libro 5.5 Paso 3 · corrección: Nivel Tres K.1 · vive en `Pluma\Redaccion\SelectorAngulo`.*

| Factor | ¿Piso o contribuyente? | Umbral / qué pasa debajo | ¿Piso de fábrica? |
|---|---|---|---|
| Sustento en hechos verificados | **Piso** | `sustento ≥ umbral_sustento_minimo`; si todos los candidatos fallan → vuelve al Investigador ("el expediente no sustenta ninguna tesis; ampliar o descartar") | **Piso de fábrica NO editable a la baja** |
| Originalidad | Contribuyente (0.40) | — | — |
| Compatibilidad con línea editorial | Contribuyente (0.35) | — | — |
| Potencial de conversación | Contribuyente (0.25) | — | — |

Estado en código: **RETROFITADA — Etapa 7 (K.1), 2026-07-25.** `SelectorAngulo::generarCandidatos()` ya filtraba por el piso; `CandidatoTesis::puntuacionTotal()` ahora pondera solo los tres factores de prioridad (antes era media simple de los 4, incluido el sustento ya gateado).

## 4. Compuerta de Calidad
*Origen: Libro 8.1 · corrección: Nivel Tres K.2 (el hallazgo más grave) · vive en `Pluma\Compuertas\CompuertaCalidad`.*

| Factor | ¿Piso o contribuyente? | Umbral / qué pasa debajo | ¿Piso de fábrica? |
|---|---|---|---|
| Densidad de sustento | **Piso NO compensable** | `< umbral_sustento_minimo_calidad` → 0 → RETENIDA (sin excepción de umbral configurable) | **Piso de fábrica NO editable a la baja** |
| Estructura completa | **Piso binario** | `gancho+tesis+contraargumento+bloque` ausente → 0 → RETENIDA | **Piso de fábrica NO editable a la baja** |
| Proporción interpretativa | Contribuyente (0.40) | — | — |
| Legibilidad | Contribuyente (0.35) | — | — |
| Presencia de voz | Contribuyente (0.25) | — | — |

Estado en código: **RETROFITADA — Etapa 7 (K.2), 2026-07-25.** `CompuertaCalidad::evaluar()` ya no suma los puntos de sustento/estructura al total: ambos son puertas binarias (`elegible = sustentoAprobado && estructuraCompleta`); si `!elegible`, `puntuacionTotal = 0` sin excepción. Solo tras superar ambos pisos se pondera proporción/legibilidad/voz.

---

## Puntuaciones futuras (declarar aquí ANTES de implementar)

Toda puntuación nueva —candidato a pieza de refuerzo del bucle SEO, valor marginal por pieza (Nivel Tres R), fuerza del contraargumento (Nivel Tres O.1), entropía estructural (Nivel Tres P.1), clasificador de comentarios (Nivel Cuatro X.1), ganador de A/B de titular (Nivel Cuatro Y.2)— añade su fila antes de escribir la función. Referencia de naturaleza de umbral: N4-I.2 ya clasificó las de N3 (contraargumento = umbral de retorno; entropía = umbral de alerta; tasa de detección del editor = alerta agregada; similitud de trazabilidad = umbral de priorización).
