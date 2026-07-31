# ADR 0011 — Retiro completo de la función de comentarios (compuertas + Sala de Comentarios)

- **Fecha**: 2026-07-30
- **Estado**: Aceptada
- **Contexto**: `docs/PLUMA_Engine_Libro_de_Arquitectura.md` Cap. 5.7 ("el compromiso de respuesta") y Nivel Cuatro X.1-X.2 (Etapas 5 y 9) — `src/Compuertas/CompuertaComentarios.php`, `src/Pipeline/GestorRespuestasComentarios.php` y toda su cadena de soporte · `docs/decisiones/0010-ncp1-recorte-enmienda-de-fase.md` (hallazgo 3)

## Decisión

**Se elimina por completo del producto la función de comentarios**: la compuerta de clasificación de comentarios de visitantes (`CompuertaComentarios`/`ClasificadorComentarios`), la Sala de Comentarios (borrador de respuesta del periodista sintético, `GestorRespuestasComentarios`/`GeneradorRespuestaComentario`), y el aprendizaje de audiencia derivado de comentarios (`AnalizadorAudiencia`). Decisión del propietario, tomada el 2026-07-30. No se comenta ni se desactiva por flag: se borra el código, el esquema de base de datos y toda la superficie de panel asociada.

## Conflicto que motivó la decisión

El propietario definió el objetivo del producto como generación de contenido periodístico, no administración de sitio: "quiero centrarme más en el contenido que se genera que en la administración del sitio como los comentarios". La función de comentarios, sin embargo, gasta crédito real de la cuenta de IA del cliente (`ClasificadorComentarios::clasificar()`, propósito económico pero sin excepción por comentario) en una tarea de moderación de sitio, no de generación de noticias — y lo hace, además, en la ruta más sensible posible: síncrona con la petición de un visitante anónimo (`CompuertaComentarios::evaluar()` en `pre_comment_approved`).

Esto ya estaba señalado como una violación de §5.1.4 de `docs/CEREBRO_PLUMA_v2.md` en `ADR 0010` (hallazgo 3, deuda `PLUMA-E9-26`): presupuesto compartido con la redacción, modos de fallo asimétricos (el comentario falla seguro, agotar el presupuesto detiene la publicación de Piezas). La reparación prevista era una porción propia con gates completos que **arreglara** el problema conservando la función. El propietario, al revisar para qué sirve realmente la función frente al presupuesto real de créditos, decidió que el problema de fondo no es cómo se llama al modelo, sino que se le llame para esto en absoluto — la función completa queda fuera del alcance del producto.

## Fundamento

- Cero llamadas al modelo por comentario de visitante: elimina de raíz el vector de gasto no controlado descrito en `PLUMA-E9-26`, sin necesidad de la porción de reparación que `ADR 0010` había proyectado — esa porción del roadmap de NCP-1 queda retirada, no pendiente.
- La Sala de Comentarios (respuesta en la voz del periodista) es una capacidad de compromiso de audiencia, no de generación de noticias — coherente con el objetivo declarado, no encaja en el producto tal como el propietario lo redefine.
- El aprendizaje de audiencia (`TipoMemoria::Audiencia`) solo se alimentaba de comentarios: sin la función que lo escribe, el caso queda huérfano de escritura. Se conserva el caso del enum (dato histórico ya persistido en instalaciones reales, `pluma_memoria_editorial.tipo = 'audiencia'`, sigue siendo válido de leer) pero se retira el único punto de lectura (el bloque "Audiencia" del informe editorial semanal) — evita el riesgo de que una fila histórica real rompa con un `ValueError` al forzar el borrado del caso del enum.

## Consecuencias

- **Esquema**: nueva versión `0.26.0`. La tabla `pluma_respuestas_comentarios` y la columna `respuestas_habilitadas` de `pluma_periodistas_conducta_versiones` se retiran con una migración forward real (`Migrador::ejecutarRetiro()`, idempotente — nunca falla si ya se ejecutó o si la instalación es nueva y nunca las tuvo), no solo se dejan de declarar en `sentenciasCreateTable()`. La reversa `0.26.0->0.25.0` restaura la FORMA del esquema (columna con su defecto, tabla vacía) para quien necesite revertir manualmente — los datos ya borrados por el retiro no se recuperan, coherente con que un `DROP` real nunca es reversible en datos, solo en forma.
- **Capacidad compartida `pluma_aprobar_piezas`**: se conserva sin cambios — la usa también la Sala de Revisión de Piezas, ajena a esta función.
- `docs/deuda.md`: `PLUMA-E9-26` se marca retirada (la función que la originaba ya no existe, no queda deuda que pagar).
- `docs/decisiones/0010-ncp1-recorte-enmienda-de-fase.md` no se reescribe (es historia inmutable de lo que se reportó en su momento); este ADR es la resolución posterior.
- Ninguna otra capa del producto dependía de estas clases (confirmado por auditoría exhaustiva antes de borrar): la Sala de Revisión, el Banco de Periodistas, el Estudio de Conducta y el resto del panel quedan funcionales sin ningún cambio de comportamiento fuera de la desaparición de la propia función.
