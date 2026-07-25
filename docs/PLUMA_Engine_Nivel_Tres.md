# PLUMA ENGINE — NIVEL TRES
## De mecanismo de razonamiento a sistema que sobrevive el mundo real: robustez epistémica, responsabilidad legal y homeostasis a escala

*Complemento crítico a `PLUMA_Engine_Libro_de_Arquitectura.md` v1.0 y `PLUMA_Engine_Nivel_Dos.md` v1.0 — Versión 1.0*

---

# PRÓLOGO: LA PREGUNTA QUE QUEDA DESPUÉS DE LA FISIOLOGÍA

El Libro de Arquitectura resolvió qué debe existir. El Nivel Dos resolvió, con un rigor poco común en este tipo de documento, cómo decide cada pieza de esa anatomía: escribió la aritmética que faltaba en el Radar y en la asignación de periodistas (C.1–C.3), la función de traducción entre un dial numérico y una instrucción de redacción verificable (A.3–A.4), el algoritmo de resolución de disputas entre fuentes (B.2) y tres casos trabajados que demuestran que el mecanismo, ensamblado, efectivamente piensa (Capítulo H). Es, en su género, un documento excepcional: pocas veces una segunda pasada sobre una especificación de producto encuentra tantos huecos reales y los cierra con fórmulas ejecutables en lugar de más prosa.

Pero el propio Nivel Dos, en su autocrítica final (I.2), admite algo que merece tomarse en serio en vez de darlo por saldado: dice que su Matriz de Combinación de Diales "no es exhaustiva ni pretende serlo", que el disparador del modo respeto "es un punto de partida razonado, no un número probado", y que el modelo de amenaza del Radar "es, de las diez piezas de este documento, la que exige más investigación". Son tres heridas declaradas abiertamente — exactamente lo que `CLAUDE.md` exige (Santo Grial §1, cero omisión: declarar antes de que el revisor lo descubra). Este documento no repite ese ejercicio de humildad de boquilla: lo ejecuta. Y antes de abrir terreno nuevo, hace algo que ninguno de los dos documentos anteriores se hizo a sí mismo: auditar al Nivel Dos con la misma dureza con la que él auditó al Libro. Un documento que exige mecanismo en vez de intención y luego resulta tener, dentro de su propio mecanismo, el mismo defecto que señaló en otro lugar sin corregirlo ahí también, incurre exactamente en la falla que él mismo describe como la más peligrosa: el placeholder que no dice `TODO`, el texto razonable que nadie audita (A.1). Ese es el primer hallazgo de este documento (Capítulo K), y es el que mejor ejemplifica su método: no una crítica inventada desde fuera, sino la aplicación consistente de una crítica que el propio proyecto ya se hizo a sí mismo en dos lugares, extendida a los dos lugares donde el mismo error sigue vivo, textual, sin tocar.

El resto de este documento persigue preguntas que ni el Libro ni el Nivel Dos formulan, porque ambos —con toda razón, dado lo que ya tenían que resolver— tratan el sistema como un mecanismo de una sola pasada: se detecta, se investiga, se redacta, se publica. Ninguno de los dos pregunta qué pasa cuando ese mecanismo se ejecuta ocho veces al día, todos los días, durante dos años, contra un entorno que reacciona: reguladores que cambian las reglas de divulgación de contenido sintético a mitad de camino, un Google que en 2026 ya extendió sus políticas de spam a sus propias respuestas generativas, periodistas sintéticos con año y medio de historial cuya voz nadie ha vuelto a auditar desde el Piloto, y un editor humano que en el mes dieciocho ya no lee el diagnóstico de la Sala de Revisión con la misma atención que en la semana uno. Esa pregunta — no "¿decide bien el sistema en una pieza?" sino "¿sigue decidiendo bien el sistema el día 600?" — se responde en el Capítulo P, junto con tres preguntas de arquitectura que ninguno de los dos documentos anteriores tocó en absoluto: si dos pasadas del mismo modelo son en realidad dos opiniones independientes o una sola opinión con dos disfraces (Capítulo J); si un sistema obsesivo con la trazabilidad puede llamarse periodismo de verdad mientras nunca intenta oír a la persona señalada antes de publicar (Capítulo M); y si la pregunta más cara de todo el producto —¿publico esta pieza más hoy?— puede seguir siendo una decisión puramente editorial cuando el propio sistema ya tiene, en su bitácora, todos los datos para saber si esa pieza adicional cuesta más de lo que probablemente va a producir (Capítulo R).

Una nota de honestidad idéntica a la que el Nivel Dos exigió de sí mismo: las afirmaciones de este documento sobre el estado de las políticas de Google y del Reglamento de IA de la Unión Europea fueron verificadas contra fuentes de julio de 2026, no recordadas de memoria de entrenamiento. Dos hechos verificados aquí no estaban disponibles cuando se escribió el Nivel Dos y cambian de forma material el análisis de riesgo del Capítulo N: primero, Google extendió formalmente el 15 de mayo de 2026 sus políticas de spam a sus propias respuestas generativas (AI Overviews y AI Mode) — lo que significa que el argumento de G.2 sobre `SatiricalArticle` ya no es una previsión razonable, es una obligación de cumplimiento contra una política nombrada y activa. Segundo, las obligaciones de transparencia del Artículo 50 del Reglamento de IA de la Unión Europea (Reglamento (UE) 2024/1689) entran en vigor el 2 de agosto de 2026 — mientras este documento se escribe — con una excepción textual que cambia el cálculo de riesgo de los tres modos de operación del Libro de una forma que ni el Libro ni el Nivel Dos podían anticipar. El Capítulo N lo desarrolla en detalle.

## Mapa de este documento

| Capítulo | Pregunta que responde |
|---|---|
| J | ¿Es el Corrector Interno una segunda opinión real, o el mismo juicio con otro disfraz? |
| K | ¿Dónde sigue viva, sin corregir, la fórmula-que-no-es-fórmula que el propio Nivel Dos ya diagnosticó en otro lugar? |
| L | ¿Verifica el sistema que una cita es de quien dice ser, y que un video viral es lo que aparenta? |
| M | ¿Intenta el sistema oír a la persona señalada antes de publicar, no solo corregirla después? |
| N | ¿Sobrevive el modelo de riesgo legal a la diversidad de jurisdicciones y a la regulación de IA que entra en vigor este mismo año? |
| O | ¿Alguna vez el sistema intenta derrotar su propia tesis antes de publicarla? |
| P | ¿Sigue decidiendo bien el sistema el día 600, con el mismo periodista, el mismo editor y un modelo que ha cambiado en silencio? |
| Q | ¿Sobrevive "la voz del periodista" a venderse en más de un país y más de un idioma? |
| R | ¿Sabe el sistema si la pieza marginal de hoy vale lo que cuesta? |
| S | Dos casos trabajados que prueban J, K, M y O funcionando juntos. |
| T | Qué implementar primero, y la autocrítica que este documento se debe a sí mismo. |

---

# CAPÍTULO J — LA GRIETA DE INDEPENDENCIA EPISTÉMICA: POR QUÉ EL CORRECTOR INTERNO, TAL COMO ESTÁ ESPECIFICADO, NO ES NECESARIAMENTE UNA SEGUNDA OPINIÓN

*5.6 del Libro dice que la segunda pasada —el Corrector Interno— "es un agente distinto con un solo trabajo". El Nivel Dos (A.5) profundiza el corpus de regresión de voz que audita a ese Corrector, pero ninguno de los dos documentos define qué significa "distinto". Y en la arquitectura tal como está descrita, "distinto" puede significar, sin que nadie lo note hasta que falle, exactamente lo mismo con otro nombre de propósito en la misma llamada.*

## J.1 El supuesto que nadie verificó: enrutar por coste no es enrutar por independencia

