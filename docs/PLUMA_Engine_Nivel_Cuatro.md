# PLUMA ENGINE — NIVEL CUATRO
## De sistema que sobrevive al mundo real a medio de comunicación completo: el territorio que ningún documento anterior pisó

*Complemento a Libro v1.0, Nivel Dos v1.0 y Nivel Tres v1.0 — Versión 1.0 · Julio 2026*

---

# PRÓLOGO: EL CAMBIO DE ROL QUE ESTE DOCUMENTO DECLARA

Los tres documentos anteriores comparten, sin decirlo, un mismo rol: el de auditor. El Libro especificó la anatomía; el Nivel Dos la fisiología; el Nivel Tres la inmunología y la supervivencia legal. Cada uno miró lo que ya existía y lo hizo más riguroso. Ese trabajo era necesario y está, en lo esencial, bien hecho. Pero un producto no se construye solo endureciendo lo que ya se pensó: se construye también descubriendo lo que nadie pensó todavía. Este documento cambia de silla — de la del revisor a la del **arquitecto de producto** — y hace la pregunta que ninguno de los tres hizo: *si esto aspira a ser un medio editorial de verdad, ¿qué le falta que ningún pipeline, por perfecto que sea, puede darle?*

La respuesta corta, que la Parte II desarrolla: los tres documentos modelan magníficamente el camino **de la tendencia al post publicado**, y ahí se detienen. Pero un medio real no termina en el post: empieza en él. Un medio tiene historias que duran semanas (no piezas que duran un ciclo), tiene una agenda propia (no solo reflejos ante tendencias ajenas), tiene lectores que vuelven, escriben, corrigen y aportan (no solo sesiones que Google envía), tiene canales propios que nadie le puede quitar (no solo un SERP alquilado), y tiene un negocio con murallas éticas explícitas (no solo costes en una bitácora). Nada de eso existe en los tres documentos anteriores — y es, precisamente, donde se construyen los fosos que el Nivel Dos reclamaba y la resiliencia frente a plataformas que el Nivel Tres exigía.

Este documento cumple además dos deberes metodológicos. Primero: **no asume que los documentos anteriores son correctos.** La Parte I los somete a dictamen pieza por pieza, con dos verificaciones de campo hechas contra fuentes de julio de 2026 que corrigen materialmente afirmaciones del Nivel Tres (una a favor de endurecerlo, otra a favor de matizarlo). Segundo: **cierra el hueco que el propio Nivel Tres predijo de sí mismo** (T.2, autocrítica final: la Regla de Puntuaciones Compuestas de K.3 no fue aplicada a los umbrales que el propio N3 introduce). La Parte I lo cierra; la Parte III aplica esa misma regla a todo lo nuevo que este documento propone, para no repetir el pecado por cuarta generación.

## Verificaciones de campo de este documento (julio 2026)

1. **Artículo 50 del Reglamento (UE) 2024/1689 — confirmado y ENDURECIDO respecto a N3.** Las obligaciones aplican desde el 2 de agosto de 2026 y la excepción para texto existe tal como N3 la cita. Pero las directrices de la Comisión (borrador del 8 de mayo de 2026, y su FAQ oficial) precisan lo que N3 no sabía: la revisión humana que califica para la excepción debe ser **sustantiva** — examen deliberado del fondo por personas con juicio profesional, con autoridad real para aprobar, alterar o rechazar por motivos de fondo, incluida verificación de hechos. Los chequeos superficiales, meramente formales o procedimentales NO cuentan. **Consecuencia de diseño que invalida parcialmente N.3 del Nivel Tres**: el modo Copiloto tal como está diseñado (ventana de veto que puede dejarse expirar sin lectura) probablemente NO califica por sí solo para la excepción cuando la ventana expira sin interacción. La corrección: el sistema debe distinguir, pieza a pieza y en auditoría, entre "aprobada activamente por humano" (califica) y "publicada por expiración de ventana" (tratada, a efectos de marcado, como el modo Autónomo: marcado técnico obligatorio). Esto convierte la ventana de veto en un dato legal, no solo en una conveniencia de flujo.
2. **Google, 15 de mayo de 2026 — confirmado y MATIZADO respecto a N3.** La actualización existe y extiende explícitamente las políticas de spam (incluido el scaled content abuse) a AI Overviews y AI Mode, con democión o eliminación como consecuencias. El matiz: es una clarificación de política documental, no un despliegue de ranking nuevo — no cambia qué es spam, declara que las mismas reglas gobiernan las superficies generativas. Implicación adicional que N3 no recoge: Google y Bing señalaron desde febrero de 2026 que mantener contenido paralelo específico para crawlers de IA constituye cloaking. Toda tentación futura de "optimizar para AI Overviews" con superficies separadas queda prohibida por diseño en PLUMA (se añade a la lista de prohibiciones de GOVERNANCE §1.5).
3. **Sátira bajo el Art. 50**: los deepfakes evidentemente artísticos o satíricos reciben un deber de revelación atenuado, no una exención. Refuerza doblemente G.2 del Nivel Dos: las piezas satíricas necesitan tanto `SatiricalArticle` en el schema como señal visible de sátira en la página — el marcado para máquinas y la señal para humanos son obligaciones distintas y ambas aplican.

