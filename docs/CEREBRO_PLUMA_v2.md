# NÚCLEO COGNITIVO PLUMA (NCP)
## Cerebro híbrido, multilingüe y universal — Arquitectura y encargo
*Anexo al canon (Libro v1.0 + Niveles Dos, Tres y Cuatro) — Versión 2.0 · Sustituye a CEREBRO_PLUMA v1.0*

> **⚠ Corrección viva — `docs/decisiones/0024-ncp-reorientacion-retiro-t3-cerebro-pure-php.md`** (2026-08-03):
> el propietario aclaró que la intención original nunca fue T3 (cerebro remoto,
> un servicio HTTP externo que el cliente debe operar) — era un cerebro
> embebido en el propio plugin, sin infraestructura externa alguna. Investigación
> real confirmó que T1 (ONNX vía FFI) tampoco es viable de forma confiable en
> hosting compartido (`ffi.enable` por defecto en `"preload"`, deshabilitado en
> peticiones web normales). **NLI y RRK se retiraron de la matriz de transportes
> T1–T4 de las Partes 1.3/3.1/3.2 de abajo y pasan a ser PHP puro (Rubix ML),
> siempre disponibles, sin transporte que sondear.** Las Partes 1.3/3.1/3.2 de
> este documento describen la arquitectura ORIGINAL (histórica, no reescrita
> aquí) — para el estado real de NLI/RRK, lee el ADR 0024, no estas secciones.

---

# PARTE 0 — VEREDICTO SOBRE ONNX RUNTIME

## 0.1 Qué sí desbloquea, qué no, y por qué eso cambia la tesis

**No desbloquea** el generativo en hosting compartido. Un decodificador de 7B+ exige memoria, proceso persistente y tiempo de ejecución que un plan compartido no da. Eso no es una limitación que se supere con mejor ingeniería: es el entorno.

**Desbloquea algo mejor**: los modelos *encoder* — embeddings, clasificadores, NER, cross-encoders, NLI — son pequeños (20–500 MB cuantizados a int8), corren en CPU en decenas o cientos de milisegundos, son deterministas con la misma entrada, y ONNX es su formato portable universal. Esto convierte **la verificación y la clasificación en tareas que ya no necesitan ningún modelo generativo**. Y ahí vive todo el riesgo legal y de calidad del producto.

**Los tres saltos concretos sobre el canon existente:**

1. **De similitud a implicación.** N3-J.3 especificó la verificación de trazabilidad con similitud de embeddings. La similitud dice "esto se parece a aquello". Un modelo **NLI** dice algo categóricamente más fuerte: si el extracto del expediente **implica** (ENTAILMENT), es **neutral** frente a, o **contradice** (CONTRADICTION) la afirmación del borrador. La regla de oro anti-alucinación (GOVERNANCE §2.4) pasa de heurística a verificación con etiqueta.
2. **La detección de disputas se vuelve nativa.** N2-B.2 clasifica contradicciones entre fuentes con reglas. La contradicción entre dos extractos ES la etiqueta CONTRADICTION del mismo modelo NLI. El Investigador gana un detector real sin una sola llamada generativa.
3. **Arranque en frío resuelto.** Todo clasificador del canon (tipo de noticia, gravedad, sensibilidad, dimensión de encuadre, comentarios, postura del señalado) puede operar **zero-shot vía NLI** desde el día uno, sin datos del cliente, y ser reemplazado más tarde por un clasificador destilado entrenado con el archivo real de ese cliente. Sin este truco, todos esos clasificadores necesitaban un LLM de pago o meses de datos.

## 0.2 La tesis corregida del cerebro

El cerebro no es "local que sustituye a la API". Es **híbrido por diseño, con la API como acelerador opcional y enchufable**, y con una consecuencia que lo hace superior a cualquiera de los dos puros: si el redactor es la API de pago y el verificador es ONNX local, se cumple de forma nativa el requisito de **familias de modelo distintas** que N3-J exige para el modo Autónomo. El híbrido no es el plan barato: es el plan correcto.