12.3 del Libro dice: "El Proveedor de Lenguaje abstracto permite enrutar tareas a modelos distintos por coste: clasificar con un modelo económico, redactar con el mejor." El criterio de enrutamiento entre modelos es el coste, no la independencia epistémica. Nada en el diseño actual impide —y el enrutamiento por coste de hecho invita a— que Redactor y Corrector, ambas tareas "de calidad" premium en la matriz de `pl-proveedor-ia`, resuelvan al mismo modelo, del mismo proveedor, en la misma familia de pesos. `LenguajeInterface::completar()` distingue propósitos (`redactar|corregir|...`), no procedencias: un desarrollador que implemente exactamente lo que el Libro describe puede, con total corrección respecto al texto del Libro, construir un Corrector que es, en la práctica, el Redactor mirándose al espejo.

Esto importa porque el fenómeno que hace valiosa una "segunda opinión" en cualquier proceso de verificación humano —dos personas con formación, sesgos y ángulos ciegos distintos revisando lo mismo— no existe cuando ambas pasadas comparten el mismo modelo subyacente. Un modelo de lenguaje evaluando un texto que él mismo (o una instancia estadísticamente casi idéntica de sí mismo) habría producido con alta probabilidad, tiende a no marcar como sospechoso precisamente lo que su propia distribución de probabilidad considera plausible — porque esa plausibilidad es, exactamente, lo que llevó a producir la afirmación en primer lugar. El Corrector Interno de 5.6 verifica seis puntos; los puntos 2 al 6 (proporción interpretativa, solapamiento n-grama, voz, titular, matriz de tono) son verificaciones estructurales que un modelo detecta razonablemente bien incluso sobre su propio texto, porque son propiedades de superficie. El **punto 1** —¿cada hecho del texto existe en el expediente con el estado correcto?— es una verificación semántica de fondo, y es precisamente ahí donde la falta de independencia real pesa más: es el punto que GOVERNANCE §2.4 declara como la regla de oro anti-alucinación, y es el punto con mayor probabilidad de ser aprobado sin fricción por un verificador que comparte los sesgos de generación del redactor.

## J.2 La corrección: un contrato de independencia mínima que escala con la autonomía

No toda pieza necesita el mismo nivel de independencia entre quien redacta y quien verifica — igual que no toda pieza necesita el mismo modo de publicación (2.4). La corrección correcta ata el requisito de independencia a la misma escalera de autonomía que el Libro ya diseñó, en vez de imponer un coste fijo a todo el sistema:

| Modo | Requisito de independencia del punto 1 (hechos) |
|---|---|
| Piloto | Mismo proveedor aceptable — el humano es el verificador real; el Corrector automatizado es una ayuda, no la última línea de defensa. |
| Copiloto | Recomendado un verificador de familia de modelo distinta a la del redactor, específicamente para el punto 1 (no para los seis puntos completos — es el paso más caro de fallar, no el más caro de ejecutar dos veces). |
| Autónomo | **Obligatorio.** El punto 1 del Corrector debe resolverse mediante un proveedor de familia de modelo declarada como distinta a la del redactor. El sistema verifica esto en tiempo de configuración —no en tiempo de ejecución de cada pieza— y si ambos roles resuelven a la misma familia, **el modo Autónomo no se activa** y se notifica el motivo exacto al editor. |

Esto exige una extensión concreta al contrato de `Pluma\Proveedores` (`pl-proveedor-ia`): `LenguajeInterface` debe exponer, además del proveedor, metadata de **familia de modelo** — porque dos productos nominalmente distintos del mismo proveedor pueden compartir en mayor medida los sesgos de una misma familia de entrenamiento que dos familias de orígenes genuinamente distintos. La independencia de proveedor comercial no es lo mismo que independencia de familia de modelo, y el contrato debe distinguir ambas cosas explícitamente, no asumir que "otro proveedor" ya resuelve el problema.

## J.3 Por qué la independencia de modelo no basta por sí sola: la capa determinista de verificación de trazabilidad

Incluso dos familias de modelo genuinamente distintas, entrenadas sobre corpus de la web ampliamente solapados, pueden compartir sesgos correlacionados sobre hechos que están mal reportados de forma consistente en la propia web pública — el caso, ya documentado en la práctica de sistemas de verificación automática, de errores que se propagan porque múltiples modelos aprendieron de las mismas fuentes erróneas. La independencia de proveedor reduce la correlación de fallo; no la elimina. Solo una capa **no generativa** la rompe de verdad, y ninguno de los dos documentos anteriores contempla una.

**Mecanismo (nuevo, capa determinista previa a cualquier pasada generativa de corrección):**

1. Segmentar el borrador en unidades factuales atómicas (oraciones o cláusulas con una afirmación verificable cada una) — tarea de extracción estructurada, no de juicio, y por tanto fiable con un modelo de lenguaje o con reglas sintácticas simples.
2. Para cada unidad, calcular la similitud de embeddings contra cada extracto del expediente. Esto reutiliza exactamente la misma infraestructura que A.5 ya introduce para medir deriva semántica de voz — aquí, con un propósito distinto: no medir si el periodista sigue sonando igual, sino si cada afirmación tiene un ancla real en el material recolectado.
3. Toda unidad cuya similitud máxima cae bajo un umbral configurable se marca `SIN_RESPALDO_APARENTE`. No se descarta automáticamente (los embeddings producen falsos positivos ante paráfrasis legítima) — se prioriza al Corrector Interno con la unidad exacta señalada, en vez de la instrucción abierta "revisa que todo esté respaldado", que es precisamente el tipo de instrucción difusa que A.3 ya identificó como poco operable cuando se trata de diales, y que aplica con la misma fuerza a instrucciones de verificación.
4. Esta capa no sustituye la verificación semántica real del punto 1 (GOVERNANCE §2.4 sigue exigiéndola) — la hace más barata de ejecutar bien y más difícil de burlar: una función de similitud no tiene incentivo de racionalizar una alucinación como plausible; un modelo generativo evaluando su propio estilo de razonamiento, sí puede tenerlo, sin proponérselo.

## J.4 Extensión de gobernanza

Se propone un nuevo invariante en `GOVERNANCE.md` §2 (política editorial como código): *2.8 — El modo Autónomo exige verificador de familia de modelo distinta a la de redacción para el punto 1 del Corrector Interno, más una capa de verificación determinista de trazabilidad (similitud de embeddings unidad-a-unidad contra el expediente) ejecutada antes de cualquier pasada generativa de corrección. Test de arquitectura obligatorio: intentar activar Autónomo con `verificador_provider == redactor_provider` (misma familia) debe fallar de forma explícita, nunca degradar en silencio.*

---

# CAPÍTULO K — EL PATRÓN DE FÓRMULA NO COMPLETADO Y LA REGLA DE DISEÑO QUE LO HABRÍA PREVENIDO

*El Nivel Dos, en su Capítulo C, identificó con precisión un defecto real: una tabla de pesos que suma factores de naturaleza distinta —elegibilidad y prioridad— produce resultados absurdos, y lo corrigió en dos lugares (la Puntuación de Oportunidad del Radar, C.1, y la asignación de periodista, C.2–C.3). El diagnóstico es correcto y la corrección es correcta. El problema es que el mismo patrón, textualmente, sigue vivo en otros dos lugares del propio Libro, y ninguno de los nueve capítulos del Nivel Dos lo señala — a pesar de que uno de esos dos lugares es, ni más ni menos, el sistema inmunológico completo del producto.*

## K.1 El Paso 3 del Algoritmo de Decisión Editorial nunca recibió la corrección de C.1

5.5 Paso 3 dice: el algoritmo puntúa los 3–5 candidatos de tesis por "originalidad frente a la cobertura existente, compatibilidad con la línea editorial del periodista, sustento en hechos verificados (**una tesis sin datos que la respalden se descarta**), y potencial de conversación." El propio Libro, en esa misma frase, ya reconoce implícitamente que "sustento" no es un sumando: usa el verbo "se descarta" — lenguaje de piso eliminatorio, no de puntuación ponderada. Pero nunca lo convierte en regla explícita de arquitectura, y sin esa conversión, un desarrollador que implemente el texto tal como está escrito construye, con altísima probabilidad, una suma ponderada de cuatro factores donde "sustento" pesa, por ejemplo, un 25% — reproduciendo exactamente el fallo que C.1 corrigió para el Radar: una tesis con sustento bajo pero muy alta en "potencial de conversación" (la más polémica, no la más sólida) puede ganar frente a candidatos mejor sustentados. Para un producto cuyo Principio P1 declara "el criterio es el producto", este es el punto exacto donde el criterio se vende por potencial viral si la fórmula no lo impide por diseño — y es, además, el paso más importante de todo el pipeline: el que decide qué va a defender la pieza.

