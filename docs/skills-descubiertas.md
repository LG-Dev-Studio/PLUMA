# Skills descubiertas — mapa de aplicabilidad (SKILLS-STACK §2)
Fecha de inventario: 2026-07-22 · Fuente: ecosistema del propietario (recursos de proyectos anteriores)

| Skill del ecosistema | Veredicto | Destino en PLUMA |
|---|---|---|
| lg-labs + 13 skills LG | APLICA íntegro | Capa de razonamiento de todo el proyecto (SKILLS-STACK §1) |
| exception-handling-and-logging | APLICA | Pluma\Proveedores + bitácora del motor |
| http-client-resilience | APLICA | Pluma\Proveedores (timeouts, backoff, circuit breaker) |
| ui-ux-pro-max | APLICA | Panel completo (tokens, tipografía, estados) |
| frontend-design | APLICA | Dirección estética del panel |
| kpi-dashboard-design | APLICA | Portada / dashboard del cap. 10 |
| tailwind-design-system | ADAPTAR | Sistema de tokens del panel (stack React del panel), con cautela por conflicto con CSS de wp-admin |
| web-design-guidelines | APLICA | Frontend público (bloque del editor) |
| spatie-security | ADAPTAR | Los principios sí; la implementación se traduce a capacidades WP |
| laravel-specialist / filament-pro / pest-testing / queues-and-horizon | NO APLICA directo | Stack Laravel; sus patrones (colas, testing por contratos) inspiran pl-pipeline y pl-testing |
| ri-processors / ri-filament / ri-testing / ri-ai-providers | NO APLICA (proyecto RoyalImage) | Su ESTRUCTURA es la plantilla de los skills pl-* de este repo |

## Ampliación — auditoría de `~/.claude/skills/` (2026-07-22, protocolo §2 completo)

Inventario ejecutado contra el directorio global de skills del agente (cientos de carpetas). Se excluyó explícitamente todo lo ofensivo/pentest (no aplica: PLUMA es un producto defensivo, no una auditoría ofensiva) y stacks ajenos (Laravel/Rails/mobile/blockchain/juegos/CRM de terceros).

