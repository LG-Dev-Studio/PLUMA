# ADR 0009 — Páginas virtuales de confianza (Capítulo Z) y corrección de la lista de superficies de frontend de CLAUDE.md

- **Fecha**: 2026-07-29
- **Estado**: Aceptada
- **Contexto**: `docs/PLUMA_Engine_Nivel_Cuatro.md` — Capítulo Z (confianza pública) · `CLAUDE.md` § Ley de Arquitectura · Etapa 9, Porción 5

## Decisión

**Se corrige `CLAUDE.md` para reflejar con precisión las superficies de frontend público ya en producción, y se autoriza el patrón "página virtual" (rewrite rule + query var + `template_redirect` + `template_include`, ya usado por `PaginaAutorPeriodista` y `HistoriaHub`) como la vía estándar para nuevas páginas públicas de PLUMA — sin exigir un ADR individual por cada página nueva que siga ese mismo patrón ya aprobado.**

## Contexto del hallazgo

Al auditar la Porción 5 se confirmó que `Pluma\Seo\HistoriaHub` (Etapa 9, Porción 1) ya es una segunda página virtual pública en producción, y la lista de `CLAUDE.md` § Ley de Arquitectura nunca se actualizó para incluirla — un descuido de documentación, no una violación deliberada: `HistoriaHub` sigue el mismo molde exacto que `PaginaAutorPeriodista` (ya autorizada explícitamente), con el mismo peso mínimo ("nunca `exit`", frontend puro, sin acoplar capas). Corregirlo ahora, en vez de acumular más deuda de documentación, es más honesto que seguir pidiendo un ADR por cada página nueva que repite un patrón ya aceptado.

El Capítulo Z (Nivel Cuatro) pide 3 páginas nuevas de este mismo tipo (metodología, historial de correcciones) más contenido embebido en la pieza (expediente resumido, vía `the_content`, mismo patrón que `BannerCorreccion` de X.4) — ninguna introduce una superficie NUEVA en el sentido arquitectónico (no service workers, no scripts nuevos, no APIs externas): son datos reales del sistema, renderizados por el mismo mecanismo ya aprobado.

## Decisión de propietario

Confirmada al abrir esta porción: en vez de pedir un ADR por cada página virtual nueva (proceso que no añade juicio real cuando el patrón ya está aprobado), `CLAUDE.md` se actualiza UNA vez para declarar el patrón de página virtual como pre-autorizado, y la lista de superficies pasa a ser ilustrativa de las CATEGORÍAS permitidas (bloque del editor, JSON-LD, banner opcional, páginas virtuales de identidad/confianza, service worker de push opt-in) en vez de una enumeración cerrada que hay que reabrir cada vez.

## Consecuencias

- `CLAUDE.md` § Ley de Arquitectura: la regla del frontend público se reescribe para nombrar las categorías, citando `PaginaAutorPeriodista`/`HistoriaHub` como precedente del patrón "página virtual", y este ADR como registro de la corrección.
- Páginas nuevas de Z (metodología, historial de correcciones) y el expediente resumido por pieza (vía `the_content`) se construyen sin ADR individual — este documento es su autorización.
- Cualquier superficie GENUINAMENTE nueva en su mecanismo (no una página virtual ni un filtro de contenido — p. ej. un segundo service worker, una API externa nueva en el frontend) sigue requiriendo su propia decisión explícita, como ya ocurrió con `ADR 0007`.