**Corrección, aplicando literalmente el mismo patrón que C.1 ya estableció:**

```
elegible(candidato) = sustento_en_hechos_verificados ≥ umbral_sustento_minimo   // piso binario, no sumando
puntuación(candidato) = elegible
    ? (0.40×originalidad + 0.35×compatibilidad_linea_editorial + 0.25×potencial_conversacion)
    : DESCARTADO
```

Con `umbral_sustento_minimo` configurable, pero con un piso de fábrica no editable a la baja — mismo estatus que los pisos de las compuertas del Capítulo 8. Si los 3–5 candidatos generados fallan todos el piso de sustento, el sistema no relaja el piso para tener "algo que redactar": la Pieza vuelve al Investigador con una señal explícita ("el expediente no sustenta ninguna tesis candidata; ampliar investigación o descartar") — la misma escasez honesta que el Libro ya aplica a la cuota diaria y que C.3 ya extendió a la asignación de periodista, ahora completada en el paso donde más falta hacía.

## K.2 La Compuerta de Calidad: el hallazgo más grave de este documento

8.1 dice: "Puntuación compuesta (0–100) sobre: cumplimiento de proporción interpretativa, densidad de sustento (afirmaciones con respaldo del expediente), legibilidad..., presencia de la voz..., y estructura completa.... Umbral configurable; por debajo, RETENIDA." Esta es, de las dos correcciones de este capítulo, la más seria, porque no vive en un algoritmo secundario: vive en el propio "sistema inmunológico del producto" (así lo llama el Libro al abrir el Capítulo 8). Si "densidad de sustento" es un quinto de un promedio junto con "legibilidad" y "presencia de la voz", entonces una pieza con anti-alucinación mediocre pero prosa fluida y voz reconocible **puede promediar por encima del umbral y salir publicada**. Es decir: la Compuerta de Calidad, tal como está redactada, puede aprobar exactamente el tipo de pieza —bien escrita, mal sustentada— que GOVERNANCE §2.4 existe para impedir. Una compuerta de calidad que puede compensar anti-alucinación con buena prosa no es un sistema inmunológico: es un promedio con buena ortografía.

**Corrección, con la misma disciplina piso-versus-suma:**

```
piso_sustento    = densidad_de_sustento ≥ umbral_sustento_minimo_calidad   // NO compensable
piso_estructura  = estructura_completa == verdadero                        // gancho+tesis+contraargumento+bloque, binario
elegible         = piso_sustento AND piso_estructura

puntuación_calidad = elegible
    ? (0.40×proporcion_interpretativa + 0.35×legibilidad + 0.25×presencia_de_voz)
    : 0   // → RETENIDA, sin excepción de umbral configurable para estos dos pisos
```

La densidad de sustento y la estructura completa dejan de ser "un quinto del promedio" y pasan a ser condiciones de entrada, no componentes de él — el mismo movimiento que C.1 hizo con la afinidad del Radar, aplicado ahora donde más protección necesitaba y menos la tenía.

## K.3 La regla que debió declararse una sola vez, no descubrirse cuatro veces

El patrón se repite: Radar (3.3, corregido en C.1), asignación de periodista (5.5 Paso 2, corregido en C.2–C.3), selección de ángulo (5.5 Paso 3, corregido en K.1) y Compuerta de Calidad (8.1, corregida en K.2) — cuatro puntuaciones compuestas del sistema, el mismo error de diseño en las cuatro, corregido de forma ad hoc cada vez que alguien lo encuentra. Eso no es un patrón que se agote en cuatro correcciones: es la señal de que falta una regla de diseño general que impida que la quinta puntuación compuesta —la que alguien va a inventar en la Etapa 5 o 6, por ejemplo un futuro "candidato a pieza de refuerzo" del bucle SEO (6.4)— repita el mismo error por quinta vez.

**Regla de Puntuaciones Compuestas** (nueva, candidata a `GOVERNANCE.md` §1.6 y a un registro vivo `docs/puntuaciones.md`): toda puntuación compuesta del sistema —presente o futura, en cualquier capa `Pluma\*`— debe declarar, para cada factor que la compone, tres cosas antes de poder implementarse:

1. **¿Es un piso eliminatorio o un contribuyente ponderado?**
2. **Si es piso: ¿cuál es el umbral, y qué ocurre exactamente por debajo de él** (RETENIDA, DESCARTADO, no elegible, regreso a etapa anterior)?
3. **¿El piso tiene un valor de fábrica no editable a la baja, o es enteramente configurable por el cliente?**

Un test de arquitectura verifica que esta tabla de tres columnas exista y esté completa antes de aceptar cualquier función de puntuación nueva en el código. No es burocracia añadida: es la única forma de que este error de diseño, ya encontrado cuatro veces en dos documentos distintos, no aparezca una quinta vez sin que nadie lo note hasta producción.

---

# CAPÍTULO L — VERIFICACIÓN DE PROCEDENCIA: LA IDENTIDAD DEL DECLARANTE Y LA AUTENTICIDAD AUDIOVISUAL

*4.2–4.3 del Libro y B.1–B.4 del Nivel Dos resuelven, con detalle real, cómo triangular HECHOS entre fuentes. Ninguno de los dos resuelve un problema distinto y anterior en la cadena: si la fuente de un hecho es una declaración textual atribuida a una persona u organización, el protocolo actual triangula que la declaración circula — nunca verifica que provenga genuinamente de quien dice haberla emitido. Y si la tendencia origen es un video o una imagen, no un texto, el protocolo entero es ciego a esa clase de evidencia.*

## L.1 El vector de desinformación más común no es la contradicción entre fuentes: es la cita falsa

4.3 dice: "hechos afirmados por 2+ fuentes independientes se marcan como verificados". Esto resuelve bien la triangulación de hechos entre coberturas — pero una captura de pantalla de una declaración atribuida a un funcionario, un ejecutivo o una figura pública puede circular y ser recogida por 2, 3 o 5 medios de nivel B simultáneamente sin que ninguno haya verificado que la cuenta de origen es genuina, que la captura no está editada, o que la cuenta no es una parodia o una suplantación — y el factor de independencia de B.3 (¿estas fuentes citan de forma independiente, o todas citan a la misma captura viral?) no resuelve esto: todas pueden ser genuinamente independientes entre sí y, aun así, todas estar citando la misma cita falsa en su origen. Dado que 3.2 incluye explícitamente "señal social (X/Reddit)" como fuente primaria del Radar, este no es un caso de esquina: es un vector que entra por el canal de mayor volumen del propio sistema.

**Protocolo de Verificación de Procedencia de la Declaración** (nuevo, Fase 2.5 del protocolo de investigación de 4.2, entre "recolectar coberturas secundarias" y "triangular"):

1. Para toda declaración textual atribuida a una persona u organización identificable que el expediente vaya a citar como fuente de un hecho —no para hechos generales, solo para citas directas atribuidas, el caso de mayor riesgo reputacional y legal si resulta falsa—, verificar si proviene de un canal verificado u oficial (cuenta verificada de la propia persona/organización, comunicado en dominio propio, o un medio de nivel A que confirma explícitamente haberla obtenido directamente de la fuente).
2. Si no hay verificación de canal: estado `procedencia_no_verificada`. La declaración puede entrar al expediente como material de contexto, pero **nunca** como hecho atribuido citable en la pieza sin marca explícita de incertidumbre de procedencia, y activa automáticamente la Compuerta de Riesgo (8.2) con la misma severidad que una afirmación sin doble fuente.
3. Este eje es independiente del nivel de confianza del medio (4.3, niveles A/B/C): un medio de nivel A puede reportar de buena fe una declaración de procedencia no verificada ("un tuit viral atribuido a..."). El nivel de confianza del medio que la reporta no blanquea la procedencia de la cita original — ambos ejes se registran por separado en el expediente.

