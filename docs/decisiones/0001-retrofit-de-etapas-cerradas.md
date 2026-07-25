# ADR 0001 — Reabrir Etapas cerradas para retrofit de mecanismo

- **Fecha**: 2026-07-25
- **Estado**: Aceptada
- **Contexto**: `PLAN-MAESTRO-EVOLUCION.md` §3, §7 decisión 1

## Decisión

Se acepta que las Etapas nuevas de evolución (7 "Endurecimiento del criterio" y 8 "El razonamiento completo") **modifiquen módulos ya enviados y en verde** (`Pluma\Compuertas`, `Pluma\Redaccion`, `Pluma\Investigacion`, `Pluma\Sensores`). Esto rompe con la lectura literal de "ninguna Etapa se abre con la anterior en rojo": aquí abrimos Etapas que endurecen Etapas verdes.

## Alternativas consideradas

1. **Congelar lo enviado, aplicar N2/N3 solo hacia adelante** — rechazada: dejaría vivo en producción el agujero de K.2 (la Compuerta de Calidad promedia anti-alucinación con buena prosa) y de M.1/N.1, exactamente la complacencia que `CLAUDE.md §Santo Grial` prohíbe.
2. **Reabrir para endurecer** — elegida.

## Consecuencias

- Los cambios de TIER 0/1 sobre código en producción con clientes beta encima son más delicados que construir en verde (autocrítica del plan §8): cambiar la Compuerta de Calidad bajo tráfico real exige el mismo Delivery Guardian + migración N-1→N probada con datos reales.
- Cada retrofit entra por su Etapa con Mission Lock propio y fixtures de aceptación (casos H de N2 y S de N3).

## Señal que obligaría a reconsiderar

Que un retrofit de compuerta rompa producción de un cliente beta de forma no contenible por el gate de release — en cuyo caso se congela ese retrofit y se re-secuencia tras cerrar la beta.