---

# PARTE I — DICTAMEN SOBRE EL CANON: QUÉ ENTRA, QUÉ SE CORRIGE, QUÉ SE RECHAZA

*Para que el equipo de desarrollo sepa exactamente qué versión de cada idea es canónica. Formato: veredicto + fundamento solo donde el veredicto no es "aceptar tal cual".*

## I.1 Tabla de veredictos

| Pieza | Origen | Veredicto |
|---|---|---|
| Prompts como DTO versionado + función dial→directriz con tramos y anclas (A.2–A.3) | N2 | **ACEPTAR** — es la pieza que hace implementable el Cap. 5 del Libro |
| Matriz de Combinación de Diales (A.4) | N2 | **ACEPTAR** con su propia mitigación de I.2 (extensión bajo demanda al crear periodistas, no 28 pares a priori) |
| Corpus de regresión de voz (A.5) | N2 | **ACEPTAR ÍNTEGRO, revisando un dictamen previo**: la deriva por embeddings había sido señalada (dictamen del arquitecto sobre N2) como deuda diferible por coste de infraestructura; J.3 del N3 exige esa misma infraestructura de embeddings para la verificación determinista de trazabilidad. Compartida entre ambos usos, su coste marginal para A.5 se vuelve trivial: entra en Etapa 2, no como deuda. Así se corrige un dictamen propio a la luz de información nueva — que es exactamente lo que este canon debe saber hacer. |
| Prioridad de corrección del Corrector (A.6) | N2 | **ACEPTAR** |
| Resolución de disputas + decaimiento + independencia de fuentes (B.1–B.3) | N2 | **ACEPTAR** — B.3 (cadena de citación) es de lo mejor de los tres documentos |
| Detección de hueco estructurada (B.4) | N2 | **ACEPTAR con la corrección O.2 de N3** (prueba de relevancia causal como cuarto paso) |
| Afinidad como puerta + fórmulas con piso (C.1–C.3) | N2 | **ACEPTAR el patrón; los pesos numéricos redistribuidos son defaults de fábrica configurables**, con revisión obligatoria tras 90 días de datos reales (mismo tratamiento que N3-I.2 da a su umbral de modo respeto) |
| Compuerta de Originalidad Visual (D.1–D.2) + derechos de imagen (N.2 de N3) | N2+N3 | **ACEPTAR fusionados** (tres chequeos: IP, procedencia, persona real) |
| Sin imagen → RETENIDA (D.3) | N2 | **CORREGIR**: se añade la **tarjeta editorial** como tercer camino antes de retener — imagen tipográfica de marca generada determinísticamente (titular + paleta del periodista + identidad del sitio), sin proveedor externo, sin riesgo de IP, apropiada para tendencias relámpago sin material visual. Retener queda como último recurso, no segundo. |
| Estados nuevos + memoria colectiva del sitio (E) | N2 | **ACEPTAR** |
| Modo respeto como máquina propia (F) | N2 | **ACEPTAR**, umbral del disparador como configuración con revisión post-evento (según la propia autocrítica I.2 de N2) |
| Naturalidad de señal del Radar (G.1) | N2 | **ACEPTAR** como heurística mínima en Etapa 1 + deuda con hito de profundización (según I.2 de N2), y se conecta con el Capítulo X de este documento (las pistas de lectores como señal humana de contraste) |
| SatiricalArticle (G.2) | N2 | **ACEPTAR y AMPLIAR** por la verificación 3 del prólogo: schema + señal visible en página, ambas de fábrica |
| Independencia epistémica del verificador (J) | N3 | **ACEPTAR** con su propia condición de T.2: hipótesis a validar en Piloto ANTES de convertirse en bloqueo duro del Autónomo; la capa determinista J.3 sí entra firme desde Etapa 2 |
| K.1–K.3 (pisos en selección de ángulo y Compuerta de Calidad + Regla de Puntuaciones Compuestas) | N3 | **ACEPTAR ÍNTEGRO** — K.2 es el hallazgo individual más valioso de los tres documentos: una compuerta que promedia no es una compuerta |
| Procedencia del declarante + evidencia audiovisual (L) | N3 | **ACEPTAR** |
| Derecho de réplica en tres niveles (M) | N3 | **ACEPTAR**; la tensión M.3-contra-velocidad se resuelve por política declarada, no por algoritmo: piezas de acusación grave pierden derecho a la vía rápida, y punto |
| Perfil de jurisdicción (N.1) | N3 | **ACEPTAR** |
| Excepción del Art. 50 por modos (N.3) | N3 | **CORREGIR según la verificación 1 del prólogo**: la línea no pasa entre Copiloto y Autónomo; pasa entre "aprobación humana sustantiva registrada" y todo lo demás. El marcado técnico es obligatorio en Autónomo Y en toda pieza de Copiloto publicada por expiración de ventana. El registro de auditoría debe capturar el tipo de aprobación como dato de primera clase. |
| Falseabilidad como Fase 3.5 (O) | N3 | **ACEPTAR** con su propia salvaguarda anti-falso-equilibrio como hito de calibración en Piloto |
| Homeostasis a escala (P.1–P.3) | N3 | **ACEPTAR** — P.2 (fatiga de alerta) es la pieza más original del N3 |
| locale_editorial (Q) | N3 | **ACEPTAR**, campo desde Etapa 2 |
| Economía unitaria (R) | N3 | **ACEPTAR** y AMPLIAR en el Capítulo Y de este documento |