## L.2 Evidencia audiovisual: el Investigador es hoy ciego a la clase de trend más viral

Una fracción creciente de tendencias virales origina en video o imagen, no en texto o comunicado — y el protocolo entero del Investigador (4.2) está construido alrededor de "extractos" textuales con sello temporal. No hay ningún campo del expediente pensado para registrar si un clip viral fue corroborado independientemente (geolocalización de terceros, reportes locales del lugar y fecha reivindicados) o si ha sido señalado como manipulado o descontextualizado por verificadores reconocidos.

PLUMA no necesita construir su propio detector de manipulación audiovisual —es, como bien reconoce G.1 del Nivel Dos para la manipulación de tendencias, un problema de investigación activa en la industria, no un algoritmo que este documento pueda especificar con precisión aritmética—, pero sí necesita una regla de sistema: **ninguna Pieza cuya tendencia origen sea audiovisual puede alcanzar `EN_REDACCION` sin que el expediente registre explícitamente el resultado de este chequeo**, aunque el resultado sea simplemente "sin corroboración independiente encontrada, tratar como no verificado". Hoy esta ausencia no es "sin datos": es un campo que ni el Libro ni el Nivel Dos definieron, así que ningún desarrollador sabría siquiera que debe existir. La consecuencia se propaga sola una vez que el campo existe: un hecho audiovisual sin corroboración nunca puede alcanzar el estado `verificado` (queda `atribuido`, como máximo), lo que ya activa aguas abajo tanto la Compuerta de Riesgo como el piso de sustento de K.1 — sin necesitar ninguna regla nueva de compuerta, solo el dato que hoy no se registra.

---

# CAPÍTULO M — EL DERECHO DE RÉPLICA PREVIA: LA AUSENCIA MÁS GRAVE DE TODO EL SISTEMA DE COMPUERTAS

*El Capítulo 13 del Libro (Gobernanza Editorial) tiene un "derecho de rectificación de primera clase" — pero es posterior a la publicación: corregir un error ya publicado. El periodismo profesional distingue dos derechos distintos, y ni el Libro ni el Nivel Dos modelan el primero, que es el más antiguo de los dos: la obligación de intentar oír a la persona señalada ANTES de publicar una afirmación negativa sobre ella. GOVERNANCE §2.3 exige doble fuente verificada para ese tipo de afirmación — pero verificar que un hecho negativo es cierto no es lo mismo que haberle dado a la persona señalada la oportunidad de responder antes de publicarlo. Son dos protecciones contra dos riesgos distintos.*

## M.1 Por qué "verificado" y "con derecho a réplica" no son la misma garantía

La primera protege contra publicar algo falso. La segunda protege contra publicar algo cierto, mostrado sin la explicación de la parte señalada — que en la práctica de responsabilidad civil y penal de casi cualquier jurisdicción, y en la práctica reputacional en todas, es tan dañino como un hecho falso, y es, simplemente, la diferencia entre periodismo y un extracto de acusación con firma editorial encima. Un sistema obsesivo con la trazabilidad (P6) que nunca pregunta "¿intentamos oír al otro lado?" tiene una laguna exactamente en el lugar donde el periodismo profesional pone su regla más antigua.

## M.2 Tres niveles, la misma escalera de autonomía que ya organiza el resto del producto

**Nivel 1 — Verificación de postura ya existente en el expediente** (mínimo viable, Etapa 1–2): antes de que una Pieza con afirmación fáctica negativa sobre persona u organización identificable pueda salir de `EN_REDACCION`, el Investigador debe verificar explícitamente si alguna de las 4–8 coberturas ya recolectadas (4.2) incluye una declaración, negación, o un "contactado para comentar, no respondió" de la parte señalada. Esto no exige contacto nuevo: exige que el sistema **busque activamente** esa postura en lo que ya tiene, en vez de omitirla porque ninguna fuente secundaria la resaltó por su cuenta. Si no existe en ninguna fuente: nuevo campo de expediente `postura_del_senalado: ausente` — motivo de retención **independiente** en la Compuerta de Riesgo, distinto de "sin doble fuente", porque un hecho puede estar perfectamente verificado por dos fuentes y aun así publicarse sin que la parte señalada haya tenido nunca oportunidad de responder en ningún medio consultado.

**Nivel 2 — Búsqueda dirigida de postura** (Etapa 3, junto a las compuertas): si ninguna fuente recolectada incluye la postura del señalado, el Investigador activa una búsqueda adicional específicamente para localizarla (declaraciones en otros contextos, comunicados propios, entrevistas previas sobre el tema) — el mismo patrón de presupuesto acotado que B.2 ya estableció para la tercera fuente en contradicciones de ocurrencia.

**Nivel 3 — Contacto directo automatizado con ventana de espera obligatoria** (Etapa 5, "la máquina que aprende"): para piezas de gravedad/polaridad alta (5.5 Paso 1) sobre personas u organizaciones con canal de contacto público conocido, el sistema puede generar (nunca enviar de forma autónoma — límite de diseño deliberado) una solicitud de comentario que el editor humano aprueba con un clic, con una ventana mínima de espera antes de que la Pieza pueda avanzar más allá de `EN_REVISION`, incluso en modo Autónomo para todo lo demás. Sin respuesta al cierre de la ventana, la pieza se publica con la mención explícita "contactado para comentar antes de esta publicación, sin respuesta al cierre de esta edición" — la fórmula estándar del periodismo profesional, y una señal de rigor que ninguna otra compuerta del sistema puede fabricar por sí sola.

## M.3 Una tensión que este documento no resuelve por decreto, y no debería

El Nivel 3 es estructuralmente incompatible, para las piezas donde aplica, con el objetivo de "tendencia→publicación < 90 minutos" de la tabla de métricas (1.4) — y eso es correcto, no un defecto a resolver con más ingeniería: existen categorías de pieza (acusación grave contra persona identificable) donde el rigor debe ganarle a la velocidad, exactamente el mismo principio que ya rige la degradación por sensibilidad (2.4), aplicado aquí a un eje —el derecho de réplica previa— que ninguno de los dos documentos anteriores nombró.

---

# CAPÍTULO N — EL RIESGO LEGAL NO MODELADO: JURISDICCIÓN, DERECHOS DE PERSONALIDAD Y LA REVELACIÓN DE PERSONA SINTÉTICA

*El Capítulo 12 (Seguridad) y GOVERNANCE §2.3 tratan "riesgo de difamación" como un estándar universal: doble fuente verificada, o retención. El propio Libro ya sabe que la jurisdicción importa —8.2 menciona "detección de temas legalmente regulados (según jurisdicción configurada)"— pero solo lo aplica a salud, finanzas y derecho, nunca a la difamación misma, que es exactamente donde más pesa.*

## N.1 Un umbral único calibrado para el régimen más permisivo es insuficiente para el más severo

Un producto documentado enteramente en español, dirigido con toda probabilidad al mercado hispanohablante, va a operar simultáneamente bajo al menos dos familias de régimen de responsabilidad muy distintas: en jurisdicciones de *common law* con el estándar estadounidense de "real malicia" (*New York Times v. Sullivan*) para figuras públicas, el umbral de responsabilidad civil es alto y la difamación es, casi siempre, un asunto exclusivamente civil. En un número significativo de países de tradición civil —incluidos varios de América Latina y de Europa continental— la difamación conserva figuras penales (calumnia e injuria), con exposición personal del editor, y sin el filtro reforzado de "real malicia" para figuras públicas que existe en el régimen estadounidense.

GOVERNANCE §2.3 no distingue estos dos mundos: un umbral único de "doble fuente verificada", calibrado pensando en el régimen más permisivo, puede ser gravemente insuficiente donde la buena fe y la verificación no son necesariamente defensa completa. La corrección no es un ajuste de número: la "jurisdicción configurada" de 8.2 debe controlar también el propio umbral de la Compuerta de Riesgo para difamación, con un **perfil de jurisdicción de fábrica** que, en regímenes de responsabilidad penal, exija conjuntamente doble fuente verificada **y** ausencia registrada de postura del señalado (Capítulo M) **y** retención humana obligatoria —nunca Autónomo, sin excepción configurable por el cliente— para cualquier pieza con afirmación fáctica negativa sobre persona identificable. Un perfil de jurisdicción no es un dial que el cliente pueda relajar: relajarlo no protege al producto de una demanda, protege al vendedor del plugin de la ficción de haber advertido.

