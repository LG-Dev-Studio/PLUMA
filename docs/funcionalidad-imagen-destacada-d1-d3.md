# Imagen destacada (Nivel Dos D.1-D.3 + N.2 + G.2) — funcionalidad diferida a una versión posterior al lanzamiento

**Estado: diferida por decisión del propietario (2026-07-27, `ADR 0005`).** Este documento existe para que retomar esta pieza en una versión futura no obligue a re-derivar el contexto desde cero: qué es, por qué el Libro original la trata mal, qué falta y exactamente cómo construirlo cuando se retome.

## Por qué esto no es un detalle de implementación

El Libro de Arquitectura original (Cap. 6.2) despacha la imagen destacada en una sola frase: "generada o seleccionada de bancos con licencia... jamás imágenes de otros medios". Esa frase esconde una decisión de producto con tres ejes que el Libro nunca compara entre sí:

1. **Riesgo legal**: una imagen generada por IA puede reproducir, sin que nadie lo note hasta la carta de cese y desista, el estilo de un artista vivo, un personaje con IP activa, o el parecido de una persona real identificable. Una imagen de banco de stock mal elegida puede violar los términos de su propia licencia.
2. **Coste recurrente**: tanto la generación con IA como un banco de stock de pago tienen un coste por imagen, distinto del coste de texto que PLUMA ya gestiona vía `PresupuestoLenguaje`.
3. **Identidad visual del banco de periodistas**: si el banco de periodistas es "el activo del cliente" (`CLAUDE.md`), la imagen destacada de cada pieza es parte de esa marca visual, no un detalle desechable pieza a pieza.

## Los tres niveles (D.1-D.3)

### D.1 — La bifurcación: generación con IA vs. selección de banco de stock (DIFERIDO)

Dos caminos con implicaciones muy distintas:

- **Selección de banco con licencia**: requiere una integración nueva con un proveedor de stock (contrato de proveedor externo, con su propio circuit breaker, mismo patrón que `pl-proveedor-ia` ya exige) **y** un algoritmo de relevancia que hoy no existe: ¿cómo decide el sistema que una foto de stock "es" la imagen correcta para una tendencia específica sin caer en la genericidad visual que delata cualquier sitio de bajo esfuerzo (la misma foto de "manos escribiendo en laptop" en cien artículos de tecnología distintos)? Para una tendencia muy "relámpago" (la clasificación de vida útil del Radar, Libro 3.3), casi nunca va a existir ya una foto de banco relevante — es, estructuralmente, el caso más común, no el raro.
- **Generación con IA**: resuelve la genericidad pero abre una superficie de riesgo que el Capítulo 12 (Seguridad) del Libro nunca menciona — ver D.2.

**Por qué está bloqueado**: PLUMA no tiene hoy ningún proveedor de generación ni de banco de imágenes con licencia integrado (OpenRouter no ofrece generación de imágenes). Elegir uno es una decisión de producto (costo recurrente + términos de licencia comercial) que el propietario decidió posponer, no una decisión de ingeniería que se pueda tomar por defecto — mismo criterio "cero invención" ya aplicado a la elección del proveedor de búsqueda web (`PLUMA-E8-1`).

### D.2 — La Compuerta de Originalidad Visual: el gemelo faltante de la Compuerta de Originalidad de texto (DIFERIDO)

Toda pieza con imagen generada por IA debería pasar, antes del Publicador, un chequeo específico — no el mismo chequeo de n-gramas de texto (no aplica a píxeles), sino su equivalente funcional:

1. **Verificación de prompt limpio de marca, antes de la llamada**: el prompt de generación no puede contener nombres de personas reales identificables, marcas, personajes con IP conocida, ni instrucciones de estilo que referencien un artista vivo o un estudio con propiedad intelectual activa (Disney, Marvel y equivalentes) — PLUMA necesita su propia lista de bloqueo previa a la llamada, con el mismo espíritu que la lista negra de fuentes del Investigador; no se puede asumir que el proveedor lo bloquea siempre por su cuenta.
2. **Registro de procedencia en el expediente de auditoría**: qué proveedor generó la imagen, con qué prompt exacto, en qué fecha — el día que un cliente reciba un reclamo de derechos sobre una imagen publicada, la pregunta legal inmediata es "¿de dónde salió esa imagen y con qué instrucciones se generó", y el sistema debe poder responderla en segundos (misma filosofía de trazabilidad total que el Libro exige para texto, P6, y todavía no extiende a imagen).
3. **Consistencia visual por periodista, no solo por pieza**: una paleta o estilo visual por periodista (campo nuevo en su Identidad) evita que la generación por pieza produzca variación visual tan alta que el sitio pierda voz reconocible en la capa visual, el mismo problema que el Libro entero busca evitar en el texto.