## I.2 Cierre del hueco que N3 predijo de sí mismo

N3-T.2 admite que sus dos umbrales nuevos no declaran su naturaleza según la regla K.3. Se cierra aquí, con la tabla de tres columnas que K.3 exige:

| Puntuación/umbral | ¿Piso o contribuyente? | Qué pasa al incumplir | ¿Piso de fábrica? |
|---|---|---|---|
| Fuerza del contraargumento (O.1) | **Umbral de retorno** (ni piso ni sumando: dispara regreso al Paso 3) | La Pieza reevalúa candidatos con la información adversarial | Configurable, con default de fábrica; hito de calibración en Piloto |
| Entropía estructural (P.1) | **Umbral de alerta** (observabilidad, no compuerta) | Alerta en Sala de Máquinas + rotación de arquitecturas del Repertorio | Configurable sin piso: no bloquea publicación, informa al editor |
| Tasa de detección del editor (P.2) | **Umbral de alerta agregada trimestral** | Métrica de salud en Portada + recomendación de recalibrar | Configurable sin piso |
| Similitud de trazabilidad (J.3) | **Umbral de priorización** (marca `SIN_RESPALDO_APARENTE`, no descarta) | La unidad se eleva al Corrector con señalamiento exacto | Default de fábrica, configurable al alza |

---

# PARTE II — EL TERRITORIO NUEVO: LO QUE UN MEDIO REAL TIENE Y ESTE PRODUCTO AÚN NO

# CAPÍTULO U — LA HISTORIA COMO ENTIDAD DE PRIMERA CLASE

*Los tres documentos giran alrededor de la Pieza. Pero las noticias que importan no son piezas: son historias que evolucionan durante días o semanas. El Libro lo intuye dos veces sin desarrollarlo — la "estrategia de dos golpes" (3.4) y el "historial de cobertura" del periodista (5.4) — y el Nivel Dos añade la memoria colectiva (E.2). Todo eso es memoria interna. Falta la superficie de producto: el lugar donde el LECTOR vive la historia.*

## U.1 La entidad Historia