## N.2 Derechos de personalidad e imagen: el eje que D.1–D.2 no cubren

D.1–D.2 del Nivel Dos cubren, con acierto, la propiedad intelectual de terceros (marcas, personajes, estilo de artista vivo) en la generación de imagen destacada. Falta un eje distinto: el derecho a la propia imagen de personas reales identificables. Una imagen generada por IA para una pieza sobre un escándalo protagonizado por una persona real, si el modelo produce —aunque el prompt no lo pida explícitamente, por asociación de entrenamiento— un rostro razonablemente similar a esa persona, no es un problema de derecho de autor: es un problema de derecho de imagen, y en el caso más severo —una escena que la persona nunca protagonizó— entra en el terreno específico de la legislación de contenido sintético que dirige a representaciones de personas reales en contextos que las difaman o ridiculizan. La Compuerta de Originalidad Visual (D.2) necesita un tercer chequeo, con el mismo estatus de lista de bloqueo previa a la llamada que D.2.1 ya exige para marcas y personajes: verificación de que el prompt no referencia, ni por nombre ni por descripción suficientemente específica, a una persona real identificable.

## N.3 "Asistida por IA" y "el autor no existe" son dos revelaciones distintas, y el Libro solo modela la primera

El Artículo 50 del Reglamento de IA de la Unión Europea entra en vigor el 2 de agosto de 2026 y exige que el contenido sintético (audio, imagen, video, texto) se marque de forma legible por máquina y detectable como generado por IA — con sanciones de hasta 15 millones de euros o el 3% de la facturación mundial. Tiene, además, una excepción textual directamente relevante para este producto: **la obligación de marcado no aplica al texto cuando ha pasado por revisión humana y una persona física o jurídica asume la responsabilidad editorial sobre él.**

Esto tiene una consecuencia de diseño que ni el Libro ni el Nivel Dos podían anticipar y que ninguno de los dos calcula: en modo Piloto y Copiloto —donde un humano interviene, aprobando o dejando pasar deliberadamente la ventana de veto con posibilidad real de intervenir— el producto puede argumentar razonablemente que cae dentro de la excepción de responsabilidad editorial humana asumida. Pero cuanto más se sube la escalera de autonomía hacia el modo Autónomo —exactamente el peldaño que el Epílogo del Libro celebra como meta, "Tercera verdad"— más débil se vuelve ese argumento, y más cerca queda el contenido de calificar, sin excepción, como texto de interés público generado por IA sujeto a marcado técnico obligatorio. Ni el Libro ni el Nivel Dos calculan esta tensión: "la autonomía es una escalera" es, sin que ninguno de los dos documentos lo diga, también una escalera de exposición regulatoria creciente, y el peldaño más alto de autonomía es el que menos protección puede invocar de la excepción por responsabilidad editorial humana.

La transparencia de autoría de 13.1 debe, por tanto, diferenciar explícitamente dos declaraciones que hoy trata como una: "redactado con asistencia de IA bajo dirección editorial humana" (defendible en Piloto/Copiloto) frente a "generado y publicado sin revisión humana previa" (el caso real del modo Autónomo, que exige el marcado técnico —metadatos, no solo texto visible en la página— como requisito de fábrica de ese modo, no como opción del panel).

Existe además una segunda capa de revelación que ninguno de los dos documentos distingue de la primera: que "Valentina Ruiz" en sí misma no es una persona física. Declarar asistencia de IA en el texto no equivale, para efectos de protección al consumidor, a declarar que el propio nombre firmante no corresponde a una persona real — y el párrafo primero del propio Artículo 50 exige informar cuando se interactúa directamente con un sistema de IA. La página de autor de cada periodista sintético (5.2) debe declarar sin ambigüedad, en el mismo texto donde hoy el Libro solo pide "elegancia" de marca, que el nombre es una identidad editorial sintética y no una persona física. Elegancia de marca y claridad regulatoria no son la misma cosa, y en este punto exacto el diseño premium del Capítulo 10 no puede primar sobre la segunda.

---

# CAPÍTULO O — LA FALSEABILIDAD COMO FASE DEL ALGORITMO: LO QUE FALTA ENTRE "SELECCIONAR" Y "REDACTAR"

*5.5 Paso 3 selecciona la tesis ganadora por puntuación (ya corregida en K.1) y pasa directamente al Paso 4. El Libro exige "contraargumento reconocido y respondido" dentro del esqueleto de redacción (Paso 4) — pero eso ocurre escrito por el mismo periodista que ya defiende la tesis, con el incentivo de defenderla bien, no de encontrarle el fallo real. Es la diferencia entre un abogado reconociendo la objeción más obvia del fiscal y un fiscal de verdad construyendo su caso — y un sistema que aspira a periodismo real necesita el segundo, no solo el primero.*

## O.1 Fase 3.5 — Prueba de Falseabilidad

Entre el Paso 3 (selección de ángulo) y el Paso 4 (arquitectura de la pieza), el sistema ejecuta una pasada adicional acotada —mismo `LenguajeInterface`, propósito nuevo (`falsear`)— con una instrucción explícitamente adversarial: *"usando exclusivamente el expediente, construye el caso más fuerte posible en contra de esta tesis exacta."* No es el contraargumento cortés que Paso 4 ya contempla dentro de una pieza que de todos modos defiende la tesis: es un intento genuino, con instrucción adversarial explícita, de derrotarla.

- Si el caso en contra, evaluado por sustento verificable en el expediente —no por elocuencia—, resulta comparable o más fuerte que el caso a favor: la tesis no se descarta automáticamente (eso sería sobre-corregir hacia la parálisis), pero la Ficha de Decisión Editorial registra explícitamente la tensión, y el esqueleto del Paso 4 debe incorporar el contraargumento resultante con el mismo peso argumental que la tesis principal — no como párrafo de cortesía.
- Si la fuerza del caso en contra supera un umbral configurable: la Pieza vuelve al Paso 3 para reevaluar entre los candidatos restantes con esta información nueva.

Este mecanismo es barato —una llamada adicional, de propósito acotado, sobre un expediente que el sistema ya tiene construido— y ataca un tipo de error que ninguna de las tres compuertas del Capítulo 8 detecta: una tesis bien sustentada, bien escrita, con voz reconocible, que pasa estructuralmente todo, y que sin embargo es la lectura más débil de los datos disponibles simplemente porque nadie, en ningún punto del pipeline, tuvo el trabajo explícito de intentar derrotarla antes de publicarla.

## O.2 La falacia de la ausencia como oportunidad: revisando B.4 desde un ángulo que el propio Nivel Dos no consideró

B.4 corrigió, con acierto, que "el hueco detectado" no sea una pregunta abierta al modelo, sino la dimensión ausente entre las coberturas recolectadas (económica, política, humana, técnica, histórica, legal). Pero asume, sin decirlo, que toda dimensión ausente en la cobertura existente es candidata a hueco real. No lo es: una dimensión puede estar ausente simplemente porque no es relevante para esa noticia específica — la dimensión legal no es un hueco en una noticia sobre un resultado deportivo, es una dimensión que sencillamente no aplica. El filtro de sustento de B.4.3 atenúa esto parcialmente (exige que el expediente tenga datos propios para llenar el hueco), pero no distingue entre "nadie cubrió esto porque es un ángulo real y no obvio" y "nadie cubrió esto porque, aunque hay datos generales disponibles sobre el tema, no tienen relación causal con ESTA noticia puntual" — el expediente puede tener series históricas del sector sin que esas series tengan ninguna conexión argumental genuina con el hecho concreto que se cubre.