| Skill | Veredicto | Destino en PLUMA |
|---|---|---|
| wordpress-plugin-development | ADAPTAR | Núcleo del plugin (hooks, admin, REST, capacidades); ignorar material de WP futuro no soportado, centrarse en PSR-4/`Pluma\*`, dbDelta, nonces |
| wordpress | ADAPTAR | Paraguas general; usar solo la porción plugin/seguridad/performance (no es theme ni WooCommerce) |
| llm-structured-output | APLICA | `Pluma\Proveedores` / `LenguajeInterface`: salidas estructuradas (JSON Schema) de los periodistas sintéticos |
| api-security-best-practices | ADAPTAR | REST controllers del panel: nonces + `permission_callback` + capacidades propias en vez de JWT/OAuth genérico |
| backend-security-coder | ADAPTAR | Sanitización/escape en todo el backend: `$wpdb->prepare`, `sanitize_*`, `esc_*` en vez de ejemplos genéricos |
| seo-schema / schema-markup / schema-markup-generator / seo-aeo-schema-generator | APLICA | Motor SEO: JSON-LD NewsArticle/OpinionNewsArticle |
| seo-sitemap | APLICA | Motor SEO: sitemap Google News (`news:news`, ventana 48h) |
| seo-technical | APLICA | Motor SEO: crawlability, structured data, convivencia con Yoast/Rank Math |
| seo-fundamentals | APLICA | E-E-A-T y Core Web Vitals → compuerta de calidad/originalidad |
| seo (umbrella) | APLICA | Auditoría end-to-end del Motor SEO |
| ai-seo / seo-geo | APLICA (adaptar a noticias) | Motor SEO: visibilidad en AI Overviews/ChatGPT/Perplexity |
| programmatic-seo | ADAPTAR | Patrón de páginas a escala análogo a publicación autónoma; usar su checklist anti-thin-content en compuerta de originalidad |
| seo-content-writer | ADAPTAR | `Pluma\Redaccion`; combinar con anti-alucinación y trazabilidad de fuentes de Investigación |
| wordpress-centric-high-seo-optimized-blogwriting-skill | ADAPTAR | Referencia de formato; sustituir "metadata Yoast" por Motor SEO/compuertas propias |
| avoid-ai-writing | APLICA | Compuerta de calidad editorial: que los periodistas sintéticos no suenen genéricos |
| professional-proofreader | ADAPTAR | Paso de post-edición automatizado dentro de la compuerta de calidad (no interactivo) |
| i18n-localization | APLICA | i18n completo del plugin (textdomain, .pot/.po) |
| accessibility-compliance-accessibility-audit / wcag-audit-patterns / ui-a11y | APLICA (ui-a11y el más accionable) | Panel admin React, cumplimiento WCAG |
| screen-reader-testing | ADAPTAR | Panel admin, adaptado a componentes React de PLUMA |
| react-best-practices | APLICA | Panel admin: performance/rendering |
| frontend-dev-guidelines | ADAPTAR | Adaptar fetching a `apiFetch`/REST WP en vez de Next.js/RSC |
| radix-ui-design-system | ADAPTAR | Solo si no colisiona con `@wordpress/components` |
| tailwind-design-system | ADAPTAR | Ver nota de cautela arriba |
| design-taste-frontend / ui-ux-designer | ADAPTAR | Estados de componentes/tipografía del panel; ignorar sesgos anti-wp-admin |
| backend-dev-guidelines / backend-architect | ADAPTAR | Traducir patrones (Node/Express) a PHP/WP: repositorios sobre `$wpdb`, servicios namespaced; boundaries para Sensores/Investigación/Proveedores |
| api-design-principles / api-patterns | ADAPTAR | REST controllers: versionado/paginación/envelopes de error, auth vía nonces WP |
| test-driven-development / tdd-workflow | APLICA | Ciclo red-green-refactor sobre PHPUnit |
| unit-testing-test-generate | APLICA | PHPUnit + Brain\Monkey |
| javascript-testing-patterns | ADAPTAR | Vitest del panel admin |
| e2e-testing / e2e-testing-patterns / playwright-skill | APLICA | Playwright E2E |
| secrets-management | ADAPTAR | Credenciales de proveedores IA: `wp_options` cifrado / constantes en `wp-config.php`, no Vault/AWS Secrets Manager |
| llm-app-patterns | ADAPTAR | Pipeline retrieval→generate para `Pluma\Investigacion` (extraer solo el patrón, no el stack Dify) |
| llm-evaluation | ADAPTAR | Métricas para la compuerta de calidad de los periodistas sintéticos |
| llm-prompt-optimizer / prompt-engineering / prompt-engineering-patterns | ADAPTAR | Diseño de prompts vía `LenguajeInterface`; NO cubren la neutralización anti-inyección obligatoria (eso es implementación propia de `Pluma\Proveedores`) |
| prompt-caching | ADAPTAR | Control de coste en publicación autónoma con presupuesto |
| workflow-orchestration-patterns / saga-orchestration | ADAPTAR | Traducir a cron propio single-node con candado global, no a motor de workflows externo (Temporal) |
| php-pro | ADAPTAR | PHP moderno junto con PHPStan L8 y `strict_types`, sin el enfoque framework-agnostic genérico |

**Excluidos explícitamente (NO APLICA):** `broken-authentication`, `security-audit`, `wordpress-penetration-testing` y demás skills de pentest ofensivo (fuera de alcance: PLUMA es defensivo); `cost-optimization` (coste de nube, no de tokens/API — para eso `prompt-caching`/`llm-ops`); `content-strategy`/`content-creator`/`copywriting` (marketing/conversión genérico, no periodismo con anti-alucinación — uso marginal como referencia); `wordpress-theme-development`, `wordpress-woocommerce-development` (PLUMA es plugin, no theme ni tienda).

Huecos identificados (investigar en fuentes oficiales antes de codificar): WordPress REST/dbDelta/i18n avanzado (→ `pl-wp-core` creado), schema.org NewsArticle/OpinionNewsArticle, protocolo de sitemap de Google News, API de Search Console, neutralización anti-inyección de prompts en `LenguajeInterface` (sin skill de ecosistema que lo cubra — implementación propia documentada en `pl-proveedor-ia`). Revisar este mapa al abrir cada Etapa (`/nueva-etapa`).