Nueva entidad `Historia` (tabla `pluma_historias`), por encima de la Pieza: agrupa todas las Piezas de una misma saga (detectada por la huella semántica que el Radar ya calcula en 3.4 para "historia que evoluciona"), con ciclo de vida propio: `ABIERTA → EN_SEGUIMIENTO → INACTIVA → CERRADA` (cierre editorial explícito: "esta historia concluyó", con pieza de cierre opcional). Campos: título de saga, periodista titular (quien la sigue, alimentando la coherencia narrativa que C.2 ya protege), piezas asociadas en orden, y el bloque **"Lo que sabemos / Lo que no sabemos"** — mantenido por el sistema a partir de los estados del expediente (hechos verificados vs. disputados vs. pendientes) y actualizado con cada pieza nueva.

## U.2 El hub de historia como superficie pública

Cada Historia con 2+ piezas genera automáticamente una página hub: cronología navegable de la cobertura, el bloque "lo que sabemos/lo que no", el periodista titular, y suscripción a la historia (ver W.3). Valor triple: para el lector, el mejor formato posible para una saga; para SEO, una página cornerstone que acumula autoridad sobre la keyword de la saga completa (lo que ninguna pieza individual logra) con schema `LiveBlogPosting`/`CollectionPage` según fase; para el negocio, la unidad natural de retención — la razón por la que un lector vuelve mañana.

## U.3 El liveblog para relámpagos de gravedad

Para tendencias relámpago de gravedad alta (elecciones, emergencias, eventos en desarrollo), el formato pieza-cada-90-minutos es estructuralmente inferior al formato que todo medio real usa: la cobertura en vivo. Modo liveblog de una Historia: entradas cortas con sello temporal (cada una con su mini-expediente y sus compuertas — abreviadas pero nunca omitidas: el piso de sustento de K aplica por entrada), encabezado de contexto actualizado, y conversión automática a pieza de síntesis al cerrar el evento. Las compuertas abreviadas del liveblog son un perfil propio declarado según la Regla K.3, no una relajación silenciosa de las normales.

## U.4 La actualización como ciudadana de primera

El Libro trata la actualización como "segunda pieza" (dos golpes). Con la entidad Historia, se formaliza: una Pieza puede ser `tipo: original | actualizacion | correccion | cierre`, las actualizaciones heredan expediente (con re-verificación de hechos en decaimiento, B.3), y el hub las ordena. El schema usa `dateModified` con rigor — la señal de frescura que Google News premia, ahora con soporte estructural real.

---

# CAPÍTULO V — LA AGENDA EDITORIAL: DE MEDIO REFLEJO A MEDIO CON INICIATIVA

*El Radar es 100% reactivo: solo cubre lo que ya es tendencia. Ningún medio serio funciona así — todos mantienen una agenda de lo previsible. La mitad del calendario noticioso se conoce con semanas de anticipación: elecciones, resultados corporativos, lanzamientos, fallos judiciales, eventos deportivos, efemérides. Un sistema que espera a que el evento sea tendencia para empezar a investigar llega estructuralmente tarde a la mitad de las noticias, con el expediente más pobre, compitiendo contra medios que prepararon su cobertura con días de ventaja.*

## V.1 El Calendario Editorial

Nueva superficie y entidad `EventoProgramado`: fecha/ventana esperada, vertical, periodista asignado por adelantado, y estado (`PREVISTO → PREPARADO → EN_CURSO → CUBIERTO`). Fuentes: calendarios estructurados por vertical (económico, electoral, deportivo, de lanzamientos — sensores nuevos del mismo contrato `SensorInterface`), sugerencias del propio sistema (una Historia EN_SEGUIMIENTO con fecha futura conocida genera su evento), y carga manual del editor.

## V.2 La pieza preparada y el paquete de cobertura

Para eventos previstos de peso, el sistema prepara con anticipación: el expediente de contexto se construye ANTES del evento (historia, datos, actores, posturas previas del sitio vía memoria colectiva), y opcionalmente una **previa** publicable ("qué esperar y por qué importa" — pieza legítima por derecho propio) y un **esqueleto condicional** de la crónica ("si sube/si baja/si sorprende") que al ocurrir el evento se completa con los datos reales en minutos, no en los 90 del ciclo reactivo. El paquete completo previa + crónica + análisis del día siguiente es la unidad de cobertura profesional que ningún competidor de auto-publicación ofrece — y las tres piezas se enlazan vía Historia (U).

## V.3 El fondo evergreen