## 0.3 El límite honesto que ninguna arquitectura evita

ONNX no escribe la columna. La generación de prosa editorial con voz seguirá exigiendo, según el entorno: modelo generativo local (VPS/dedicado), cerebro remoto propio, API de pago, o el humano (Modo Redacción Asistida). Cualquier promesa distinta es un generador de plantillas disfrazado, y eso mata el producto bajo scaled content abuse. **El NCP hace que todo lo demás deje de depender de un generador; no finge generar sin generador.**

---

# PARTE 1 — ARQUITECTURA DEL NÚCLEO COGNITIVO

## 1.1 Los cinco planos

```
┌─────────────────────────────────────────────────────────────────────┐
│ P4 · ENRUTADOR COGNITIVO   política por propósito × contexto × entorno│
│      + Sonda de Capacidades + Escalada por calidad + Auditoría        │
└───────┬─────────────────────────────────────────────────────────────┘
        │
 ┌──────┴───────┬──────────────┬──────────────────┬───────────────────┐
 │ P0 LÉXICO    │ P1 SEMÁNTICO │ P2 GENERATIVO    │ P3 HUMANO         │
 │ PHP puro     │ ONNX encoders│ decoder o API    │ Redacción Asistida│
 │ cero modelo  │ 20–500 MB    │ local/remoto/pago│ el humano escribe │
 │ SIEMPRE      │ casi siempre │ según entorno    │ siempre disponible│
 └──────────────┴──────────────┴──────────────────┴───────────────────┘
```

Ningún plano es opcional en el diseño; todos lo son en el despliegue. El sistema **siempre funciona**: cae de plano en plano declarando la degradación, nunca falla en silencio ni rebaja compuertas.

## 1.2 Plano 0 — Léxico (PHP puro, cero inferencia, universal)

Obligatorio en todo entorno, incluido el hosting más humilde. Nada de esto necesitó nunca un modelo:

- Puntuaciones y umbrales (Radar, calidad, asignación) con la disciplina de pisos de N3-K.3.
- Decaimiento temporal, cadena de citación, multiplicidad de fuentes.
- Solapamiento por n-gramas (con la salvedad de escritura de §3.3), huella estructural, entropía de arquitecturas.
- Proporción interpretativa, verificación de estructura, longitudes, legibilidad por perfil de idioma.
- Reconciliación taxonómica léxica: normalización, alias, distancia de edición, stemming por idioma.
- BM25/TF-IDF sobre el archivo propio: recuperación de piezas relacionadas, canibalización, enlazado interno candidato.
- Detección de idioma por perfiles de n-gramas de caracteres.
- Todo el SEO técnico, schema, cuotas, ventanas, jitter, candados, presupuestos.
- **Modo P0-only funcional**: sin ningún modelo, el sistema hace radar, expediente, SEO, taxonomía, publicación y cola. Falta solo escribir.

## 1.3 Plano 1 — Semántico (ONNX Runtime): los órganos del cerebro

Cada órgano es un **rol**, no un modelo concreto. El registro de modelos (§3.4) mapea rol → artefacto ONNX por idioma y por perfil de recursos. *La IA de desarrollo debe investigar y decidir los artefactos concretos con fuentes actuales; este documento fija los roles y sus contratos, jamás nombres de modelos recordados de memoria.*

