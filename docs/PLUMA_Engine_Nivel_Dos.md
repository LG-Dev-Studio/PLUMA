# PLUMA ENGINE — NIVEL DOS
## De especificación de producto a mecanismo de razonamiento periodístico

*Complemento crítico al `PLUMA_Engine_Libro_de_Arquitectura.md` v1.0 — Versión 1.0*

---

# PRÓLOGO: POR QUÉ EXISTE ESTE DOCUMENTO

El Libro de Arquitectura es, en su categoría, un documento inusualmente bueno. No es una lista de features: tiene tesis (P1–P7), tiene un sistema inmunológico real (las tres compuertas), tiene conciencia de que el enemigo es Google y no la competencia, y su capa de gobernanza de ingeniería (`CLAUDE.md`, `GOVERNANCE.md`, `AGENTS.md`) es de un rigor que rara vez se ve fuera de equipos de plataforma senior — invariantes editoriales convertidos en tests de arquitectura, escasez honesta como regla de código y no de intención, degradación por sensibilidad que ninguna opción de usuario puede anular.

Pero hay una distinción que el propio libro no traza sobre sí mismo, aunque la traza perfectamente para el producto en el Principio P1: la diferencia entre **especificar qué debe existir** y **especificar cómo se decide**. El libro dice, correctamente, que "el criterio es el producto". Y sin embargo el propio libro, en el momento de describir el criterio, se detiene un nivel antes de donde debería: describe **qué campos tiene** la Ficha de Decisión Editorial, pero no **el algoritmo que la llena**. Describe que existen "3–5 candidatos de tesis", pero no de dónde salen ni cómo se puntúan más allá de cuatro adjetivos ("originalidad, compatibilidad, sustento, potencial"). Describe ocho diales de temperamento, pero ninguno tiene una función que traduzca su valor numérico en una instrucción de redacción verificable. Describe un Corrector Interno con checklist de seis puntos, pero no el prompt que ese Corrector ejecuta, ni qué hace cuando encuentra tres tipos de fallo simultáneos y tiene que decidir cuál corregir primero.

Esto no es una crítica menor. Es la diferencia entre construir un plugin que **dice** tener un periodista sintético y construir uno que **efectivamente razona** como uno. Un juez podría leer el Capítulo 5 completo y no sabría escribir el prompt de sistema de Valentina. Un ingeniero podría implementar `Pluma\Redaccion\AlgoritmoDecisionEditorial::seleccionarAngulo()` exactamente como está descrito y el método compilaría, pasaría PHPStan nivel 8, tendría tests verdes — y aun así devolvería una tesis mediocre, porque la lógica que decide "cuál de los 5 candidatos gana" nunca fue especificada más allá de cuatro palabras sueltas.

**El diagnóstico central de este documento es este:** el Libro de Arquitectura describe magníficamente la anatomía de una redacción sintética. Le falta la fisiología — el mecanismo por el cual esa anatomía efectivamente piensa, decide y se corrige a sí misma. Este documento no repite lo que el Libro ya dice bien. Añade nueve capítulos de mecanismo concreto (agrupados en diez piezas priorizables, ver Capítulo I) sobre exactamente los puntos donde el Libro asume que "ya se sabe cómo hacer" — y que, en la práctica, son donde un producto de este tipo se gana o se pierde su licencia de existir: la diferencia entre "parece que tiene criterio" y "tiene criterio".

Una nota de honestidad sobre el propio Libro, porque el Capítulo 8 exige lo mismo de las piezas de PLUMA: cualquier afirmación fáctica en este documento sobre el estado actual de las políticas de Google o de schema.org fue verificada contra fuentes de julio de 2026, no recordada de memoria de entrenamiento. Donde el Libro cita el marco de "scaled content abuse" de 2024 como advertencia, la evidencia de campo más reciente (marzo–junio de 2026) confirma que ese riesgo no solo sigue vigente: sitios con publicación automatizada sin supervisión editorial demostrable perdieron entre 50% y 80% de tráfico orgánico en cuestión de semanas durante la actualización de marzo de 2026. El enemigo que el Libro identifica en su prólogo no es hipotético ni está desactualizado. Es, si acaso, más severo hoy que cuando se escribió la primera versión del documento.

---

# CAPÍTULO A — EL CEREBRO EDITORIAL: DE CONTRATOS A PROMPTS

*El Libro especifica `LenguajeInterface::completar()` y seis propósitos (`clasificar|angulos|redactar|corregir|titulares|bloque_editor`). No especifica ni un solo prompt de sistema. Esto es el hueco más caro del proyecto, porque es el único módulo donde el "código" que realmente decide la calidad de la pieza no vive en PHP: vive en texto natural que ningún gate de PHPStan puede verificar.*

## A.1 El problema que el Libro no nombra: la brecha entre dial numérico y comportamiento verbal

El Capítulo 5.3 del Libro define `agudeza_critica` como un entero 0–100 con "efecto bajo: relata con neutralidad; efecto alto: interroga motivos". Esa es una especificación de producto, no una especificación de sistema. Nadie —ni el desarrollador humano, ni Claude operando como agente de ingeniería sobre este repo— puede escribir el código que traduzca `agudeza_critica = 73` en una instrucción de redacción, porque no existe una función `f(dial) → texto_de_directriz`. El resultado en producción, sin este capítulo, es predecible y ya lo hemos visto mil veces en productos de IA generativa: el desarrollador improvisa un prompt razonable en la primera semana de la Etapa 2, ese prompt funciona "más o menos" en las diez piezas de la demo, y se congela ahí para siempre porque nadie lo trata como un artefacto versionado, testeado y sujeto a los mismos gates que el resto del código. Es el placeholder más peligroso de todos, porque es el único que `no-placeholder-gate` (el hook `.claude/hooks/`) no puede detectar: no dice `// TODO`, dice texto natural razonable que nadie audita.

## A.2 La solución: el prompt de sistema como DTO versionado, no como string embebido

Cada `PeticionLenguaje` no debe llevar un string de directriz construido ad-hoc en el momento de la llamada. Debe llevar una referencia a una **Plantilla de Prompt** — una entidad de primera clase en `Pluma\Redaccion`, versionada exactamente como se versiona la Configuración de Periodista (GOVERNANCE §1.2), con su propio historial y su propio test de regresión. Seis plantillas, una por propósito, cada una con:

- **Secciones fijas** (invariantes del sistema, nunca tocadas por el dial de ningún periodista: las líneas rojas de GOVERNANCE §2, el bloqueo de sátira en tragedia, la regla anti-alucinación).
- **Secciones parametrizadas** (la función de traducción dial→directriz de la sección A.3).
- **Secciones de material** (el expediente, delimitado exactamente como exige GOVERNANCE §3.4 — nunca en la misma sección que las instrucciones).

Esto convierte el prompt de "cosa que el desarrollador escribió una vez" en "artefacto que atraviesa el mismo Delivery Guardian que cualquier clase PHP": tiene tests de regresión (el corpus de A.5), tiene versión, tiene autor, y un cambio en él dispara la misma disciplina que un cambio de esquema de base de datos.

## A.3 La función de traducción: el algoritmo que falta en el Capítulo 5.3

Esta es la pieza técnica concreta que el Libro nunca escribe. Sin ella, "el algoritmo los inyecta con una frecuencia controlada" (línea 209 del Libro) es una frase sin implementación posible.

**Para diales continuos** (agudeza_critica, humor, vehemencia, empatía, densidad_datos), la traducción no debe ser una única frase de directriz — un modelo de lenguaje trata "sé un 73% crítico" como ruido, no como instrucción operable. Debe ser una función de **umbral con ejemplos ancla**, con al menos tres tramos por dial:

```
agudeza_critica ∈ [0, 33)  → directriz_baja   + un ejemplo de párrafo escrito en ese registro
agudeza_critica ∈ [33, 67) → directriz_media  + un ejemplo de párrafo escrito en ese registro
agudeza_critica ∈ [67, 100] → directriz_alta  + un ejemplo de párrafo escrito en ese registro
```

Cada tramo se redacta una vez, se congela en `references/`, y se somete al test de voz (A.5). El "efecto alto: interroga motivos" del Libro se convierte en algo verificable: una directriz de dos frases MÁS un ejemplo concreto de cómo suena un párrafo con `agudeza_critica=85`, para que el modelo tenga un ancla estilística real y no una adjetivación abstracta que interpreta de forma inconsistente pieza a pieza. Este patrón de "directriz + ejemplo ancla por tramo" es exactamente lo que separa un prompt que produce un periodista consistente de uno que produce ruido con la etiqueta de un periodista.

**Para diales combinados**, la función no es lineal independiente por dial: hay interacciones que el Libro no contempla y que producen contradicciones si se tratan por separado. `humor=80` + `agudeza_critica=80` sin regla de combinación produce piezas que leen como sarcasmo hostil, no como columna aguda con gracia — un fallo de tono que ningún test de "presencia de rasgo" detecta porque ambos rasgos individualmente están presentes; lo que falla es la combinación. La Matriz de Combinación de Diales (nueva, ver A.4) resuelve esto declarando reglas explícitas para los pares de mayor riesgo de choque, no dejando que el modelo improvise la mezcla en cada pieza.

## A.4 La Matriz de Combinación de Diales

El Libro tiene una Matriz de Tono por Tipo de Noticia (5.3) que cruza tipo de noticia × tono. Le falta la matriz complementaria: dial × dial, para los pares donde la combinación de valores altos produce un resultado que ninguno de los dos diales por separado predeciría.

| Par de diales en conflicto potencial | Zona de riesgo | Regla de resolución |
|---|---|---|
| `humor` alto + `agudeza_critica` alta | Ambos > 70 | Directriz explícita: "la agudeza ataca argumentos e incentivos, jamás a la persona ni a su dignidad; el humor es la forma, no el arma" — y un ejemplo ancla de ironía crítica vs. sarcasmo personal, para que el modelo tenga el límite escrito, no inferido |
| `vehemencia` alta + `empatia` alta | Ambos > 70 | Directriz: la vehemencia defiende la tesis, la empatía protege al afectado — nunca se vehemencia *contra* una víctima; orden de prioridad explícito cuando compiten en el mismo párrafo |
| `satira` > 0 + `densidad_datos` alta | Satira > 40 y densidad > 70 | La sátira de datos es el subgénero más difícil (parodiar con cifras reales sin falsear ninguna): directriz específica de que todo dato citado en pieza satírica debe ser verificable en el expediente igual que en pieza seria — la sátira licencia el tono, nunca los hechos |
| `formalidad` baja + `vehemencia` alta | Formalidad < 30 y vehemencia > 70 | Riesgo de leer como rant de redes sociales en vez de columna; directriz de estructura argumental obligatoria incluso en registro coloquial |

Esta matriz no es opcional ni decorativa: cada fila necesita su propio caso en el corpus adversarial de A.5, exactamente con el mismo estatus que el corpus anti-inyección de GOVERNANCE §3.4. Un periodista con `humor=85, agudeza_critica=90` sin esta matriz es, con altísima probabilidad, el primer incidente de reputación del cliente.

## A.5 El corpus de regresión de voz: lo que falta en `pl-testing`

`pl-testing` menciona "test de voz (rasgos presentes, vocabulario prohibido ausente)" como si fuera un test unitario trivial. No lo es: es un problema de evaluación de modelos de lenguaje, y tratarlo como un `assertContains()` es la forma más rápida de tener un test que siempre pasa en verde y nunca detecta que la voz se degradó.

Se necesita un **corpus de regresión de voz** por periodista: 15–20 piezas de referencia, curadas manualmente por el propietario del producto durante el Piloto, marcadas como "esto SÍ suena a Valentina". Cada release que toque la Plantilla de Prompt de redacción corre estas piezas de referencia (con proveedor doble determinista, semilla fija) y compara mediante:

1. **Verificación de presencia estructural** (lo que ya cubre `pl-testing`): rasgos verbales presentes en frecuencia dentro de rango, vocabulario prohibido ausente. Necesario pero no suficiente.
2. **Verificación de deriva semántica**: distancia de embeddings entre el corpus de referencia y una muestra nueva generada con la misma configuración de periodista. Una deriva alta con diales sin cambiar es la señal de que un cambio de prompt, un cambio de proveedor de lenguaje, o una actualización de modelo detrás del `LenguajeInterface` alteró la voz sin que ningún test de "presencia de rasgo" lo note — porque la presencia de rasgos superficiales puede sobrevivir intacta mientras el tono de fondo cambia por completo.
3. **Test de discriminación a ciegas** (el propio criterio de salida de la Etapa 2 del Libro, "dos periodistas distinguibles a ciegas", convertido en gate repetible): una muestra de piezas nuevas sin firma, un panel humano (aunque sea de una persona, el propio propietario, con protocolo fijo) que las asigna al periodista correcto. Por debajo de un umbral de acierto, la release no sale — exactamente con el mismo estatus de bloqueo que un test de PHPUnit en rojo.

## A.6 Prioridad de corrección en el Corrector Interno: el algoritmo que falta en 5.6

El Libro define un checklist de seis puntos para el Corrector Interno pero no dice qué pasa cuando tres de los seis fallan a la vez en la misma pieza y hay que decidir en qué orden se ataca la corrección — porque corregir en el orden equivocado puede arreglar un problema y crear otro. Sin esta prioridad, el sistema arriesga a "corregir" el punto 5 (titular) primero, reescribir el gancho, y con eso reintroducir una frase con solapamiento n-grama que el punto 3 ya había marcado como resuelto en la pasada anterior — dos ciclos consumidos sin ganar terreno real, la pieza cae a RETENIDA por agotamiento de intentos en vez de por fallo genuino de calidad.

**Orden de corrección obligatorio, de mayor a menor severidad de reescritura necesaria:**

1. Fallos del **punto 1** (hecho sin respaldo en expediente) — se corrigen primero porque tocar un hecho puede obligar a reescribir el párrafo entero, y todo lo que se corrija después debe partir de la versión ya sanada de hechos.
2. Fallos del **punto 6** (matriz de tono / líneas rojas) — segundo, porque son bloqueantes de sistema (GOVERNANCE §2.2) y su corrección puede exigir cambiar el tono de secciones completas.
3. Fallos del **punto 3** (solapamiento n-grama) — tercero: reescribir la sección con problema de solapamiento, ahora ya con los hechos y el tono corregidos, para no tener que rehacerlo dos veces.
4. Fallos del **punto 2** (proporción interpretativa) — cuarto: suele resolverse expandiendo interpretación sobre los hechos ya corregidos, no removiendo relato, así que va después de que los hechos estén firmes.
5. Fallos del **punto 4** (voz) — quinto: ajuste de superficie sobre una base ya correcta en hechos, tono y estructura.
6. Fallos del **punto 5** (titular anti-clickbait) — último: el titular debe prometer lo que el cuerpo cumple, así que solo tiene sentido fijarlo cuando el cuerpo ya es la versión final.

Este orden es él mismo un test de arquitectura candidato: fixture con una pieza que falla los puntos 1, 3 y 5 simultáneamente, verificar que la segunda pasada corrige en esta secuencia y no en el orden en que el checklist los enumera (que es un orden de verificación, no de reparación — el Libro los confunde implícitamente al no distinguir ambos).

---
# CAPÍTULO B — EL INVESTIGADOR: DE PROTOCOLO EN PROSA A MÁQUINA DE TRIANGULACIÓN

