# PLAN-MAESTRO.md — PLUMA Engine
# De la arquitectura a la venta · Etapas ejecutables

Fuente de producto: `docs/PLUMA_Engine_Libro_de_Arquitectura.md`. Cada Etapa abre con `/nueva-etapa` (descubrimiento de skills + inventario + Risk Radar) y cierra con `/qa` + demo funcional al propietario. Ninguna Etapa se abre con la anterior en rojo.

| Etapa | Entrega | Criterio de salida (verificable, no opinable) |
|---|---|---|
| 0 · Cimientos | Repo, gobernanza activa, esqueleto de plugin instalable, CI con los gates, `wp-env` | El ZIP vacío instala/activa/desinstala limpio; `/qa` en verde en CI |
| 1 · El esqueleto que camina | Kernel+DI, tablas y máquina de estados de Pieza, motor cron con candado y bitácora, un Sensor (Trends), Piloto | Una tendencia real termina en borrador trazable de punta a punta; doble ejecución y muerte a mitad de lote probadas |
| 2 · El periodista | Banco con diales, decisión editorial, redacción dos pasadas + Corrector, memoria, bloque del editor | Dos periodistas distinguibles a ciegas; invariantes GOVERNANCE §2 en verde; export/import del banco |
| 3 · La capa competitiva | Motor SEO (+ convivencia Yoast/Rank Math), Taxónomo, tres compuertas, Copiloto y Autónomo con degradación, sala de revisión + notificaciones | Una semana en Copiloto sin corrección posterior; suite de invariantes y matriz de compatibilidad SEO en verde |
| 4 · La experiencia premium | Panel completo (cap. 10), onboarding 5 actos, estudio de conducta con vista previa, presupuestos de coste | Usuario nuevo: instalación→primer borrador < 20 min sin documentación (test moderado real) |
| 5 · La máquina que aprende | Bucle Search Console, memoria de audiencia, piezas de refuerzo/"dos golpes", respuestas asistidas, informes | El sistema propone ≥3 decisiones/semana basadas en datos reales del sitio |
| 6 · Producto en venta | Licenciamiento + updates firmadas, empaquetado reproducible, telemetría opt-in, docs de venta, beta cerrada | GOVERNANCE §5 íntegro¹; 3 instalaciones beta externas estables 2 semanas; 0 incidencias de seguridad |

**Deuda**: todo lo que se posponga vive en `docs/deuda.md` con etapa de pago asignada. La deuda sin etapa de pago no existe: es omisión.

¹ **Nota (2026-07-24)**: decisión explícita del propietario al abrir la Etapa 6 — GOVERNANCE §5.4 (licenciamiento + servidor de actualizaciones propio con firmas) requiere infraestructura de servidor fuera de este repo y se pospone al final del desarrollo como deuda aceptada (`PLUMA-E6-1` en `docs/deuda.md`). La Etapa 6 se cierra con §5.1, 5.2, 5.3, 5.5, 5.6, 5.7 íntegros; §5.4 queda fuera y no bloquea el cierre. La beta cerrada externa se ejecuta sin verificación de licencia.