## Apertura de Etapa 0 — revisión del mapa (2026-07-22)

Inventario re-ejecutado sobre `.claude/skills/` (ubicación consolidada: los 6 `pl-*` y los 14 LG viven ahí desde 2026-07-22; las carpetas `skills/` y `skills-lg/` ya no existen). Los 20 skills son descubiertos y cargables por el agente en sesión.

Aplicables a la Etapa 0 (Cimientos), leídos íntegros antes de planificar:

| Skill | Aplicación en Etapa 0 |
|---|---|
| `lg-first-principles` | Cuestionar el "esqueleto estándar" antes de crearlo (ritual de apertura) |
| `lg-risk-radar` | Pre-mortem de la Etapa + registro vivo inicial |
| `lg-cto-mode` | Encaje del esqueleto con las 13 capas futuras sin crearlas vacías |
| `lg-critical-review` + `lg-metacognition` | Cierre del plan y de toda entrega |
| `pl-wp-core` | Ciclo de vida (activación/desactivación/uninstall), capacidades, scoping, i18n, wp-env |
| `pl-testing` | Mapa de tests del ciclo de vida, wp-env, convenciones desde el primer test |
| `pl-pipeline` | Solo lectura anticipatoria: el esqueleto no debe contradecir los contratos del Transicionador (Etapa 1) |

## Apertura de Etapa 6 — revisión del mapa (2026-07-24)

Etapa 6 ("Producto en venta") es la primera que no añade un módulo de dominio nuevo — cubre GOVERNANCE §5 (release/venta): versionado, empaquetado reproducible, matriz de compatibilidad, telemetría, modo diagnóstico, docs de venta. **§5.4 (licenciamiento + servidor de actualizaciones propio) queda fuera del alcance y se registra como deuda `PLUMA-E6-1`** — decisión del propietario al abrir la etapa (ver `PLAN-MAESTRO.md` nota¹ y `docs/deuda.md`).

Aplicables a la Etapa 6, releídos íntegros antes de planificar:

| Skill | Aplicación en Etapa 6 |
|---|---|
| `lg-first-principles` | Cuestionar qué de §5 es axioma de plataforma (WordPress no soporta licenciamiento premium nativo → servidor propio o de terceros es convención de la industria, no ley física) vs. convención revisable |
| `lg-risk-radar` | Pre-mortem de release: ZIP que rompe un sitio de cliente = devolución + reseña 1 estrella; migración N-1→N mal probada; matriz de compatibilidad incompleta que deja un combo WP/PHP/SEO real sin cubrir |
| `lg-independence` | Empaquetado reproducible (PHP-Scoper) y cualquier SDK de telemetría/diagnóstico van detrás de interfaz propia — ninguna lógica de negocio importa un SDK de terceros directamente |
| `lg-decision-framework` | Decisión ya registrada esta sesión: §5.4 se pospone (puerta de una dirección, con registro en deuda + nota en PLAN-MAESTRO) |
| `lg-elegance` | El modo diagnóstico y la telemetría no deben convertirse en un segundo sistema de logging paralelo a `pluma_bitacora_motor` — reutilizar, no duplicar |
| `pl-wp-core` | Empaquetado (build reproducible, PHP-Scoper), migraciones `dbDelta` N-1→N con reversa probada, matriz de compatibilidad (WP min/latest × PHP 8.2/8.3 × Yoast/Rank Math/ninguno), i18n de las nuevas pantallas/docs |
| `pl-proveedor-ia` | Si la telemetría opt-in envía datos fuera del sitio, ese envío es HTTP saliente → vive en `Pluma\Proveedores` detrás de una interfaz propia, igual que cualquier otro proveedor externo (nunca contenido del cliente ni llaves, por §5.5) |
| `pl-testing` | Matriz de compatibilidad como suite real (no solo documentada); smoke test del ZIP de producción en WP limpio como gate obligatorio antes de publicar (§5.2) |

