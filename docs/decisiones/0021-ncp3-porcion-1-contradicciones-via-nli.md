# ADR 0021 — NCP-3 Porción 1: detección de contradicciones vía NLI en el Corrector Interno

- **Fecha**: 2026-08-03
- **Estado**: Aceptada — construida, conectada a producción, verificada
- **Contexto**: `docs/CEREBRO_PLUMA_v2.md` §0.1 punto 1-2, `ADR 0020` (NLI/RRK vía T3), `GOVERNANCE.md` §2.4

## Decisión

Se construye `Pluma\Redaccion\VerificadorContradiccionNli`, primer consumidor real de `ProveedorNliCerebroRemoto` (`ADR 0020`) — primera vez en la sesión que una capacidad de NCP-2 se conecta a producción. Detecta, para cada unidad factual del borrador, si algún hecho del expediente la contradice según NLI real (etiqueta `contradiction` por encima de un umbral configurable, `pluma_umbral_contradiccion_nli`, valor de fábrica `0.5`). Se conecta a `Pluma\Redaccion\CorrectorInterno` como una alerta adicional para el punto "hechos", mismo patrón que la alerta de trazabilidad por embeddings ya existente (N3-J.3) — prioriza, nunca sustituye el veredicto del LLM (GOVERNANCE §2.4).

## Decisión de arquitectura confirmada explícitamente por el propietario — T3 pasa a ser obligatorio

Al diseñar esta porción se detectó que `VerificadorContradiccionNli` depende del cerebro remoto (T3), infraestructura estrictamente opcional (`ADR 0013`). Conectarlo como dependencia dura de `CorrectorInterno` (paso obligatorio de todo el pipeline de redacción) significa que **cualquier instalación sin T3 configurado deja de poder corregir/publicar cualquier Pieza**.

Se presentó esta consecuencia al propietario dos veces, incluyendo la alternativa de captura silenciosa específica (que mantendría T3 opcional). **El propietario confirmó explícitamente que quiere T3 obligatorio.** `CorrectorInterno::revisar()` propaga cualquier fallo de `VerificadorContradiccionNli` (sin credenciales, red caída, formato inesperado) sin capturar, igual que cualquier otro fallo real del proveedor de lenguaje. Se documenta aquí como decisión deliberada, no un descuido.

## Hallazgo real de infraestructura: bug de descubrimiento de tests en PHPUnit + Docker-en-Windows

Durante la verificación de esta porción, la suite completa `--testsuite=Unit` empezó a reportar **540 tests en lugar de 738** (el total real) tras añadir el archivo `tests/Unit/Redaccion/VerificadorContradiccionNliTest.php` — sin ningún error ni advertencia visible, "OK (540 tests)". Investigación real:

1. Confirmado con `git stash` que el commit base (`f506052`) reportaba correctamente 732 tests.
2. Confirmado que el archivo nuevo, incluso reducido a un placeholder vacío, seguía disparando el problema — no era el contenido del archivo.
3. Confirmado que ejecutar `tests/Unit/Redaccion` como directorio aislado encontraba sus ~205 tests reales correctamente.
4. Confirmado que la suma de cada subdirectorio de `tests/Unit` ejecutado por separado daba 738 (el total real), pero el escaneo recursivo único de `tests/Unit` completo daba 540 — con `tests/Unit/Redaccion` (la carpeta más grande, ~40 archivos) casi enteramente ausente del resultado (solo sobrevivía el último archivo en orden alfabético).
5. **Solución real, verificada**: sustituir el `<directory>tests/Unit</directory>` único de `phpunit.xml.dist` por un `<directory>` explícito por cada subcarpeta de primer nivel. Con ese cambio, la cuenta pasa a 738 (correcta) de forma determinista (verificado 3 veces).

Causa raíz no identificada con certeza (probable interacción entre el iterador de directorios de PHPUnit y el bind-mount de Docker Desktop en Windows al cruzar cierto número de archivos en un árbol recursivo) — no se investiga más a fondo por no ser el objetivo de esta porción, pero la solución es real y verificada, no especulativa. `phpunit.xml.dist` queda con la lista explícita de subcarpetas; cualquier subcarpeta nueva bajo `tests/Unit/` en el futuro debe añadirse a esa lista o el mismo problema puede reaparecer.

**Consecuencia importante**: cualquier corrida anterior de `composer test:unit`/`phpunit --testsuite=Unit` en este entorno de desarrollo específico, desde que el árbol de tests cruzó el umbral que dispara este bug, pudo haber reportado "verde" sin ejecutar realmente toda la suite. Los números de "PHPUnit Unit N/N en verde" citados en ADR anteriores de esta sesión (Porciones 3-6 de NCP-2) fueron tomados de esas corridas — no hay evidencia de que fueran incorrectos (la cuenta reportada coincidía con la esperada en cada caso), pero se registra la posibilidad honestamente.

## Otro hallazgo real: 2 consumidores de `CorrectorInterno` no actualizados en el primer intento

Al añadir el 5º parámetro a `CorrectorInterno::__construct()`, una búsqueda inicial solo encontró y actualizó `tests/Unit/Redaccion/CorrectorInternoTest.php`. Una búsqueda posterior, más exhaustiva (`grep -r "new CorrectorInterno("` en todo el repo), encontró 4 archivos adicionales con construcciones directas: `tests/Unit/Redaccion/RedactorConFallbackMecanicoTest.php` (3 sitios), `tests/Unit/Redaccion/RedactorSinteticoTest.php` (1 sitio + helper compartido), `tests/Invariantes/AntiAlucinacionInvarianteTest.php` (2 sitios), `tests/Invariantes/CitarYEnlazarFuentesInvarianteTest.php` (1 sitio) — todos corregidos, con mocking real de `get_option`/`wp_remote_post` donde el test efectivamente ejecuta `CorrectorInterno::revisar()`.

## Verificación

- Gates: PHPCS 0, PHPStan nivel 8 0, PHPUnit `--testsuite=Unit` 738/738 en verde (número real, confirmado tras el fix de `phpunit.xml.dist`).
- PHPUnit `--testsuite=Invariantes`: 30 tests, 2 errores — **confirmados preexistentes** (`Pluma\Pipeline\Orquestador::__construct()`, no tocado por esta porción; el mismo fallo aparece en el commit base `f506052` antes de cualquier cambio de esta porción, verificado con `git stash`). Los 2 archivos de Invariantes que sí toca esta porción (`AntiAlucinacionInvarianteTest`, `CitarYEnlazarFuentesInvarianteTest`) pasan limpios.

## Consecuencias

- Primera capacidad de NCP-2 conectada a un consumidor real de producción.
- T3 (cerebro remoto) pasa de opcional a obligatorio para el pipeline de redacción — actualizar `docs/ncp-estado-y-continuidad.md` §4 con esta regla viva.
- El error preexistente de `Orquestador`/Invariantes queda fuera del alcance de esta porción — se registra para que un continuador no lo confunda con algo introducido aquí.
- `phpunit.xml.dist` requiere mantenimiento manual de la lista de subcarpetas de `tests/Unit/` — cualquier carpeta nueva debe añadirse explícitamente.