| Órgano | Rol | Consumidores en el canon |
|---|---|---|
| **ENC** Codificador de frases | Vectores multilingües, con alineación translingüe | Deduplicación de tendencias, canibalización, reconciliación de entidades, deriva de voz (N2-A.5), huella estructural (N3-P.1) |
| **NLI** Implicación textual | ENTAILMENT / NEUTRAL / CONTRADICTION entre par de textos | **Trazabilidad afirmación↔expediente (N3-J.3, sustituye a similitud)**, contradicciones entre fuentes (N2-B.2), postura del señalado (N3-M.1), zero-shot de todos los clasificadores |
| **RRK** Reordenador (cross-encoder) | Relevancia fina de par consulta↔pasaje | Selección de extractos del expediente, relevancia causal del hueco (N3-O.2), enlazado interno, ranking de fuentes |
| **NER** Entidades nombradas | Personas, organizaciones, lugares, obras | Etiquetado (Cap. 7), detección de persona identificable (Compuerta de Riesgo y N3-N.2), actores para memoria colectiva |
| **CLS** Clasificadores | Tipo de noticia, gravedad, sensibilidad, dimensión de encuadre, comentario, spam, naturalidad de señal | Paso 1 del Algoritmo Editorial, degradación por sensibilidad, modo respeto, compuertas de comentarios (N4-X.1) |
| **SEG** Segmentador | Frases y unidades factuales atómicas, con soporte de escrituras sin espacios | Base de J.3, proporción interpretativa, legibilidad, n-gramas por carácter |
| **LID** Identificación de idioma | Idioma + variante de cada fuente y del borrador | Perfil de idioma, coherencia de locale, rechazo de fuentes fuera de idioma editorial |
| **TOX** Seguridad de contenido | Toxicidad, ataque personal, contenido sensible | Compuertas de comentarios, salvaguarda de sátira, verificación de derivados |

**Regla de composición**: los órganos se combinan en *pipelines cognitivos* declarativos, no en código disperso. Ejemplo del pipeline `verificar_trazabilidad`: SEG → (por unidad) ENC+índice del expediente → RRK sobre los k mejores → NLI sobre el mejor par → veredicto {SUSTENTADA | SIN_RESPALDO | CONTRADICE_EXPEDIENTE}. El tercer veredicto es nuevo en el canon y es de máxima severidad: una afirmación que **contradice** el propio expediente no es un fallo de estilo, es retención inmediata.

## 1.4 Plano 2 — Generativo

Mismo `LenguajeInterface` del canon, tres procedencias intercambiables por propósito: **local** (decoder servido en el propio servidor, solo entornos capaces), **remoto propio** (el cerebro del cliente en su VPS, hablado por HTTP autenticado), **de pago** (API). La voz de cada periodista vive en directrices+anclas (N2-A.3) y, cuando el entorno lo permite y el corpus lo justifica, en adaptador propio versionado (ciclo de vida en §5.3).

## 1.5 Plano 3 — Humano

Modo Redacción Asistida: producto completo sin generación automática. No es un modo degradado de emergencia: es una **edición comercial vendible** a clientes que rechazan por política la redacción por IA, y es el suelo absoluto sobre el que ninguna configuración puede caer.

## 1.6 Plano 4 — El Enrutador Cognitivo

- **Sonda de Capacidades** (en instalación y periódica): detecta FFI, extensiones, RAM disponible, tiempo máximo de ejecución, capacidad de proceso hijo, presencia de cerebro remoto, presencia de API. Produce un **Perfil de Entorno** y de él se derivan los planos disponibles. Nunca se asume nada del hosting.
- **Matriz de enrutamiento**: propósito × gravedad × modo de operación × Perfil de Entorno → plano y órgano. Configurable con defaults de fábrica. Permite exactamente lo que el usuario pidió: quien paga API la enchufa para redactar; quien no, usa local; y **quien paga puede seguir verificando en local**, que además es lo epistémicamente correcto (§0.2).
- **Escalada por calidad, no por capricho**: se intenta con el plano más económico capaz; si las compuertas o el Corrector rechazan tras N ciclos, se escala con el diagnóstico como contexto. La **tasa de escalada por periodista y vertical** es la métrica que mide si el cerebro propio basta.
- **Presupuesto invertido**: el gasto de pago se presupuesta como *escaladas*, no como tokens. Agotado: RETENIDA, jamás publicación degradada (escasez honesta).
- **Auditoría por pieza**: qué plano, qué órgano, qué versión de modelo y qué familia atendió cada operación. Requisito de N3-J y del registro del Art. 50.