Huecos identificados para esta etapa: ningún skill del ecosistema cubre firmado de actualizaciones/licenciamiento de plugins WP de pago — irrelevante mientras `PLUMA-E6-1` esté diferida, pero investigar en fuentes oficiales (EDD Software Licensing / Freemius / patrón de firma tipo Sparkle) cuando se retome.

No aplicables aún (se releerán en su Etapa): `pl-periodistas` (E2), `pl-compuertas` (E3), `pl-proveedor-ia` (E1–E2). Del ecosistema global, para Etapa 0 solo tocan: `test-driven-development`, `e2e-testing`/`playwright-skill` (montaje de suites) y `php-pro` (estándares PHP 8.2).

## Apertura de Etapa 1 — "El esqueleto que camina" (2026-07-22)

Etapa 0 cerrada (CI en verde, run 29968501162). Inventario para Etapa 1 releído íntegro:

| Skill | Aplicación en Etapa 1 |
|---|---|
| `pl-pipeline` (+ `references/estados.md`) | Núcleo de la etapa: grafo de estados de la Pieza, Transicionador, candado global+por-Pieza, presupuestos, azar, perecibilidad |
| `pl-proveedor-ia` (+ `references/contrato-lenguaje.md`) | `SensorInterface` para el Radar; `LenguajeInterface` SOLO si Etapa 1 decide generar contenido real (ver pregunta de alcance en el plan) |
| `pl-wp-core` | dbDelta de tablas `pluma_*` nuevas, endpoint del cron con token+candado, creación del post WP al final del pipeline |
| `pl-testing` | Convenciones ya usadas en E0 (RelojInterface/AzarInterface inyectables) + nuevo: doble ejecución del orquestador, muerte a mitad de lote, fixtures de proveedores sin red |
| `lg-first-principles`, `lg-risk-radar`, `lg-cto-mode` (lente Cerebro), `lg-critical-review`, `lg-metacognition` | Ritual de apertura completo (igual que Etapa 0) |

Sub-agentes de AGENTS.md que se activan: **ESQUEMA** (tablas `pluma_piezas`, `pluma_tendencias`, `pluma_fuentes`, `pluma_bitacora_motor`, `pluma_auditoria`), **ORQUESTADOR** (motor cron, candado, presupuesto de tiempo), **SEGURIDAD** (endpoint del cron con token rotable + rate limit).

No aplicables aún: `pl-periodistas` (E2 — el redactor con diales no existe hasta entonces), `pl-compuertas` (E3). Hueco a decidir con el propietario antes de codificar: la Sala de Redacción completa es Etapa 2, pero el criterio de salida de Etapa 1 pide un "borrador trazable, aunque sea rudimentario" — el plan de Etapa 1 propone el alcance exacto y lo deja pendiente de aprobación.

## Apertura de Etapa 2 — "El periodista" (2026-07-23)

Etapa 1 cerrada (CI en verde, run 29973288960). Inventario para Etapa 2 releído íntegro:

| Skill | Aplicación en Etapa 2 |
|---|---|
| `pl-periodistas` (+ `references/conducta.md`) | Núcleo de la etapa: 4 capas persistentes, versionado de conducta, memoria-antes-de-tesis, Corrector con checklist de 6 puntos, test de voz medible, export/import como API pública |
| `pl-proveedor-ia` (+ `references/contrato-lenguaje.md`) | Primera implementación real de `LenguajeInterface`: PeticionLenguaje/RespuestaLenguaje, neutralización anti-inyección (corpus adversarial), presupuesto ANTES de cada llamada, enrutamiento por propósito, fixtures sin red |
| `pl-wp-core` | 3 tablas nuevas + migración 0.2.0→0.3.0 con datos existentes, endpoints REST export/import con capacidad `pluma_gestionar_periodistas`, cifrado de llaves |
| `pl-testing` | Nace `tests/Invariantes/` (GOVERNANCE §2.4, §2.2-parcial, §2.5, §2.6); fixtures de proveedor de lenguaje |
| `pl-pipeline` | El Orquestador incorpora las fases de decisión editorial y redacción sintética; estados EN_REVISION/RETENIDA cobran uso real |
| Ecosistema: `llm-structured-output`, `prompt-engineering-patterns`, `prompt-caching`, `avoid-ai-writing` | Salidas JSON del modelo, compilación de directrices, control de coste, lista de muletillas IA para el vocabulario prohibido |
| LG: combo completo de "módulo nuevo" (SKILLS-STACK §1) | First Principles → Product Vision → CTO Mode → Risk Radar → Critical Review → Decision Framework |