**Por qué está bloqueado**: depende por completo de que exista un proveedor de generación real contra el cual evaluar el prompt — sin D.1 resuelto, D.2 no tiene nada que evaluar.

### D.3 — El fallback: tarjeta editorial tipográfica (DIFERIDO junto con el resto)

Si ni la generación ni el banco de stock producen una imagen aceptable, la regla es análoga a la "escasez honesta" que gobierna la cuota de publicación: **una Pieza sin imagen aceptable no se publica sin imagen ni con relleno genérico — se retiene con motivo explícito** (`RETENIDA: sin_activo_visual`, ya modelado como motivo lateral desde la Porción 3 de esta etapa, ver `EstadoPieza`). Publicar sin imagen destacada es una degradación de producto tan visible como un titular clickbait, y el Libro ya trata eso como línea roja del Corrector Interno.

Un tercer camino, corregido por el propio N4 sobre el diseño original: antes de llegar a `RETENIDA: sin_activo_visual`, una **tarjeta editorial** — imagen tipográfica determinista (titular + paleta del periodista + identidad del sitio), generada localmente, sin proveedor externo y sin riesgo de IP — como alternativa de menor calidad pero cero riesgo, dejando la retención como último recurso, no como único camino cuando D.1/D.2 fallan.

**Por qué está diferido junto con D.1/D.2, aunque técnicamente es independiente**: sin D.1/D.2 construidos, el fallback se activaría siempre — todas las piezas caerían en él (o en `RETENIDA`), lo cual no es el comportamiento que el propietario quiere entregar todavía. Se retoma como parte del mismo paquete para no fragmentar la porción.

## N.2 (derechos de personalidad) y G.2 (`SatiricalArticle`) — diferidos como extensión directa

- **N.2** es una extensión de la Compuerta de Originalidad Visual (D.2, punto de bloqueo de personas reales identificables) — no tiene superficie propia sin D.2.
- **G.2** (declarar `SatiricalArticle` en schema.org para piezas del cronista satírico) es conceptualmente independiente de la imagen, pero el plan aprobado la agrupó en esta porción por pertenecer al mismo módulo (`Seo`) que D.1-D.3 tocan. Cuando se retome, puede pagarse por separado si conviene — no tiene dependencia técnica real de D.1-D.3.

## Diseño de referencia para cuando se retome

1. **Elegir y verificar un proveedor real** (generación de IA o banco de stock con licencia, decisión de propietario) contra su documentación oficial — mismo estándar ya aplicado a OpenRouter/embeddings en esta sesión.
2. Nuevo contrato `Pluma\Proveedores\ProveedorImagenInterface` (análogo a `LenguajeInterface`), con su propio circuit breaker/presupuesto.
3. `Pluma\Compuertas\CompuertaOriginalidadVisual` (o extensión de `CompuertaOriginalidad`) implementando la lista de bloqueo previa (D.2 punto 1).
4. Campo de paleta/estilo visual en `Periodista`/`ConductaVersion` (D.2 punto 3) — mismo patrón de versión de Conducta ya establecido.
5. `Pluma\Seo\GeneradorTarjetaEditorial` (D.3) — determinista, sin proveedor externo, candidato a construirse primero por ser el de menor riesgo.
6. Nuevo motivo `sin_activo_visual` en `RETENIDA` — el estado lateral y el motivo ya existen en el vocabulario de la Sala de Revisión desde la Porción 3 (`EstadoPieza`); falta solo la lógica que lo dispara.
7. `Pluma\Seo\TipoEsquemaArticulo` (ya existe, ver `MotorSeo`) gana el caso `SatiricalArticle` (G.2) — extensión de bajo coste, no depende de 1-6.

## Qué NO se pierde al diferir esto

- Ninguna porción posterior de la Etapa 8 depende de la imagen destacada — el roadmap continúa sin bloqueo con la Porción 9 (Legitimidad del insumo, G.1).
- El estado lateral `RETENIDA` y su vocabulario de motivos ya soportan añadir `sin_activo_visual` sin cambios de esquema cuando se retome.
- La decisión de proveedor de imagen es independiente de la decisión de proveedor de búsqueda web (`PLUMA-E8-1`) — no comparten mecanismo ni bloqueo cruzado.