---

# PARTE 2 — MULTILINGÜISMO REAL: EL MOTOR NO PUEDE SER MONOLINGÜE

*N3-Q resolvió los catálogos de voz por locale. Pero el motor entero tiene supuestos de idioma incrustados en sitios que nadie audita. Un producto para tiendas internacionales debe resolverlos en la capa de arquitectura, no parcheando.*

## 2.1 El contrato `PerfilIdioma`

Entidad de primera clase que agrupa TODO lo dependiente de idioma y escritura. Cada operación léxica o semántica recibe el perfil; ninguna asume nada.

| Aspecto | Por qué rompe si se asume | Qué provee el perfil |
|---|---|---|
| Segmentación de frases | Los puntos no delimitan igual en japonés, tailandés o árabe; hay idiomas sin espacios entre palabras | Segmentador por escritura (regla o modelo SEG) |
| Tokenización para n-gramas | N-gramas de **palabra** son inútiles en chino/japonés/tailandés | Selector: n-gramas de palabra o **de carácter** según escritura |
| Normalización | Diacríticos, mayúsculas, formas de ancho completo, ligaduras árabes, kana/kanji | Cadena de normalización por idioma |
| Stemming/lematización | Español, alemán, turco, árabe y ruso no se derivan igual | Derivador por idioma o desactivado |
| Palabras vacías | Lista por idioma; ausencia degrada BM25 y taxonomía | Catálogo por idioma |
| Legibilidad | Las fórmulas silábicas no aplican a CJK ni a árabe | Métrica por familia lingüística; nunca una fórmula global |
| Dirección y presentación | Árabe/hebreo son RTL: panel, tarjetas editoriales y derivados | Bandera RTL propagada a UI y a generación de imagen |
| Formatos | Fechas, números, separadores, comillas tipográficas, moneda | Formateadores por locale (vía funciones de WP) |
| Convenciones editoriales | Titulares en mayúscula inicial no existen igual en alemán o francés; densidad de citas varía | Reglas de estilo por locale |

## 2.2 Cobertura y honestidad de idioma

- **Idiomas de primera clase**: aquellos con paquete de modelos completo (los ocho órganos), catálogos léxicos y corpus de voz validado. El sistema declara la lista y **no acepta configurar un periodista en un idioma sin cobertura declarada**: prefiere negar la venta de una función a entregarla degradada en silencio.
- **Niveles de cobertura por idioma** (visibles en el panel y en la ficha de producto): COMPLETO (todos los órganos + catálogos) · PARCIAL (órganos multilingües genéricos, sin catálogos curados: el sistema advierte y limita el modo Autónomo) · NO SOPORTADO (el idioma no se puede seleccionar).
- **Multisitio y multi-idioma en un mismo sitio**: `locale_editorial` es del periodista (N3-Q) y determina perfil, catálogos y paquete de modelos. Un mismo sitio puede tener periodistas en idiomas distintos, cada uno con su cobertura.
- **Traducción automática de piezas: PROHIBIDA por diseño.** Publicar la misma pieza traducida a N idiomas es contenido duplicado escalado, exactamente lo que Google castiga. Si un cliente quiere cubrir dos idiomas, son dos periodistas con dos criterios, dos expedientes y dos piezas originales — no una pieza y un traductor.

## 2.3 Paquetes de modelos por idioma

Los artefactos ONNX no viajan dentro del ZIP del plugin (§4.2). Se distribuyen como **paquetes por idioma y por perfil de recursos** (compacto / equilibrado / amplio), descargados bajo consentimiento explícito, verificados por checksum y firma, almacenados fuera del directorio del plugin, con versión, licencia y procedencia registradas y visibles en el panel. Actualización desacoplada del plugin, con reversión posible.

