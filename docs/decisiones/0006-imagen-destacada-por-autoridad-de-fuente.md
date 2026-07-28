# ADR 0006 — Imagen destacada por autoridad de fuente (alternativa del propietario a la Porción 8 original)

- **Fecha**: 2026-07-27
- **Estado**: Aceptada
- **Contexto**: sustituye, por decisión explícita del propietario, el diseño original de la Porción 8 de la Etapa 8 (D.1-D.3 + N.2 + G.2, diferido en `ADR 0005`) · deuda relacionada `PLUMA-E3-2`, `PLUMA-E8-7`

## Decisión

En vez de generar o comprar una imagen destacada (`ADR 0005`, diferido a versión posterior al lanzamiento por falta de proveedor de imagen decidido), PLUMA **transfiere la imagen del sitio fuente de mayor autoridad** y la asocia a la Pieza propia. La fuente con mayor `NivelFuente` (A > B > C, ya construido en la Etapa 8 Porción 2) se prueba primero para extraer su `og:image`/`twitter:image`; si no tiene imagen o no responde, se prueba la siguiente fuente por orden de autoridad, sin repetir host.

Dos modos, **ninguno activo de fábrica** (`ModoImagenDestacada::Ninguna` por defecto — nadie queda expuesto sin activarlo explícitamente en la pestaña de ajustes del motor):

- **Enlazada**: la imagen se incrusta en el contenido (`<img src="...">` apuntando a la URL original) — nunca se copia al servidor del cliente.
- **Descargada**: se descarga vía `media_sideload_image()` a la biblioteca de medios de WordPress y se fija como imagen destacada nativa (`set_post_thumbnail()`) — copia real en el servidor del cliente.

El crédito a la fuente (enlace con `rel="nofollow noopener"` al artículo original) es **configurable de forma independiente** del modo (`OPCION_CREDITO_VISIBLE`, visible por defecto) — el cliente puede ocultarlo, pero eso no cambia el riesgo legal, y el texto del panel lo dice explícitamente.

## Fundamento — por qué esto es una desviación deliberada, no un descuido

`Pluma\Investigacion\HechoFuente` documenta desde la Etapa 1 el principio "extracto acotado, url y fecha para citar y enlazar — **jamás para reproducir**", aplicado hasta hoy a todo el material textual del expediente. Esta funcionalidad **rompe ese principio para imágenes**, a instrucción directa y explícita del propietario, después de que el agente señalara el riesgo:

> "ahora quiero que el sistema transfiera la imagen del sitio donde se cogieron las noticias y la suba con la noticia nuestra, la calificación de la portada dependerá de cuál fuente tiene mayor autoridad"

El riesgo señalado y aceptado por el propietario: usar la imagen de un medio de terceros sin licencia puede infringir derechos de autor (la imagen puede pertenecer a una agencia de fotografía, no al medio que la publicó), incluso citando la fuente — citar no exime de la infracción. El propietario, tras conocer el riesgo, decidió proceder de todas formas, con estas mitigaciones explícitas:

1. **Ambos modos son opt-in, apagados por defecto** — el riesgo no llega a ningún cliente que no lo active a propósito.
2. **Aviso legal persistente en la propia UI del plugin** (no solo verbal/una vez): el texto de la pestaña de ajustes dice literalmente que activar cualquiera de los dos modos implica asumir el riesgo de infracción, que PLUMA no verifica licencias de terceros, y que mostrar u ocultar el crédito **no cambia ese riesgo**.
3. **El riesgo lo asume el titular del sitio WordPress que activa el modo**, no PLUMA — mismo principio de responsabilidad ya aplicado a otras funciones de alto riesgo del producto (p. ej. el modo Autónomo).
4. **Extracción defendida contra SSRF**: tanto la URL del artículo como la URL de imagen extraída se validan con `Pluma\Proveedores\ValidadorUrl::esSegura()` (HTTPS, no rango privado/reservado) antes de usarse — una fuente maliciosa o comprometida no puede usar esta ruta para hacer que el servidor del cliente contacte una URL interna.

## Arquitectura

- `Pluma\Proveedores\ExtractorImagenFuenteInterface`/`ExtractorImagenFuente` (nuevo): único punto de contacto HTTP para esta funcionalidad (`Pluma\Proveedores`, regla dura de CLAUDE.md). Recibe interfaz — no clase final directa — siguiendo el mismo criterio ya establecido para toda clase de `Proveedores` que llama a un servicio externo (`LenguajeInterface`, `EmbeddingsInterface`, `ProveedorTendenciasInterface`), necesario además para que `SelectorImagenPorAutoridad` sea testeable sin `ClassIsFinalException`.
- `Pluma\Investigacion\SelectorImagenPorAutoridad` (nuevo, `final class` sin interfaz — clase de dominio puro, mismo criterio que `SelectorAngulo`/`AsignadorPeriodista`): ordena `Expediente::$hechos` por `NivelFuente::pesoBase()` descendente (reutiliza `ClasificadorNivelFuente`, ya construido en la Etapa 8 Porción 2 y registrado en `Nucleo.php` en esta misma porción — no existía todavía en el contenedor DI), prueba cada host una sola vez.
- `Pluma\Publicacion\AsignadorImagenDestacada` (nuevo): único punto del plugin que llama `media_sideload_image()`/`set_post_thumbnail()`/`wp_update_post()` para esta imagen — vive en `Publicacion` porque crea/edita el post WP (regla dura de CLAUDE.md).
- `Pluma\Pipeline\Orquestador::procesarRedaccionYBorrador()`: la asignación es **mejor esfuerzo, nunca bloqueante** — envuelta en su propio `try/catch` que registra el fallo en `$errores` sin detener la Pieza, mismo patrón de resiliencia ya usado para `evaluarModoRespeto()` (Etapa 8 Porción 7).
- Panel: nueva pestaña `imagenDestacada` en la Sala de Máquinas (`BloqueImagenDestacada.tsx`), con el aviso legal como texto persistente, no un modal de un solo uso.

## Consecuencias

- `docs/deuda.md`: se añade una fila nueva documentando la falta de circuit breaker/reintentos en `ExtractorImagenFuente` (un artículo lento/caído puede añadir latencia al procesamiento de la Pieza, mitigado por el timeout de 8s y por ser mejor-esfuerzo no bloqueante) y la falta de verificación automática de licencia (ningún sistema puede verificar automáticamente si una imagen tiene licencia libre — es una limitación de la industria, no solo de PLUMA).
- `ADR 0005` (Porción 8 original: generación/banco de imágenes con licencia) **sigue diferida tal cual** — esta funcionalidad no la sustituye conceptualmente, es un mecanismo distinto y de menor costo de infraestructura que el propietario prefirió para esta versión. Cuando se retome D.1-D.3, ambos mecanismos pueden coexistir como modos adicionales de la misma pestaña.
- `PLUMA-E3-2` (deuda de imagen destacada desde la Etapa 3) se marca pagada parcialmente por esta funcionalidad — la necesidad de producto ("las piezas necesitan imagen destacada") queda cubierta por transferencia de fuente; la necesidad original literal del Libro (imagen generada/banco con licencia propia) sigue abierta bajo `PLUMA-E8-7`.
