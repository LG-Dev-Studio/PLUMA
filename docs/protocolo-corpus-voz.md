# Protocolo del corpus de regresión de voz (Nivel Dos A.5)

El corpus de regresión de voz vive en `tests/Fixtures/corpus-voz.php`: copy editorial original, congelado, con el mismo estatus que el corpus adversarial de `GOVERNANCE.md` §3.4 (fixtures de test, no una pantalla de administración). Hoy es un **corpus mínimo de desarrollo** — 2-3 piezas por cada uno de los 3 periodistas sembrados de `PlantillasSiembra` (`Marcos Iriarte`, `Valentina Ruiz`, `Bruno Castell`) — no las 15-20 piezas curadas que la versión madura de A.5 exige. Ampliarlo con piezas reales elegidas por el propietario durante Piloto es trabajo pendiente, no deuda oculta: `CLAUDE.md` prohíbe presentar datos inventados como reales, así que el corpus actual se declara explícitamente por lo que es.

A.5 define 3 verificaciones. Solo la primera vive enteramente en el código de producto; la segunda tiene su mecanismo en código pero su ejecución contra un proveedor real es manual; la tercera es, por diseño, un protocolo humano.

## 1. Presencia estructural (automática, en `composer test:unit`)

`Pluma\Redaccion\VerificadorVoz` (el mismo verificador del punto 4 del Corrector Interno) se ejecuta contra cada pieza del corpus en `tests/Unit/Redaccion/CorpusVozFixturesTest.php`. Corre en cada `composer test:unit`, sin necesidad de intervención manual. Verifica que ninguna pieza del corpus contiene vocabulario prohibido (global o propio del periodista) — el check honesto que el propio `VerificadorVoz` puede hacer contra prosa real sin depender de que un texto contenga literalmente la descripción de un rasgo de voz (una limitación conocida y preexistente de `VerificadorVoz::verificar()`, fuera del alcance de esta porción).

## 2. Deriva semántica (mecanismo automático, ejecución manual antes de release)

`Pluma\Redaccion\VerificadorRegresionVoz::derivaExcesiva()` compara por embeddings una muestra nueva contra el corpus de referencia de un periodista. El mecanismo está cubierto por tests unitarios con dobles (`tests/Unit/Redaccion/VerificadorRegresionVozTest.php`, `Pluma\Tests\Unit\Dobles\EmbeddingsFalso`) — GOVERNANCE §4.4 prohíbe que un test Unit llame a un proveedor real.

**Antes de cualquier release que toque `PlantillaPrompt`, `CompiladorDirectrices` o `anclas-diales.php`**, ejecutar manualmente contra el proveedor real:

1. Con credenciales de OpenRouter configuradas en un entorno de desarrollo (nunca en CI), generar una pieza de muestra por cada periodista sembrado, con la Conducta actual sin modificar diales.
2. Instanciar `ProveedorOpenRouter` real (implementa `EmbeddingsInterface`) y `VerificadorRegresionVoz`.
3. Llamar `derivaExcesiva($corpusDelPeriodista, $muestraNueva)` para cada periodista.
4. Si algún periodista marca deriva excesiva (umbral de fábrica 0.70, opción `pluma_umbral_similitud_regresion_voz`): investigar antes de publicar el release — puede ser un cambio real de comportamiento del modelo subyacente, o una regresión en la plantilla de prompt.

Este paso no está automatizado en CI deliberadamente: automatizarlo obligaría a CI a llamar a un proveedor de pago en cada ejecución, o a mockear la respuesta de forma que el check dejaría de detectar deriva real.

## 3. Discriminación a ciegas (protocolo manual, sin código)

El propio Nivel Dos es explícito: esta verificación es un juicio humano ("un panel humano, aunque sea de una persona"), y simularla con código sería inventar una verificación falsa.

**Protocolo, a ejecutar antes de cualquier release que toque `PlantillaPrompt`/`CompiladorDirectrices`/`anclas-diales.php`:**

1. Tomar 2 piezas del corpus de un periodista + 1 pieza recién generada con la misma Conducta, mezcladas sin etiqueta visible de cuál es cuál.
2. Un editor humano (el propietario, o quien delegue en Piloto) debe poder identificar cuál de las 3 piezas es la nueva, o confirmar que las 3 son indistinguibles en voz.
3. Repetir para cada periodista sembrado.
4. Registrar el resultado (aprobado/observaciones) en la bitácora de release — sin bloquear el release por defecto (es una señal de calidad, no una compuerta dura), salvo que el editor identifique una discrepancia clara de voz, en cuyo caso se trata como bug de regresión antes de publicar.

## Cadencia mensual independiente de release (Nivel Tres P.3)

Las tres verificaciones de arriba se disparan "antes de cualquier release que toque..." — pero un periodista puede llevar meses sin que nadie toque su plantilla y aun así derivar, porque el proveedor detrás de `LenguajeInterface`/`EmbeddingsInterface` puede actualizar silenciosamente los pesos del modelo bajo el mismo alias de API, sin que ninguna línea de código cambie. El corpus, tal como está definido arriba, nunca se ejecuta si lo que cambió fue el modelo y no el prompt.

**Corrección (Etapa 8, Porción 10)**: además del disparador por release, el corpus de regresión de voz se ejecuta en un calendario **independiente**, mensual como mínimo:

1. Ejecutar `composer test:voz` (aísla, vía `@group voz`, exactamente los dos archivos de esta cadencia: `CorpusVozFixturesTest.php` — verificación 1 — y `VerificadorRegresionVozTest.php` — mecanismo de la verificación 2, con dobles; para la verificación 2 contra el proveedor real, repetir los pasos 1-4 de la sección "Deriva semántica" arriba, en este calendario mensual, no solo antes de release).
2. Registrar el resultado (y cualquier verdicto de la auditoría editorial por periodista) en `docs/bitacora-auditoria-periodistas.md`.

Esta cadencia no depende de infraestructura nueva — reutiliza el mismo corpus y el mismo mecanismo de A.5, solo cambia cuándo se dispara.