---

# PARTE 3 — UNIVERSALIDAD DE HOSTING: LOS CUATRO TRANSPORTES

*El plugin no puede exigir un tipo de hosting. Puede exigir honestidad sobre lo que cada hosting permite.*

## 3.1 Transportes de ejecución del Plano 1 (ONNX)

| Transporte | Cómo | Dónde funciona | Coste de despliegue |
|---|---|---|---|
| **T1 — En proceso** | Enlace nativo desde PHP al runtime | VPS/dedicado con la extensión disponible | Bajo si el entorno lo permite; imposible donde está deshabilitado |
| **T2 — Sidecar local** | Servicio local del propio servidor, hablado por socket/HTTP local | VPS/dedicado | Medio; requiere proceso persistente |
| **T3 — Cerebro remoto propio** | El servicio del cliente en su VPS o el servicio gestionado del vendedor, HTTP autenticado | **Cualquier hosting, incluido compartido** | Requiere infraestructura o suscripción, pero desbloquea todo |
| **T4 — Navegador (WASM)** | Ejecución en el navegador del editor durante trabajo interactivo | Cualquier hosting, solo tareas interactivas del panel (nunca cron) | Nulo; alcance limitado |

**Matriz de decisión de la Sonda**: T1 si disponible → T2 si hay proceso → T3 si está configurado → T4 para lo interactivo → **P0-lite** si nada: el Plano 1 se sustituye por sus aproximaciones léxicas (BM25, n-gramas de carácter, léxicos y reglas) con **degradación declarada al usuario y restricción automática del modo Autónomo**. Un cerebro sin plano semántico no publica solo: asiste.

## 3.2 Contrato de degradación

Cada órgano declara su **sustituto léxico** y el **coste de calidad** de usarlo. Reglas duras: la degradación siempre es visible en el panel; ninguna degradación reduce un piso de compuerta; la ausencia de NLI **prohíbe el modo Autónomo** para piezas con afirmación negativa sobre persona identificable (sin verificador de implicación no hay garantía anti-alucinación suficiente para publicar sin humano).

## 3.3 Rendimiento y límites del entorno

Presupuesto de tiempo por operación cognitiva, tamaño máximo de lote, caché de vectores del expediente (se calcula una vez por pieza, no por comparación), reutilización del índice del archivo, y **corte limpio entre lotes** al agotar el presupuesto del cron. Ninguna inferencia en petición de navegador del visitante: jamás. El frontend público sigue con peso ≈ 0.

---

# PARTE 4 — DISTRIBUCIÓN EN TIENDAS

## 4.1 Restricciones que condicionan la arquitectura

- Código legible y con fuentes; nada ofuscado. Licencias de todo lo empaquetado, compatibles con la distribución elegida.
- **Sin binarios ni modelos dentro del paquete**: descarga posterior con consentimiento (§2.3).
- **Divulgación completa de servicios externos**: qué se envía, a dónde, cuándo y con qué consentimiento — para cada proveedor de pago, para el cerebro remoto y para la descarga de modelos.
- Sin llamadas a casa sin permiso; telemetría opt-in (GOVERNANCE §5.5).
- Desinstalación limpia respetando "conservar datos", incluidos paquetes de modelos y adaptadores.
- **Licencia de cada modelo verificada para uso comercial** y registrada. Es criterio eliminatorio, no detalle.
- **Cumplimiento del Art. 50 propagado al cerebro**: la procedencia (local/remoto/pago) y el tipo de aprobación humana se registran por pieza; el marcado técnico se aplica según N4-I.1.

## 4.2 Ediciones del producto

Núcleo (P0 + P3, universal, cualquier hosting) · Semántico (añade P1 por el transporte disponible) · Completo (añade P2 local o cerebro remoto) · Con API (P2 de pago enchufable en cualquier edición). Una misma base de código, capacidades detectadas y licenciadas — jamás forks paralelos.

