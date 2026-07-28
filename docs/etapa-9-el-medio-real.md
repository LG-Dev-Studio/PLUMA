# Etapa 9 — El medio real (Nivel Cuatro Parte II, territorio nuevo, TIER 3)

**Estado: EN CURSO.** Porción 1 (Historia como entidad, U.1-U.2+U.4) y Porción 2 (Comunidad mínima viable, X.1+Y.1) completas. Orden interno confirmado por `docs/PLAN-MAESTRO-EVOLUCION.md` §6 (N4-III.1): Historia primero porque toca esquema y grafo — se hace temprano para no migrar dos veces; el resto llega con el primer tráfico real.

## Objetivo y criterio de salida (`docs/PLAN-MAESTRO-EVOLUCION.md` §6)

> Etapa TIER 3: territorio nuevo del Nivel Cuatro — Historia como entidad, comunidad mínima viable, iniciativa editorial, canal propio, confianza y negocio.

## Porción 1 — Historia como entidad (Nivel Cuatro U.1, U.2, U.4)

**Texto fuente** (`docs/PLUMA_Engine_Nivel_Cuatro.md`, Cap. U): "Los tres documentos giran alrededor de la Pieza. Pero las noticias que importan no son piezas: son historias que evolucionan durante días o semanas... Falta la superficie de producto: el lugar donde el LECTOR vive la historia."

**Verificado contra el código real antes de diseñar nada**: no existía ninguna entidad `Historia`, ninguna tabla `pluma_historias`, ninguna página pública que agrupara Piezas de una misma saga. Lo único que existía era `Pluma\Sensores\ComparadorHistorias` (huella semántica del Radar, Libro Cap. 3.4) y el enlace de un solo salto `pieza_original_id`/`tendencia_original_id` — ninguno de los dos reconstruye una cadena completa de Piezas. Se construyó ENCIMA de ese mecanismo existente, reutilizando exactamente su punto de enganche ("dos golpes" confirmado por el editor), nunca inventando una detección de saga nueva.

**U.1 — la entidad Historia:**

- **`Pluma\Pipeline\Historia`** (DTO) + **`EstadoHistoria`** (`Abierta → EnSeguimiento → Inactiva → Cerrada`) + **`TipoPieza`** (`Original|Actualizacion|Correccion|Cierre`, Nivel Cuatro U.4) + **`BloqueConocimientoHistoria`** ("Lo que sabemos / Lo que no sabemos").
- **`Pluma\Pipeline\GestorHistorias`**: `vincularActualizacion()` se engancha en el ÚNICO punto donde hoy se enlazan dos Piezas de la misma saga — `GestorSalaTendencias::cubrirComoActualizacion()`, justo después de `RepositorioPiezasInterface::crearComoActualizacion()`. Crea la Historia si la Pieza original todavía no pertenecía a ninguna; la marca `EnSeguimiento` en cuanto tiene 2+ Piezas (el umbral de U.2 para el hub). `bloqueConocimiento()` agrega los hechos de TODOS los expedientes de la saga: `Verificado`/`Atribuido` → "sabemos"; `Disputado` → "no sabemos aún" (Nivel Dos B.1-B.2, reutilizado, cero heurística nueva). `marcarInactivasVencidas()` corre en cada tick del Orquestador (mejor esfuerzo, no bloqueante) y pasa a `Inactiva` las Historias sin Pieza nueva en `pluma_historia_dias_inactividad` días (default 14) — nunca un cierre, solo un estado descriptivo; `Cerrada` es exclusivamente un cierre editorial explícito.
- **Esquema `0.17.0 → 0.18.0`**: tabla nueva `pluma_historias` (título, estado, periodista titular) + `pluma_piezas` gana `historia_id`/`tipo`. Reversa registrada.
- **Bug preexistente corregido de paso**: los métodos "wither" de `Pieza` (`conEstado()`, `conExpediente()`, etc.) reconstruían el objeto sin pasar `piezaOriginalId` — se perdía silenciosamente en cada transición de estado. Se corrigió al añadir `historiaId`/`tipo` a esos mismos métodos, ya que tocarlos era inevitable.

**U.2 — el hub de historia como superficie pública:**

