# ADR 0012 — Capa `Pluma\Idioma` y activación del locale editorial

- **Fecha**: 2026-07-31
- **Estado**: Aceptada
- **Contexto**: `docs/CEREBRO_PLUMA_v2.md` Parte 2 (fundación multilingüe, NCP-1) · `Periodista.php` (`localeEditorial`), `CompiladorDirectrices.php`, `VerificadorVoz.php`, `VocabularioProhibidoGlobal.php` · Nivel Tres Q.1 (Etapa 8, Porción 10) · `PLUMA-E9-21` (deuda de folding de acentos)

## Decisión

Se crea **`Pluma\Idioma`** como capa nueva de la Ley de Arquitectura (`CLAUDE.md`), con el contrato `PerfilIdioma` y el servicio `ResolutorPerfilIdioma`, y se activa por primera vez `Periodista::$localeEditorial` en el panel (selector en creación e Identidad). Junto con esto, se cierra `PLUMA-E9-21` en 5 de los 7 sitios donde el bug de folding de acentos estaba repetido, y se corrige `VerificadorVoz.php`, que llamaba a `VocabularioProhibidoGlobal::combinarCon()` sin pasar el locale del periodista.

## Contexto que motivó la decisión

Una auditoría de esta sesión encontró que `localeEditorial` (columna `locale_editorial`, viva desde la transición 0.16.0→0.17.0) era **inerte**: ningún componente del panel lo mostraba ni lo editaba, `RestPeriodistas::crear()` nunca lo pasaba, y solo 2 consumidores reales existían (`CompiladorDirectrices`, que sí pasa el locale, y `VerificadorVoz`, que no — mismo método estático, un sitio lo hace bien y el otro no). Al mismo tiempo, `VocabularioProhibidoGlobal::muletillasDeTextoIa()` ya aceptaba un parámetro `$locale` pero su `match` interno solo tenía una rama `default`: cualquier locale, soportado o no, caía silenciosamente al catálogo de `es-ES`.

Activar el selector de locale en el panel sin corregir esto habría convertido un catch-all inofensivo (porque nadie podía alcanzarlo) en una degradación silenciosa real: un editor eligiendo un locale nuevo vería su periodista evaluado igual contra un catálogo que no le corresponde, sin ningún aviso. El Santo Grial de `CLAUDE.md` (§3, cero placeholders; §5, 100% o declarado) exige que esta porción cierre ambos problemas juntos, no solo el de superficie.

Paralelamente, `Periodista::dominioDe()` (asignación editorial por vertical) normaliza con `mb_strtolower(trim())` pero no pliega diacríticos — "Economía" (como lo devuelve el LLM en texto libre) no calzaba con una especialidad declarada "economia". Ya era deuda ticketada (`PLUMA-E9-21`). El mismo patrón roto se repetía en 6 sitios más del código.

## Fundamento

- **`Pluma\Idioma` como capa nueva, no un sub-namespace de `Redaccion`**: hoy solo `Redaccion` la consume, pero el propio nombre del contrato (`PerfilIdioma`) y el diseño de `docs/CEREBRO_PLUMA_v2.md` Parte 2 la describen como fundación transversal — cuando el Motor SEO o las Compuertas necesiten legibilidad/formato por idioma (Plano 1, NCP-2/NCP-3), deben poder depender de ella sin que `Redaccion` se vuelva una dependencia cruzada no autorizada. Mismo patrón que `Pluma\Taxonomia`: capa "hoja", sin dependencias de otras capas de dominio, cualquiera puede depender de ella.
- **Alcance estrictamente Plano 0**: `PerfilIdioma` expone `locale`, `direccion` (LTR/RTL) y `cobertura` (`Completo`/`Parcial`/`NoSoportado`) — nada de segmentador, tokenizador, stemmer, fórmula de legibilidad o formatos de fecha/número, porque esos dependen de los órganos semánticos ONNX del Plano 1, que no existen todavía (NCP-2/NCP-3, sin planificar). Añadirlos habría sido la forma más silenciosa de placeholder que `CLAUDE.md` prohíbe: un campo que nadie lee. `NivelCobertura::Completo` queda reservado y ningún resolutor de esta porción lo produce — invariante cubierta por test.
- **Negar en vez de degradar**: `RestPeriodistas` valida el `localeEditorial` declarado contra `ResolutorPerfilIdioma::resolver()` y rechaza con 400 cualquier locale `NoSoportado`, con el motivo exacto como mensaje. El selector del panel nace con una sola opción visible (`es-ES`) — honesto en vez de fingir soporte multilingüe que no existe.
- **`VocabularioProhibidoGlobal`**: el catálogo actual de muletillas de IA se mueve de `default` a una rama explícita `'es-ES' =>`; `default` pasa a devolver lista vacía. No se inventan catálogos para otros locales — con el borde REST rechazando ya cualquier locale sin cobertura, este `default` vacío es cinturón de seguridad, no ruta esperada.
- **Folding de acentos — decisión de alcance del propietario**: de los 7 sitios con el mismo patrón roto, 5 son comparaciones internas puras (`Periodista::dominioDe()`, `ClasificadorNivelFuente`, `VerificadorProcedenciaDeclaracion`, `CreadorAutomaticoPeriodistas`, `EvaluadorLegitimidadInsumo`) y se corrigen en esta porción. Los otros 2 (`RepositorioTendencias`, `AgrupadorTemasSinCobertura::muestrasDeduplicadas()` en su uso como especialidad declarada) cambian datos persistidos o mostrados al editor, no solo una clave de comparación — el propietario decidió dejarlos fuera de esta porción; quedan como deuda nueva en `docs/deuda.md`.
- **Tabla manual `strtr()`, no `Normalizer`/`iconv`** (`PlegadorDiacriticos`): `ext-intl` no está garantizado en el hosting de un cliente, e `iconv//TRANSLIT` depende de la libc del servidor — ya señalado en `PLUMA-E9-21`. Una tabla fija es 100% determinista en cualquier instalación PHP 8.2.

## Consecuencias

- **Esquema**: sin cambios — `locale_editorial` ya existe desde 0.16.0→0.17.0; esta porción solo la activa.
- **`RepositorioPeriodistas::actualizarIdentidad()`** gana el parámetro `string $localeEditorial = 'es-ES'` (antes no lo tocaba en absoluto); `crear()` ya lo tenía.
- **`docs/deuda.md`**: `PLUMA-E9-21` se cierra para los 5 sitios corregidos; se abre una entrada nueva para los 2 sitios de riesgo (`RepositorioTendencias`, `AgrupadorTemasSinCobertura`) que siguen sin foldear.
- Ninguna otra capa depende de `Pluma\Idioma` todavía — el Orquestador y las Compuertas no se tocan: ningún verificador dentro de ellas lee `localeEditorial` hoy, y los 2 consumidores reales ya reciben `Periodista` completo como parámetro directo.
