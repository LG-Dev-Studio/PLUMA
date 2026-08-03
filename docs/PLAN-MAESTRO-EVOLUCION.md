# PLAN-MAESTRO-EVOLUCIÓN — PLUMA Engine

## De un pipeline que publica a una redacción sintética que razona, sobrevive y retiene

*Plan de implementación por fases de los tres complementos al Libro de Arquitectura (Nivel Dos, Nivel Tres, Nivel Cuatro). Extiende `PLAN-MAESTRO.md` (Etapas 0-6), no lo reemplaza. Redactado 2026-07-25.*

---

## 0. Propósito y cómo leer este documento

Los cuatro documentos de diseño (Libro v1.0 + Niveles Dos/Tres/Cuatro) suman ~45 piezas de mecanismo y territorio nuevo que el Libro original no contempla. Este plan las ordena en fases ejecutables **sin romper la disciplina de gobernanza** (`CLAUDE.md`, `GOVERNANCE.md`, `AGENTS.md`) ni el camino crítico hacia el primer producto vendible (Etapa 6, en curso).

- **`PLAN-MAESTRO.md`** sigue siendo la ley del roadmap 0-6. Este documento añade las Etapas 7-10 y una porción legal urgente dentro de la 6.
- **La numeración de piezas** (A.3, K.2, U.1, …) referencia el capítulo del nivel correspondiente. Nunca se parafrasea el contenido: se implementa lo que el documento fuente dice, con el veredicto que N4 Parte I ya fijó.
- Cada pieza lleva: **veredicto canónico** (de N4-I.1), **criterio propio** donde N4 lo invita, y **bandera de riesgo**.

## 1. Descubrimiento de skills aplicado (SKILLS-STACK §2)

Este re-planning es una decisión de arquitectura mayor (múltiples módulos, puerta de una dirección). Stack LG aplicado y por qué:

| Skill | Aplicación en este plan |
|---|---|
| `lg-first-principles` | Romper el supuesto heredado de los tres documentos: que su mapeo "Etapa 1/2/3" sigue vigente. No lo está — esas etapas están cerradas. El axioma real es "no hay ruta de código que publique bajo umbral"; todo lo demás (dónde vive cada pieza) es convención revisable → §3. |
| `lg-cto-mode` (lente sistema) | Encaje del retrofit en módulos ya enviados sin contaminación de capas: A/B/C/J/L/O tocan `Redaccion`/`Investigacion`/`Compuertas`/`Sensores` existentes; U/V/W/X tocan esquema y grafo → orden por coste de migración. |
| `lg-risk-radar` | Pre-mortem del propio plan: el fracaso más probable no es técnico, es **explosión de alcance que mata el foco** (el propio N4-III.3 lo advierte). Mitigación estructural en §4 (triaje por riesgo) y §6 (regla de sacrificio inversa). |
| `lg-decision-framework` | Las 4 decisiones de una dirección de §7 se registran como ADR en `docs/decisiones/` antes de implementar. |
| `lg-independence` | El requisito de doble familia de modelo (J) y el envío de telemetría/imagen tocan proveedores externos: todo detrás de interfaz propia, con plan de salida. |
| `lg-critical-review` + `lg-metacognition` | Cierre obligatorio: §8, autocrítica del propio plan (mismo ejercicio que cada nivel se hizo a sí mismo). |

Skills `pl-*` de dominio: se consultan **al abrir cada porción**, no aquí (pl-periodistas para A/E/P.3/Q, pl-compuertas para K/M/N/O, pl-pipeline para B/C/F/U, pl-proveedor-ia para J, pl-wp-core para U/V/W/X esquema, pl-testing para los corpus H/S).

## 2. El canon reconciliado — N4 Parte I es la ley

N4 ya emitió veredicto sobre cada pieza de N2 y N3 (tabla I.1). **Ese veredicto es autoritativo**; este plan no re-adjudica. Deltas de criterio propio, solo donde N4 explícitamente los invita o donde una fecha externa los fuerza:

- **Sin imagen → tarjeta editorial ANTES de RETENIDA** (N4 corrige D.3 de N2): imagen tipográfica de marca determinista, sin proveedor externo. Retener queda como último recurso.
- **A.5 (corpus de voz por embeddings) entra en firme, no como deuda diferible** (N4 revisa un dictamen previo): comparte infraestructura de embeddings con J.3, coste marginal trivial.
- **Umbrales numéricos = defaults de fábrica configurables, revisión obligatoria a los 90 días de datos reales** (N4 sobre C, N2-I.2, N3-T.2): ningún número de estos documentos es constante de sistema; todos son hipótesis a calibrar en Piloto.
- **Art. 50 (N.3): la línea legal no pasa entre Copiloto y Autónomo, sino entre "aprobación humana sustantiva registrada" y todo lo demás** (N4 verificación 1, corrige N3). Marcado técnico obligatorio en Autónomo Y en toda pieza de Copiloto publicada por expiración de ventana.

## 3. La reconciliación con la realidad — el conflicto que hay que declarar

**Los documentos asumen construcción hacia adelante. La realidad: Etapas 0-5 cerradas y en producción, Etapa 6 en curso.** Consecuencia:

- **N2 + N3** son mayormente **retrofit** a módulos ya enviados (`Radar`, `Investigador`, `Redacción`, `Compuertas`, `Corrector`). No se puede "construir hacia adelante" un Corrector que ya existe: se endurece en su sitio.
- **N4 Parte II** (Historia, Agenda, Distribución, Comunidad, Negocio, Confianza) es **territorio genuinamente nuevo** → Etapas hacia adelante.

Esto obliga a **reabrir criterio de módulos cerrados** para endurecerlos. Es un cambio de postura respecto a la regla "ninguna Etapa se abre con la anterior en rojo" — aquí abrimos Etapas nuevas que *modifican* Etapas verdes. **Decisión de propietario requerida** (§7, decisión 1). El argumento a favor de hacerlo igual: dejar viva en producción la Compuerta de Calidad de K.2 (que hoy puede aprobar una pieza bien escrita y mal sustentada) es exactamente la complacencia que `CLAUDE.md` §Santo Grial prohíbe.

## 4. Triaje por riesgo (criterio propio — lg-risk-radar)

No todas las piezas pesan igual. Ordenadas por (exposición legal/seguridad × qué tan viva está la brecha en producción):

**TIER 0 — Urgente (brecha de seguridad/legal en código ya enviado, algunas con fecha externa dura):**
`K.2` (compuerta de calidad promedia anti-alucinación) · `K.1` (criterio se vende por viralidad) · `K.3` (regla que previene la 5.ª recurrencia) · `N.3` (marcado Art. 50 UE, **en vigor 2-ago-2026**) · `N.1` (perfil de jurisdicción para difamación) · `M.1` (derecho de réplica previa, "la ausencia más grave").

**TIER 1 — Alto valor de mecanismo (hacer que el pipeline enviado razone de verdad):**
`A.2-A.6` · `B.1-B.4`+`O.2` · `C.1-C.3` · `J.1-J.3` · `L.1-L.2` · `O.1` · `M.2` · `E.1-E.2` · `F.1-F.3` · `G.1`/`G.2` · `P.3` · `Q` · `D.1-D.3`+`N.2` (imagen — hoy inexistente, deuda `PLUMA-E3-2`).

**TIER 2 — Homeostasis/escala (necesitan meses de datos reales; naturalmente tarde):**
`P.1` (huella estructural) · `P.2` (fatiga de alerta) · `M.3` (réplica nivel 3) · `R` (valor marginal por pieza).

**TIER 3 — Territorio nuevo (N4 Parte II):**
`U` Historia · `V` Agenda · `W` Distribución · `X` Comunidad · `Y` Negocio · `Z` Confianza · `AA` Operaciones.