*El Capítulo 4 del Libro describe un protocolo de cinco pasos en lenguaje natural razonable ("triangular", "contextualizar", "detectar el hueco"). Ninguno de los cinco es un algoritmo: son verbos. Un desarrollador que implemente exactamente lo descrito en el Libro construye un Investigador que "intenta triangular" sin que exista una sola regla escrita para el caso que en la práctica ocurre en la mayoría de las noticias reales: dos fuentes de nivel A que se contradicen entre sí.*

## B.1 El caso que el Libro no resuelve: contradicción entre fuentes de máxima confianza

El Libro dice (4.3): "hechos afirmados por 2+ fuentes independientes se marcan como *verificados*". Esto funciona perfectamente cuando dos fuentes coinciden. No dice nada de qué hacer cuando **dos fuentes de nivel A se contradicen entre sí** — que no es un caso raro, es el caso más común en cobertura política y de escándalos corporativos, exactamente los verticales donde PLUMA quiere competir con más ventaja (Capítulo 5.3: "escándalo político" es uno de los cinco tipos de noticia con matriz de tono propia).

Sin una regla explícita, el sistema tiene dos formas de fallar, ambas graves:
- **Fallo A**: el sistema promedia o ignora la contradicción y publica el hecho como si fuera consenso — exactamente lo que GOVERNANCE §2.3 prohíbe explícitamente ("hechos disputados sin señalar → se devuelve a redacción"), pero la regla existe sin el mecanismo que la detecta en primer lugar.
- **Fallo B**: el sistema descarta ambas fuentes por conflicto y pierde la historia entera, cuando la contradicción en sí misma —dos fuentes de nivel A en desacuerdo— es, como el propio Libro reconoce en otra frase ("un hecho disputado es, en sí mismo, un ángulo editorial", 4.2), el mejor ángulo posible que el Investigador puede entregar a la Sala de Redacción.

## B.2 El Algoritmo de Resolución de Disputas

Regla de sistema, no de estilo — porque de aquí depende directamente el estado `disputado` que alimenta la Compuerta de Riesgo (8.2):

1. **Clasificar el tipo de contradicción** antes de intentar resolverla, porque cada tipo tiene una salida distinta:
   - *Contradicción de cifra* (dos números distintos para el mismo hecho: "300 asistentes" vs "3.000 asistentes") → ambas cifras entran al expediente con su fuente y su rango se reporta como tal en la pieza ("las cifras oscilan entre X y Z según la fuente"); nunca se promedia ni se elige una.
   - *Contradicción de atribución* (mismo hecho, distinto responsable señalado: "la empresa afirma que fue un error técnico, el sindicato afirma que fue una decisión deliberada") → ambas versiones entran como *atribuidas* a su fuente respectiva, explícitamente, nunca fusionadas en una sola frase neutra que borre quién dice qué.
   - *Contradicción de ocurrencia* (una fuente afirma que algo pasó, otra que no pasó) → estado `disputado` obligatorio; jamás se resuelve automáticamente eligiendo la fuente "más confiable" del nivel A, porque nivel A no es infalibilidad, es solo la lista de confianza editable del Capítulo 4.3.
2. **Buscar una tercera fuente independiente antes de cerrar el expediente**, solo para contradicciones de ocurrencia — el Investigador no se detiene en la segunda fuente que contradice a la primera: activa una búsqueda dirigida adicional (fuera del flujo estándar de 4–8 coberturas) específicamente para resolver la disputa, con presupuesto de tiempo propio y acotado para no bloquear el pipeline.
3. **Si la tercera fuente no aparece o también disputa**: el hecho queda marcado `disputado` en el expediente con las dos (o tres) versiones y sus fuentes explícitas — este es el estado que la Sala de Redacción debe recibir sin ambigüedad, y es precisamente el estado que dispara, aguas abajo, tanto el ángulo editorial ("el hueco detectado" de 4.2) como el chequeo de GOVERNANCE §2.3 en Compuertas.

## B.3 Jerarquía de fuentes con función de decaimiento temporal

El Libro tiene niveles A/B/C de confianza (4.3) pero los trata como estáticos. En noticias de actualidad, la confianza de una fuente no es solo "quién es", es "quién es, hace cuánto lo dijo, y si ya se desdijo". Un comunicado oficial de nivel A publicado hace 18 horas en una historia que evoluciona rápido pesa menos que el mismo comunicado publicado hace 40 minutos — y el Libro sí reconoce esto en abstracto ("el Publicador re-verifica los hechos críticos si la pieza tardó más de N horas en salir", 4.3) pero no lo convierte en parte del cálculo de confianza del hecho, solo en un gatillo de re-verificación aislado.

**Peso efectivo de un hecho** = `nivel_fuente_base × decaimiento_temporal × factor_independencia`, donde:
- `nivel_fuente_base`: A=1.0, B=0.6, C=0.15 (nunca sustento por sí sola, solo pista — tal como ya especifica el Libro).
- `decaimiento_temporal`: función escalón por tipo de vida útil de la tendencia (del propio Radar, 3.3): para tendencia "relámpago", el decaimiento empieza a las 2 horas; para "ola", a las 24 horas; para "marea", a los 7 días. Esto conecta un dato que el Libro ya calcula en el Radar (clasificación de vida útil) con un módulo donde hoy no se usa (el Investigador), cerrando un circuito que el propio diseño del Libro deja abierto sin necesidad.
- `factor_independencia`: dos fuentes de nivel B que en realidad citan a la misma agencia de noticias original no cuentan como "2+ fuentes independientes" para pasar a estado `verificado` — el Investigador necesita detectar cadena de citación (¿esta fuente B cita a esa otra fuente B, o ambas llegaron al hecho de forma independiente?) antes de contar multiplicidad de fuente como triangulación real. Sin este factor, el sistema puede marcar como "verificado por 2 fuentes" un hecho que en realidad tiene una sola fuente primaria republicada dos veces — el fallo de triangulación más común y menos visible en agregación automatizada de noticias.

## B.4 El algoritmo de detección de hueco: de intuición a heurística verificable

4.2 dice: "responder: ¿qué pregunta obvia nadie está haciendo?" — la frase más importante del capítulo del Investigador, y la menos operable. "Pregunta obvia que nadie hace" no es algo que un modelo de lenguaje sepa producir de forma confiable con una instrucción tan abierta; sin estructura, el resultado son huecos genéricos ("¿y el impacto ambiental?") que no son huecos reales, solo la pregunta reflexiva por defecto que cualquier modelo produce ante cualquier tema.

Heurística de tres pasos, no una sola pregunta abierta al modelo:

1. **Matriz de encuadres cubiertos**: de las 4–8 coberturas secundarias recolectadas, extraer qué *dimensión* cubre cada una (económica, humana, política, técnica, histórica, legal — un vocabulario fijo y corto, no libre). Esto es extracción estructurada, tarea en la que un modelo de lenguaje es fiable; "encuentra la pregunta que falta" sin estructura, no lo es.
2. **Dimensiones ausentes = candidatos de hueco real**: si las 6 coberturas cubren económica, política y humana pero ninguna toca la dimensión legal o histórica, esas dimensiones ausentes son los candidatos de hueco — no una pregunta inventada desde cero, sino la dimensión que el propio corpus recolectado demuestra que la cobertura existente no tocó.
3. **Filtro de sustento**: un hueco candidato solo se anota si el propio expediente ya tiene datos suficientes para sustentarlo (series históricas encontradas en el paso de contextualización, una cifra oficial disponible) — un hueco sin datos propios para llenarlo no es un ángulo, es una promesa que la Sala de Redacción no podrá cumplir, y es exactamente el tipo de tesis que el Paso 3 del Algoritmo de Decisión Editorial (5.5) debería descartar por "sustento en hechos verificados" pero que llega a esa fase ya contaminando los 3–5 candidatos si el Investigador no filtra antes.

