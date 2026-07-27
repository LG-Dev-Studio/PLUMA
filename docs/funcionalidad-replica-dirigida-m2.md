# Réplica dirigida (Nivel Tres M.2) — funcionalidad diferida a una versión posterior al lanzamiento

**Estado: diferida por decisión del propietario (2026-07-27, `ADR 0004`).** Este documento existe para que retomar esta pieza en una versión futura no obligue a re-derivar el contexto desde cero: qué es, por qué existe, qué parte ya está construida, qué falta y exactamente cómo construirlo cuando se retome.

## Qué es el "derecho de réplica previa" y por qué es distinto de "verificado"

El Capítulo 13 del Libro de Arquitectura ya tiene un derecho de rectificación — pero es **posterior** a la publicación: corregir un error ya publicado. El derecho de réplica previa es un derecho **anterior y distinto**: la obligación de intentar oír a la persona señalada **antes** de publicar una afirmación negativa sobre ella.

La distinción importa porque son dos protecciones contra dos riesgos distintos:

- **"Verificado"** (`GOVERNANCE §2.3`, doble fuente) protege contra publicar algo **falso**.
- **"Con derecho a réplica"** protege contra publicar algo **cierto, mostrado sin la explicación de la parte señalada** — que en responsabilidad civil/penal de casi cualquier jurisdicción, y en práctica reputacional en todas, es tan dañino como un hecho falso. Es la diferencia entre periodismo y un extracto de acusación con firma editorial encima.

Un hecho puede estar perfectamente verificado por dos fuentes de nivel A y aun así publicarse sin que la parte señalada haya tenido nunca oportunidad de responder en ningún medio consultado. `GOVERNANCE §2.3` no cubre ese caso — por eso el Nivel Tres (Capítulo M) lo añade como eje independiente.

## Los tres niveles (M.2) — mismo patrón de escalera de autonomía que ya organiza el resto del producto

### Nivel 1 — Verificación de postura ya existente en el expediente

**Ya construido — Etapa 7, Porción 2 (M.1+N.1), en producción desde 2026-07-25.**

Antes de que una Pieza con afirmación fáctica negativa sobre persona u organización identificable pueda salir de `EN_REDACCION`, el sistema verifica explícitamente si alguna de las 4-8 coberturas ya recolectadas incluye una declaración, negación, o un "contactado para comentar, no respondió" de la parte señalada. Esto **no exige contacto nuevo**: exige que el sistema busque activamente esa postura en lo que ya tiene, en vez de omitirla porque ninguna fuente secundaria la resaltó por su cuenta.

**Implementación real** (`src/Compuertas/CompuertaRiesgo.php`, `DiagnosticoRiesgo.php`):

- La misma llamada consolidada al proveedor de lenguaje que ya evalúa el resto de la Compuerta de Riesgo gana dos preguntas: `afirmacionNegativaSobrePersonaIdentificable` (¿la pieza afirma algo negativo sobre alguien identificable?) y `posturaSenaladoAusente` (solo relevante si la primera es verdadera: ¿ningún hecho del expediente registra la postura de esa persona?).
- Si ambas son verdaderas: motivo de retención **independiente** en `EvaluadorCompuertas`, distinto de "hechos disputados sin señalar" o "sin doble fuente" — un hecho puede estar impecablemente verificado y aun así activar este motivo.
- Perfil de jurisdicción (`N.1`, mismo cierre de Etapa 7): en régimen `Penal` (opción `pluma_regimen_responsabilidad`), cualquier afirmación negativa sobre persona identificable exige retención humana incondicional, sin importar si hay postura registrada o no.

### Nivel 2 — Búsqueda dirigida de postura (DIFERIDO)

Si ninguna fuente recolectada incluye la postura del señalado, el Investigador activaría una búsqueda adicional específicamente para localizarla: declaraciones en otros contextos, comunicados propios, entrevistas previas sobre el tema. El texto fuente es explícito: **"el mismo patrón de presupuesto acotado que B.2 ya estableció para la tercera fuente en contradicciones de ocurrencia"** (Nivel Dos B.2, Etapa 8 Porción 2).

**Por qué está bloqueado**: B.2 (la búsqueda de tercera fuente para contradicciones de ocurrencia) se diferió en la Porción 2 de la Etapa 8 como deuda `PLUMA-E8-1` — PLUMA no tiene hoy ningún proveedor de búsqueda web. Los únicos proveedores externos del plugin son `Pluma\Proveedores\ProveedorGoogleTrends` (tendencias, no búsqueda de texto libre) y `Pluma\Proveedores\ProveedorOpenRouter` (lenguaje/embeddings, no búsqueda web). "Buscar activamente declaraciones previas del señalado" es, en la práctica, una búsqueda tipo Google/Bing sobre texto libre — una capacidad que ningún proveedor actual ofrece.

**Diseño de referencia para cuando se retome** (mismo patrón que B.2, no reinventar):