---

# PARTE 5 — RESTRICCIONES INNEGOCIABLES

## 5.1 De código (verificables por test de arquitectura)

1. **Ningún modelo fuera de `Pluma\Proveedores`.** Ni una importación, ni una ruta de artefacto, ni un tensor. Las capas editoriales hablan con contratos: `LenguajeInterface`, `EmbeddingsInterface`, `NliInterface`, `ClasificadorInterface`, `EntidadesInterface`, `SegmentadorInterface`, `IdiomaInterface`, `SeguridadContenidoInterface`.
2. **Ninguna capa editorial sabe qué plano la atendió.** Cualquier condicional sobre procedencia, transporte o nombre de modelo fuera del Enrutador es violación de arquitectura.
3. **Ningún supuesto de idioma fuera de `PerfilIdioma`.** Prohibidas en `src/`: separación por espacios asumida, listas de palabras vacías embebidas, expresiones de puntuación fijas, fórmulas de legibilidad únicas, `strtolower` sin conciencia de escritura.
4. **Ninguna inferencia en petición de visitante.** Solo cron y peticiones autenticadas del panel.
5. **Ningún artefacto sin registro**: todo modelo cargado declara rol, versión, licencia, idioma, checksum y procedencia; sin registro, no carga.
6. **Ninguna degradación silenciosa.** Todo descenso de plano se anuncia, se audita y ajusta automáticamente el modo de operación permitido.
7. **Ningún piso de compuerta se mueve por el cerebro.** Los pisos de fábrica son constantes; el cerebro se adapta a ellos, jamás al revés.
8. **Ninguna salida generativa sin verificación de trazabilidad** cuando el plano semántico está disponible; y sin él, ninguna publicación autónoma de piezas con afirmación negativa sobre persona identificable.
9. **Ningún dato del cliente sale del sitio sin consentimiento explícito y divulgación**, incluido lo que viaja al cerebro remoto y a la API.
10. **Ninguna traducción automática de piezas.** Prohibida por diseño (§2.2).
11. **Determinismo verificable**: toda operación del Plano 0 y toda inferencia con semilla fija debe ser reproducible en test. Azar solo vía `AzarInterface`.
12. **Presupuestos verificados antes, no después** de cada operación cara (tiempo, memoria, coste).

## 5.2 De manera de pensar (el agente de desarrollo las cita al abrir cada fase)

1. **Primero pregunta si necesita cerebro.** Toda operación que hoy llama a un modelo se audita: ¿es léxica (P0), semántica (P1) o genuinamente generativa (P2)? La mayoría no era lo tercero. Reducir dependencia empieza por reconocer lo que nunca la necesitó.
2. **El entorno se sonda, no se supone.** Ninguna decisión de arquitectura puede asumir FFI, RAM, procesos o red. La Sonda decide.
3. **Investiga antes de nombrar.** Ningún modelo, versión, licencia o requisito se afirma de memoria. Fuente oficial y fecha, o no se escribe (lg-research-depth; Santo Grial §4).
4. **La licencia comercial es criterio eliminatorio**, evaluada antes que la calidad: un modelo excelente sin licencia clara no existe para este producto.
5. **Degradar es un acto de honestidad, no de vergüenza.** Diseña la caída antes que el camino feliz: qué se pierde, quién se entera, qué modo se restringe.
6. **El multilingüismo es arquitectura, no traducción.** Si una decisión funciona solo en idiomas con espacios entre palabras, está mal tomada.
7. **La calidad se mide con el instrumento que ya existe.** Compuertas y corpus de voz son el juez de si el cerebro basta. No inventes métricas paralelas ni confíes en impresiones.
8. **Ninguna capa nueva puede aflojar una vieja.** Si el cerebro propio hace tentador bajar un piso, la respuesta es escalada o retención, jamás el piso.
9. **Aplica la Regla de Puntuaciones Compuestas (N3-K.3)** a todo umbral nuevo del cerebro: piso, contribuyente o alerta; qué pasa al incumplir; si tiene suelo de fábrica.
10. **Cero complacencia con el propio diseño**: si la investigación contradice este documento, se detiene y se reporta con evidencia. Este anexo es canon, no dogma.