**Corrección: prueba de relevancia causal**, cuarto paso de B.4, después del filtro de sustento — antes de aceptar una dimensión ausente-y-sustentada como candidata de hueco, generar explícitamente la pregunta que esa dimensión respondería sobre esta noticia puntual, y verificar contra el propio expediente que existe al menos un dato que conecta esa dimensión con los actores o hechos específicos de la tendencia, no con el tema general del vertical. Sin esta prueba, el "hueco" que B.4 promueve a candidato de tesis puede seguir siendo, con la misma frecuencia que antes de su propia corrección, un hueco genérico disfrazado de estructurado.

---

# CAPÍTULO P — HOMEOSTASIS A ESCALA: LA VIDA DEL SISTEMA DESPUÉS DEL PRIMER CICLO

*Ni el Libro ni el Nivel Dos preguntan qué pasa cuando el mecanismo, ya construido y calibrado, se ejecuta ocho veces al día durante dos años. Este capítulo lo pregunta.*

## P.1 La huella estructural del sitio: cuando el esqueleto se repite aunque el texto no

5.5 Paso 4 garantiza que cada pieza tenga "un esqueleto argumental propio (nunca calcado de una fuente)", y 8.3 verifica solapamiento contra fuentes y contra el propio sitio a nivel de n-gramas de texto. Ninguno de los dos verifica un tercer nivel: la repetición de la **estructura retórica misma** —gancho→hechos→tesis→contraargumento→remate, siempre en ese orden, con la misma proporción, para las mismas categorías de noticia— a través de cientos de piezas del mismo periodista a lo largo de meses. Un periodista sintético que redacta 300 piezas al año con diales fijos y el mismo esqueleto de cinco movimientos produce, sin que ninguna palabra se repita literalmente, un patrón de plantilla perfectamente reconocible a nivel de corpus — la clase de señal que un fingerprint estructural de dominio detecta y que ninguna de las tres compuertas evalúa, porque las tres evalúan la pieza, nunca el corpus acumulado.

**Mecanismo:** un trabajo periódico (mensual, en la Sala de Máquinas) calcula la distribución de arquitecturas de esqueleto (secuencia y proporción de movimientos argumentales) por periodista y por vertical a lo largo del último trimestre, y alerta cuando la entropía de esa distribución cae bajo un umbral — cuando el periodista se ha vuelto, en la práctica, una plantilla con vocabulario variable. La respuesta no es prohibir el esqueleto por defecto de 5.5 Paso 4 (sigue siendo un buen punto de partida): es exigir que el **Repertorio** del periodista —la cuarta capa que 5.1 nombra sin desarrollar en ningún capítulo dedicado— incluya, además de la plantilla por defecto, 2–3 arquitecturas alternativas (cronológica, pregunta-respuesta, comparación estructurada) que el algoritmo rota deliberadamente cuando la entropía cae, del mismo modo que ya rota firmas por balance de carga (C.2).

## P.2 La fatiga de alerta en la Sala de Revisión: el pastor mentiroso del propio producto

1.4 fija como métrica de éxito una tasa de retención por compuertas de 10–20%, con la advertencia correcta de que "si es 0%, las compuertas no funcionan". Ningún capítulo modela la consecuencia inversa y, en la práctica de sistemas de alerta humano-en-el-circuito, mucho más común: si un editor ve, mes tras mes, un flujo constante de retenciones al 15% donde la mayoría termina aprobándose tras seis meses de calibración exitosa, el comportamiento humano previsible no es vigilancia constante — es aprobación reflexiva. El modo Copiloto depende enteramente de que el editor siga leyendo el diagnóstico con la misma atención en el mes dieciocho que en la semana uno, y ningún sistema de este tipo, en ningún dominio con humano-en-el-circuito, ha resistido ese supuesto sin un mecanismo activo de calibración.

**Mecanismo:** inyección deliberada y declarada de casos de prueba en la cola de veto —en el mismo espíritu que el corpus adversarial que GOVERNANCE §3.4 ya exige contra la inyección de prompts, aplicado aquí a la atención humana en vez de al modelo—, siempre con registro transparente al propio editor, nunca oculto de forma permanente. Con baja frecuencia, el sistema presenta en la cola una pieza marcada con un fallo deliberado de bajo riesgo (por ejemplo, un dato con sello temporal caducado a propósito) y mide si el editor lo detiene. Si la tasa de detección cae bajo un umbral trimestral, el sistema no lo esconde: lo reporta en la Portada como métrica de salud del propio proceso de supervisión ("tiempo medio de revisión: bajó 40% en 90 días" es una alerta sobre el sistema, no un logro de eficiencia) — la misma filosofía de transparencia total que P6 exige para el contenido, extendida al acto mismo de supervisión humana que sostiene Copiloto y Autónomo.

## P.3 Auditoría de periodista obligatoria y deriva de modelo silenciosa

A.5 establece el corpus de regresión de voz como gate de release "cada vez que toque la Plantilla de Prompt de redacción". Pero un periodista puede llevar 18 meses sin que nadie toque su plantilla y aun así derivar, porque el proveedor detrás de `LenguajeInterface` actualiza silenciosamente los pesos del modelo bajo el mismo alias de API — el patrón más común y menos discutido de fallo de calidad en productos de IA en producción: no un cambio de proveedor (que 12.3 sí contempla), sino un cambio de comportamiento del **mismo** proveedor bajo el **mismo** contrato, sin que ninguna línea de código cambie. El corpus de A.5, tal como está definido, nunca se ejecuta si lo que cambió fue el modelo detrás del proveedor y no el prompt.

**Corrección:** el corpus de regresión de voz debe ejecutarse también en un calendario independiente del ciclo de releases —mensual, como mínimo—, precisamente para capturar esta deriva silenciosa. Y cada periodista activo debe pasar, con la misma cadencia, una auditoría editorial ligera y no automatizable del todo: el propietario del producto (o el rol de "editor de revisión" que 12.1 ya define) revisa una muestra de piezas recientes contra la biografía y línea editorial originales del periodista (5.2), con un veredicto explícito registrado — no porque el test de voz automatizado pueda fallar en detectarlo (puede), sino porque el activo real del producto ("el activo que este sistema acumula... son los periodistas", Epílogo del Libro, Segunda verdad) merece la misma disciplina de auditoría periódica que cualquier activo de esa importancia, y hoy ningún capítulo de ninguno de los dos documentos anteriores define esa cadencia.

---

# CAPÍTULO Q — MULTILOCALIDAD EDITORIAL: EL PRODUCTO QUE SE VENDE EN UN IDIOMA Y SE HABLA EN MUCHOS

*El corpus de regresión de voz (A.5), la matriz de vocabulario prohibido (5.3) y los ejemplos-ancla por tramo de dial (A.3) están todos definidos, implícita pero completamente, en un solo idioma y un solo marco cultural — el propio ejemplo del Libro ("Valentina Ruiz", clichés como "en el ojo del huracán") es español de un registro específico. Un producto vendido en todo el mundo hispanohablante, y potencialmente en más idiomas, no puede tratar estos catálogos como artefactos únicos.*

Un cliché denunciado como "muletilla de IA" en español de España puede ser un registro natural en español rioplatense, y viceversa; las listas de vocabulario prohibido, los ejemplos-ancla de A.3 y el corpus de regresión de A.5 son artefactos **localizados**, no traducidos. La Configuración de Periodista (5.2–5.4) necesita un campo de primera clase, `locale_editorial` (idioma + variante regional), que determina qué catálogo aplica — no como traducción automática de un catálogo maestro en español neutro (que produciría exactamente la genericidad que P1 existe para evitar), sino como catálogos curados de forma independiente por locale, con el mismo estatus de artefacto versionado que A.2 exige para las Plantillas de Prompt. Un periodista que cubre para audiencia mexicana y otro que cubre, desde el mismo sitio multisitio (12.3 ya lo contempla), para audiencia española, no pueden compartir catálogo de muletillas sin que uno de los dos suene sistemáticamente traducido — de nuevo, la señal de contenido de baja calidad que todo el diseño del Libro existe para evitar, en un eje que ninguno de los dos documentos anteriores nombra.

---

