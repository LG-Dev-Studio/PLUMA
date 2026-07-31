# ADR 0015 — NCP-2 Porción 1: veredicto del spike de embeddings locales (T1/FFI descartado con evidencia)

- **Fecha**: 2026-07-31
- **Estado**: Aceptada — spike ejecutado, veredicto: **FALLO en el paso 1**, documentado con evidencia real
- **Contexto**: `ADR 0014` (investigación NCP-2), plan de la Porción 1 de NCP-2 (spike de `CodeWithKyrian/transformers-php` + `Xenova/multilingual-e5-small` para el rol ENC vía transporte T1/FFI)

## Decisión

El spike se detiene en su primer paso, tal como el propio plan de esta porción anticipó explícitamente como resultado válido ("si no lo permite, ese es ya un resultado del spike... se documenta y se para ahí, no se fuerza"). **Los pasos 2-6 del plan (instalar `transformers-php`, descargar el modelo, tokenizar, inferir, medir tiempos) no se ejecutaron** porque su precondición (FFI disponible) no se cumple ni siquiera en el entorno de desarrollo que el propio proyecto controla por completo.

## Evidencia

Contenedor `cli` de wp-env (el mismo entorno Docker donde corren todos los gates del proyecto — el más parecido a una VPS que PLUMA controla hoy):

```
$ php -m | grep -i ffi
(sin salida — el módulo FFI no está entre las extensiones cargadas)

$ php -r "var_dump(class_exists('FFI'));"
bool(false)

$ php -i | grep -i "ffi.enable"
(sin salida — la directiva ni siquiera existe: la extensión no está compilada)

$ find / -name "*ffi*" -iname "*.so" 2>/dev/null
(sin resultados — no hay ninguna librería .so de FFI en el sistema de archivos)

$ cat /etc/os-release
NAME="Alpine Linux"
VERSION_ID=3.24.1

$ php -v
PHP 8.2.32 (cli) (built: Jul 2 2026 20:50:19) (NTS)

$ php -i | grep "Configure Command"
Configure Command => './configure' '--build=x86_64-linux-musl' [...]
'--enable-mbstring' '--enable-mysqlnd' '--with-password-argon2'
'--with-sodium=shared' '--with-pdo-sqlite=/usr' '--with-sqlite3=/usr'
'--with-curl' '--with-iconv=/usr' '--with-openssl' '--with-readline'
'--with-zlib' '--enable-phpdbg' '--enable-phpdbg-readline' '--with-pear'
(sin '--enable-ffi' en ningún punto de la línea de configuración)
```

Este contenedor usa la imagen oficial de Docker `php:8.2-cli-alpine` (la misma familia de imagen que `@wordpress/env` — mantenida por el propio proyecto oficial de PHP). **La imagen Docker oficial de PHP no compila FFI por defecto.** No es una directiva de `php.ini` deshabilitada (`ffi.enable=false`, algo reversible en runtime/config) — es que el módulo `FFI` no existe en absoluto en este binario de PHP: `class_exists('FFI')` devuelve `false`, y no hay ninguna directiva `ffi.enable` que activar porque no hay extensión que la reconozca.

## Fundamento — por qué esto es un hallazgo real, no un obstáculo del entorno de pruebas

La investigación de `ADR 0014` (transportes) ya señalaba que FFI depende de que el hosting lo permita, con la hipótesis (sin confirmar entonces) de que el hosting compartido probablemente lo bloquea pero un VPS/entorno controlado por el propio equipo probablemente sí lo permitiría. Este spike refuta esa hipótesis de forma más fuerte de lo esperado: **ni siquiera el entorno más controlado que existe — el contenedor Docker que el propio proyecto define, construye y ejecuta para sus propios tests — tiene FFI disponible sin una intervención deliberada** (reconstruir la imagen con `docker-php-ext-install ffi` o cambiar a una imagen base distinta). Esto no es un límite de un hosting de terceros fuera de nuestro control: es el punto de partida por defecto de la propia cadena de herramientas oficial de PHP.

Esto tiene una implicación directa para el transporte T1 como estrategia por defecto: si el equipo que construye el producto necesita un paso de build explícito para tener FFI disponible incluso en su propio contenedor de desarrollo, exigirle lo mismo a cada cliente en su VPS (reconstruir su stack de PHP, o depender de que su proveedor de VPS lo haya compilado) es una fricción operativa real, no un detalle menor — coherente con el veredicto ya anticipado en `ADR 0014` de que T1 tiene "menor complejidad operativa" solo *cuando* FFI ya está disponible, no como estado por defecto a asumir.

## Consecuencias

- **T1 (FFI en proceso) queda descartado como transporte por defecto para el rol ENC**, con evidencia real, no solo con la hipótesis de la investigación previa. No se descarta para siempre — un cliente con un VPS donde decida reconstruir su PHP con FFI compilado seguiría siendo un caso válido — pero no puede ser el camino que el producto asuma como "simplemente funciona en un VPS".
- **La pregunta de tokenización (SentencePiece/Unigram vía `transformers-php`) queda sin verificar** — no porque se haya refutado, sino porque nunca llegó a probarse: el spike se detuvo en su precondición de transporte, antes de llegar a la pregunta de tokenización. Esto sigue siendo un hueco de investigación abierto si en el futuro se retoma T1 o T2 (sidecar, que también podría usar la misma librería de tokenización en un proceso separado con su propio PHP compilado con FFI).
- **Próxima porción**: evaluar T3 (cerebro remoto/servicio gestionado) para el rol ENC, que es transporte-agnóstico respecto a este hallazgo — no depende de FFI en el proceso de WordPress, ya tiene el contrato de credenciales construido (`Pluma\Proveedores\ProveedorCerebroRemoto`, NCP-1 Porción 4), y es el único transporte que la propia investigación de `ADR 0014` ya identificaba como viable en cualquier hosting. La pregunta de tokenización se trasladaría entonces al lado del servicio remoto (Python/Rust, con las librerías de tokenización maduras de Hugging Face), no a PHP — resolviendo de raíz el hueco de `transformers-php` señalado arriba, a cambio de la complejidad de operar (o exigir al cliente que opere) un servicio real.
- No se tocó ningún archivo de producción (`src/`, `tests/`) — esta porción fue exclusivamente de investigación ejecutada, tal como el plan aprobado especificaba.