## 5.3 Nota de riesgo declarada: destilación desde la API

Usar salidas de una API de pago para entrenar modelos locales propios puede ser un acelerador poderoso del cerebro híbrido — y puede violar los términos de servicio del proveedor. **Prohibido implementarlo sin revisión legal explícita y registro de la conclusión.** Se declara aquí para que nadie lo introduzca por iniciativa propia como "optimización".

---

# PARTE 6 — CRITERIOS DE ACEPTACIÓN Y FASES

## 6.1 Aceptación (medible)

1. Con la API desconectada, el ciclo completo se ejecuta y publica superando todas las compuertas con los pisos de fábrica intactos.
2. Con la API conectada solo para `redactar`, la verificación permanece local y la auditoría registra familias distintas (cumple N3-J sin coste extra).
3. Sin Plano 1 disponible (P0-lite), el sistema opera, declara la degradación y restringe el modo Autónomo automáticamente.
4. Sin Plano 2 de ningún tipo, el Modo Redacción Asistida es un producto funcional completo.
5. Dos idiomas de escrituras distintas (uno con espacios, uno sin) completan el ciclo con perfiles correctos: segmentación, n-gramas, legibilidad y taxonomía verificadas por test.
6. El pipeline `verificar_trazabilidad` detecta, en corpus adversarial, afirmaciones sin respaldo **y** afirmaciones que contradicen el expediente.
7. La Sonda produce Perfil de Entorno correcto en los cuatro escenarios de hosting probados (compartido, VPS sin FFI, VPS con FFI, cerebro remoto).
8. Cero condicionales de procedencia fuera del Enrutador; cero supuestos de idioma fuera de `PerfilIdioma` (tests de arquitectura).
9. Registro de modelos completo: rol, versión, licencia, idioma, checksum, procedencia — con divulgación de servicios externos lista para tienda.
10. Todos los gates de GOVERNANCE §4.5 en verde, suite de invariantes editoriales intacta.

## 6.2 Fases

| Fase | Contenido | Criterio de salida |
|---|---|---|
| **NCP-1 · Recorte** | Auditoría de todas las llamadas generativas actuales; reasignación a P0; contratos nuevos; `PerfilIdioma`; Sonda de Capacidades | % de llamadas de pago eliminadas, medido sobre bitácora real; dos idiomas de escrituras distintas pasando los tests léxicos |
| **NCP-2 · Semántico** | ONNX con los cuatro transportes; órganos ENC/SEG/LID/NER; registro y paquetes de modelos; sustitutos léxicos | Funciona en los cuatro escenarios de hosting; degradación declarada correcta |
| **NCP-3 · Verificación** | NLI y RRK; pipeline `verificar_trazabilidad`; contradicciones entre fuentes; clasificadores zero-shot; TOX | Corpus adversarial superado; J.3 y B.2 sin ninguna llamada de pago |
| **NCP-4 · Enrutador** | Matriz de enrutamiento, escalada por calidad, presupuesto invertido, auditoría de procedencia y familia | Una semana en Piloto con API desconectada; tasa de escalada medida por periodista y vertical |
| **NCP-5 · Generativo propio** | P2 local y cerebro remoto; voz por directrices; ciclo de vida del adaptador con validación obligatoria contra corpus de voz y test a ciegas | Dos periodistas distinguibles a ciegas con cerebro propio |
| **NCP-6 · Internacional** | Cobertura por idioma con niveles declarados; catálogos curados; RTL y CJK en panel y derivados; divulgaciones de tienda | Tres idiomas en COMPLETO, uno de ellos de escritura no latina; paquete de tienda aprobado internamente |