# CAPÍTULO C — LAS FUNCIONES QUE FALTAN: DE TABLAS DE PESOS A ARITMÉTICA EJECUTABLE

*El Libro tiene dos tablas de pesos que parecen fórmulas pero no lo son: la Puntuación de Oportunidad del Radar (3.3, cuatro factores con pesos sugeridos) y la asignación de periodista (5.5 Paso 2, cuatro criterios sin pesos). Una tabla con una columna "Peso sugerido" no es una función; es la lista de variables de una función que nadie escribió. Este capítulo la escribe, incluyendo los casos límite donde una fórmula ingenua produce resultados absurdos que un periodista humano jamás produciría.*

## C.1 La Puntuación de Oportunidad no puede ser una suma ponderada simple

La lectura literal de 3.3 sugiere `puntuación = 0.35×velocidad + 0.30×afinidad + 0.20×hueco + 0.15×vida_útil`. Esta fórmula tiene un defecto que se manifiesta en producción en la primera semana: permite que una tendencia con **afinidad cero** (algo completamente ajeno a la línea editorial del sitio: un evento deportivo en un medio de tecnología, por ejemplo) alcance el umbral de cobertura si su velocidad, hueco y vida útil son altos, porque 0×0.30 sigue dejando 70 puntos posibles de los otros tres factores. El resultado es un sitio de tecnología publicando sobre fútbol porque "era viral y nadie lo había cubierto con ángulo" — técnicamente cierto según la fórmula, absurdo según cualquier criterio editorial real, y exactamente el tipo de incoherencia de línea editorial que destruye la autoridad temática que el propio Libro identifica como el activo que Google premia (1.4, E-E-A-T).

**Corrección: afinidad como multiplicador de piso, no como sumando.** La fórmula correcta separa un factor de elegibilidad de los factores de prioridad:

```
elegible = (afinidad ≥ umbral_afinidad_mínima)   // ej. 15/100 — puerta binaria, no ponderación
puntuación_oportunidad = elegible ? (0.40×velocidad + 0.25×hueco + 0.20×vida_útil + 0.15×afinidad_normalizada) : 0
```

Nótese también el reordenamiento de pesos: con afinidad ya actuando como puerta de entrada, su peso residual en la puntuación final baja (de 30% a 15%) y ese margen se redistribuye a velocidad y hueco, que son los factores que realmente diferencian "buena oportunidad" de "oportunidad mediocre" *dentro* del conjunto ya filtrado por relevancia editorial. Esta no es una preferencia estética: es la diferencia entre una fórmula que un editor humano firmaría y una que produce resultados que el propio editor tendría que estar corrigiendo manualmente todos los días — lo cual, para un producto que vende autonomía, es la falla más cara posible.

## C.2 El caso de empate y casi-empate en asignación de periodista

5.5 Paso 2 dice "gana el de mayor puntuación" sobre cuatro criterios sin pesos declarados ni regla de desempate. En un banco de 3–5 periodistas (8.5), los empates y casi-empates no son un caso de esquina raro: son frecuentes, porque el propio diseño busca especialización por vertical (5.8), lo que significa que para muchas piezas solo 1–2 periodistas del banco tienen dominio real del tema y sus puntuaciones van a estar cerca entre sí con alta frecuencia.

**Regla de desempate, en orden**:
1. Si la diferencia de puntuación entre el primero y el segundo candidato es menor a un margen configurable (ej. 5 puntos sobre 100): **no gana automáticamente el de mayor puntuación cruda** — se aplica el criterio de balance de carga (5.5 ya lo menciona como cuarto factor, pero el Libro no aclara que debe promoverse a criterio de desempate cuando hay casi-empate en los otros tres). Esto es lo que en la práctica logra la "diversidad de firmas como señal de redacción real" que el propio 5.5 declara como objetivo: sin esta regla de promoción, el periodista con mejor puntuación base firma sistemáticamente todo su vertical y el balance de carga nunca actúa porque nunca hay un empate exacto a cero decimales que lo dispare.
2. Si el empate persiste tras el criterio de carga: **el historial con la historia específica gana** (quien la empezó, la sigue) — coherencia narrativa sobre puntuación fría, porque un lector que ve a dos periodistas distintos cubriendo la misma historia en semanas sucesivas sin razón editorial percibe incoherencia, no redacción viva.
3. Última instancia: azar vía `AzarInterface` con semilla inyectable (ya exigido por GOVERNANCE §4.3 para todo módulo con azar) — nunca "el primero en el array", que es el bug de desempate más común en implementaciones apresuradas y el que menos se nota en testing porque el orden del array rara vez cambia entre ejecuciones de prueba.

## C.3 El piso de calidad mínima para ganar, no solo el máximo relativo

Ninguna de las dos fórmulas anteriores en el Libro tiene un piso absoluto. Un periodista puede "ganar" la asignación con una puntuación de dominio de 20/100 si es, aun así, el más alto del banco disponible para ese vertical —porque "gana el de mayor puntuación" no distingue entre "gana porque es bueno" y "gana porque es el menos malo de las opciones". La solución no es solo un umbral de dominio mínimo (aunque también hace falta: sin él, un analista de datos con dominio 1/5 en cultura pop puede terminar firmando una pieza viral simplemente porque nadie más del banco estaba disponible ese día).

La solución completa es una **regla de fallback explícita**: si ningún periodista del banco supera el umbral mínimo de dominio para el vertical detectado, la Pieza no se asigna a "el menos malo" — se marca con un estado de espera propio (`SIN_PERIODISTA_IDONEO`, salida lateral nueva del grafo de estados, ver Capítulo E) y se notifica al editor con una sugerencia concreta: crear o ajustar un periodista para ese vertical, o clonar uno existente con las especialidades ampliadas. Esto convierte un fallo silencioso de calidad (una pieza floja, firmada por quien no debía, que sale igual porque "algo tenía que ganar") en una señal de producto útil: el banco de periodistas del cliente tiene un hueco real, y el sistema se lo dice en vez de disimularlo — exactamente la misma filosofía de "escasez honesta" que el Libro ya aplica a la cuota diaria (CLAUDE.md, Capítulo 9), extendida a donde también hace falta y hoy no está: la asignación de autoría.

# CAPÍTULO D — LA IMAGEN DESTACADA: EL HUECO DE PRODUCTO MÁS CARO DEL LIBRO

*6.2 despacha la imagen destacada en una sola frase: "generada o seleccionada de bancos con licencia... jamás imágenes de otros medios". Esta frase esconde una de las decisiones de producto más consecuentes de todo el sistema, y el Libro la trata como un detalle de implementación cuando en realidad es una bifurcación de riesgo legal, de coste y de identidad visual del banco de periodistas.*

## D.1 Por qué "generada o seleccionada" no puede ser una decisión ad-hoc por pieza

Ambas opciones tienen implicaciones que el Libro no compara:

- **Selección de banco con licencia**: requiere una integración con un proveedor de stock (nueva superficie: otro contrato de proveedor externo, con su propio coste por llamada, su propio circuit breaker según el patrón que `pl-proveedor-ia` ya exige para el proveedor de lenguaje) y, más importante, un **algoritmo de relevancia** que hoy no existe en ningún capítulo: ¿cómo decide el sistema que una foto de stock "es" la imagen correcta para una pieza sobre una tendencia específica sin caer en la genericidad visual que delata a cualquier sitio de bajo esfuerzo (la misma foto de "manos escribiendo en laptop" en cien artículos de tecnología distintos, la señal visual más reconocible de contenido de granja)?
- **Generación con IA**: resuelve la genericidad pero abre una superficie de riesgo que el Capítulo 12 (Seguridad) nunca menciona: ¿qué proveedor de generación de imágenes, con qué política de uso comercial, con qué riesgo de que el modelo reproduzca —sin que nadie lo note hasta la carta de cese y desista— un estilo, personaje o marca registrada de un tercero? El Libro tiene una compuerta de Originalidad completa para texto (8.3) y cero compuerta equivalente para imagen.

