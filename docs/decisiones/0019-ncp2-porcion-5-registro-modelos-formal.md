# ADR 0019 — NCP-2 Porción 5: registro de modelos formal

- **Fecha**: 2026-08-03
- **Estado**: Aceptada — construido y verificado
- **Contexto**: `docs/CEREBRO_PLUMA_v2.md` Parte 5.1 regla 5, `ADR 0016` (ENC vía T3), `docs/ncp-estado-y-continuidad.md` §5(c)

## Decisión

Se construye la estructura formal que el canon exige (*"Ningún artefacto sin registro: todo modelo cargado declara rol, versión, licencia, idioma, checksum y procedencia; sin registro, no carga"*), consolidando lo que hasta ahora era una constante informativa suelta (`ProveedorEmbeddingsCerebroRemoto::MODELO_REFERENCIA`, `ADR 0016`) en un registro real:

- `Pluma\Proveedores\RolModelo` — enum de los 8 roles del canon (`Enc`, `Ner`, `Seg`, `Lid`, `Nli`, `Rrk`, `Cls`, `Tox`), mismos códigos que `ADR 0014` §2.
- `Pluma\Proveedores\ModeloRegistrado` — DTO `final readonly` (rol, artefacto, versión, licencia, idiomas, checksum nullable, motivo del checksum nulo, procedencia).
- `Pluma\Proveedores\RegistroModelos` — catálogo, hoy con **una única entrada real**: ENC vía `multilingual-e5-small`/T3, migrada de `ADR 0016`.

`ProveedorEmbeddingsCerebroRemoto::MODELO_REFERENCIA` se retira (sin consumidores reales, confirmado por búsqueda en todo el repositorio) — su docblock ahora apunta a `RegistroModelos` como única fuente de verdad. Cero cambio de comportamiento.

## Por qué solo una entrada

El registro lista modelos **realmente en uso**, no candidatos investigados en `ADR 0014` que todavía no se construyeron (NER, LID, TOX, etc.) — inventar entradas "planeadas" para roles sin implementación real violaría cero-invención (`CLAUDE.md`). La siguiente porción de NCP-2 (opción (a), NLI/RRK vía T3) añadirá la segunda entrada real.

## El campo `checksum` y su honestidad

El canon exige checksum para todo artefacto cargado, pero T3 (cerebro remoto) no descarga nada — el modelo vive en el servicio remoto, fuera del control de PLUMA (`ADR 0016`). En vez de omitir el campo en silencio o inventar un checksum que no existe, `ModeloRegistrado` tiene un campo `motivoSinChecksum` obligatorio-por-convención cuando `checksum` es `null` — verificado en `RegistroModelosTest::test_toda_entrada_sin_checksum_declara_un_motivo()`, no en el DTO en sí (el DTO es deliberadamente `final readonly` sin lógica; la invariante se comprueba en el test, no en el constructor).

## Qué NO hace esta porción

- **No aplica ningún enforcement runtime** ("sin registro, no carga") — no existe hoy ningún transporte que cargue un artefacto local (T1/T2 sin construir) al que enganchar ese gate. Conectar el registro a una compuerta real de carga es una decisión futura explícita, cuando exista algo real que cargar.
- **No añade UI de panel** para ver el registro — el canon lo pide para paquetes de modelos descargables (T1/T2), que no existen; se difiere hasta que haya una razón real.

## Verificación

- `tests/Unit/Proveedores/RegistroModelosTest.php`: `todos()` devuelve exactamente 1 entrada hoy (ENC); `porRol(Enc)` la devuelve; `porRol(Ner)`/`porRol(Seg)` devuelven vacío; toda entrada sin checksum declara motivo no vacío; todo campo obligatorio no está vacío; construir un `ModeloRegistrado` directamente conserva sus valores.
- Gates: PHPCS 0, PHPStan nivel 8 0, PHPUnit Unit 719/719 en verde (incluida la suite existente de `ProveedorEmbeddingsCerebroRemotoTest.php`, confirmando que retirar la constante no rompió nada).

## Consecuencias

- Las próximas porciones que añadan un rol real (NLI/RRK) registran su entrada en `RegistroModelos` desde el principio, en vez de repetir el patrón de "constante suelta por clase".
- `ProveedorEmbeddingsCerebroRemoto` queda más alineado con su propio principio de diseño ("no sabe qué modelo hay detrás") al no tener ya ninguna referencia al nombre del modelo.