Un medio no vive solo de actualidad: vive del archivo que sigue posicionando años. El sistema reserva una fracción configurable de la capacidad (no de la cuota de actualidad — capacidad aparte, para no competir con el ciclo) para piezas atemporales por vertical: explicadores, guías, "qué es X y por qué importa", mantenidas con revisión programada (el trabajo de mantenimiento del archivo del Capítulo AA). Son las piezas que estabilizan el tráfico cuando el ciclo noticioso baja, el colchón que convierte 100k visitas volátiles en 100k visitas defendibles.

---

# CAPÍTULO W — LA CAPA DE DISTRIBUCIÓN: CANALES QUE NADIE PUEDE QUITARLE AL MEDIO

*Los tres documentos apuestan todo el tráfico a un único canal alquilado: el SERP de Google. El propio Nivel Tres documenta que ese canal se encarece (las AI Overviews reducen clics de forma material). La respuesta de todo medio que sobrevivió a los últimos quince años es la misma: canales propios. El Libro los menciona en una línea de "Después". Aquí se especifican, porque son Etapa de producto, no epílogo.*

## W.1 El boletín como producto del periodista

Newsletter por periodista (no un genérico "boletín del sitio"): la voz que el lector eligió, en su bandeja, con cadencia propia. Se compone automáticamente desde las piezas del periodista con un párrafo de apertura redactado en su voz (mismo pipeline, propósito `boletin`, mismas compuertas de tono), y su lista de suscriptores es EL dato propio del negocio — exportable, del cliente, independiente de toda plataforma (lg-independence aplicado al tráfico). Métricas de apertura y clic alimentan la memoria de audiencia (5.4) con señal más limpia que el SERP.

## W.2 La adaptación por canal, no la republicación

Cada pieza publicada genera derivados por canal con formato nativo: extracto social con gancho en la voz del periodista (propósito `derivado_social`, longitudes por plataforma), imagen social (la tarjeta editorial de I.1 reutilizada en formato social), y metadatos de Discover (imagen grande, titular editorial). Regla de sistema: los derivados JAMÁS contradicen ni exageran la pieza (el anti-clickbait del Corrector aplica a derivados — un derivado clickbait envenena la marca igual que un titular clickbait). La publicación en plataformas puede ser asistida (borradores en cola para aprobación) o directa por canal, con el mismo modelo de modos del Libro.

## W.3 Suscripciones de precisión y notificaciones

El lector puede suscribirse a: un periodista (W.1), una Historia (U.2 — "avísame cuando esta saga avance": la notificación con mayor tasa de retorno que existe en medios), un vertical, o alertas de última hora (solo gravedad alta, con umbral estricto: la notificación devaluada es peor que ninguna). Canales: email transaccional y push web (PWA). Todo opt-in explícito, exportable, del cliente.

## W.4 La edición de audio

Versión en audio de piezas seleccionadas (TTS de calidad, una voz consistente por periodista — con revelación explícita de voz sintética alineada con el marcado del Art. 50, que cubre audio con más severidad que texto y sin excepción editorial). Empaquetable como feed de podcast por vertical. Valor: accesibilidad real, un hábito de consumo nuevo (el lector de camino al trabajo), y presencia en un canal donde la competencia de auto-publicación es cero. Marcado como fase avanzada: entra tras validar demanda con métricas del sitio, no antes.

---

# CAPÍTULO X — LA COMUNIDAD: EL ACTIVO QUE LOS TRES DOCUMENTOS INVITAN Y NINGUNO ADMINISTRA

*El Bloque del Editor (5.7) termina cada pieza incitando a comentar. Ningún capítulo de ningún documento modela qué pasa después: los comentarios de un sitio de noticias con opinión vehemente y sátira son, sin moderación, un pasivo legal (difamación de terceros EN los comentarios, con responsabilidad del medio según jurisdicción), un pasivo de marca (spam, odio) y un desperdicio del dato más valioso que el sistema pide a gritos. Invitar a la conversación sin diseñar la casa donde ocurre es la omisión de producto más incoherente del canon actual.*

## X.1 Compuertas de comentarios

