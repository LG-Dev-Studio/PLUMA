# ADR 0013 — Sonda de Capacidades: medición sin enrutamiento

- **Fecha**: 2026-07-31
- **Estado**: Aceptada
- **Contexto**: `docs/CEREBRO_PLUMA_v2.md` Parte 1.6 (Plano 4 — El Enrutador Cognitivo), Parte 3.1-3.2 (transportes del Plano 1, contrato de degradación), Parte 5.1 (restricciones de código), Parte 5.2.2 ("el entorno se sonda, no se supone"), Parte 6.2 (tabla de fases NCP) · NCP-1 · Porción 4

## Decisión

Se construye la **Sonda de Capacidades**: `Pluma\Kernel\SensorCapacidades` (sensor de hechos crudos de infraestructura), `Pluma\Kernel\ResolutorPerfilEntorno` (derivación pura del transporte prioritario), `Pluma\Kernel\AlmacenPerfilEntorno` (snapshot cacheado, primer patrón de este tipo en el proyecto) y `Pluma\Proveedores\ProveedorCerebroRemoto` (credenciales + prueba del cerebro remoto, T3). Se expone en Sala de Máquinas (franja de salud + bloque de configuración del cerebro remoto) y en el export de diagnóstico.

**Esta porción es estrictamente de medición.** No conecta ninguna restricción de comportamiento — ni al modo Autónomo, ni a ninguna compuerta, ni al Orquestador. Eso es trabajo de una fase posterior y distinta (NCP-4 · Enrutador, Parte 6.2 del canon), no de esta.

## Fundamento — decisiones de diseño que un lector futuro necesita sin re-derivar

### a) `TransportePlano1` es medición prospectiva, no capacidad activa

El Plano 1 (semántico, ONNX) **no existe todavía en el código** — es NCP-2, sin construir. La Sonda mide hechos reales del hosting (FFI, proceso hijo, cerebro remoto configurado) y deriva qué transporte **se usaría si el Plano 1 existiera**, pero ningún código fuera de `ResolutorPerfilEntorno` puede tratar un valor de `TransportePlano1` distinto de `Ninguno` como "el Plano 1 está disponible hoy". Mismo patrón de honestidad que `Pluma\Idioma\NivelCobertura::Completo` (Porción 3 de NCP-1): un valor modelado y documentado, deliberadamente nunca producido por ningún resolutor actual, que existe para que el contrato no se rompa cuando la infraestructura real llegue.

### b) T4 (navegador/WASM) queda fuera del enum, a propósito

El canon (Parte 3.1) describe T4 como ejecución en el navegador del editor, "solo tareas interactivas del panel, nunca cron". Un resolutor PHP server-side (invocado desde REST o desde el tick del Orquestador) no tiene ninguna forma de observar "hay un navegador disponible ahora" — no es un hecho medible desde el servidor. A diferencia de `NivelCobertura::Completo` (que tiene un significado estable aunque hoy inalcanzable), incluir T4 en `TransportePlano1` habría sido un valor que el servidor no puede legítimamente producir nunca: activamente engañoso, no solo prematuro. Se excluye del enum; si NCP-2 construye trabajo interactivo en el panel que necesite modelar T4, será una extensión explícita, no una que esta porción anticipó mal.

### c) Ningún campo de "extensión ONNX candidata"

Se consideró y se descartó un campo `extensionOnnxCandidataPresente` en `HechosEntorno`. Ningún runtime ONNX está integrado en el código todavía, y no existe un nombre de extensión fijado por investigación con fuente (Parte 5.2.3: "investiga antes de nombrar... ningún modelo/runtime se afirma de memoria"). Fijar un nombre de extensión hoy sería inventar un requisito que NCP-2 todavía no ha investigado y decidido. Solo se reporta `ffiDisponible` — el mecanismo habilitante genérico (`extension_loaded('FFI')`), un hecho real y verificable hoy.

### d) Sin enforcement — el límite explícito de esta porción

El canon dice que sin Plano 1 disponible se debe restringir "automáticamente" el modo Autónomo (Parte 3.2). Esa restricción **no se conecta en esta porción**: `Pluma\Compuertas\ModoOperacion`, `Pluma\Compuertas\GestorDegradacion` y `Pluma\Compuertas\EvaluadorCompuertas` quedan sin tocar. La razón no es negligencia — es que el canon reserva explícitamente "matriz de enrutamiento, escalada por calidad... auditoría de procedencia" a **NCP-4 · Enrutador** (tabla de fases, Parte 6.2), una fase completamente distinta de NCP-1. Conectar una restricción real de comportamiento aquí, antes de que exista el Enrutador que la orquesta, sería exactamente el tipo de cambio de comportamiento disruptivo que `CLAUDE.md` exige declarar y acordar explícitamente con el propietario antes de implementar — no se hizo porque no se pidió, y porque hacerlo ahora habría sido inventar alcance. Queda documentado aquí para que ningún lector futuro asuma que ya está conectado.

### e) El cerebro remoto (T3) nunca se prueba en vivo desde el tick