## 5. Cambios de gobernanza transversales (hacer primero — baratos y previenen recurrencia)

Estos no son features; son normas que deben existir **antes** de escribir las piezas que gobiernan. Modifican `GOVERNANCE.md`, que es ley de ingeniería → **decisión de propietario** (§7, decisión 4).

| Cambio | Origen | Qué es |
|---|---|---|
| `GOVERNANCE §1.6` — Regla de Puntuaciones Compuestas | K.3 | Toda puntuación compuesta declara, por factor: ¿piso o contribuyente? ¿umbral y qué pasa debajo? ¿piso de fábrica no editable? Test de arquitectura que verifica la tabla de 3 columnas antes de aceptar cualquier función de puntuación nueva. |
| `docs/puntuaciones.md` — registro vivo | K.3 | Todas las puntuaciones del sistema (Radar, asignación, ángulo, calidad, y las futuras) con su tabla de 3 columnas. |
| `GOVERNANCE §2.8` — invariante de independencia | J.4 | Autónomo exige verificador de familia de modelo distinta para el punto 1 + capa determinista de trazabilidad. Test: activar Autónomo con `verificador_provider == redactor_provider` debe fallar explícito. |
| `GOVERNANCE §1.5` — prohibición de cloaking de IA | N4 verif. 2 | Prohibido mantener superficie paralela para crawlers de IA (Google/Bing lo tratan como cloaking desde feb-2026). |
| `docs/decisiones/` — ADR ligeros | lg-decision-framework | Las 4 decisiones de §7 registradas antes de implementar. |

## 6. El plan por Etapas

Regla de sacrificio (N4-III.3, criterio propio): **si hay que recortar, se recorta desde el final** (Etapa 10 antes que una sola pieza de la 7). El camino crítico al primer producto vendible (Etapa 6) manda.

### Etapa 6 — Producto en venta (EN CURSO) · se le añade una porción legal bloqueante

| Porción | Piezas | Nota |
|---|---|---|
| 1-3 (hechas) | Versionado/empaquetado/matriz · Telemetría+diagnóstico · Docs de venta | Ya cerradas y en verde. |
| **4 (nueva)** | **`N.3`** sección configurable del panel (perfil de jurisdicción + formato del bloque de transparencia) SOBRE un piso de fábrica inamovible (marcado legible por máquina en Autónomo y Copiloto-por-expiración + identidad sintética en página de autor). Auditoría captura el tipo de aprobación (activa vs. por expiración) como dato de primera clase | **Fecha dura: 2-ago-2026.** El piso (capacidad) debe existir antes de que una instalación beta publique en Autónomo bajo jurisdicción UE; la configuración es del cliente. |
| 5 (cierre) | Coordinación de beta cerrada externa (proceso del propietario) | Sin código. |

### Etapa 7 — Endurecimiento del criterio (retrofit crítico, TIER 0)

*El sistema inmunológico enviado tiene agujeros; esto los cierra. Toca `Compuertas`, `Redacción`, `GOVERNANCE`.*

| Pieza | Veredicto N4 | Qué se hace | Riesgo |
|---|---|---|---|
| Gobernanza §5 completa | — | `§1.6`+`docs/puntuaciones.md`, `§2.8`, `§1.5` cloaking | Bajo; base de todo lo demás |
| `K.2` | ACEPTAR íntegro | Piso de sustento + piso de estructura NO compensables en Compuerta de Calidad; dejan de ser 1/5 de un promedio | **El hallazgo más grave**: hoy publica bien-escrito-mal-sustentado |
| `K.1` | ACEPTAR | Piso de sustento binario en selección de ángulo (Paso 3); sin candidato con piso → vuelve a Investigador | Alto: el paso que decide qué defiende la pieza |
| `M.1` | ACEPTAR | Campo `postura_del_senalado`; motivo de retención independiente en Compuerta de Riesgo | Legal/reputacional |
| `N.1` | ACEPTAR | Perfil de jurisdicción de fábrica controla el umbral de difamación; régimen penal → doble fuente + postura + retención humana obligatoria | Legal, varía por mercado |
| `J.1-J.2` | ACEPTAR (hipótesis Piloto) | Metadata de familia de modelo en `LenguajeInterface`; contrato de independencia. **La obligatoriedad dura en Autónomo se valida en Piloto antes de activarse** (N3-T.2) | Coste/latencia; validar empíricamente |

