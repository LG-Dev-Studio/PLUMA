# ADR 0003 — Independencia de familia de modelo para el verificador (Corrector Interno)

- **Fecha**: 2026-07-25
- **Estado**: Aceptada (con hito de validación en Piloto)
- **Contexto**: `PLAN-MAESTRO-EVOLUCION.md` §7 decisión 3 · Nivel Tres J · `GOVERNANCE §2.8`

## Decisión

Se construye el **contrato** de independencia en la Etapa 7 y se **gatea la obligatoriedad dura** del modo Autónomo tras validación empírica en Piloto (Nivel Tres T.2).

- **Etapa 7 (contrato)**: `LenguajeInterface` expone metadata de **familia de modelo** (no solo proveedor comercial — dos productos del mismo proveedor pueden compartir familia de entrenamiento). `GOVERNANCE §2.8` queda escrito.
- **Etapa 8 (capa determinista)**: J.3 — verificación de trazabilidad por similitud de embeddings unidad-a-unidad contra el expediente, previa a cualquier pasada generativa. Comparte infraestructura con el corpus de voz A.5.
- **Obligatoriedad dura del Autónomo** (`verificador_provider` de familia distinta): NO se activa como requisito incondicional hasta validar en Piloto que la alucinación correlacionada es un riesgo de primer orden en piezas cortas con expediente acotado — comparar tasa de aprobación sin fricción de verificador misma-familia vs. familia-distinta sobre el mismo corpus.

## Fundamento

N3-T.2 lo declara explícitamente como hipótesis: añade coste/latencia y potencialmente un segundo contrato de proveedor (toca la infraestructura que `PLUMA-E6-1` difirió), y no hay datos empíricos todavía (ningún producto en producción). Encajarlo como requisito duro sin validación sería invertir coste real contra un riesgo no cuantificado.

## Consecuencias / hito

- Hito de validación registrado como deuda en `docs/deuda.md`: "calibración empírica en Piloto del requisito de doble familia (J)".
- El segundo proveedor de familia distinta se coordina con la resolución futura de `PLUMA-E6-1` (infraestructura externa diferida).