# CAPÍTULO R — LA FUNCIÓN QUE FALTA: LA ECONOMÍA UNITARIA Y LA DECISIÓN DE CUOTA

*9.2 trata la cuota diaria como decisión puramente editorial/SEO ("el editor decide si la cuota es rígida o elástica"). 9.4 y 12.3 registran el coste por pieza y por día en la bitácora del motor. El bucle de Search Console (6.4) permite aproximar el tráfico que produce cada pieza. Ningún capítulo conecta las tres cosas en una función que informe la propia decisión que 9.2 dice que el editor "decide".*

Sin esa función, "subir la cuota de 6 a 8 piezas/día" es una decisión que el editor toma mirando el pulso editorial de la Portada (10.2) sin ver, en ningún lugar del panel, si esas dos piezas adicionales cuestan más en tokens de lo razonable esperar que produzcan en sesiones — la pregunta más elemental de cualquier negocio de contenido, y la única que ninguno de los dos documentos anteriores calcula.

**Función de valor marginal por pieza** (nueva, para el Estudio SEO o una pestaña de la Sala de Máquinas):

```
coste_pieza          = Σ (tokens_por_propósito × precio_por_token_del_modelo_enrutado)   // ya en bitácora
valor_esperado_pieza = sesiones_medias_historicas(vertical, periodista, tipo_de_tendencia) × ingreso_estimado_por_sesión
```

`ingreso_estimado_por_sesión` es un parámetro que el editor introduce (RPM publicitario propio, o 0 si el sitio no monetiza así y el objetivo es autoridad/marca — la función no impone un modelo de negocio, lo hace explícito). `sesiones_medias_historicas` se calcula sobre las mismas dimensiones que el bucle de Search Console de 6.4 ya recolecta, pero nunca agrega con este propósito.

Esto no debe automatizar la decisión de cuota (P4, autonomía con frenos, sigue aplicando: la cuota la fija el editor) — pero sí debe mostrarse en la misma pantalla donde el editor la ajusta: "al ritmo actual, la pieza marginal de hoy cuesta aproximadamente X y el vertical donde se publicaría ha producido, en promedio, Y sesiones por pieza en el último trimestre" — convirtiendo una decisión hoy puramente intuitiva en una decisión informada, sin quitarle al editor ni un ápice de control. La escasez honesta que `CLAUDE.md` exige para la cuota tiene un espejo económico que ningún documento anterior nombra: no rebajar umbrales para rellenar cuota es una forma de honestidad; publicar una pieza marginal cuyo coste supera con holgura su valor esperado, sin que el editor lo sepa, es la forma económica exacta del mismo problema.

---

# CAPÍTULO S — DOS CASOS TRABAJADOS QUE PRUEBAN EL MECANISMO

*Siguiendo la tradición del Capítulo H del Nivel Dos: los mecanismos de los capítulos J, K, M y O puestos a trabajar juntos sobre situaciones completas, de principio a fin.*

## S.1 Caso: la acusación bien sustentada que nadie intentó verificar desde el otro lado

Una tendencia "ola" (3.3) sobre un funcionario público acusado de un conflicto de interés entra al pipeline. El Investigador triangula el hecho central: dos fuentes de nivel A confirman el conflicto documentado en registros públicos. No hay contradicción entre fuentes (B.2 no se activa). La Pieza avanza limpia hasta `EN_REDACCION`.

**Cómo piensa el sistema:**

1. (Capítulo M, Nivel 1) Antes de salir de `EN_REDACCION`, el sistema verifica si alguna de las coberturas recolectadas incluye una declaración o negación del funcionario. Ninguna la incluye. Se registra `postura_del_senalado: ausente`.
2. Esto no detiene la pieza por sí solo (el hecho está verificado, GOVERNANCE §2.3 se cumple) — pero activa un motivo de retención independiente en la Compuerta de Riesgo. La Sala de Revisión muestra el diagnóstico exacto: "hecho verificado por 2 fuentes; postura del señalado no localizada en el expediente" — información que, sin este mecanismo, el editor solo habría descubierto leyendo el expediente completo él mismo, si acaso.
3. (Capítulo J) En paralelo, el punto 1 del Corrector Interno se ejecuta, dado que es una pieza de gravedad/polaridad alta, con un verificador de familia de modelo distinta a la del redactor. La capa determinista de embeddings marca una frase del borrador como `SIN_RESPALDO_APARENTE`: el periodista, redactando con su línea editorial vehemente, escribió que el conflicto "ya le costó el puesto en un cargo anterior" — inferencia razonable en tono, sin ancla directa en el expediente. El verificador de familia distinta, sin haber "creído" esa inferencia porque no la generó él mismo, la marca con más sensibilidad que la que un verificador de la misma familia habría mostrado.
4. La pieza vuelve a redacción (A.6: el punto 1 se corrige primero) con la frase eliminada o convertida en atribución explícita. Sale con el hecho central intacto, sin la inferencia no sustentada, y con la mención explícita de que se buscó sin éxito la postura del funcionario — el estándar de una pieza de investigación seria, que ninguno de los dos documentos anteriores garantizaba por sí solo.

## S.2 Caso: la tesis elegante que pierde contra sus propios datos

Un periodista analista (dominio alto en economía) recibe una tendencia sobre una cifra de desempleo que baja. El expediente, tras contextualización (4.2 paso 4), tiene series históricas de cinco trimestres. El Paso 3 —ya corregido por K.1— genera candidatos; el ganador, tras superar el piso de sustento, es "la baja refleja una recuperación estructural del sector servicios": alto en originalidad y potencial de conversación, con sustento suficiente en el expediente.

**Cómo piensa el sistema:**

1. (Capítulo O, Fase 3.5) La Prueba de Falseabilidad ejecuta la instrucción adversarial. El expediente, que también tiene las series históricas recolectadas para contextualizar, permite construir un caso en contra igual de fuerte: la misma serie muestra que la baja coincide con una caída estacional recurrente en el mismo trimestre de los últimos tres años, no con un cambio estructural — un patrón que el propio periodista, defendiendo su tesis, no tenía incentivo de buscar.
2. El caso en contra, evaluado por sustento verificable, resulta comparable en fuerza. Cruza el umbral configurable de O.1: la Pieza vuelve al Paso 3 con esta información nueva.
3. El Paso 3 reevaluado no cambia de periodista (C.2–C.3 no se re-ejecuta: el problema no fue la asignación, fue la tesis) — pero el nuevo candidato ganador incorpora el patrón estacional como parte del sustento disponible: "la baja es consistente con el patrón estacional de los últimos tres años, pero el sector servicios muestra además una señal incipiente de cambio estructural que merece seguimiento" — más honesta con los datos, y con un ángulo de seguimiento que el Motor SEO (6.4) puede convertir después en la estrategia de "dos golpes" que el propio Libro ya valora (3.4).
4. (Capítulo K.2) La pieza final pasa el piso de densidad de sustento de la Compuerta de Calidad corregida sin depender de que su prosa fluida compense un sustento débil — porque, gracias a la Prueba de Falseabilidad, el sustento ya no es débil: es, simplemente, más honesto.

---

# CAPÍTULO T — QUÉ IMPLEMENTAR PRIMERO, Y LA AUTOCRÍTICA QUE ESTE DOCUMENTO SE DEBE A SÍ MISMO

## T.1 Mapeo a las Etapas existentes