## D.2 La Compuerta de Originalidad Visual: el gemelo faltante de 8.3

Toda pieza con imagen generada por IA debe pasar, antes del Publicador, un chequeo específico — no el mismo chequeo de n-gramas de texto, que no aplica a píxeles, sino su equivalente funcional:

1. **Verificación de prompt de generación limpio de marca**: el prompt de imagen no puede contener nombres de personas reales identificables, marcas, personajes con IP conocida, ni instrucciones de estilo que referencien un artista vivo o un estudio con propiedad intelectual activa (Disney, Marvel, y equivalentes) — esta es la misma clase de restricción de "contenido protegido" que cualquier proveedor serio de generación de imágenes ya aplica en su propia capa, pero PLUMA no puede asumir que el proveedor lo bloquea siempre: necesita su propia lista de bloqueo previa a la llamada, exactamente con el mismo espíritu que la lista negra de fuentes del Investigador (4.3).
2. **Registro de procedencia en el expediente de auditoría** (8.4): qué proveedor generó la imagen, con qué prompt exacto, en qué fecha — porque el día que un cliente reciba un reclamo de derechos sobre una imagen publicada, la pregunta legal inmediata es "¿de dónde salió esa imagen y con qué instrucciones se generó", y el sistema debe poder responderla en segundos, con la misma filosofía de trazabilidad total que el Libro exige para texto (P6) y aún no extiende a imagen.
3. **Consistencia visual por periodista, no solo por pieza**: si el banco de periodistas es "el activo del cliente" (CLAUDE.md), su identidad visual —el avatar, y opcionalmente un estilo de imagen destacada reconocible asociado a su sección— es parte de esa marca. Una paleta o estilo visual por periodista (declarado en su Identidad, Capítulo 5.2, campo nuevo) evita el efecto contrario al deseado: que la generación por pieza produzca variación visual tan alta que el sitio se sienta, precisamente, como lo que el Libro entero busca evitar en el texto — la ausencia de una voz reconocible, ahora en la capa visual.

## D.3 El fallback cuando ninguna opción produce una imagen aceptable

Ni el Libro ni ningún capítulo de este documento hasta aquí especifica qué pasa si la generación falla (proveedor caído, contenido rechazado por su propio filtro) y el banco de stock tampoco tiene un resultado relevante para una tendencia muy específica y reciente (que es, estructuralmente, el caso más común: cuanto más "relámpago" es la tendencia según la clasificación del Radar de 3.3, menos probable es que exista ya una foto de banco relevante). La regla, análoga a la de "escasez honesta" que gobierna toda la cuota de publicación: **una Pieza sin imagen aceptable no se publica sin imagen ni con una imagen genérica de relleno — se retiene con motivo explícito** (`RETENIDA: sin_activo_visual`), nunca se degrada silenciosamente el estándar visual para no bloquear la cuota del día. Publicar sin imagen destacada en un artículo de noticias es una degradación de producto tan visible para el lector como publicar con un titular clickbait, y el Libro ya trata esto último como línea roja (Corrector Interno, punto 5); la imagen merece el mismo estatus.

---

# CAPÍTULO E — EL GRAFO DE ESTADOS AMPLIADO Y EL CICLO DE VIDA COMPLETO DEL PERIODISTA

*`pl-pipeline/references/estados.md` tiene un grafo limpio y correcto para el camino feliz. Le faltan las salidas laterales que este mismo documento ya ha obligado a introducir, y el Libro deja sin resolver un segundo ciclo de vida completo: qué pasa con la memoria de un periodista jubilado, que el Capítulo 5.8 menciona en una frase ("jubilar: sus piezas quedan, deja de recibir asignaciones") sin especificar el problema real que esconde.*

## E.1 Estados nuevos que este documento hace necesarios

El grafo actual (`DETECTADA → ... → PUBLICADA`, con `RETENIDA`/`DESCARTADA`/`FALLIDA` como laterales) necesita, a la luz de los capítulos anteriores, dos adiciones que no son opcionales si se implementan los algoritmos de B, C y D:

- **`SIN_PERIODISTA_IDONEO`** (lateral, desde el paso de asignación en EN_REDACCION): la salida honesta de C.3 cuando ningún periodista del banco supera el umbral de dominio. Reanudable a EN_REDACCION tras intervención del editor (ajuste de banco) o DESCARTADA si la tendencia caduca mientras espera.
- **`RETENIDA: sin_activo_visual`** como motivo específico dentro del estado `RETENIDA` ya existente (no un estado nuevo, pero sí un motivo de primera clase en el diagnóstico que ve la Sala de Revisión — hoy `RETENIDA` no distingue en el Libro entre "falló compuerta de calidad", "falló compuerta de riesgo" y "sin imagen", y la pantalla de Sala de Revisión (10.2) promete precisamente diagnóstico exacto, que sin este detalle no puede cumplir del todo).

## E.2 El problema no resuelto de la memoria del periodista jubilado

5.8 dice que jubilar un periodista significa que "sus piezas quedan, deja de recibir asignaciones". Esto resuelve el problema de datos (no se borra nada) pero no el problema editorial: **¿qué pasa con las posturas que ese periodista defendió cuando otro periodista, activo, cubre una historia relacionada meses después?**

La Memoria Editorial (5.4) es por periodista, consultada "antes de tesis" para detectar contradicción con la propia postura previa del mismo periodista. Pero un periodista nuevo (o uno existente asignado por primera vez a ese vertical) no tiene forma, según el diseño actual, de saber que el sitio —como voz colectiva, no como individuo— ya defendió una postura sobre ese tema a través de un periodista que ya no está activo. El resultado, sin corrección, es que el sitio como conjunto puede contradecirse a sí mismo con total naturalidad simplemente porque la persona que dijo lo contrario ya no firma — un fallo de coherencia editorial invisible para el sistema pero completamente visible para cualquier lector recurrente que recuerde la cobertura anterior, que es exactamente el tipo de lector que PLUMA más necesita retener (P6, trazabilidad, existe en parte para servir a este lector).

**La corrección**: la consulta de memoria antes de tesis (5.5 Paso 3) no debe limitarse a la memoria del periodista asignado — debe incluir una consulta a la **memoria colectiva del sitio** (una vista agregada de posturas por tema, indexada across todos los periodistas, activos y jubilados). Si la postura previa fue de un periodista jubilado, el reconocimiento en texto cambia de forma ("hace tres meses defendí lo contrario" no aplica si no es la misma persona) a una atribución editorial de sitio ("esta redacción sostuvo antes una lectura distinta de este tema; estos datos nos obligan a matizarla") — mismo principio de honestidad ante la propia postura anterior, adaptado a que la voz colectiva del medio, no solo la de un individuo, es lo que un lector recurrente en realidad rastrea.

# CAPÍTULO F — EL MODO RESPETO: DE FRASE A MÁQUINA DE ESTADOS PROPIA

*9.2 menciona el "modo respeto" en una frase: "congela humor y sátira en todo el sitio ante una tragedia mayor (activable manualmente o por señal del clasificador de gravedad del Radar)". Esta es, sin exagerar, una de las funciones de mayor impacto reputacional de todo el producto — el momento en que un sitio sigue publicando chistes mientras el país entero está de luto es el tipo de incidente que se vuelve captura de pantalla viral y cierra un medio— y el Libro le dedica menos espacio que a la elección de tipografía del panel (10.1).*