`SensorCapacidades::medir()` (llamado desde el housekeeping no bloqueante de cada tick del Orquestador, fuera del presupuesto de tiempo) lee `ProveedorCerebroRemoto::ultimaPruebaOk()` — un flag **cacheado** — nunca llama a `ProveedorCerebroRemoto::probar()` (que sí hace una petición HTTP real). La única llamada de red real al cerebro remoto ocurre en la ruta REST `POST /motor/cerebro-remoto/probar`, disparada por un humano desde el panel, con su propio ciclo de vida y timeout corto. Esto significa que `cerebroRemotoConfigurado` en `HechosEntorno` responde "¿fue alcanzable la última vez que se guardó o se probó?", no "¿es alcanzable ahora mismo?" — una garantía más débil pero honesta, y la única compatible con no introducir la primera llamada de red real dentro de la sección no presupuestada del tick.

### f) Primer uso de `ValidadorUrl::esSegura()` sobre una URL introducida por un humano

Hasta esta porción, `ValidadorUrl` solo validaba URLs auto-descubiertas (tendencias, imágenes de fuente). El campo "URL del cerebro remoto" del panel es la primera vez que se valida una URL que un administrador escribe directamente — se rechaza con 400 cualquier URL que no sea `https`, no resuelva, o resuelva a un rango privado/reservado, antes de cualquier llamada de red (`RestSalaMaquinas::guardarCerebroRemoto()`).

### g) Snapshot cacheado — primer patrón de este tipo en el proyecto

No existía en el proyecto ningún patrón de "JSON versionado en un `wp_option`, refrescado periódicamente, con fallo abierto si la lectura falla". `AlmacenPerfilEntorno` lo introduce: `refrescar()` es el único punto de escritura (llamado desde `Nucleo::arrancar()` la primera vez tras activación — mismo patrón guardián que `Activador::actualizarEsquemaSiHaceFalta()` — y desde cada tick del Orquestador); `leer()` falla abierto a un `refrescar()` fresco si la opción falta, el JSON no decodifica, o la versión no coincide. Este es el molde a copiar para cualquier futura caché de cómputo periódico, en vez de reinventar la semántica de fallo abierto cada vez.

### h) Por qué no se enhebró `LenguajeInterface` en `Activador::activar()`

Se consideró refrescar el snapshot dentro de `Activador::activar()` (el archivo puente `pluma-engine.php` invoca `Activador::activarParaRed()` directamente, sin contenedor DI). `ProveedorOpenRouter` (la implementación real de `LenguajeInterface`) requiere `EnrutadorModelos`+`PresupuestoLenguaje`+`RelojInterface` — construirlo a mano en el bootstrap sin DI habría sido una complicación real para un valor de bajo retorno (en una activación fresca, `apiPagoConfigurada` es casi siempre `false`: el cliente activa el plugin antes de configurar cualquier llave). Se optó por resolver "en instalación" en `Nucleo::arrancar()`, que sí tiene el contenedor completo — cubre el mismo caso (primera carga real tras activar) sin tocar el archivo de bootstrap más delicado del plugin.

## Pregunta abierta, explícitamente diferida

¿Debería el tick del Orquestador re-probar periódicamente la alcanzabilidad real del cerebro remoto (no solo leer el flag cacheado de la última prueba manual)? El canon exige "detecta... presencia de cerebro remoto" en instalación y periódicamente — esta porción satisface esa letra con el refresco de caché por tick (§f), pero no re-verifica reachability en vivo. No es requisito de aceptación de esta porción (Parte 6.1.7 solo exige que la Sonda produzca un Perfil de Entorno correcto en los cuatro escenarios de hosting, verificado por test). Queda diferido, no decidido por omisión.

## Consecuencias

- **Esquema**: sin cambios de tabla — el snapshot vive en `wp_option` (`pluma_perfil_entorno`, `autoload = false`), no en una tabla propia; nuevas opciones escalares para el cerebro remoto (`pluma_cerebro_remoto_url`, `pluma_cerebro_remoto_token_cifrado`, `pluma_cerebro_remoto_ultima_prueba_ok`).
- `Pluma\Kernel\AlmacenPerfilEntornoInterface` se introduce porque `AlmacenPerfilEntorno` tiene múltiples consumidores (`Orquestador`, `ExportadorDiagnostico`, `PantallaPanel`, `RestSalaMaquinas`) que necesitan sustituirlo en tests — mismo patrón que `RepositorioXInterface` en `Pluma\Datos`. `SensorCapacidades` y `ProveedorCerebroRemoto` permanecen `final` sin interfaz propia: sus tests construyen instancias reales controladas vía `get_option`/colaboradores mockeados (interfaces), no mocks del propio `final class`.
- Ninguna capa de dominio (`Redaccion`, `Compuertas`, `Investigacion`) depende de esta porción — vive enteramente en `Pluma\Kernel`/`Pluma\Proveedores`/`Pluma\Admin`, sin necesidad de una capa nueva en `CLAUDE.md`.