---

# PARTE 7 — PROMPT DE ACTIVACIÓN

*Guardar este documento como `docs/CEREBRO_PLUMA.md` (sustituye a la v1.0) y enviar:*

```
Nueva línea de trabajo en PLUMA Engine: el Núcleo Cognitivo Propio (NCP).

Objetivo: un cerebro HÍBRIDO. La API de pago pasa a ser un acelerador
opcional que el usuario enchufa si quiere; el plugin debe ser un producto
completo sin ella, en cualquier hosting y en todos los idiomas principales.

ANTES DE ESCRIBIR CÓDIGO:

1. Relee CLAUDE.md, GOVERNANCE.md, AGENTS.md, SKILLS-STACK.md y el skill
   pl-proveedor-ia. Luego lee íntegro docs/CEREBRO_PLUMA.md v2.0: es canon
   de esta línea de trabajo. Presta atención especial a la Parte 5:
   las restricciones de código y de manera de pensar son innegociables y
   debes citarlas al abrir cada fase.

2. Ejecuta el Protocolo de Descubrimiento de Skills (SKILLS-STACK §2) sobre
   los dominios nuevos: ONNX Runtime, modelos encoder, NLI, multilingüismo
   y segmentación de escrituras no latinas, distribución de artefactos.
   Registra veredictos en docs/skills-descubiertas.md.

3. INVESTIGACIÓN OBLIGATORIA CON FUENTES (lg-research-depth nivel Fuente;
   Santo Grial §4: cero invención). Todo lo que yo pueda "recordar" sobre
   modelos, runtimes, licencias y requisitos está desactualizado por
   definición. Investiga y documenta en docs/decisiones/, con enlaces y
   fecha de consulta:
   - Estado actual de ONNX Runtime y sus vías de ejecución desde PHP, y
     qué exige cada una del entorno. Verifica qué se puede esperar en
     hosting compartido real, no en teoría.
   - Candidatos por ROL (ENC, NLI, RRK, NER, CLS, SEG, LID, TOX) con
     cobertura multilingüe, tamaño int8, rendimiento en CPU y LICENCIA
     COMERCIAL. La licencia es criterio eliminatorio: se evalúa primero.
   - Segmentación y tokenización para escrituras sin espacios y RTL.
   - Distribución de artefactos: tamaños, verificación de integridad,
     almacenamiento fuera del plugin, y las reglas vigentes de las tiendas
     donde vamos a publicar sobre binarios, servicios externos y licencias.
   - Requisitos reales para el Plano 2 local con nuestro throughput
     (3–10 piezas/día, ventana de 90 min), no para chat concurrente.

4. Aplica el stack LG: First Principles sobre qué operación es léxica,
   semántica o genuinamente generativa; CTO Mode lente Cerebro sobre el
   Enrutador y la Sonda (son un sistema autónomo dentro de otro: diseña su
   homeostasis primero); Risk Radar con pre-mortem por transporte y por
   idioma; Decision Framework con matriz completa para cada rol de modelo,
   cada transporte y cada edición de producto — todo queda como ADR.

5. Entrégame el PLAN GUARDIAN completo de la fase NCP-1 y ESPERA
   APROBACIÓN. NCP-1 no toca ningún modelo: es la auditoría del gasto
   generativo actual, su reasignación al Plano 0, los contratos nuevos,
   PerfilIdioma y la Sonda de Capacidades. Primero medimos cuánta
   dependencia era innecesaria desde el principio.

Si la investigación contradice el documento en cualquier punto: DETENTE,
repórtalo con evidencia y propón la corrección. Prefiero cambiar el canon
que ejecutar un plan equivocado en silencio.
```

*— Fin del anexo v2.0 —*