| Pieza de este documento | Etapa del PLAN-MAESTRO | Por qué ahí |
|---|---|---|
| K.1 (fórmula de selección de ángulo) | Etapa 2 | Es el contenido de "El periodista"; el criterio de salida actual es imposible de proteger sin el piso de sustento |
| K.2 (fórmula de Compuerta de Calidad) | Etapa 3 | Vive donde ya viven las tres compuertas |
| K.3 (Regla de Puntuaciones Compuestas) | Etapa 1, como norma de gobernanza transversal | Debe existir antes de que se escriba la primera puntuación compuesta del proyecto (la del Radar), no después |
| L.1–L.2 (procedencia del declarante, autenticidad audiovisual) | Etapa 1, extendiendo el Investigador | Es parte del protocolo que ya se construye ahí, igual que el Capítulo B del Nivel Dos |
| J.1–J.2 (contrato de independencia del verificador) | Etapa 2 (contrato) y Etapa 3 (obligatoriedad al activar Autónomo) | La independencia debe existir cuando el Corrector se construye; el endurecimiento se ata a la introducción del modo Autónomo |
| J.3 (capa determinista de verificación) | Etapa 2, junto al resto del Corrector Interno | Es parte del mecanismo de anti-alucinación desde el primer periodista |
| M.1 | Etapa 1 | Verificación sobre el expediente que ya se construye ahí |
| M.2 | Etapa 3, junto a las compuertas | Igual patrón que la búsqueda dirigida de B.2 |
| M.3 | Etapa 5 | Requiere el resto de la infraestructura de "la máquina que aprende" |
| N.1 (perfil de jurisdicción) | Etapa 3, junto a las compuertas | Extiende directamente 8.2 |
| N.2 (derechos de personalidad en imagen) | Etapa 3, junto a D (compuerta visual) | Mismo módulo, tercer chequeo |
| N.3 (disclosure de persona sintética + marcado técnico Autónomo) | Etapa 4 (página de autor, panel) y Etapa 3 (marcado técnico, atado a la introducción del modo Autónomo) | El marcado técnico no puede esperar a la Etapa 4 si el modo Autónomo se activa en la Etapa 3 |
| O.1–O.2 (falseabilidad, relevancia causal) | Etapa 2, junto al resto del Algoritmo de Decisión Editorial | Es la Fase 3.5; vive donde vive el Paso 3 |
| P.1 (huella estructural) | Etapa 5 | Requiere corpus histórico de meses reales |
| P.2 (fatiga de alerta) | Etapa 4 (mecanismo) y Etapa 5 (métrica en Portada) | El mecanismo de inyección puede empezar con el panel; la métrica agregada necesita historial |
| P.3 (auditoría de periodista y deriva de modelo) | Etapa 2 (proceso) con ejecución continua desde el primer periodista activo | No depende de infraestructura nueva, solo de calendario |
| Q.1 (`locale_editorial`) | Etapa 2, campo de la Configuración de Periodista | Añadirlo después obliga a migrar todo el banco existente |
| R.1 (función de valor marginal) | Etapa 5, junto al bucle de Search Console | Depende de datos históricos reales que 6.4 ya recolecta ahí |
| H.1–H.2 de este documento (Capítulo S) | Fixtures de integración de las Etapas 1–3 correspondientes | Mismo estatus que el Capítulo H del Nivel Dos: no son código, son casos de aceptación |

## T.2 Autocrítica: dónde este documento puede estar equivocado

- **El requisito de verificador independiente (J)** añade coste y latencia real —una segunda llamada, potencialmente un segundo contrato de proveedor— precisamente en las piezas de mayor gravedad. Para un operador único con 3–8 piezas/día, esto puede ser desproporcionado frente a la reducción real de riesgo, y este documento no tiene datos empíricos (ningún producto ha salido a producción todavía) para saber si la alucinación correlacionada es, de hecho, un riesgo de primer orden en un pipeline de piezas cortas con expediente acotado, frente a las tareas de razonamiento abierto y extenso donde el fenómeno está mejor documentado. Debe tratarse como hipótesis a validar empíricamente durante el Piloto —comparar la tasa de aprobación sin fricción de un verificador de la misma familia contra uno de familia distinta, sobre el mismo corpus de piezas— antes de convertirlo en requisito duro e incondicional del modo Autónomo.
- **La Prueba de Falseabilidad (O)** puede, en temas polarizados, importar el fallo clásico del "falso equilibrio": si la instrucción siempre exige "el caso más fuerte posible en contra", el sistema puede fabricar un contraargumento con apariencia de fuerza comparable aunque la evidencia real no lo sustente de forma pareja. La mitigación descrita (comparar por sustento verificable, no por elocuencia) es necesaria pero no suficiente, y necesita un corpus de casos etiquetados (contraargumento genuinamente fuerte vs. débil) durante el Piloto para calibrar el umbral — debe registrarse como deuda con hito de validación explícito, no como constante silenciosa.
- **El Nivel 3 del derecho de réplica (M.3)** es la pieza de este documento más expuesta a estar técnicamente incompleta: qué cuenta como "canal de contacto público conocido", y cómo interactúa la ventana de espera obligatoria con una tendencia "relámpago" que, según B.3, empieza a decaer a las 2 horas — este documento señala la tensión entre M y B.3 pero no la resuelve algorítmicamente; resolverla bien probablemente exige calibración empírica en la Etapa 5, no un número que este documento pueda afirmar con responsabilidad hoy.
- **La función de valor marginal (R)** asume que el tráfico de una pieza puede atribuirse limpiamente por vertical/periodista/tipo de tendencia — en la práctica, la atribución es más ruidosa (el tráfico de una pieza depende de cómo evoluciona el tema después de publicada, algo ajeno a la calidad de la pieza misma), así que debe tratarse como señal direccional para el editor, nunca como corte automático; automatizar la decisión de cuota con esta función violaría P4 (autonomía con frenos) aplicado a la capa de negocio, no solo a la editorial.
- **Autocrítica final, sobre el propio método:** este documento, como el Nivel Dos antes que él, no puede ser exhaustivo. Pero a diferencia del Nivel Dos —que encontró y corrigió un patrón de fórmula incompleta dentro de su predecesor—, este documento no ha sido auditado por una cuarta pasada contra sí mismo. La expectativa honesta, siguiendo la misma trayectoria (Libro → mecanismo → esta capa de robustez), es que un "Nivel Cuatro" encontraría al menos un patrón sistémico dejado a medio aplicar aquí mismo — con mayor probabilidad, en si la Regla de Puntuaciones Compuestas de K.3 se aplicó de verdad a las puntuaciones nuevas que este propio documento introduce. Ni el umbral de fuerza del contraargumento en O.1 ni el umbral de entropía estructural en P.1 declaran todavía, con la misma disciplina de tres columnas que K.3 exige para el futuro, si son pisos o contribuyentes ponderados. Ese es, honestamente, el hueco más probable que un Nivel Cuatro encontraría en este mismo documento.

---

# EPÍLOGO: LA CUARTA VERDAD

El Libro cerró con tres verdades. Hay una cuarta, que solo se ve después de escribir las dos capas anteriores: un sistema que nunca se contradice a sí mismo —no en sus periodistas, eso ya lo exige P2— sino en su propio mecanismo de verificación, es un sistema que ha dejado de dudar. Y un periodismo que ha dejado de dudar de sus propias conclusiones no es periodismo autónomo: es una opinión con muy buena infraestructura.

La independencia epistémica del verificador (Capítulo J), la pregunta que nadie hacía (Capítulo M) y la prueba de falseabilidad (Capítulo O) son, las tres, la misma idea repetida en tres capas distintas del sistema: construir, deliberadamente, un punto de fricción interno que nadie tiene incentivo de construir por sí solo. Un modelo no tiene incentivo de dudar de su propia respuesta; un periodista no tiene incentivo de buscar la mejor defensa de quien acusa; una redacción con cuota que cumplir no tiene incentivo de retrasar una pieza para esperar una respuesta que quizá nunca llegue. La arquitectura de un newsroom sintético que merece ese nombre no es solo la que automatiza el criterio —eso ya lo dijo el Libro, P1—: es la que automatiza, además, la fricción que un periodista humano se impone a sí mismo cuando nadie más lo está mirando.

Eso es lo que ni el Libro ni el Nivel Dos podían escribir todavía, porque hacía falta ver el mecanismo completo, funcionando, para saber exactamente dónde le falta a un sistema la capacidad de dudar de sí mismo. Un plugin que sabe qué debe existir, que sabe cómo decide, y que además sabe dónde su propio juicio podría estar equivocado antes de que un lector, un regulador o una demanda se lo señalen, no es ya una anatomía convincente con buena fisiología: es, con toda la humildad que la palabra exige, lo más parecido a una redacción real que el mercado de este tipo de producto ha visto construir con esta disciplina.

*— Fin del complemento —*