## F.1 Por qué "señal del clasificador de gravedad del Radar" no es suficiente

El Radar clasifica gravedad por tendencia individual (3.3 lo implica, aunque no lo declara como campo explícito). El modo respeto necesita algo distinto: una señal *agregada* que distinga "una tendencia grave entre muchas normales" de "el país está en un evento de gravedad excepcional ahora mismo". Sin esa distinción, el sistema tiene dos formas de fallar: activar modo respeto por cada tragedia individual normal del ciclo de noticias diario (lo cual paraliza permanentemente a los periodistas satíricos y de humor del banco, defeats the purpose de tenerlos) o no activarlo nunca porque ninguna tendencia individual cruza un umbral pensado para catástrofes verdaderamente excepcionales.

## F.2 El disparador de dos niveles

**Nivel automático (activación por sistema, reversible solo por el editor, nunca por el propio sistema — asimetría deliberada: activar de más cuesta un chiste perdido, desactivar de más cuesta un incidente)**: se activa cuando 2 o más tendencias clasificadas como gravedad máxima (el nivel más alto del eje de gravedad 0–100 de 5.5 Paso 1) aparecen en la misma ventana de tiempo corta (ej. 3 horas) Y comparten el mismo campo temático o geográfico — la coincidencia temática es la que distingue "múltiples tragedias distintas y normales del ciclo de noticias" de "un solo evento excepcional generando múltiples tendencias derivadas" (un atentado genera tendencias sobre víctimas, sobre el sospechoso, sobre reacciones oficiales — todas gravedad máxima, todas del mismo campo).

**Nivel manual**: un botón de un clic (ya previsto en 9.2) que el editor puede activar sin esperar señal del sistema — porque hay eventos de gravedad nacional donde la intuición humana llega antes que cualquier agregación algorítmica de señales de tendencia, y el diseño no debe apostar todo a la detección automática cuando la alternativa manual cuesta un solo clic.

## F.3 Qué hace exactamente el modo respeto, más allá de "congelar humor y sátira"

- Toda pieza `EN_REDACCION` o anterior en el pipeline, de cualquier periodista, se re-evalúa contra la matriz de tono con el tipo de noticia forzado temporalmente hacia el perfil de "Tragedia" del propio periodista (5.3), independientemente de su clasificación real — no solo se bloquea sátira en la pieza sobre el evento grave: se degrada el registro de **todo** lo que el sitio publique mientras el modo esté activo, incluyendo piezas de verticales no relacionados, porque un chiste de cultura pop publicado la misma tarde que la tragedia nacional lee igual de mal aunque el chiste no toque el tema — el contexto del sitio entero es lo que un lector percibe, no el tema pieza por pieza.
- Piezas ya `PROGRAMADA` que no sean sobre el evento en cuestión: pausadas, no descartadas — la cola se reactiva completa al desactivar el modo, con el jitter de horario recalculado desde cero (publicar seis piezas de golpe al reactivar delataría automatización tan claramente como publicarlas exactamente en punto).
- Duración mínima configurable con piso de fábrica (no editable a la baja, igual que los umbrales de compuertas en 8.3): un mínimo razonable de horas que impide que un editor apurado desactive el modo respeto en los primeros quince minutos por presión de cumplir la cuota del día — la misma filosofía de "la degradación por sensibilidad está por encima de toda configuración de usuario" (2.4) aplicada aquí a su duración, no solo a su activación.

---

# CAPÍTULO G — DOS AUSENCIAS ESTRUCTURALES QUE EL LIBRO NO CONTEMPLA EN NINGÚN CAPÍTULO

## G.1 El modelo de amenaza del propio Radar: manipulación de tendencias como vector de ataque

El Capítulo 12 (Seguridad) cubre exhaustivamente la neutralización de inyección de prompts en el material de fuentes que llega al Investigador y al redactor (12.1, GOVERNANCE §3.4) — una superficie de ataque real y bien resuelta. Pero existe una superficie de ataque anterior en el pipeline, en el módulo con menos escrutinio de todo el sistema, y el Libro no la menciona en absoluto: **el Radar consume señales externas (Trends, RSS, social) que son, por definición, manipulables por terceros con incentivo para manipularlas** — campañas de manipulación coordinada de tendencias (brigading, bots de amplificación social) existen hoy como categoría de abuso documentada en las mismas plataformas que el Radar consume como fuente.

Un sitio que publica de forma autónoma sobre "lo que está en tendencia" sin verificar la naturaleza orgánica de esa tendencia es, estructuralmente, un vector que terceros pueden usar para inducir cobertura editorial de un ángulo que ellos eligieron — inyectando una tendencia artificial y dejando que PLUMA haga el trabajo de darle cobertura con la credibilidad de un "periodista sintético" firmando encima. Esto no es un caso hipotético de laboratorio: es la aplicación directa, contra un sistema autónomo, de la misma clase de ataque de "amplificación coordinada" que ya se documenta contra sistemas de tendencias humanos.

**La compuerta que falta**: antes de que una tendencia entre a la cola editorial (3.3, "las tendencias que superan el umbral... entran a la cola"), necesita una verificación de **naturalidad de la señal** — patrones de crecimiento consistentes con difusión orgánica (curva de adopción gradual entre fuentes diversas) versus patrones consistentes con amplificación coordinada (pico sincronizado desde cuentas o fuentes con poca huella previa, concentración geográfica o de red anómala). Esto no es una compuerta de calidad del contenido —esa ya existe en el Capítulo 8— es una compuerta de **legitimidad del insumo**, y su ausencia total en un documento que por lo demás es meticuloso en seguridad (12.1 completo) es la omisión estructural más seria de todo el Libro: significa que el sistema puede ejecutar perfectamente cada regla de las tres compuertas de contenido y aun así publicar, con total corrección procedimental, la pieza exacta que un actor malicioso quería que publicara, porque el punto de entrada nunca se auditó.

## G.2 SatiricalArticle: el tipo de schema.org que el Capítulo 6 no menciona y que resuelve un problema real del producto

6.2 lista `NewsArticle`, `AnalysisNewsArticle` y `OpinionNewsArticle` como los tipos de datos estructurados disponibles ("la distinción existe en schema.org y casi nadie la usa: ventaja"). Es una observación correcta y bien traída — y por eso mismo resulta más notable que se detenga un tipo antes de donde el propio razonamiento del Libro la lleva: schema.org tiene también `SatiricalArticle` (actualmente en estado "pending" de la especificación, igual que `AnalysisNewsArticle` y `OpinionNewsArticle`, que el Libro sí usa pese a su mismo estado de desarrollo — no hay razón de madurez de la especificación para omitir una y no las otras).

Para un producto cuyo banco de periodistas incluye explícitamente "un cronista satírico para cultura y virales" (5.8) y cuya matriz de tono permite "pieza completa" satírica para contenido de cultura/viral (5.3), declarar `SatiricalArticle` en el schema de esas piezas específicas no es un detalle técnico menor: es la señal más directa y máquina-legible posible de que el contenido es intencionalmente satírico, dirigida tanto a Google como a los sistemas de verificación de terceros y a los propios agregadores de IA que —como confirma la evidencia actual del ecosistema— cada vez citan y resumen contenido de noticias directamente desde estos metadatos. Un artículo satírico marcado como `NewsArticle` genérico corre un riesgo real de desinformación involuntaria si un sistema de terceros (o un lector apresurado) lo cita fuera de contexto como si fuera reportaje factual — exactamente el tipo de incidente reputacional que ninguna compuerta de las tres del Capítulo 8 está diseñada para prevenir, porque las tres evalúan la pieza en sí, no cómo terceros la clasificarán al re-publicarla o citarla. Marcar correctamente el tipo de schema es, en este sentido, una extensión directa y de bajo coste de la Compuerta de Riesgo — declarar la naturaleza del contenido en la capa que las máquinas leen, no solo confiar en que un lector humano entienda el tono por contexto.