- **`Pluma\Seo\HistoriaHub`**: segunda página virtual del plugin (la primera fue `PaginaAutorPeriodista`, Etapa 6), mismo mecanismo exacto — `rewrite_rule` → query var → `template_redirect` resuelve → `template_include` sirve plantilla propia, nunca `exit` (GOVERNANCE §1.5). URL por id numérico (`/historia/{id}/`), no por slug del título: un título de saga no es estable ni único, y generar/garantizar unicidad de slug no es algo que U.1/U.2 pidan — cero invención de una capa que el texto fuente no exige.
- Solo Historias con 2+ Piezas resuelven — con menos, 404 real (U.2: "cada Historia con 2+ piezas genera automáticamente una página hub").
- Cronología navegable (solo Piezas ya publicadas, con post real que enlazar), bloque "lo que sabemos/no sabemos", periodista titular.
- **Schema.org**: `CollectionPage` con `hasPart` — no `LiveBlogPosting` como el texto fuente sugiere "según fase": `LiveBlogPosting` exige semánticamente `liveBlogUpdate` (actualizaciones dentro de UNA publicación), no una lista de artículos NewsArticle publicados por separado. Usarlo aquí habría sido un mal tipado de schema.org, no lo que U.2 realmente describe — decisión de ingeniería documentada en el propio código, no una decisión de producto.
- **Bug encontrado y corregido durante el testing de integración real**: las propiedades estáticas de `HistoriaHub` (mismo patrón que `PaginaAutorPeriodista`) no se reseteaban en la rama 404 — una petición que resolvía con éxito dejaba datos residuales que una petición 404 posterior seguía sirviendo. Corregido reseteando las cuatro propiedades incondicionalmente al inicio de `resolverPeticion()`. Se verificó que `PaginaAutorPeriodista` NO tiene este bug (reasigna su propiedad estática incondicionalmente antes del chequeo de null).

**U.4 — la actualización como ciudadana de primera:** formalizado como el campo `TipoPieza` de la Pieza (`original|actualizacion|correccion|cierre`) en vez de una convención implícita basada solo en `pieza_original_id IS NOT NULL`.

**Deuda pagada**: `PLUMA-EV-1` (rendimiento del modelo de datos ampliado, Nivel Cuatro) — parcialmente, `Historia` es la primera de las cinco entidades nuevas que ese ticket lista; el resto (`EventoProgramado`, suscriptor, pista, comentario clasificado) sigue pendiente hasta que se construyan.

### Evidencia de gates — Porción 1

| Gate | Resultado |
|---|---|
| PHPCS | 0 errores |
| PHPStan L8 | 0 errores |
| `composer test:unit` | 566/566 |
| `composer test:invariantes` | 21/21 |
| `composer test:integration` (wp-env real, migración 0.17.0→0.18.0) | 200/200 (2 skipped esperados) |
| `npx playwright test tests/e2e/salud.spec.ts` | 2/2 (verificación de que la tabla/entidad nueva no rompe activación) |
| `npx vitest run` / `tsc` / `build` | sin cambios de panel — no aplica a esta porción |

## Porción 2 — Comunidad mínima viable (Nivel Cuatro X.1 + Y.1)

**Texto fuente** (`docs/PLUMA_Engine_Nivel_Cuatro.md`, Cap. X.1 e Y.1). X.1: "moderación por IA — clasificar cada comentario nuevo en una de cinco categorías (spam, odio/ataque personal, afirmación arriesgada, crítica legítima, aporte informativo); spam/odio se marcan como tal automáticamente, afirmación arriesgada se retiene para revisión humana bajo régimen de responsabilidad severo, los dos últimos se publican y se destacan visualmente". Y.1: "la muralla entre redacción y publicidad, como código — ninguna ruta del pipeline editorial puede producir ni aceptar contenido patrocinado; test de arquitectura, no solo convención".

**Verificado contra el código real antes de diseñar nada**: WordPress ya expone comentarios reales end-to-end desde la Etapa 5 (`Pluma\Publicacion\LectorComentarios`/`PublicadorComentario`, borradores de respuesta del periodista) — X.1 no inventa el sistema de comentarios, añade una capa de clasificación y decisión de moderación encima de `wp_insert_comment`. Y.1 no tenía ningún precedente: `Publicador::publicar()` era el único punto que crea/publica el post de una Pieza, y no aceptaba ningún concepto de "tipo de contenido".

**X.1 — moderación por IA de comentarios:**

