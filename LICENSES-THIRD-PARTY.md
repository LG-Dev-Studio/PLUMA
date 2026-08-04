# Licencias de Terceros — PLUMA Engine

Este documento lista las dependencias de **producción** (las que viajan dentro
del ZIP de distribución, prefijadas vía PHP-Scoper cuando corresponda). Las
herramientas de desarrollo (PHPUnit, PHPCS, PHPStan, Vite, Vitest, Playwright,
`@wordpress/env`, etc.) **no se distribuyen** con el plugin — viven en
`require-dev` / `devDependencies` y quedan fuera del paquete final.

Se regenera con `composer licenses --no-dev` (PHP) y `npm ls --omit=dev`
(JS) en cada release, como parte del checklist del sub-agente RELEASE
(AGENTS.md).

## Estado — NCP · Reorientación (`ADR 0024`)

Tercera dependencia de producción PHP del plugin: `rubix/ml` (machine
learning puro PHP, sin extensión nativa), reemplaza al cerebro remoto (T3,
retirado en esta misma reorientación) para los roles NLI y RRK del canon.
Verificado con `composer licenses --no-dev`:

| Paquete | Versión | Licencia | Uso |
|---|---|---|---|
| `rubix/ml` | ^2.5 | MIT | Clasificador NLI pure-PHP (`Pluma\Proveedores\ProveedorNliEntrenado`) — entrenamiento offline y persistencia del modelo. |
| `rubix/tensor` | — | MIT | Dependencia transitiva de `rubix/ml` — álgebra vectorial/matricial. |
| `andrewdalpino/okbloomer` | — | MIT | Dependencia transitiva de `rubix/ml`. |
| `wamania/php-stemmer` | — | MIT | Dependencia transitiva de `rubix/ml` (no usada por `CaracteristicasNli`, que implementa su propia tokenización). |
| `joomla/string` | — | **GPL-2.0-or-later** | Dependencia transitiva de `rubix/ml`. Compatible por diseño: WordPress y sus plugins son GPL-2.0-or-later; no introduce ningún conflicto de licencia con la distribución del plugin. |
| `amphp/amp`, `amphp/parallel`, `amphp/byte-stream`, `amphp/parser`, `amphp/process`, `amphp/serialization`, `amphp/sync` | — | MIT | Dependencias transitivas de `rubix/ml` (procesamiento paralelo opcional, no usado por el pipeline de entrenamiento de esta porción). |
| `symfony/polyfill-php80`, `symfony/polyfill-php82` | — | MIT | Dependencias transitivas de `rubix/ml`. |

**Datos de entrenamiento (no es una dependencia de Composer, divulgación
igualmente obligatoria — GOVERNANCE §5.2)**: el clasificador NLI se entrenó
offline (`tools/entrenamiento-nli/entrenar.php`, herramienta de desarrollo,
no se empaqueta) sobre **InferES** (Kovatchev & Taulé, 2022 — *"InferES: A
Natural Language Inference Corpus for Spanish Featuring Negation-Based
Contrastive and Adversarial Examples"*, ACL Anthology / Hugging Face
`venelin/inferes`), **licencia CC-BY-4.0** — atribución obligatoria, uso
comercial permitido. El dataset en sí no se distribuye con el plugin; solo
el modelo ya entrenado (`recursos/modelos/nli-es.rbx` + `nli-es-vocab.json`,
~114 KB) — atribución documentada aquí y en `ADR 0024`.

## Estado — Etapa 9 (El medio real, Porción 4)

Primeras dependencias de producción PHP del plugin, añadidas para las
notificaciones push web reales (Nivel Cuatro W.3): `vendor/` en el ZIP
distribuido las incluye prefijadas vía PHP-Scoper (`scoper.inc.php`, ya
configurado de forma agnóstica al paquete — cualquier dependencia nueva bajo
`vendor/` se prefija automáticamente sin tocar ese archivo). Todas MIT,
verificadas con `composer licenses --no-dev`:

| Paquete | Versión | Licencia | Uso |
|---|---|---|---|
| `minishlink/web-push` | ^11.0 | MIT | Envío real de notificaciones push web (VAPID + cifrado RFC 8291/8292). |
| `nyholm/psr7` | ^1.8 | MIT | Implementación PSR-7/PSR-17 mínima — mensajes HTTP para `minishlink/web-push`, sin traer un cliente HTTP de terceros (`Pluma\Proveedores\ClienteHttpWp` sigue siendo el único punto de contacto con la red, sobre `wp_remote_request()`). |
| `php-http/discovery`, `php-http/httplug`, `php-http/promise` | — | MIT | Dependencias transitivas de `minishlink/web-push` (descubrimiento de cliente PSR-18, no se usan directamente). |
| `psr/http-client`, `psr/http-factory`, `psr/http-message`, `psr/clock`, `psr/log` | — | MIT | Interfaces PSR estándar, sin lógica propia. |
| `spomky-labs/base64url`, `spomky-labs/pki-framework`, `web-token/jwt-library`, `brick/math`, `symfony/polyfill-mbstring`, `symfony/polyfill-php83` | — | MIT | Dependencias transitivas de `minishlink/web-push` (criptografía VAPID/JWT). |

## Estado — Etapa 4 (La experiencia premium)

El panel React (`build/panel/`, el único bundle JS que se distribuye — se
carga solo en la pantalla propia del plugin, GOVERNANCE §pl-wp-core) empaqueta
una dependencia real desde la Mesa Editorial (Cap. 10.2, vista de diff entre
ciclos de borrador):

| Paquete | Versión | Licencia | Uso |
|---|---|---|---|
| `react` | ^18.3.1 | MIT | Motor de la interfaz del panel. |
| `react-dom` | ^18.3.1 | MIT | Renderizado de React sobre el DOM del panel. |
| `diff` (jsdiff) | ^9.0.0 | BSD-3-Clause | Diff línea a línea entre ciclos de un borrador en la Mesa Editorial. |