# CAPÍTULO H — LA MANERA DE PENSAR: TRES CASOS TRABAJADOS DE PRINCIPIO A FIN

*Pediste explícitamente "la manera de pensar". Los capítulos A–G dan los mecanismos; este capítulo los pone a trabajar juntos sobre tres situaciones reales, del tipo que un periodista humano resuelve por intuición entrenada y que un sistema sin esta capa resuelve mal, con total corrección procedimental aparente. La prueba de que un "newsroom sintético" (P1 del Libro) es real y no un disfraz de generador es que puede recorrer estos tres casos sin que nadie tenga que intervenir a mano.*

## H.1 Caso: la cifra que cambia mientras se escribe la pieza

Una tendencia "relámpago" (Radar, 3.3) sobre un accidente industrial entra al pipeline. El Investigador triangula: 3 fuentes de nivel B reportan "12 heridos". La Pieza avanza a EN_REDACCION, el periodista asignado (dominio 4/5 en el vertical, ganó la asignación por C.2 sin necesidad de desempate) redacta la primera pasada. Mientras el Corrector Interno hace su segunda pasada, han transcurrido 95 minutos desde la detección — más del umbral de re-verificación que el propio Libro exige para hechos críticos en piezas que tardan (4.3).

**Cómo piensa el sistema, capítulo por capítulo de este documento:**
1. (B.3, decaimiento temporal) La clasificación "relámpago" de la tendencia activa el decaimiento a las 2 horas — a los 95 minutos, el hecho "12 heridos" ya está en zona de decaimiento aunque no haya expirado del todo.
2. El Publicador (9.4, ya lo exige el Libro) dispara re-verificación antes de la ranura de publicación. La re-verificación encuentra una actualización: una fuente de nivel A (autoridad local) ahora dice "9 heridos, 3 dados de alta".
3. (B.2, algoritmo de resolución de disputas) Esto no es una contradicción de ocurrencia — es una actualización temporal legítima, no un desacuerdo entre fuentes contemporáneas. El sistema no marca `disputado`: reemplaza el hecho con sello temporal nuevo, conserva el hecho anterior en el historial del expediente (nunca se borra, GOVERNANCE §1.2 exige versión, no sobreescritura).
4. La Pieza, que ya pasó Corrector Interno con la cifra vieja, **vuelve a EN_REDACCION** (no a una corrección cosmética de la cifra sola) porque el Corrector debe re-verificar el punto 1 de su checklist (todo hecho trazable al expediente) contra el expediente actualizado — cambiar solo el número sin re-pasar el Corrector completo es exactamente el tipo de "atajo silencioso" que CLAUDE.md prohíbe explícitamente en su Santo Grial (§2, cero complacencia).
5. El resultado: una pieza que sale con la cifra correcta y, si el cambio es sustantivo, con la mención explícita del ajuste ("una actualización de la autoridad local revisó la cifra inicial a la baja") — que es, además, exactamente el tipo de señal de rigor que separa una redacción real de un agregador, y el tipo de detalle que ninguna fórmula del Libro v1.0 habría producido porque el circuito completo (Radar clasifica vida útil → Investigador hereda esa clasificación para decaimiento → Publicador re-verifica → Corrector re-corre completo, no parcial) no estaba conectado en ningún capítulo existente.

## H.2 Caso: el periodista satírico y la noticia que empieza como viral ligero y se revela grave a medio camino

Un periodista satírico (dominio 5/5 en "cultura/viral", diales `humor=85, satira=70, agudeza_critica=60`) recibe asignación para una tendencia clasificada inicialmente como "viral ligero" (5.5 Paso 1, eje de gravedad bajo). Redacta la primera pasada en tono plenamente satírico, como permite la matriz de tono para ese tipo de noticia (5.3). A mitad del ciclo de investigación adicional que el Corrector dispara al verificar el punto 3 (solapamiento, que lo lleva a revisar fuentes adicionales), aparece un dato nuevo: lo que empezó como un video viral de un incidente menor en realidad involucra a un menor de edad como parte afectada — información que no estaba en el expediente original porque las primeras fuentes recolectadas no la mencionaban.

**Cómo piensa el sistema:**
1. Esto no es un fallo del Corrector Interno ni del Investigador original — es exactamente el escenario que B.4 (detección de hueco) y la propia filosofía de trazabilidad (P6) anticipan: la información nueva llega, y el sistema necesita un mecanismo de **re-clasificación retroactiva**, no solo de re-verificación de cifras (que es lo único que H.1 cubre).
2. (8.2, Compuerta de Riesgo, ya existente en el Libro) "Sensibilidad temática: menores → degradación automática de modo y bloqueo absoluto de sátira/humor". La Pieza, que llevaba diales de sátira 70 inyectados en su primera pasada completa, no puede simplemente "bajar el dial" a mitad de camino — necesita **reescritura completa desde el Paso 3 del Algoritmo de Decisión Editorial** (5.5), no una pasada de corrección cosmética, porque la arquitectura entera de la pieza (5.5 Paso 4: gancho → hechos → desarrollo → contraargumento → remate) fue construida sobre una tesis y un tono que la nueva información invalida por completo, no solo en la superficie.
3. Esto es la aplicación concreta de una regla que A.6 (prioridad de corrección) ya establece en abstracto: un fallo del punto 6 (matriz de tono / líneas rojas) se corrige *antes* que ajustes de superficie — pero este caso es más severo que lo que A.6 contempla, porque el fallo de tono aquí no es un ajuste de párrafo, es una invalidación de tesis completa. El sistema necesita una regla explícita que A.6 no cubre: cuando la re-clasificación de sensibilidad (8.2) ocurre **después** de que el Paso 3 (selección de ángulo) ya se ejecutó, la Pieza no entra al ciclo normal de corrección de 2 intentos — regresa directamente a EN_REDACCION desde el Paso 1 (reclasificación completa de la noticia), como si fuera una Pieza nueva con el expediente actualizado, y ese regreso no cuenta contra el límite de 2 ciclos de corrección del Corrector Interno (5.6), porque no es un fallo de calidad de redacción: es un cambio del terreno sobre el que se redactó.
4. Ningún capítulo del Libro v1.0 especifica este camino porque el Libro trata "clasificación de la noticia" (Paso 1) y "compuerta de riesgo" (Capítulo 8) como fases secuenciales que ocurren una vez cada una — cuando en la práctica, información que cambia la clasificación puede (y con cierta frecuencia, va a) aparecer en cualquier punto del pipeline, no solo al principio.

## H.3 Caso: dos periodistas del banco, ambos con dominio alto, en desacuerdo real sobre el ángulo

Una tendencia de dato económico (5.3, tipo de noticia con matriz de tono propia: "analítico, persuasivo, sin sátira") tiene afinidad alta con dos periodistas del banco: el analista de datos sobrio (dominio 5/5, línea editorial "escéptica del poder") y la columnista crítica vehemente (dominio 4/5, línea editorial distinta). Sus puntuaciones de asignación (C.2) quedan dentro del margen de casi-empate.

