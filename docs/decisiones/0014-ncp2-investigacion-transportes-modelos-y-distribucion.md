# ADR 0014 — NCP-2 · Semántico: investigación de transportes, modelos, segmentación y distribución

- **Fecha**: 2026-07-31
- **Estado**: Aceptada (investigación) — NO es luz verde de implementación, ver "Siguiente paso"
- **Contexto**: `docs/CEREBRO_PLUMA_v2.md` Parte 1.3 (Plano 1 — Semántico), Parte 3.1-3.3 (transportes T1-T4), Parte 2.3 (paquetes de modelos), Parte 4 (distribución en tiendas), Parte 7 (prompt de activación, punto 3: "investigación obligatoria con fuentes"). NCP-1 · Porción 4 (Sonda de Capacidades, `ADR 0013`) ya mide la infraestructura real que este ADR necesita para decidir; NCP-2 es la fase que construye los órganos que esa sonda todavía no tiene nada que enrutar.

## Decisión

Se registra aquí la investigación con fuentes exigida por el canon (Parte 5.2.3: "investiga antes de nombrar") antes de escribir una sola línea de código de NCP-2. Cuatro líneas de investigación, cada una con hallazgos citados y huecos declarados explícitamente donde no se encontró respuesta autorizada — nunca inventados. **Este ADR no autoriza empezar a construir NCP-2**: es el insumo para el PLAN GUARDIAN que se presenta al propietario a continuación, pendiente de aprobación, tal como exige la Parte 7 punto 5 del canon.

## 1. Transportes T1-T4 — veredicto por hosting real

Investigación exhaustiva de las 4 vías de ejecución de ONNX Runtime desde PHP:

- **T1 (en proceso, FFI)**: técnicamente posible vía `ankane/onnxruntime-php` o `CodeWithKyrian/transformers-php` (ambos FFI sobre la librería nativa de ONNX Runtime), pero `ffi.enable` es `INI_SYSTEM` (solo configurable a nivel de servidor, no en runtime) y su valor por defecto (`"preload"`) restringe FFI a CLI — la documentación oficial de PHP y la práctica general de hosting compartido (bloqueo de acceso a librerías nativas/sistema) hacen que esto **no sea viable en hosting compartido típico**, solo en VPS/dedicado donde el cliente controla `php.ini`. Único punto de datos real encontrado (charla SymfonyCon Vienna 2024 / blog de Upsun): clasificación de texto con un modelo de ~2MB corriendo en una VPS de 2 vCPU/2GB RAM — sugestivo, no concluyente para modelos de 20-500MB.
- **T2 (sidecar local)**: requiere un proceso persistente fuera del ciclo de vida de PHP-FPM (gestionado por systemd/supervisor) — imposible en hosting compartido por definición, solo VPS. Candidatos reales: `kibae/onnxruntime-server` (C++, genérico, Docker) y Hugging Face `text-embeddings-inference` (Rust, pero **una instancia por modelo**, no sirve varios modelos pequeños en un solo proceso). Ningún número de RAM/disco publicado y verificado para servir varios modelos int8 concurrentes — hueco declarado, exige benchmarking propio antes de dimensionar.
- **T3 (cerebro remoto, HTTP)**: el único transporte que funciona desde CUALQUIER hosting, incluido el compartido más barato, porque PHP solo hace una petición HTTP autenticada. **Ningún paquete "listo para que un cliente no técnico lo instale" fue encontrado** — todos los candidatos (`onnxruntime-server`, TEI) son herramientas de línea de comandos orientadas a desarrolladores. Esto confirma que T3 para clientes no técnicos significa en la práctica **un endpoint gestionado por el propio vendedor**, con autohospedaje como opción solo para clientes técnicos con su propio VPS — ya anticipado por `Pluma\Proveedores\ProveedorCerebroRemoto` (NCP-1 Porción 4), cuyo contrato de credenciales queda confirmado como el diseño correcto.
- **T4 (navegador, WASM)**: `onnxruntime-web` es maduro (mantenimiento activo confirmado), sin problema de tamaño para modelos de 20-500MB (el límite real es 4GB direccionamiento WASM / 2GB formato de datos externos de ONNX). **Ningún precedente encontrado** de un panel de WordPress usándolo en producción — terreno genuinamente nuevo, coherente con el alcance ya decidido en NCP-1 (T4 excluido del resolutor server-side, `ADR 0013`, uso estrictamente interactivo del panel).
- **Veredicto por throughput real (3-10 piezas/día, ventana de 90 min, nunca en petición de visitante)**: al no requerir baja latencia ni concurrencia, la prioridad recomendada es (1) VPS con FFI en proceso si el cliente lo controla — menor complejidad operativa; (2) VPS con sidecar si FFI no es viable; (3) cerebro remoto gestionado por el vendedor para hosting compartido; (4) WASM solo para trabajo interactivo del panel. Ningún precedente publicado de ONNX corriendo desde un contexto WordPress fue encontrado — territorio genuinamente inexplorado para este ecosistema.