La misma filosofía del Capítulo 8, aplicada a la entrada: clasificación automática de cada comentario (spam / odio-ataque personal / afirmación fáctica riesgosa sobre terceros / crítica legítima / aporte con información). Los dos primeros se filtran; el tercero se retiene para revisión humana (el perfil de jurisdicción de N.1 aplica también aquí — en regímenes severos, el medio responde por lo que aloja); los dos últimos se publican y se destacan. El editor configura el punto de corte; los pisos de fábrica protegen lo innegociable.

## X.2 La respuesta del periodista como sistema

5.7 ya prevé borradores de respuesta aprobables. Se formaliza: los comentarios con `aporte` o `crítica legítima` de mayor engagement generan borrador de respuesta en la voz del periodista (con su memoria: puede referirse a lo que ya defendió), cola de aprobación de un toque en la Sala de Revisión. La pregunta final del Bloque del Editor se convierte además en **widget de encuesta** cuando el dilema es dicotómico — participación de un clic (10–50× la tasa de comentar), resultado visible que alimenta la pieza de seguimiento ("el 72% de nuestros lectores dijo que no lo aceptaría"), y dato de audiencia propio que ningún competidor tiene.

## X.3 El buzón de pistas: los lectores como fuente

La superficie que convierte audiencia en redacción: "¿Sabes algo más sobre esta historia?" en cada hub de Historia (U.2). Toda pista entra a un protocolo estricto: NUNCA directo al expediente — es material de procedencia no verificada por definición (L.1 aplica con máxima severidad), sirve solo como disparador de investigación dirigida del Investigador por los canales normales. Con volumen, esto es un foso genuino (información que solo este medio recibe) y la señal humana de contraste más barata contra la manipulación de tendencias de G.1: una tendencia fabricada por bots no genera pistas de lectores reales.

## X.4 La corrección con crédito

El flujo de rectificación (13.6) gana una puerta de entrada pública: "reportar un error" en cada pieza, con formulario estructurado (qué afirmación, qué evidencia). Verificado el error, la corrección publicada puede acreditar al lector (opt-in). Efecto compuesto: los lectores más exigentes — los que un medio de IA más necesita convencer — se convierten en guardianes del estándar en vez de detractores en redes.

---

# CAPÍTULO Y — EL NEGOCIO EDITORIAL COMPLETO: MURALLAS, EXPERIMENTOS Y LA DECISIÓN DE CRECER

## Y.1 La muralla entre redacción y publicidad, como código

Si el sitio venderá contenido patrocinado o usará afiliados (la realidad de la mayoría de los medios del segmento), la separación iglesia/estado del periodismo clásico se implementa como invariante, no como intención: (a) el contenido patrocinado usa un tipo de pieza propio (`patrocinada`) con etiquetado visible de fábrica no desactivable, schema propio, `sponsored` en enlaces, y JAMÁS lo firma un periodista del banco de noticias — lo firma una identidad comercial separada (la credibilidad de Valentina es el activo; alquilarla lo destruye); (b) los enlaces de afiliado llevan revelación automática en la pieza y atributo correcto, y una pieza de noticias no puede contener afiliados (solo los tipos `guia`/`evergreen` comercial declarado); (c) test de arquitectura: ninguna ruta de código permite a una pieza `patrocinada` entrar al pipeline editorial normal ni viceversa. Esto no es moralina: es la línea que separa un medio vendible de un sitio de granja con columnistas falsos — y es exactamente lo que el scaled content abuse y el site reputation abuse de Google castigan cuando se cruza.

## Y.2 El experimento de titular

Los medios profesionales prueban titulares; PLUMA tiene la infraestructura sin usarla. A/B del **titular editorial** (nunca del title SEO, que queda fijo — dos superficies, dos reglas, como 6.2 ya establece): dos variantes generadas en la voz del periodista, servidas al azar las primeras N horas, la ganadora por CTR interno se consolida. Compuerta anti-clickbait sobre AMBAS variantes (el A/B no es licencia para exagerar). Los resultados alimentan la memoria de audiencia del periodista: su estilo de titular aprende de datos propios.

## Y.3 De la economía unitaria (R de N3) a la decisión de crecimiento

R calcula coste y valor por pieza. La capa que falta es la decisión que ese dato debe informar trimestre a trimestre: el **informe de asignación de capacidad** — qué vertical produce más valor por pieza, qué periodista retiene más audiencia propia (boletín W.1, no solo SERP), dónde una pieza evergreen (V.3) rinde más que tres de actualidad. Presentado como recomendación con evidencia en la Portada, decidido siempre por el editor (P4). Es la diferencia entre un blog que publica y un negocio editorial que asigna capital.