### Etapa 8 — El razonamiento completo (retrofit de mecanismo, TIER 1)

*Hace que el pipeline enviado efectivamente razone. La etapa más grande; se entrega por porciones verticales por módulo.*

| Bloque | Piezas | Módulo | Nota de criterio propio |
|---|---|---|---|
| Cerebro editorial | `A.2-A.6` (prompts como DTO versionado, función dial→directriz con anclas, matriz de combinación de diales, corpus de voz A.5, prioridad de corrección A.6) | `Redacción` | A.5 comparte infra de embeddings con J.3; se construye una vez. Matriz de diales (A.4) se extiende bajo demanda al crear periodistas, no 28 pares a priori. |
| Verificación determinista | `J.3` (capa de embeddings unidad-a-unidad contra expediente, `SIN_RESPALDO_APARENTE`) | `Redacción`/`Proveedores` | Rompe la correlación de fallo que J.1-J.2 solo reduce. |
| Investigador máquina | `B.1-B.4` (resolución de disputas, decaimiento temporal, cadena de citación, hueco estructurado) + `O.2` (relevancia causal) + `L.1-L.2` (procedencia del declarante, autenticidad audiovisual) | `Investigación` | B.3 (cadena de citación) es "de lo mejor de los tres documentos" (N4). L registra el dato; la compuerta ya existente lo usa sin regla nueva. |
| Aritmética del Radar y asignación | `C.1` (afinidad como puerta) + `C.2-C.3` (desempate + piso de dominio, estado `SIN_PERIODISTA_IDONEO`) | `Sensores`/`Redacción` | C.1 paga parte de deuda `PLUMA-E1-1`. Pesos = defaults, revisión 90 días. |
| Falseabilidad | `O.1` (Fase 3.5, propósito `falsear`) | `Redacción` | Salvaguarda anti-falso-equilibrio como hito de calibración en Piloto (N4). |
| Réplica dirigida | `M.2` (búsqueda dirigida de postura) | `Investigación`/`Compuertas` | Mismo patrón de presupuesto acotado que B.2. |
| Grafo y memoria | `E.1` (estados `SIN_PERIODISTA_IDONEO`, motivo `sin_activo_visual`) + `E.2` (memoria colectiva del sitio) | `Pipeline`/`Datos` | E.2 toca esquema. |
| Modo respeto | `F.1-F.3` (máquina de estados propia, disparador de dos niveles) | `Compuertas`/`Orquestador` | Paga parte de deuda `PLUMA-E3-6`. Umbral configurable, revisión post-evento. |
| Imagen destacada | `D.1-D.3` (compuerta de originalidad visual, tarjeta editorial, fallback) + `N.2` (derechos de personalidad) + `G.2` (`SatiricalArticle` + señal visible) | `Proveedores`/`Seo`/`Publicación` | Paga deuda `PLUMA-E3-2` (imagen no existe hoy). Nuevo `ProveedorImagenInterface`. |
| Legitimidad del insumo | `G.1` (naturalidad de señal del Radar) | `Sensores` | Heurística mínima + deuda con hito de profundización (N2-I.2). Se conecta con el buzón de pistas X.3 como señal humana de contraste. |
| Disciplina de activo | `P.3` (auditoría de periodista + deriva de modelo, cadencia mensual) + `Q` (`locale_editorial`) | `Redacción` | Q como campo desde ya evita migrar el banco después. P.3 es proceso + calendario, no infra. |