Sub-agentes que se activan: **ESQUEMA** (pluma_periodistas, pluma_periodistas_versiones, pluma_memoria_editorial, pluma_borradores, ALTER pluma_piezas), **PERIODISTA** (toda la capa Redaccion), **SEGURIDAD** (llave de API cifrada, anti-inyección, endpoints REST de import), **ORQUESTADOR** (nuevas fases del tick, presupuesto de coste).

## Apertura de Etapa 3 — "La capa competitiva" (2026-07-23)

Etapa 2 cerrada (CI en verde, run 29981885310). Inventario para Etapa 3 releído íntegro:

| Skill | Aplicación en Etapa 3 |
|---|---|
| `pl-compuertas` | Núcleo de la etapa: las tres compuertas (Calidad/Riesgo/Originalidad), único camino legal hacia `Publicacion`, degradación por sensibilidad en dos capas, riesgo de difamación → RETENIDA, umbrales configurables con pisos de fábrica, tasa de retención observable |
| `pl-wp-core` §8 | Detección y escritura en campos de Yoast/Rank Math (nunca duplicar su capa SEO) — meta keys reales a verificar contra código/documentación oficial antes de escribir, nunca alucinados |
| `pl-pipeline` | Los modos Piloto/Copiloto/Autónomo pasan de configuración latente a comportamiento real del Orquestador; ventana de veto de Copiloto; estado EN_REVISION cobra su primer uso real con cola de decisión humana |
| `pl-proveedor-ia` | Posible uso de `PropositoLenguaje` para heurísticas de la Compuerta de Originalidad (ganancia de información) — a decidir en el plan si es determinista o vía modelo económico |
| `pl-testing` | `tests/Invariantes` recibe ahora los ítems diferidos en la Etapa 2: §2.1 (registro en `pluma_auditoria`), §2.2 segunda capa (bloqueo de sátira en `Pluma\Compuertas`), §2.3 (RETENIDA por afirmación fáctica sin doble fuente), §2.7 (escasez honesta) |
| Ecosistema: `seo-schema`/`schema-markup-generator`, `seo-sitemap`, `seo-technical`, `avoid-ai-writing` (compuerta de calidad) | Datos estructurados NewsArticle/OpinionNewsArticle, sitemap de noticias (protocolo Google News), auditoría de canibalización |
| LG: combo completo de "módulo nuevo" (SKILLS-STACK §1) | First Principles → Product Vision → CTO Mode → Risk Radar → Critical Review → Decision Framework |

No aplicable todavía: el bucle de retroalimentación de Search Console (Libro Cap. 6.4) es Etapa 5 explícita en PLAN-MAESTRO, no Etapa 3. El panel visual pulido de Cap. 10 (incl. Sala de Revisión con diseño premium) es Etapa 4; Etapa 3 solo necesita la superficie funcional (REST + notificación) que sostenga el criterio de salida ("una semana en Copiloto sin corrección posterior").

Sub-agentes que se activan: **COMPUERTAS** (nuevo — el más crítico de la etapa), **SEO** (Motor SEO, convivencia con plugins SEO existentes), **TAXÓNOMO**, **ORQUESTADOR** (modos reales, degradación, ventana de veto), **SEGURIDAD** (ningún bypass de compuertas, capacidad `pluma_aprobar_piezas` cobra uso real en Sala de Revisión).

### Hallazgo de `pl-testing` — tablas nuevas en Integración (wp-env) necesitan `set_up_before_class()`

