# Hooks públicos de PLUMA Engine

Convención: `pluma/` + snake_case (GOVERNANCE §1.4). Estabilidad declarada por
hook: **interno** (puede cambiar de firma sin aviso, aún no lo consume nadie
fuera del núcleo) o **público** (API de venta — romperlo es breaking change
SemVer, pl-wp-core §4).

## Eventos de transición de la Pieza

Disparados por `Pluma\Pipeline\Transicionador::transitar()` en toda
transición de estado aplicada (CLAUDE.md § Ley de Arquitectura: "toda
transición de estado dispara evento `pluma/pieza_{estado}`").

| Hook | Firma | Estabilidad | Desde |
|---|---|---|---|
| `pluma/pieza_en_investigacion` | `(int $piezaId, EstadoPieza $estadoAnterior, string $motivo)` | Interno | Etapa 1 |
| `pluma/pieza_investigada` | `(int $piezaId, EstadoPieza $estadoAnterior, string $motivo)` | Interno | Etapa 1 |
| `pluma/pieza_en_redaccion` | `(int $piezaId, EstadoPieza $estadoAnterior, string $motivo)` | Interno | Etapa 1 |
| `pluma/pieza_redactada` | `(int $piezaId, EstadoPieza $estadoAnterior, string $motivo)` | Interno | Etapa 1 |
| `pluma/pieza_fallida` | `(int $piezaId, EstadoPieza $estadoAnterior, string $motivo)` | Interno | Etapa 1 |
| `pluma/pieza_descartada` | `(int $piezaId, EstadoPieza $estadoAnterior, string $motivo)` | Interno | Etapa 1 |
| `pluma/pieza_retenida` | `(int $piezaId, EstadoPieza $estadoAnterior, string $motivo)` | Interno | Etapa 1 |

Los estados posteriores (`optimizada`, `en_revision`, `aprobada`,
`programada`, `publicada`) existen en el grafo (`pl-pipeline`,
`references/estados.md`) pero ningún código los alcanza todavía — sus hooks
se documentan cuando el Motor SEO / Compuertas / Publicador (Etapas 3+) los
disparen por primera vez.

**Promoción a público**: cuando un módulo fuera del núcleo (newsletter,
redes sociales, analítica — Libro Cap. 2.3) consuma un hook, se promueve
aquí a "Público" y su firma queda congelada hasta la siguiente major.

## Eventos de la Sala de Redacción (Etapa 2)

| Hook | Firma | Estabilidad | Desde |
|---|---|---|---|
| `pluma/presupuesto_al_80` | `(float $gastoHoyUsd, float $limiteDiarioUsd)` | Interno | Etapa 2 |
| `pluma/redactor_fallback_mecanico` | `(int $piezaId, string $motivo)` | Interno | Etapa 2 |

`pluma/presupuesto_al_80` lo dispara `Pluma\Proveedores\PresupuestoLenguaje::registrarGasto()`
una sola vez por día al cruzar el 80% del límite diario configurado.

`pluma/redactor_fallback_mecanico` lo dispara `Pluma\Redaccion\RedactorConFallbackMecanico`
cuando el proveedor de lenguaje no tiene presupuesto disponible o no hay
credenciales configuradas — decisión explícita del propietario: notificar y
usar `RedactorMecanico` en vez de bloquear la pieza (CLAUDE.md § Contrato del
Proveedor de Lenguaje). Un fallo técnico real (red, HTTP, formato, circuito
abierto) NO dispara este hook: se propaga y la pieza se marca `fallida`.

## Frontend público (Etapa 6, porción 4a)

`wp_head` — `Pluma\Seo\EmisorEsquemaFrontend::emitir()` es el **primer y único
hook de frontend del plugin** (hasta ahora el frontend público solo recibía lo
horneado en `post_content`). Sobre una pieza singular publicada por PLUMA
(identificada por la post meta `_pluma_pieza_id`) emite:

- El documento JSON-LD `NewsArticle`/`OpinionNewsArticle`/`AnalysisNewsArticle`
  (Libro Cap. 6.2). Paga la deuda `PLUMA-E3-4`.
- El marcado de transparencia de IA legible por máquina (Reglamento (UE)
  2024/1689, Art. 50; Nivel Tres N.3): la etiqueta `<meta name="iptc.digitalSourceType">`
  con el valor de vocabulario controlado IPTC `trainedAlgorithmicMedia`, solo
  sobre piezas generadas y publicadas por el sistema sin aprobación humana
  activa (post meta `_pluma_generado_ia`). Piso de fábrica no desactivable.

Lee solo post meta ya persistidas por `Pluma\Publicacion\Publicador` al publicar
— cero consultas a repositorios en tiempo de render (CLAUDE.md: peso adicional
en frontend ≈ 0).

## Página de autor por periodista (Etapa 6, porción 4b)

`Pluma\Seo\PaginaAutorPeriodista` es la **primera página virtual del plugin**:
no existía hasta ahora ningún `add_rewrite_rule`/`query_vars`/`template_include`
en el proyecto. Patrón:

- `init` (prioridad por defecto): `registrarReglaReescritura()` añade
  `^periodista/([^/]+)/?$` → `index.php?pluma_periodista_slug=$matches[1]`.
- `init` (prioridad 20): `flushSiHaceFalta()` consume una vez la opción
  `pluma_flush_reescritura_pendiente` (fijada por `Pluma\Kernel\Activador::activar()`)
  y llama `flush_rewrite_rules()`. **Necesario** porque `activar()` también
  corre en `plugins_loaded` (auto-actualización de esquema), antes de que
  `$wp_rewrite` exista — no puede purgar ahí mismo.
- `query_vars`: registra `pluma_periodista_slug`.
- `template_redirect`: resuelve el slug contra `RepositorioPeriodistasInterface::obtenerTodos()`
  (solo periodistas `EstadoPeriodista::Activo`); si no resuelve, `$wp_query->set_404()`
  + `status_header(404)` — el tema activo renderiza su 404 normal.
- `template_include`: cuando resuelve, sirve `src/Seo/templates/pagina-autor.php`,
  que envuelve con `get_header()`/`get_footer()` del tema activo (nunca `exit`/`die`
  fuera del guard `ABSPATH`, GOVERNANCE §1.5).

Declara siempre, sin opción de ocultarla, `Pluma\Redaccion\DeclaracionIdentidadSintetica`
(Art. 50 del Reglamento (UE) 2024/1689, Nivel Tres N.3): el nombre del
periodista es una identidad editorial sintética, no una persona física. El
`author.url` del JSON-LD emitido por `EmisorEsquemaFrontend` apunta aquí
cuando el nombre de la pieza resuelve a un periodista activo real.