- **`Pluma\Compuertas\CategoriaComentario`** (enum): las cinco categorías literales del texto fuente.
- **`Pluma\Compuertas\ClasificadorComentarios`**: mismo molde que `Pluma\Redaccion\AnalizadorAudiencia` (clasificación de bajo coste, `PropositoLenguaje::ClasificarComentario` nuevo, temperatura 0.0, sin premium) — presupuesto verificado ANTES de la llamada (`PresupuestoLenguaje::disponible()`), cualquier fallo del proveedor o respuesta no interpretable devuelve `null`, nunca lanza.
- **`Pluma\Compuertas\CompuertaComentarios`**: se engancha a tres hooks reales de WordPress, verificados contra `developer.wordpress.org` antes de escribir la llamada (`pre_comment_approved`, `comment_post`, `comment_class`):
  - `evaluar()` (`pre_comment_approved`, prioridad 10, 2 argumentos): deja pasar sin clasificar cualquier post que no sea una Pieza de PLUMA (`Publicador::META_PIEZA_ID`) o cualquier valor ya no-numérico de `$aprobado` (otro filtro, ej. Akismet, ya decidió). Spam/odio → `'spam'` real. Afirmación arriesgada → retenida (`0`) según `pluma_comentarios_retener_afirmacion_riesgosa` (por defecto `true`), **siempre** retenida bajo régimen de responsabilidad Penal (reutiliza `RegimenResponsabilidad`/`CompuertaRiesgo::OPCION_REGIMEN_RESPONSABILIDAD`, construido en la Etapa 7 N.1 — la jurisdicción no es un dial que el cliente pueda relajar). Crítica legítima/aporte informativo → aprobados directos.
  - `persistirCategoria()` (`comment_post`, 1 argumento): guarda la categoría clasificada como comment meta, solo si hubo clasificación.
  - `destacarEnMarcado()` (`comment_class`, 3 argumentos): añade `pluma-comentario--destacado` + una clase por categoría a los comentarios de crítica legítima/aporte informativo — el tema activo decide cómo se ve, PLUMA no impone plantilla propia (frontend peso ≈ 0).
- **Bug real encontrado por el test de integración contra WordPress real, no por el test unitario con dobles**: `get_comment_class()` puede invocar el filtro `comment_class` con su segundo argumento (`$css_class`) como `string`, no solo `array` — la documentación oficial lo declara `string|string[]`, pero el primer borrador de `destacarEnMarcado()` tipó el parámetro como `array` a secas (nunca verificado contra la firma real antes de escribir la llamada, violación puntual de la regla 1 del `/goal`, corregida antes de cerrar la porción). Al llamar `get_comment_class('', $id, $postId)` en el test de integración, WordPress pasó `''` (string) y PHP lanzó `TypeError` en producción real — algo que ningún test con dobles habría detectado, porque el doble nunca ejercita el contrato real de WordPress. Corregido a `array|string $cssClass` (el parámetro nunca se usa, solo existe porque es posicionalmente obligatorio).

**Y.1 — la muralla comercial:**

- **`Pluma\Publicacion\TipoContenido`** (enum: `Editorial|Patrocinada`). `Publicador::publicar()` escribe SIEMPRE `Editorial` en un nuevo post meta (`META_TIPO_CONTENIDO`), hardcodeado, sin aceptarlo como parámetro — la muralla es estructural, no convención.
- **`tests/Invariantes/MurallaComercialInvarianteTest.php`**: dos verificaciones, tal como Y.1 exige "test de arquitectura". (1) `Publicador::publicar()` con cualquier snapshot escribe siempre `Editorial`. (2) ningún archivo del pipeline editorial (`Pipeline`, `Redaccion`, `Investigacion`, `Compuertas`, `Publicacion`, `Seo`, `Taxonomia`) referencia `TipoContenido::Patrocinada` — si este test se pone en rojo, la muralla se rompió.
- **Alcance deliberadamente NO construido, registrado como deuda explícita**: el flujo de CREACIÓN de contenido patrocinado (identidad comercial separada del banco de periodistas, pantalla/API de creación, revelación de afiliados visible, schema.org propio) — ninguna pantalla ni API lo pide todavía, y construirlo ahora exigiría inventar decisiones de producto que nadie ha tomado (condición de parada del `/goal`, no expansión de alcance no pedida). Ver `PLUMA-E9-1`.

**Deuda pagada**: ninguna deuda previa cerraba X.1/Y.1 directamente; `PLUMA-E9-1` se abre como deuda nueva por la mitad de creación de contenido patrocinado que Y.1 deja fuera.

### Evidencia de gates — Porción 2

| Gate | Resultado |
|---|---|
| PHPCS (repo completo) | 0 errores, 463/463 archivos |
| PHPStan L8 (repo completo, `--memory-limit=2G`) | 0 errores |
| `composer test:unit` | 596/596 |
| `composer test:invariantes` | 23/23 (+2 nuevos: `MurallaComercialInvarianteTest`) |
| `composer test:integration` (wp-env real) | 204/204 (2 skipped esperados, preexistentes; +4 nuevos: `tests/Integration/CompuertaComentariosTest.php`, hooks reales `pre_comment_approved`/`comment_post`/`comment_class` vía `wp_new_comment()`) |
| `npx vitest run` / `tsc` / `build` / Playwright | sin cambios de panel — no aplica a esta porción |

## Porciones 3-5

Pendientes. Orden: Iniciativa editorial (V.1-V.2) → Canal propio (W.1-W.3) → Confianza y negocio (X.2-X.4+Y.2-Y.3+Z).
