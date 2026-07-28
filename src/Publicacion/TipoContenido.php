<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

/**
 * Nivel Cuatro Y.1 — "la muralla entre redacción y publicidad, como
 * código": la separación iglesia/estado del periodismo clásico implementada
 * como invariante, no como intención. `Editorial` es el único valor que
 * `Publicador::publicar()` puede escribir — el pipeline editorial completo
 * (Investigación → Redacción → Compuertas → Publicación) no acepta ni
 * puede producir `Patrocinada` por construcción (ver
 * `tests/Invariantes/MurallaComercialInvarianteTest.php`, "test de
 * arquitectura" que el propio Y.1 exige).
 *
 * `Patrocinada` está declarado aquí como la mitad de la muralla que SÍ
 * existe hoy (el pipeline editorial no puede producirla); la otra mitad —
 * un flujo real para CREAR contenido patrocinado (identidad comercial
 * separada del banco de periodistas, revelación de afiliados, schema
 * propio) — queda deliberadamente sin construir: ninguna pantalla ni API
 * lo pide todavía, y diseñarla ahora exigiría inventar decisiones de
 * producto (¿cómo la crea el editor? ¿qué es exactamente una "identidad
 * comercial"?) que nadie ha tomado. Registrado como deuda explícita
 * (`PLUMA-E9-1`), no construido en silencio.
 */
enum TipoContenido: string {

	case Editorial   = 'editorial';
	case Patrocinada = 'patrocinada';
}