## 2. Modelos por rol — veredicto de licencia comercial (criterio eliminatorio, Parte 5.2.4)

Tabla completa de candidatos con licencia verificada directamente en la ficha de cada modelo (no de memoria):

| Rol | Candidato recomendado | Licencia | Veredicto |
|---|---|---|---|
| ENC | `intfloat/multilingual-e5-small` | MIT | Cualificado (~118M params, ONNX oficial) |
| ENC | `BAAI/bge-m3` (respaldo) | MIT | Cualificado, más pesado |
| NER | `Davlan/bert-base-multilingual-cased-ner-hrl` | AFL-3.0 | Cualificado, ONNX confirmado, **sin cobertura ja/th** |
| NER | `urchade/gliner_multi-v2.1` | Apache-2.0 | Cualificado, mejor cobertura de idioma (zero-shot), **ONNX sin verificar** |
| SEG | ICU `IntlBreakIterator` (`ext-intl`) | Unicode License | **Recomendado como opción por defecto — sin modelo, sin coste de inferencia** (ver §3) |
| SEG | `segment-any-text/sat-3l-sm` (respaldo ML) | MIT | Cualificado, solo si ICU resulta insuficiente |
| LID | Google CLD3 | Apache-2.0 | Cualificado, licencia más limpia, **no nativo en ONNX** |
| LID | `papluca/xlm-roberta-base-language-detection` | MIT | Cualificado, solo 20 idiomas, **no distingue pt-BR/pt-PT** (ningún candidato lo hace) |
| NLI | `MoritzLaurer/mDeBERTa-v3-base-xnli-multilingual-nli-2mil7` | MIT | **Cualificado, recomendado como motor de NLI Y de CLS zero-shot (mismo modelo, dos roles)** |
| RRK | `cross-encoder/ms-marco-MiniLM-L6-v2` | Apache-2.0 | Cualificado (inglés, ya int8/ONNX) |
| RRK | `BAAI/bge-reranker-v2-m3` | Apache-2.0 | Cualificado (multilingüe) — re-exportar ONNX en casa, no confiar en conversión comunitaria de terceros |
| TOX | `unitary/multilingual-toxic-xlm-roberta` | Apache-2.0 | Cualificado, solo 7 idiomas |

**Descalificados explícitamente** (nunca usar, sin importar calidad):
- `Babelscape/wikineural-multilingual-ner` — CC BY-NC-SA 4.0, no comercial.
- `jinaai/jina-reranker-v2-base-multilingual` — CC-BY-NC-4.0, no comercial (Jina exige API/licencia de pago aparte).
- `facebook/fasttext-language-identification` (lid218e) — CC-BY-NC-4.0, no comercial.
- `fastText lid.176` (original) — CC-BY-SA 3.0, ShareAlike de aplicabilidad ambigua a un producto cerrado de pago — **descalificado hasta revisión legal explícita**, no asumido permisivo por ser popular.
- `MoritzLaurer/deberta-v3-base-zeroshot-v2.0` (variante SIN sufijo "-c") — licencia MIT en los pesos, pero el propio autor documenta que el mix de entrenamiento incluye datos no comerciales — **usar solo la variante "-c"**, verificando su ficha directamente antes de adoptar.
- `textdetox/*-toxicity-classifier*` — OpenRAIL++ (mejor cobertura de idioma para TOX, 15 idiomas) — **no descalificado pero marcado como "necesita revisión de propietario/legal"**: OpenRAIL permite uso comercial pero con restricciones de uso que viajan con el modelo (Anexo A) — no es un Apache/MIT equivalente, no se adopta por defecto.