Al añadir `pluma_vocabulario` (primera tabla GENUINAMENTE nueva desde el esquema original de este entorno de pruebas) se reprodujo un fallo real contra wp-env real: la tabla existía momentáneamente tras `Activador::activar()` pero desaparecía antes del siguiente test. Causa raíz verificada: `WP_UnitTestCase::set_up()` instala un filtro (`_create_temporary_tables`) que reescribe todo `CREATE TABLE` ejecutado DESPUÉS de ese punto como `CREATE TEMPORARY TABLE` — y las tablas temporales, a diferencia de las reales, SÍ participan del `ROLLBACK` transaccional entre tests. Una `ALTER TABLE` sobre una tabla ya existente no se ve afectada (por eso las columnas nuevas en `pluma_piezas` sí persistían). Mismo patrón que usa el propio test de `dbDelta` del núcleo de WordPress (`tests/phpunit/tests/dbdelta.php`): crear la tabla en `set_up_before_class()` (antes de que el filtro exista), no dentro de un método de test normal. **Regla para toda tabla nueva futura** (p. ej. `pluma_cola_publicacion` en H3): su test de Integración dedicado debe activar el esquema en `set_up_before_class()`, no reutilizar la activación incidental de otro test.

## NCP-2 · Semántico — inventario de dominio nuevo (2026-07-31, protocolo §2)

Dominio nuevo: ejecución de modelos ONNX (encoders pequeños int8) desde PHP en hosting WordPress real, con los cuatro transportes de `docs/CEREBRO_PLUMA_v2.md` Parte 3.1. Auditoría contra `~/.claude/skills/` (skills globales del ecosistema):

| Skill | Veredicto | Motivo |
|---|---|---|
| `ai-ml` | NO APLICA | Bundle orientado a apps LLM/RAG/agentes — no cubre servir un runtime de inferencia ONNX embebido en PHP ni selección de modelo por licencia comercial |
| `embedding-strategies` | ADAPTAR (parcial) | Cubre selección/optimización de modelos de embeddings para búsqueda vectorial — relevante como referencia general para el rol ENC, pero no aborda cuantización int8 para CPU restringida ni transportes T1-T4 ni licencia comercial, que son los ejes reales de la decisión aquí |
| `hugging-face-model-trainer` | NO APLICA | Entrenamiento/fine-tuning de LLMs en infraestructura cloud de Hugging Face — PLUMA consume modelos ya entrenados, nunca entrena ni ajusta ninguno |
| `ml-engineer` | NO APLICA | PyTorch/TensorFlow, serving y monitorización de sistemas ML propios — no es el problema (PLUMA no entrena ni sirve modelos propios, ejecuta artefactos ONNX de terceros) |
| `ml-pipeline-workflow` / `mlops-engineer` | NO APLICA | MLOps end-to-end (MLflow/Kubeflow, registries, experiment tracking) — sin relación con el problema real: cargar un artefacto ONNX firmado y ejecutarlo en un tick de cron con presupuesto de tiempo |
| `azure-ai-ml-py` | NO APLICA | SDK propietario de Azure ML — PLUMA no depende de ningún cloud de ML gestionado (§4.2 del canon: sin llamadas a casa sin permiso, sin binarios de terceros no auditables) |

**Hueco confirmado, sin skill que lo cubra**: nada en el inventario local ni del ecosistema resuelve (a) qué vías de ejecución de ONNX Runtime existen desde PHP hoy y qué exige cada una del entorno real (T1 FFI / T2 sidecar / T3 remoto / T4 WASM), (b) candidatos concretos de modelo por rol (ENC/NLI/RRK/NER/CLS/SEG/LID/TOX) con cobertura multilingüe, tamaño int8 y **licencia comercial verificada** (criterio eliminatorio antes que la calidad, Parte 5.2.4 del canon), (c) segmentación/tokenización para escrituras sin espacios y RTL, (d) reglas vigentes de las tiendas de distribución sobre binarios/modelos/servicios externos. Todo esto exige investigación directa en fuentes oficiales con fecha de consulta (`lg-research-depth` nivel Fuente, Parte 5.2.3 del canon: "investiga antes de nombrar") — no hay atajo de skill, tal como el propio documento anticipa en su Parte 7, punto 3.
