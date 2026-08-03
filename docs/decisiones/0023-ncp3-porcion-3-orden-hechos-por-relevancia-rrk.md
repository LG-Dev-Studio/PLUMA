# ADR 0023 — NCP-3 Porción 3: reordenamiento de hechos por relevancia vía RRK

- **Fecha**: 2026-08-03
- **Estado**: Aceptada — construida, conectada a producción, verificada
- **Contexto**: `docs/CEREBRO_PLUMA_v2.md` §1.3 (tabla de roles, RRK), `ADR 0020` (NLI/RRK vía T3), `ADR 0021`/`ADR 0022` (Porciones 1-2 de NCP-3, patrón previo)

## Decisión

Se construye `Pluma\Investigacion\OrdenadorHechosPorRelevancia`, primer consumidor real de `ProveedorRerankCerebroRemoto`. El canon (§1.3, tabla de roles) lista para RRK: "**Selección de extractos del expediente**". Reordena `$expediente->hechos` por relevancia a `tendenciaOrigen` — el orden se propaga a `Pluma\Redaccion\FormateadorExpediente::comoTexto()`, el único formateador que usan los 6 sitios que envían el expediente a cualquier llamada de lenguaje.

Conectado en `Pluma\Pipeline\Orquestador::procesarInvestigacion()`, como tercer paso de enriquecimiento tras `ResolutorDisputas`/`DetectorHuecos` — el orden queda persistido en el expediente de la Pieza.

## Dos decisiones de arquitectura confirmadas explícitamente por el propietario

1. **Solo reordenar, nunca excluir.** A diferencia de una "selección" que filtrase a un top-K, esta implementación reordena el array `hechos` completo — TODOS viajan siempre al redactor. `OrdenadorHechosPorRelevancia::ordenar()` valida que la respuesta de `/rerank` sea una permutación EXACTA de los índices `0..n-1` (mismo tamaño, sin duplicados, sin índices fuera de rango); si no lo es, trata la respuesta como inválida y devuelve el expediente sin tocar. Nunca construye un array con huecos.
2. **Degradación con gracia** (a diferencia de `ADR 0021`/`ADR 0022`, donde el fallo de T3 debe propagarse por ser verificaciones de seguridad editorial). Si `ProveedorRerankCerebroRemoto::reordenar()` lanza `ProveedorLenguajeException` (sin credenciales, red caída, respuesta malformada), `ordenar()` devuelve el `Expediente` original intacto — la investigación de la Pieza no falla. T3 sigue siendo obligatorio para las Porciones 1-2; esta porción es la primera de NCP-3 que deja T3 explícitamente opcional para su propia función, porque reordenar es una optimización de presentación sin riesgo de pérdida de información.

## Verificación

- Gates: PHPCS 0, PHPStan nivel 8 0.
- PHPUnit `--testsuite=Unit`: 753 tests, **747 en verde** — los 5 errores + 1 fallo restantes son exclusivamente `CifradoTest` (`PLUMA-E9-30`, preexistente, ya documentado en `ADR 0022`). 8 tests nuevos de `OrdenadorHechosPorRelevanciaTest` cubren: <2 hechos no llama a nada; reordenamiento válido preserva `tendenciaOrigen`/`huecosDetectados`; fallo del proveedor devuelve el original sin lanzar; sin credenciales devuelve el original sin lanzar; respuesta con menos resultados que hechos devuelve el original; índices duplicados devuelve el original; índice fuera de rango devuelve el original.
- PHPUnit `--testsuite=Invariantes`: 30 tests, los mismos 2 errores preexistentes de `PLUMA-E9-29` (ahora "32 vs 33", mecánicamente consistente con el nuevo parámetro; confirmado que el error sigue siendo `Orquestador::__construct()`, no introducido por esta porción).
- No hace falta verificación real contra un servicio TEI — el protocolo de `ProveedorRerankCerebroRemoto` ya está verificado en `ADR 0020`; esta porción es integración de código ya verificado.

## Consecuencias

- Tercer consumidor real de un rol Plano 1 en producción (NLI×2 + RRK×1) — NCP-3 ya cubre las 3 aplicaciones reales de NLI/RRK que el propietario identificó como opciones concretas tras cerrar la Porción 1.
- Primera pieza de NCP-3 donde T3 sigue siendo genuinamente opcional — el patrón "degradar con gracia cuando la operación no tiene riesgo de seguridad editorial" queda establecido como alternativa legítima al "propagar el fallo" de las Porciones 1-2, para que un continuador no asuma que TODA integración de T3 debe ser obligatoria por defecto.
- `Orquestador::__construct()` alcanza 33 parámetros — la deuda preexistente `PLUMA-E9-29` (2 tests de Invariantes desincronizados) sigue sin resolverse, ahora con un número distinto pero el mismo origen.