**Huecos declarados** (nunca inventados): ningún candidato de LID distingue variantes regionales (pt-BR vs pt-PT) — se necesitará una capa heurística propia encima, decisión de alcance pendiente, no resuelta por ningún modelo existente. Ningún benchmark de CPU de primera mano fue encontrado para ningún candidato — cualquier cifra de latencia citada en el futuro debe venir de medición propia, no de fuentes secundarias.

## 3. Segmentación para escrituras sin espacios y RTL

- **ICU vía `ext-intl` (`IntlBreakIterator`) es la respuesta correcta para SEG**, no un modelo neuronal: ICU trae segmentación por diccionario integrada para chino, japonés, tailandés, lao, jemer y birmano — sin coste de inferencia, sin archivo de modelo, sin riesgo de licencia (Unicode License, permisiva). Confirmado contra la documentación oficial de ICU y de PHP.
- **Riesgo real**: `ext-intl` es "muy recomendada" pero NO obligatoria en el manual de hosting de WordPress.org, y un post oficial del equipo de Hosting de WordPress (2021) describe un problema de huevo-y-gallina: los hosts no la habilitan porque el núcleo de WP no la exige — a diferencia de `ext-mbstring`, que es casi universal. **Debe tratarse como opcional-con-fallback** (`extension_loaded('intl')` en runtime), nunca como dependencia dura. Fallbacks PHP-nativos existen (`fukuball/jieba-php` para chino, `farzai/thai-word` para tailandés — este último exige PHP 8.4+, incompatible con el piso actual de PLUMA de PHP ≥8.2, hueco a resolver) pero son de comunidad más pequeña.
- **Árabe y hebreo SÍ usan espacios entre palabras** (a diferencia de CJK) — la segmentación de palabras/frases funciona con las reglas estándar de ICU/Unicode TR29 sin componente de diccionario. El problema real de RTL es **presentación (bidi), no segmentación**: dirección de renderizado, gobernado por el Algoritmo Bidireccional Unicode (UAX #9) — un asunto de `Pluma\Admin`/`Pluma\Seo` (CSS `dir`, `unicode-bidi`), nunca del motor de segmentación. Confirmado contra la documentación oficial de Unicode/W3C.
- Ningún modelo neuronal de segmentación (wtpsplit/SaT) es necesario para el caso de uso real de PLUMA (texto ya limpio, generado o editado, no texto ruidoso sin puntuación) — se reserva como mejora futura opcional, no como parte del diseño base.

## 4. Distribución — reglas de tienda y precedente de divulgación

- **Envato/CodeCanyon**: varias páginas de reglas técnicas específicas devolvieron error 403 al intento de lectura automatizada — **hueco explícito, sin resolver**, requiere lectura manual en navegador antes de comprometerse a este canal para la descarga de modelos post-instalación. No se encontró ni permiso ni prohibición explícita sobre descargas de binarios post-instalación ni sobre exigir extensiones PHP específicas (como FFI).
- **Venta directa/autohospedada**: la guía de "External Services" de WordPress.org (aunque PLUMA no se distribuye ahí) es el estándar del ecosistema más citable y ya adoptable voluntariamente: divulgar, por cada servicio externo, su nombre, URL, propósito, qué dato se envía y cuándo, con enlace a su política de privacidad — exactamente el patrón que `Pluma\Proveedores\ProveedorCerebroRemoto` y `ProveedorOpenRouter` (NCP-1) ya siguen de facto y que la futura pantalla de divulgación de NCP-2 debe formalizar explícitamente. Freemius/EDD Software Licensing son la infraestructura real de licencia+actualizaciones para este modelo de venta, sin que ninguno de los dos documente verificación por checksum de artefactos binarios — ese patrón (descarga bajo consentimiento, verificación por checksum, almacenamiento en `wp_upload_dir()` fuera del directorio del plugin) es práctica de ingeniería estándar de WordPress, no una regla legal documentada en ningún sitio único.
- Ningún ejemplo concreto de un plugin de WordPress con IA haciendo divulgación granular por endpoint (al nivel del propio `README`) fue encontrado — Jetpack AI divulga a nivel de política de privacidad, no de README. El estándar de WordPress.org, no un plugin observado, es la referencia más citable.

## Siguiente paso

Este ADR es el insumo de investigación. **No autoriza construir nada todavía.** El siguiente paso, per Parte 7 punto 5 del canon, es un PLAN GUARDIAN completo de la fase NCP-2 (o de su primera porción, dado el tamaño de la fase) presentado al propietario en modo de planificación, en espera de aprobación explícita — no una continuación automática de este ADR hacia código.
