# ADR 0018 — NCP-2 Porción 4: SEG, segmentación de oraciones vía ICU

- **Fecha**: 2026-08-03
- **Estado**: Aceptada — construida y verificada con evidencia real
- **Contexto**: `ADR 0014` §3 (investigación de segmentación), `docs/ncp-estado-y-continuidad.md` §5(b)

## Decisión

Se construye `Pluma\Idioma\SegmentadorOraciones`, primera implementación real del rol **SEG** del canon (`docs/CEREBRO_PLUMA_v2.md` Parte 2.3): segmentación de oraciones, Plano 0 puro (sin modelo, sin coste de inferencia), usando **ICU vía `ext-intl` (`IntlBreakIterator`)** cuando está disponible, con fallback determinista en PHP puro cuando no lo está — exactamente la recomendación de `ADR 0014` §3.

## Evidencia — verificación real dentro del contenedor `cli` de wp-env

**`ext-intl` está compilado en el entorno de desarrollo** (`php -m | grep intl` → `intl`).

**Hallazgo real no trivial**: `IntlBreakIterator::createSentenceInstance('es')` **no protege por defecto** abreviaturas editoriales. Ejecución real:

```php
$bi = IntlBreakIterator::createSentenceInstance("es");
$bi->setText("El Dr. Smith llegó tarde. La reunión empezó a las 4.5 en punto. ¿Cómo estás?");
foreach ($bi->getPartsIterator() as $p) { echo "[" . trim($p) . "]\n"; }
```
```
[El Dr.]
[Smith llegó tarde.]
[La reunión empezó a las 4.5 en punto.]
[Como estas?]
```

ICU partió "El Dr." como si fuera una oración completa — el locale `es` de ICU no trae un diccionario de abreviaturas editoriales en español. Los números decimales (`4.5`) sí los maneja correctamente sin ayuda.

**Con la misma protección de abreviaturas/decimales que ya usa `Pluma\Redaccion\SegmentadorUnidadesFactuales`** (sustituir el punto por un marcador Unicode de uso privado `\u{E000}` antes de segmentar, restaurarlo después), el resultado real es correcto:

```
[El Dr. Smith llegó tarde.]
[La reunión empezó a las 4.5 en punto.]
[Como estas?]
```

`IntlBreakIterator::createSentenceInstance()` acepta tanto `'es'` como `'es-ES'` sin error (confirmado con `var_dump($bi !== null)` → `true`).

## Diseño

`Pluma\Idioma\SegmentadorOraciones::segmentar(string $texto, string $locale = 'es-ES'): list<string>`:

1. Protege abreviaturas (lista propia, 16 entradas) y números decimales — misma técnica, constante propia (deliberadamente NO compartida con `Redaccion\SegmentadorUnidadesFactuales::MARCADOR`: acoplar `Pluma\Idioma` a `Pluma\Redaccion` por una lista de 16 strings no está decidido).
2. Si `extension_loaded('intl')`: `IntlBreakIterator::createSentenceInstance($locale)`.
3. Si no: fallback `preg_split('/(?<=[.!?])\s+(?=[A-ZÁÉÍÓÚÑ0-9¿¡])/u', ...)` — mismo patrón que `SegmentadorUnidadesFactuales`, nunca deja el rol SEG sin funcionar en un host sin `ext-intl` (`ADR 0014` §3: "opcional-con-fallback", nunca dependencia dura).
4. Restaura marcadores, recorta, filtra vacíos.

Clase `final`, sin interfaz — mismo patrón que `ResolutorPerfilIdioma`/`SensorCapacidades` (sin múltiples consumidores reales que necesiten sustituirla en tests).

**Fuera de alcance, declarado explícitamente**: segmentación de PALABRAS para escrituras sin espacios (chino/japonés/tailandés/lao/jemer/birmano), que ICU también resuelve vía diccionario — no hay ningún locale con `NivelCobertura` real que la necesite hoy (`ResolutorPerfilIdioma` solo cubre `es-ES`); construirla sería el mismo "campo que nadie lee" que `PerfilIdioma` ya evitó en NCP-1 Porción 3 (`ADR 0012`).

## Qué NO hace esta porción

- No modifica `Pluma\Redaccion\SegmentadorUnidadesFactuales` ni lo sustituye en `VerificadorTrazabilidadDeterminista` — la nueva clase queda registrada en el contenedor de DI (`Nucleo.php`), disponible, sin consumidor real todavía. Mismo principio que `ProveedorEmbeddingsCerebroRemoto` (`ADR 0016`): ninguna capa nueva reemplaza una vieja sin decisión explícita.
- No añade cobertura de ningún locale nuevo a `ResolutorPerfilIdioma`.

## Nota de infraestructura de tests

`Brain\Monkey`/Patchwork no permite mockear una función interna de PHP (`extension_loaded`) sin declararla explícitamente en `patchwork.json` bajo `redefinable-internals` — confirmado con el error real `NotUserDefined` al intentarlo sin declarar. Se añadió `"extension_loaded"` a la lista ya existente (`gethostbyname`, `function_exists`) en `patchwork.json`, mismo mecanismo ya usado en el proyecto para otras funciones internas. Esto permite que `SegmentadorOracionesTest` ejercite de verdad ambas ramas (ICU real del entorno + fallback forzado), no solo la que da la casualidad del entorno de CI.

## Consecuencias

- El rol SEG del canon tiene su primera implementación real, disponible para cualquier consumidor futuro (p. ej. una eventual fusión con `SegmentadorUnidadesFactuales`, o un futuro rol NLI/RRK que necesite pre-segmentar texto en oraciones).
- El hallazgo de que ICU no protege abreviaturas por defecto es información real que cualquier futura decisión de conectar `SegmentadorOraciones` a un consumidor de producción debe tener en cuenta — ya resuelto aquí vía la misma protección de marcador, pero documentado para que no se pierda si se reimplementa en otro lugar.