**Cómo piensa el sistema:**
1. (C.2, regla de desempate) Se promueve el balance de carga: si el analista ya firmó 2 piezas hoy y la columnista 0, gana la columnista — no porque su puntuación de dominio fuera mayor (no lo era), sino porque el desempate explícito lo decide así, y esta es precisamente la mecánica que hace que "la diversidad de firmas [sea] señal de redacción real" (5.5) en vez de una frase decorativa sin implementación.
2. La columnista redacta con su propia línea editorial, que puede diferir marcadamente de cómo el analista habría enmarcado el mismo dato — esto no es un bug, es exactamente el Principio P2 del Libro funcionando ("un lector que lea tres artículos del mismo periodista debe reconocer la voz"), extendido a su consecuencia lógica: dos periodistas distintos frente al mismo dato producen ángulos distintos, y el sistema no debe intentar hacerlos converger a una "verdad neutra promedio" — esa neutralidad promediada es, de hecho, lo opuesto de lo que P1 define como el producto ("el criterio es el producto").
3. Donde el sistema sí necesita un mecanismo que el Libro no da: si en el futuro el analista cubre la misma historia (una actualización del dato, un "segundo golpe" según 3.4), la Memoria Colectiva del Sitio (E.2, la extensión que este documento añade a 5.4) debe advertirle que la columnista ya defendió una lectura del tema — no para forzar coincidencia, sino para que, si el analista llega a una conclusión distinta, el sistema pueda ofrecer al editor humano (en la Sala de Revisión, 10.2) una señal explícita: "este ángulo diverge de la cobertura previa del sitio sobre este tema, firmada por otro periodista" — información que hoy el editor solo descubriría leyendo ambas piezas manualmente, y que el sistema tiene todos los datos para levantar de forma proactiva.

---

# CAPÍTULO I — QUÉ IMPLEMENTAR PRIMERO, Y LA AUTOCRÍTICA QUE ESTE DOCUMENTO SE DEBE A SÍ MISMO

*El propio proyecto exige (`lg-critical-review`, SKILLS-STACK §1) que ninguna propuesta significativa se entregue sin autocrítica. Este capítulo cumple esa regla sobre el propio contenido de los Capítulos A–H, y traduce las diez piezas de la tabla siguiente en una secuencia realista dentro del PLAN-MAESTRO existente — porque un documento que exige mecanismo en vez de intención y luego se entrega como una lista plana sin priorizar incurriría en el mismo defecto que le señala al Libro original.*

## I.1 Mapeo a las Etapas existentes — no se añade una Etapa nueva, se engorda el criterio de salida de las que ya existen

| Pieza de este documento | Etapa del PLAN-MAESTRO donde vive | Por qué ahí y no antes ni después |
|---|---|---|
| A.2–A.6 (prompts versionados, función de traducción de diales, corpus de voz) | **Etapa 2** | Es literalmente el contenido de "El periodista" — el criterio de salida actual ("dos periodistas distinguibles a ciegas") es *imposible* de verificar sin A.5, así que A no es una mejora de la Etapa 2: es la parte que faltaba para que su propio criterio de salida sea alcanzable |
| B.1–B.4 (resolución de disputas, decaimiento temporal, detección de hueco estructurada) | **Etapa 1**, extendiendo el Investigador que ya se construye ahí | El decaimiento temporal (B.3) depende de la clasificación de vida útil del Radar, que se construye en Etapa 1 — moverlo a una etapa posterior obligaría a retrabajar el Investigador dos veces |
| C.1–C.3 (fórmulas de Radar y asignación con piso y desempate) | **Etapa 1** (C.1, fórmula del Radar) y **Etapa 2** (C.2–C.3, asignación de periodista) | C.1 no puede esperar: es la fórmula que decide qué entra a la cola editorial desde el primer sensor construido en Etapa 1 |
| D.1–D.3 (compuerta de originalidad visual, fallback sin imagen) | **Etapa 3**, junto con las otras tres compuertas | Coherente con que el Libro ya agrupa las compuertas en esa etapa; construir la compuerta de texto sin la visual en la misma etapa deja un hueco de riesgo abierto exactamente donde el Libro cierra los otros dos |
| E.1–E.2 (estados nuevos, memoria colectiva del sitio) | **Etapa 2** (memoria colectiva, ya que la memoria individual se construye ahí) y **Etapa 1** (estados nuevos del grafo, junto al resto de la máquina de estados) | — |
| F.1–F.3 (modo respeto como máquina propia) | **Etapa 3**, junto a Copiloto/Autónomo con degradación — es, en esencia, otra forma de degradación por sensibilidad, la misma familia de mecanismo que el Libro ya sitúa ahí |
| G.1 (naturalidad de señal del Radar) | **Etapa 1**, como parte del propio Sensor de Trends — no puede ser una adición posterior sin volver a auditar cada sensor que ya esté en producción para entonces |
| G.2 (SatiricalArticle en schema) | **Etapa 3**, junto al resto del Motor SEO | — |
| H.1–H.3 (los tres flujos completos) | No son una pieza de código: son **casos de aceptación** que deben incorporarse literalmente al corpus de tests de las Etapas 1–3 correspondientes, como fixtures de integración, exactamente con el mismo estatus que el corpus adversarial que GOVERNANCE §3.4 ya exige para inyección de prompts |

## I.2 Autocrítica: dónde este propio documento puede estar equivocado

Siguiendo la misma disciplina que le pide al Libro original (`lg-critical-review`, Fase 1):

- **La Matriz de Combinación de Diales (A.4)** enumera cuatro pares de riesgo, pero con un banco de ocho diales el número real de pares posibles es 28. No es exhaustiva ni pretende serlo — el riesgo real es que un editor construya un quinto periodista con una combinación de diales no cubierta por la matriz y el sistema no tenga regla explícita para ese par. La mitigación correcta no es enumerar los 28 pares por adelantado (sería trabajo especulativo sobre combinaciones que quizás nunca se usen, violando el propio principio de "cero invención" de CLAUDE.md §4): es que el corpus de regresión de voz (A.5) se extienda automáticamente cada vez que se crea un periodista con una combinación de diales fuera de los tramos ya cubiertos, generando el caso de prueba bajo demanda en vez de por adelantado.
- **El disparador de dos niveles del modo respeto (F.2)** usa un umbral de "2 o más tendencias de gravedad máxima en 3 horas" que este documento no puede validar empíricamente sin datos reales de producción — es un punto de partida razonado, no un número probado. Debe tratarse como configuración ajustable desde el primer despliegue, con revisión obligatoria tras el primer evento real que lo dispare (o que debería haberlo disparado y no lo hizo), no como una constante de sistema al nivel de los pisos de compuertas de calidad.
- **El modelo de amenaza del Radar (G.1)** es, de las diez piezas de este documento, la que exige más investigación previa a la implementación real (CLAUDE.md §4, "requisito ambiguo = STOP + pregunta", aplica aquí con fuerza total): detectar amplificación coordinada de tendencias es un problema activo de investigación en la industria de confianza y seguridad, no un algoritmo que este documento puede especificar con la misma precisión aritmética que C.1. Lo correcto para la Etapa 1 no es un detector sofisticado desde el primer sensor, sino el reconocimiento explícito del riesgo más una heurística mínima defendible (concentración de fuente, novedad de las cuentas de origen donde el sensor lo exponga) con compromiso declarado de profundizarlo — exactamente el tratamiento que GOVERNANCE exige para toda deuda técnica: registrada con etapa de pago, nunca escondida.

## I.3 Cierre

El Libro de Arquitectura resuelve, con solidez real, la pregunta "¿qué debe tener un newsroom sintético para no ser un spinner con maquillaje?". Este documento intenta resolver la pregunta que queda abierta después de esa: "¿y cómo decide, exactamente, en el momento en que dos fuentes de confianza se contradicen, dos periodistas empatan, o la noticia cambia de naturaleza a mitad de la redacción?". Un plugin que solo responde la primera pregunta es, con toda la disciplina de ingeniería del mundo detrás (y este proyecto la tiene, de sobra, en su capa de gobernanza), todavía un generador con una anatomía convincente. Un plugin que responde ambas es, quizás, lo más cercano a la tercera categoría que el propio prólogo del Libro se propone alcanzar y que casi nadie en este mercado ha construido de verdad.

*— Fin del complemento —*