---

# CAPÍTULO Z — LA CONFIANZA COMO SUPERFICIE PÚBLICA

*E-E-A-T, el Art. 50, y la batalla reputacional de un medio sintético convergen en un mismo lugar que ningún documento diseñó: las páginas donde el medio explica qué es y cómo trabaja.*

- **Página de metodología** ("Cómo trabaja esta redacción"): qué es un periodista sintético aquí, cómo se investiga (multifuente, triangulación, derecho de réplica), qué supervisión humana existe por modo, y la declaración de identidad sintética de N.3 con la elegancia del Cap. 10 pero la claridad que la regulación exige. Generada desde la configuración real del sistema — nunca prosa de marketing desincronizada de la operación.
- **Historial público de correcciones**: la página que casi ningún medio se atreve a tener y que más confianza compra. Cada corrección (13.6) listada con fecha y alcance. Para un medio de IA, la transparencia del error es el argumento comercial, no la vergüenza.
- **El expediente resumido opcional por pieza** ("Cómo se hizo esta pieza"): número de fuentes consultadas, fecha de verificación, si se buscó la postura del señalado (M). Un desplegable discreto al pie. Ningún competidor puede copiarlo sin construir todo el pipeline — la trazabilidad de P6 convertida de virtud interna en diferenciador visible.
- **Presencia legítima en superficies de IA**: dado 15-mayo-2026 (verificación 2), la única optimización defendible para AI Overviews es la que ya está diseñada: schema riguroso, autoría real, fuentes citadas, contenido con ganancia de información — y ninguna superficie paralela para crawlers (cloaking). Se documenta como política para resistir la tentación futura.

---

# CAPÍTULO AA — OPERACIONES DE UN MEDIO REAL

- **Copia y restauración del estado editorial completo**: el backup del cliente no es "la base de datos de WordPress" — es banco de periodistas + memorias + Historias + vocabulario + configuración + plantillas de prompt versionadas, exportable e importable como unidad (extiende el export del banco a TODO el estado). Sin esto, la promesa "el activo es del cliente" es incompleta.
- **El sandbox de periodista**: probar cambios de diales contra piezas históricas reales (re-redactarlas con la configuración candidata, comparar con el corpus de voz) SIN publicar nada — el estudio de conducta del Cap. 10 gana su banco de pruebas. Misma infraestructura sirve de **replay del sistema**: re-ejecutar una Pieza histórica contra la versión nueva del pipeline como test de regresión integral pre-release (el smoke test definitivo del sub-agente RELEASE).
- **La gestión del archivo**: trabajo programado sobre lo ya publicado — piezas evergreen con revisión vencida (V.3), piezas de actualidad con datos caducados (banner de contexto temporal: "publicado durante los hechos de..."), redirecciones al consolidar coberturas, y poda propuesta de piezas que nunca posicionaron ni retienen (el contenido muerto diluye la autoridad del dominio: podar es SEO, no pérdida).
- **Continuidad**: los circuit breakers de `pl-proveedor-ia` ganan su capa de producto — panel de estado de dependencias con degradaciones declaradas visibles al editor ("sin proveedor de tendencias: operando con RSS y Agenda") y simulacro de caída como parte de la matriz de release.

---

# PARTE III — PRIORIZACIÓN, DISCIPLINA Y AUTOCRÍTICA

## III.1 Mapeo al PLAN-MAESTRO

El territorio nuevo NO se intercala en las Etapas 1–3 (que ya absorben N2+N3 según I.1 y T.1): se estructura como evolución de las Etapas 4–6 y una nueva Etapa 7, para proteger el camino crítico hacia el primer producto vendible.