1. Nuevo contrato `Pluma\Proveedores\ProveedorBusquedaInterface` (análogo a `LenguajeInterface`/`EmbeddingsInterface`) — un método `buscar(string $consulta, int $limite): list<ResultadoBusqueda>` o similar. **Cero invención**: verificar la API oficial real del proveedor elegido (documentación, endpoints, formato de respuesta) antes de escribir una sola línea de integración — mismo estándar ya aplicado a OpenRouter/embeddings en la Porción 1 de esta etapa.
2. Presupuesto de tiempo propio y acotado para esta búsqueda adicional, fuera del flujo estándar de 4-8 coberturas — mismo patrón que cualquier operación con presupuesto en `Pluma\Proveedores` (ver `PresupuestoLenguaje` como referencia de diseño, aunque esta sería una cuota/tiempo distinta, no de coste en USD necesariamente).
3. Nuevo método en `InvestigadorMecanico` (o un colaborador dedicado, mismo patrón que `ResolutorDisputas`/`DetectorHuecos` de la Porción 2): cuando `posturaSenaladoAusente` sea verdadero tras el Nivel 1, ejecutar la búsqueda dirigida; si encuentra una declaración/comunicado/entrevista previa relevante, añadirla al expediente como nuevo `HechoFuente` (con su procedencia registrada, `L.1`); si no encuentra nada, el expediente queda igual que hoy (Nivel 1 sigue aplicando el motivo de retención).
4. Registrar el resultado de la búsqueda (encontrado / no encontrado / presupuesto agotado) de forma auditable, mismo criterio de trazabilidad que el resto del pipeline.

**Decisión pendiente para cuando se retome**: qué proveedor de búsqueda real elegir (coste, límites de uso, licencia de los resultados) — la misma decisión de propietario que desbloquea `PLUMA-E8-1`. Ambas piezas (B.2 Nivel 2 de contradicciones, M.2 Nivel 2 de réplica) deberían resolverse juntas, comparten exactamente el mismo proveedor y el mismo patrón de presupuesto acotado.

### Nivel 3 — Contacto directo automatizado con ventana de espera obligatoria (DIFERIDO)

Para piezas de gravedad/polaridad alta sobre personas u organizaciones con canal de contacto público conocido, el sistema generaría (nunca enviaría de forma autónoma — límite de diseño deliberado del propio texto fuente) una solicitud de comentario que el editor humano aprueba con un clic, con una ventana mínima de espera antes de que la Pieza pueda avanzar más allá de `EN_REVISION`, incluso en modo Autónomo para todo lo demás. Sin respuesta al cierre de la ventana, la pieza se publica con la mención explícita "contactado para comentar antes de esta publicación, sin respuesta al cierre de esta edición".

**Por qué está bloqueado, más allá del proveedor de búsqueda**: este nivel tiene dependencias propias que Nivel 2 no tiene:

- Un **canal de contacto verificado por entidad** (¿cómo sabe el sistema que un email/formulario de contacto realmente pertenece a la persona/organización señalada, y no a un impostor? Riesgo de suplantación si se automatiza mal).
- Una **cola de aprobación humana con un clic** en el panel (nueva pantalla/endpoint REST, mismo patrón que la Sala de Revisión existente pero para un flujo distinto).
- Una **ventana de espera mínima** integrada al grafo de estados de la Pieza (`EstadoPieza`) — la pieza tendría que poder quedar "esperando respuesta" en `EN_REVISION` con un temporizador, algo que el grafo actual no modela (los estados laterales existentes — `Retenida`, `Fallida`, `SinPeriodistaIdoneo` — son indefinidos en el tiempo, no temporizados).

**M.3 (el propio Capítulo M) ya reconoce una tensión estructural que no se resuelve con más ingeniería**: el Nivel 3 es incompatible con el objetivo de "tendencia→publicación < 90 minutos" (Libro Cap. 1.4) para las piezas donde aplica — y eso es correcto, no un defecto: existen categorías de pieza (acusación grave contra persona identificable) donde el rigor debe ganarle a la velocidad, el mismo principio que ya rige la degradación por sensibilidad (Cap. 8.2). El propio texto fuente ubica este nivel en "Etapa 5, la máquina que aprende" — una fase de madurez del producto muy posterior al lanzamiento inicial, consistente con diferirlo.

## Qué NO se pierde al diferir esto

- El Nivel 1 (el caso más común y de menor coste — la postura YA está en alguna fuente recolectada) sigue en producción sin cambios, protegiendo activamente contra publicar sin dar voz al señalado cuando esa voz ya existe en el expediente.
- Ninguna porción posterior de la Etapa 8 depende de M.2 Nivel 2/3 — el roadmap continúa sin bloqueo.
- Cuando se elija un proveedor de búsqueda real, esta funcionalidad y `PLUMA-E8-1` (B.2, tercera fuente de contradicciones) se desbloquean juntas.