### Etapa 9 — El medio real (N4 Parte II, territorio nuevo, TIER 3)

*Internamente ordenada por el propio N4-III.1: Historia primero (toca esquema), luego lo que llega con el primer tráfico real.*

| Bloque | Piezas | Nota |
|---|---|---|
| Historia como entidad | `U.1-U.2`, `U.4` (entidad `Historia`, hub público, actualización de primera clase) | Toca esquema y grafo — se hace temprano para no migrar dos veces (N4). |
| Comunidad mínima viable | `X.1` (compuertas de comentarios) + `Y.1` (muralla redacción/publicidad como invariante) | Llegan con el primer tráfico/cliente; no posponibles. RGPD/LOPD entra aquí como deuda declarada con revisión legal humana. |
| Iniciativa editorial | `V.1-V.2` (Calendario Editorial, paquete de cobertura) | De aprender del pasado a preparar el futuro. |
| Canal propio | `W.1-W.3` (boletín por periodista, derivados por canal, suscripciones) | El canal que nadie le quita al medio (lg-independence sobre el tráfico). |
| Confianza y negocio | `X.2-X.4` (respuesta del periodista, buzón de pistas, corrección con crédito) + `Y.2-Y.3` (A/B de titular, asignación de capacidad) + `Z` completo (metodología, correcciones públicas, expediente por pieza) | Producto en venta completo = confianza pública + negocio. |

> **Nota — iniciativa paralela NCP**: en paralelo a estas Etapas corre el **Núcleo Cognitivo Propio** (`docs/CEREBRO_PLUMA_v2.md`, estado y continuidad en `docs/ncp-estado-y-continuidad.md`), una línea de trabajo distinta que reduce la dependencia de APIs de pago de IA generativa. No sustituye ninguna Etapa de este roadmap ni la reordena; se documenta aquí solo para que quien navegue el plan maestro sepa que existe.

### Etapa 10 — Homeostasis a escala y el medio 2.0 (TIER 2 + resto de N4)

*Necesitan datos reales de meses; genuinamente lo último.*

| Pieza | Nota |
|---|---|
| `P.1` (huella estructural), `P.2` (fatiga de alerta) | Requieren corpus histórico real. P.2 (pastor mentiroso) es "la pieza más original de N3". |
| `M.3` (réplica nivel 3, contacto directo con ventana) | Tensión con velocidad se resuelve por política declarada, no algoritmo. |
| `R` (función de valor marginal por pieza) | Señal direccional al editor, jamás corte automático (P4). |
| `U.3` liveblog, `V.3` evergreen, `W.4` audio, `AA` operaciones | El medio 2.0; cada pieza tras validar demanda con métricas reales. |

### Casos de aceptación (no son código)

`H.1-H.3` (N2) y `S.1-S.2` (N3) se incorporan **literalmente como fixtures de integración** de las Etapas 7-8 correspondientes, con el mismo estatus que el corpus adversarial de GOVERNANCE §3.4.

## 7. Decisiones del propietario (RESUELTAS 2026-07-25 — se registran como ADR en `docs/decisiones/`)

1. **Reabrir criterio de Etapas cerradas para retrofit.** → **RESUELTO: SÍ, reabrir para endurecer.** Se acepta que Etapas nuevas (7-8) modifiquen módulos ya enviados y verdes. Dejar vivos K.2/M.1/N.1 en producción sería la complacencia que `CLAUDE.md` prohíbe.
2. **La porción legal N.3 (Art. 50 UE).** → **RESUELTO: configurable dentro del plugin, SOBRE un piso de fábrica inamovible.** Lo configura el cliente: perfil de jurisdicción (N.1) y formato/texto del bloque de transparencia (13.1: la opción controla el formato, no la existencia). Piso de fábrica NO desactivable (N3-N.3 + N4-verif.1 + la propia ley): la existencia del marcado legible por máquina en piezas Autónomo y Copiloto-por-expiración, y la declaración de identidad sintética en la página de autor. La *capacidad* (el piso) debe existir antes de que cualquier instalación beta publique en Autónomo bajo jurisdicción UE; la *configuración* es del cliente. Se construye como sección del panel en la Etapa 6, porción 4.
3. **El requisito de doble familia de modelo (J).** Recomendación (sin objeción del propietario): construir el contrato (metadata de familia) en Etapa 7, pero **gatear la obligatoriedad dura del Autónomo tras validación en Piloto** (N3-T.2). Se registra como ADR con hito de validación.
4. **Los tres cambios de `GOVERNANCE.md`** (§1.6, §2.8, §1.5) + orden de arranque. → **RESUELTO: cambios de gobernanza primero.** Es la primera porción del trabajo de evolución: baratos, transversales, y previenen que la 5.ª puntuación compuesta repita el error de K.3.

