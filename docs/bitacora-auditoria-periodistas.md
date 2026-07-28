# Bitácora de auditoría de periodistas (Nivel Tres P.3)

Registro mensual, append-only, de dos verdictos distintos que **no se mezclan** en un solo tipo de fila: el resultado automatizado del corpus de regresión de voz (`composer test:voz`) y el veredicto humano de la auditoría editorial ligera por periodista activo. Mezclarlos confundiría un resultado de test con un juicio editorial — son señales de naturaleza distinta.

**Sin infraestructura nueva** (Nivel Tres, tabla de ubicación de P.3: "no depende de infraestructura nueva, solo de calendario"): este archivo markdown, actualizado a mano cada mes, es el mecanismo completo. Sin tabla nueva, sin endpoint REST, sin pantalla de panel.

## Checklist mensual

1. Ejecutar `composer test:voz` (ver `docs/protocolo-corpus-voz.md` §"Cadencia mensual independiente de release"). Registrar el resultado en la tabla §1.
2. Para cada periodista de `RepositorioPeriodistasInterface::obtenerActivos()` (el banco real de la instalación — no solo los 3 periodistas de siembra de `PlantillasSiembra`, que son solo la composición inicial recomendada), el propietario o el editor de revisión (Libro Cap. 12.1) revisa una muestra de piezas recientes contra la biografía y línea editorial originales del periodista (`ReglasConducta::$lineaEditorial`). Registrar un veredicto explícito en la tabla §2: **Conforme** / **Deriva detectada** / **Requiere ajuste**, con una nota de una línea.
3. Si el corpus de voz marca deriva excesiva (§1) o la auditoría editorial detecta un desajuste (§2), investigar antes del siguiente release que toque prompts — puede ser deriva silenciosa del modelo (Nivel Tres P.3) o una Conducta que necesita ajuste manual.

## §1 — Resultado de `composer test:voz`

| Fecha | Resultado | Notas |
|---|---|---|

## §2 — Auditoría editorial por periodista activo

| Fecha | Periodista | Veredicto | Nota |
|---|---|---|---|
