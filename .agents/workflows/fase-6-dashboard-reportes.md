---
description: Fase 6 — Dashboard con datos reales, Notificaciones, Reportes CSV, Configuración, búsqueda global y auditoría
---

1. Dashboard: 5 KPIs (tickets abiertos, incidencias críticas, SLA vencidos, % cumplimiento SLA, tiempo medio de resolución) con tendencia vs período anterior; 4 gráficos (por estado, por prioridad, creados vs resueltos 7 días, cumplimiento SLA); Insights IA y Actividad reciente desde la BD. Todo filtrado por el alcance del rol. Cachea 60 s por organización.
2. Notificaciones: centro idéntico a `renderNotifications`, dropdown de la campana con no leídas y punto rojo solo si hay no leídas, "Marcar todo como leído".
3. Reportes: las 6 tarjetas del mockup, cada una descarga su CSV real (mesa de ayuda, SLA, efectividad IA, disponibilidad de servicios, productividad técnica, incidencias críticas) con filtro de fechas.
4. Configuración: switches de IA (`configuracion_organizacion`) y de notificaciones (`preferencias_notificaciones`) que guardan de verdad; ADMIN_SAAS ve además planes SaaS.
5. Búsqueda global del header (`/buscar?q=`): tickets, usuarios y activos, máx. 8 resultados, respetando tenant.
6. Auditoría: observer activo y vista simple de auditoría para ADMIN_SAAS/ADMIN_ORGANIZACION dentro de Configuración.
7. Tests de cada punto. `/verificar`. Commit.