## 8. Autocrítica del plan (lg-critical-review + lg-metacognition)

*El mismo ejercicio que cada nivel se hizo a sí mismo, aplicado a este plan.*

- **Riesgo principal, heredado de N4-III.3: este plan puede matar el foco.** Son 4 etapas nuevas sobre un producto que aún no cerró la 6. La mitigación es estructural (regla de sacrificio inversa, §6) pero depende de que el propietario la respete cuando la presión de "terminar la 6 y vender" choque con "endurecer K.2 primero". La tentación de saltarse la Etapa 7 para llegar antes a la beta es exactamente el riesgo que el plan existe para nombrar.
- **El triaje por riesgo (§4) es mío, no de los documentos.** Podría estar equivocado en el orden: p. ej., ¿es `M.1` (réplica previa) más urgente que `A.5` (corpus de voz)? Puse safety/legal antes que mecanismo de calidad; un argumento defendible pondría A.5 antes porque sin él el criterio de salida de la Etapa 2 nunca fue realmente verificable. Es un juicio, no un teorema — sujeto a tu corrección.
- **No he re-verificado los límites de rendimiento (Cap. 12 del Libro) contra el nuevo total de entidades.** N4-III.3 ya predijo que este sería el hueco de un "Nivel Cinco": Historia + EventoProgramado + suscriptor + pista + comentario clasificado multiplican el modelo de datos del Cap. 11 sin que nadie haya re-medido rendimiento. Este plan hereda ese hueco sin cerrarlo — queda como deuda declarada.
- **Las fechas externas dependen de la línea de tiempo real del proyecto.** Art. 50 (2-ago-2026) es urgente *si* "hoy" es ~julio 2026. Verificar contra el calendario real antes de tratar la porción 4 de la Etapa 6 como bloqueante.
- **Este plan tampoco fue auditado por una pasada posterior** (misma honestidad que N3-T.2). Su hueco más probable: haber subestimado el coste de los retrofit sobre código en producción con clientes beta encima — cambiar la Compuerta de Calidad (K.2) bajo tráfico real es más delicado que construirla en verde.

## 9. Deuda nueva a registrar en `docs/deuda.md` al aprobar este plan

- Rendimiento del modelo de datos ampliado (Cap. 12 vs. nuevas entidades de Etapa 9) — sin re-medir.
- RGPD/LOPD de datos de lectores (buzón de pistas X.3, encuestas X.2, suscripciones W) — requiere revisión legal humana, no solo ingeniería.
- Calibración empírica en Piloto de todos los umbrales de fábrica (J, O.1, F.2, M.3) — hito de validación explícito, no constante silenciosa.
- Detector real de manipulación de tendencias (G.1) y de autenticidad audiovisual (L.2) — problemas de investigación activa; heurística mínima + hito de profundización.

---

*Este plan es un artefacto de planificación, no una autorización de implementación. Cada Etapa se abre con su propio `/nueva-etapa` (descubrimiento de skills + Mission Lock + Risk Radar) y se entrega por porciones bajo el Delivery Guardian, como toda Etapa anterior.*