| Pieza | Etapa | Nota |
|---|---|---|
| U.1–U.2, U.4 (Historia + hub) | **Etapa 3.5 (nueva, corta)** | Única excepción al criterio anterior: la Historia toca el esquema y el grafo — añadirla tras la Etapa 4 obligaría a migrar; su superficie pública mínima entra aquí, el resto después |
| X.1 (compuertas de comentarios) | **Etapa 4** | Los comentarios llegan con el primer tráfico real; moderarlos no es opcional ni posponible |
| Y.1 (muralla comercial) | **Etapa 4** | Debe existir ANTES del primer cliente que pida contenido patrocinado, no después |
| V.1–V.2 (Agenda + paquete) | **Etapa 5** | Es la evolución natural de "la máquina que aprende": de aprender del pasado a preparar el futuro |
| W.1–W.3 (boletín, derivados, suscripciones) | **Etapa 5** | El canal propio se construye cuando hay contenido y algo de audiencia que retener |
| X.2–X.4, Y.2–Y.3, Z completo | **Etapa 6** | Producto en venta = producto con confianza pública y negocio completo |
| U.3 (liveblog), V.3 (evergreen), W.4 (audio), AA completo | **Etapa 7 (nueva): "El medio completo"** | La versión 2.0 comercial; cada pieza con validación de demanda previa |

## III.2 La Regla K.3 aplicada a lo propio

Toda puntuación o umbral nuevo de este documento, declarado según la disciplina que exigimos a los demás: clasificador de comentarios (X.1) — **pisos de fábrica** para las categorías legales (odio, difamación de terceros), umbral configurable para el resto; umbral de alerta de última hora (W.3) — **piso de fábrica alto** no editable a la baja (la notificación devaluada daña a todos los clientes del canal); ganador del A/B de titular (Y.2) — **contribuyente puro** sin piso (ambas variantes ya pasaron compuertas); recomendaciones de asignación de capacidad (Y.3) y poda de archivo (AA) — **señal direccional**, jamás automática (P4).

## III.3 Autocrítica de este documento

- **El riesgo de este documento es el opuesto al de sus predecesores**: donde N2 y N3 podían pecar de sobre-endurecer lo existente, este puede pecar de ampliar el alcance hasta matar el foco. La mitigación es estructural (III.1): nada del territorio nuevo toca el camino crítico de las Etapas 1–3 salvo la entidad Historia, elegida deliberadamente como única excepción por su coste de migración. Si el propietario debe recortar, el orden de sacrificio es el inverso de la numeración: Etapa 7 completa antes que una sola pieza de la 4.
- **El buzón de pistas (X.3) y la encuesta (X.2)** introducen datos personales de lectores donde antes solo había contenido: la superficie RGPD/LOPD del producto crece materialmente (consentimiento, retención, exportación, borrado). Este documento lo señala y lo asigna (Etapa 6, junto a Z) pero no lo especifica — es deuda declarada con etapa de pago, y requiere revisión legal humana, no solo ingeniería.
- **La tarjeta editorial (I.1/D.3)** resuelve el caso común pero introduce un riesgo propio no validado: si la mayoría de las piezas relámpago salen con tarjeta tipográfica, la portada del sitio puede adquirir la monotonía visual que D.1 combatía. Necesita un umbral de proporción observado en Piloto (¿qué % de tarjetas tolera la portada antes de verse pobre?) — hipótesis a medir, no constante a decretar.
- **Sobre el método**: este documento tampoco fue auditado por una pasada posterior. Su hueco más probable, siguiendo el patrón de la saga: las entidades nuevas (Historia, EventoProgramado, suscriptor, pista, comentario clasificado) multiplican el modelo de datos del Capítulo 11 del Libro sin que nadie haya re-verificado los límites de rendimiento del Capítulo 12 contra el nuevo total. Un Nivel Cinco honesto empezaría por ahí.

# EPÍLOGO: LA QUINTA VERDAD

El Libro dijo que el criterio es el producto. El Nivel Dos, que el criterio necesita mecanismo. El Nivel Tres, que el mecanismo necesita dudar de sí mismo. La quinta verdad es la que un arquitecto de producto añade a las cuatro anteriores: **un medio no es un pipeline con lectores al final — es una relación con lectores, y el pipeline es solo cómo se la honra cada día.** Las historias que se siguen, la agenda que se anticipa, el boletín que se espera, la pista que se confía, la corrección que se agradece: nada de eso sale de una tendencia de Google, y todo eso es lo que queda cuando Google cambia las reglas — que es, como los tres documentos anteriores demostraron con fechas y fuentes, lo único que Google hace con total fiabilidad. El producto que este canon describe ya sabe pensar como un periodista. Con esta capa, aprende lo segundo que todo periodista sabe: que el oficio no es publicar — es que te vuelvan a leer mañana.

*— Fin del Nivel Cuatro —*
