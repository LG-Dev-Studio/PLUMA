# ADR 0010 — NCP-1 se enmienda: el recorte no es "reasignación a P0", y la medición es su prerequisito

- **Fecha**: 2026-07-30
- **Estado**: Aceptada
- **Contexto**: `docs/CEREBRO_PLUMA_v2.md` — Parte 6.2 (fase NCP-1), Parte 5.2.10 ("cero complacencia con el propio diseño"), Parte 7 ("si la investigación contradice el documento: DETENTE") · `docs/deuda.md` · Auditoría de las 21 llamadas vivas al proveedor de lenguaje

## Decisión

**La fase NCP-1 pasa a definirse como: "auditoría de todas las llamadas al proveedor; eliminación por pre-compuerta léxica, deduplicación de juicios repetidos, sustitución por hechos estructurales ya conocidos, y caché; reasignación a P0 donde exista; contratos nuevos; `PerfilIdioma`; Sonda de Capacidades" — y se divide en porciones, siendo la primera el instrumento de medición.** Decisión del propietario, tomada el 2026-07-30 tras el reporte de los tres hallazgos de abajo.

Además: **la violación de §5.1.4 que ya existe en producción se repara en su propia porción, fuera de NCP-1**, con gates completos (mismo criterio aplicado a `PLUMA-E9-19`).

## Conflicto que motivó la decisión

Tres contradicciones entre el canon y el código real, verificadas archivo por archivo antes de escribir una línea:

**1. "Reasignación a P0" no tiene destino real.** Auditadas las 21 llamadas vivas a `LenguajeInterface::completar()` y los 2 consumidores de `EmbeddingsInterface`: **ninguna** se convierte en PHP puro. Once son genuinamente generativas (→P2) y diez son semánticas (→P1, que no existe hasta NCP-2/3). El recorte léxico fácil **ya se cosechó en etapas anteriores**: `VerificadorNGramas`, `VerificadorVoz`, `VerificadorLegibilidad`, `VerificadorComentarioSustantivo` y 2 de los 6 puntos del `CorrectorInterno` ya son deterministas. La premisa del documento ("la mayoría no era genuinamente generativa") es **correcta**; su conclusión ("luego se reasigna a P0") no lo es, porque el destino correcto de esas diez es P1.

**2. El criterio de salida es hoy inmedible.** El documento exige medir "% de llamadas de pago eliminadas **sobre bitácora real**". `Pluma\Proveedores\PresupuestoLenguaje` guarda un único escalar de USD del día (`pluma_gasto_lenguaje`), que se pisa al cambiar la fecha, sin historial ni desglose por propósito. `RespuestaLenguaje` sí lleva coste, tokens, modelo y latencia por llamada, pero **nunca se persiste**. `pluma_bitacora_motor` registra ejecuciones del motor, no gasto de modelo. No existe ninguna bitácora contra la que medir: la instrumentación es un prerequisito que la lista de fases omite.

**3. §5.1.4 ("ninguna inferencia en petición de visitante") ya está violado.** `src/Compuertas/CompuertaComentarios.php:48` engancha `pre_comment_approved` — síncrono con el envío de un visitante anónimo — y en `:72` llama a `ClasificadorComentarios`, que llega hasta `ProveedorOpenRouter::completar()` con `TIMEOUT_SEGUNDOS = 60`. El presupuesto diario es **compartido** con la redacción y los modos de fallo son **asimétricos**: el comentario falla seguro (cae a la moderación nativa de WordPress), pero agotar el presupuesto detiene la publicación de piezas. Spam de comentarios anónimos es, hoy, una denegación de servicio sobre la función central del producto.

## Fundamento

- El documento se declara a sí mismo "canon, no dogma" (§5.2.10) y ordena detenerse y reportar con evidencia antes que ejecutar un plan equivocado en silencio. Ejecutar la letra de NCP-1 habría cerrado la fase reportando ~0% de llamadas reasignadas a P0 — un resultado técnicamente cierto y completamente inútil — mientras el trabajo que sí ahorra llamadas quedaba fuera de alcance por no llamarse "P0".
- El recorte que sí existe es de cuatro mecanismos, todos medibles y todos compatibles con las restricciones de la Parte 5: pre-compuertas léxicas que evitan la llamada; deduplicación (la gravedad 0-100 se le pregunta al modelo hasta tres veces sobre el mismo ítem, entre `ClasificadorGravedadTendencia`, `ClasificadorNoticia` y `CompuertaRiesgo`, sin reconciliación); sustitución por hechos que PLUMA ya tiene y le pregunta al modelo igual (`hechosDisputadosSinSenalar` ya vive en `NivelVerificacion::Disputado`; `posturaSenaladoAusente` en `EstadoProcedenciaDeclaracion`); y caché de embeddings (hoy `VerificadorRegresionVoz` re-embebe el corpus de referencia completo en cada invocación).
- Medir y recortar no caben en el mismo commit: sin un "antes" registrado, el "% eliminado" no es verificable y la fase se vuelve incomprobable — precisamente lo que §5.2.7 ("la calidad se mide con el instrumento que ya existe... no confíes en impresiones") prohíbe.
- La violación de §5.1.4 es un cambio de comportamiento del motor con implicaciones de coste y de disponibilidad. Mezclarla dentro de una refactorización grande impediría verificarla por separado; el proyecto ya sentó ese precedente con `PLUMA-E9-19`.

## Consecuencias

- NCP-1 se ejecuta en porciones: (1) instrumento de medición — cero cambio de comportamiento; (2) auditoría y reporte sobre datos reales; (3) `Pluma\Idioma` + `PerfilIdioma`; (4) Sonda de Capacidades; (5) el recorte propiamente dicho. Las porciones 1 y 5 nunca se fusionan.
- El instrumento (`pluma_llamadas_modelo`, esquema `0.25.0`) registra además el **origen** de cada llamada (`cron`/`panel`/`visitante`), que es lo que vuelve medible el hallazgo 3 y lo que NCP-4 §1.6 exigirá después para la auditoría de procedencia por pieza.
- `docs/deuda.md`: filas nuevas por la violación de §5.1.4 (con su exposición de coste y de disponibilidad), por la deduplicación de la gravedad entre tres clasificadores, y por las limitaciones declaradas del instrumento (coste no atribuible en embeddings; propósito de `embed()` registrado como bucket).
- Este ADR es el reporte formal exigido por §5.2.10. Si una porción posterior vuelve a contradecir el canon, se abre otro ADR en vez de resolverlo en el código.
