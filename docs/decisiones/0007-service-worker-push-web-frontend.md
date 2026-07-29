# ADR 0007 — El service worker de notificaciones push web se autoriza como 5ª superficie de frontend público

- **Fecha**: 2026-07-28
- **Estado**: Aceptada
- **Contexto**: `docs/PLUMA_Engine_Nivel_Cuatro.md` — Capítulo W.3 (suscripciones de precisión y notificaciones) · Etapa 9, Porción 4 · `CLAUDE.md` § Ley de Arquitectura

## Decisión

**`CLAUDE.md` amplía de forma permanente la lista de superficies de frontend público permitidas**, de 4 a 5: bloque del editor, schema JSON-LD, banner de corrección opcional, página de autor por periodista, y ahora el **service worker + JS mínimo de suscripción a notificaciones push web** que Nivel Cuatro W.3 exige ("push web (PWA)... todo opt-in explícito, exportable, del cliente").

Es un cambio del propio texto de `CLAUDE.md` (la ley de ingeniería), no una excepción puntual documentada solo aquí — cualquier trabajo futuro sobre el frontend público debe tratar esta 5ª superficie como parte de la ley vigente, igual que las otras cuatro.

## Conflicto que motivó la decisión

`CLAUDE.md` (antes de este ADR) restringía el frontend público a 4 superficies enumeradas explícitamente, con "peso adicional en frontend ≈ 0". Las notificaciones push web reales (Push API del navegador) exigen un service worker registrado en el propio dominio del frontend público — no existe forma técnica de entregar push web sin él; no es una superficie que se pueda mover a `Pluma\Admin` o servir solo en wp-admin. `CLAUDE.md` § IDENTIDAD ordena explícitamente: "Ante conflicto entre ambos [CLAUDE.md y el Libro]: DETENTE y pide decisión del propietario. Nunca resuelvas el conflicto en silencio." Este ADR es esa decisión.

## Fundamento

- W.3 es alcance de producto ya fijado por el propietario en `docs/PLAN-MAESTRO-EVOLUCION.md` (fila "Canal propio", `W.1-W.3`) — no es una pieza nueva que se esté inventando ahora, solo su mecanismo de entrega (push web real) choca con una regla de arquitectura escrita antes de que W.3 se detallara.
- El peso real permanece mínimo: el service worker se sirve únicamente al lector que se suscribió explícitamente (opt-in, "todo opt-in explícito" es literal del texto fuente) — no se registra para todo visitante del sitio, a diferencia de cargar un bundle de admin en el frontend público (lo que sigue prohibido sin excepción).
- Alternativas descartadas: (a) diferir W.3-push como deuda — descartada porque el propietario decidió expresamente construirlo en esta porción; (b) tratarlo como "excepción puntual" sin tocar `CLAUDE.md` — descartada porque el propietario prefirió que la ley de ingeniería refleje la superficie real del producto de forma duradera, en vez de acumular excepciones no escritas en el documento que se supone que gobierna cada decisión futura.

## Consecuencias

- `CLAUDE.md` § Ley de Arquitectura, regla del frontend público: editada para listar la 5ª superficie, con referencia a este ADR.
- Todo código de este service worker/JS de suscripción vive fuera de las capas de dominio (`Pluma\Kernel`...`Pluma\Dto`) — es un artefacto de frontend puro, análogo al bloque del editor Gutenberg, registrado/encolado desde `Pluma\Publicacion` o una capa de distribución nueva (a definir en el Mission Lock de la Porción 4), nunca desde `Pluma\Admin`.
- Ninguna otra superficie de frontend queda autorizada por este ADR — sigue prohibido encolar cualquier otro asset de admin o de otra pantalla fuera de las 5 superficies ahora enumeradas.
