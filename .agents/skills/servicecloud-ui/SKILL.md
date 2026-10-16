---
name: servicecloud-ui
description: Mapa del mockup docs/mockup/index.html de ServiceCloud AI y cómo convertir cada sección en vistas Blade. Úsala siempre que crees o edites una vista, el layout, el CSS o el JavaScript de la interfaz.
---

# ServiceCloud UI — mapa del mockup

El mockup es una SPA en un solo archivo (`docs/mockup/index.html`). Cada pantalla la dibuja una función JS. Busca la función, copia su HTML a Blade y reemplaza los datos de `state`/`defaults` por variables del controlador.

## Dónde está cada cosa (líneas aproximadas)

| Sección | Líneas | Destino Laravel |
|---|---|---|
| `<style>` completo | 10–497 | `public/css/servicecloud.css` (copia exacta) |
| Estructura HTML: sidebar, header, modal, toasts | 499–570 | `resources/views/layouts/app.blade.php` |
| `menu` (18 módulos, ícono Lucide, etiqueta) | 578 | `config/servicecloud.php` → `menu` (ruta, ícono, etiqueta, roles) |
| `defaults` (datos demo) | 599 | `database/seeders/*` |
| `moduleMeta` (campos de los CRUD genéricos) | 698 | Formularios de Organizaciones, Usuarios, Activos, Servicios, Contratos, Problemas, Solicitudes |
| `badge()` mapa de colores | 730 | `app/Enums/*::badge()` + `<x-badge>` |
| `pageHeader()` | 746 | `<x-page-header>` |
| `initChart()` opciones Chart.js | 754 | `public/js/servicecloud.js` → `scInitChart()` |
| `renderDashboard`, `kpi`, `chartCard`, `activity`, `initDashboardCharts` | 775–847 | `dashboard.blade.php` + `DashboardController` (5 KPIs, 4 gráficos, Insights IA, Actividad reciente) |
| `renderGenericModule`, `genericRow` | 829–870 | `components/tabla-crud` reutilizable (toolbar con búsqueda, filtro de estado, Exportar; acciones ver/editar/eliminar) |
| `renderTickets` | 871 | `tickets/index.blade.php` (columnas: Código, Título, Organización, Prioridad, Estado, Técnico, SLA, Fecha) |
| `renderTicketDetail`, `metaBox`, `timeline`, `rag` | 907–966 | `tickets/show.blade.php`: columna principal (meta-grid de 6 cajas, cambiar estado, agregar nota, actividad) + `aside.ai-panel` (AI Classification, Posibles causas, Knowledge Suggestion, botones) |
| `renderIncidents` | 968 | `incidencias/index` |
| `renderSLA` | 982 | `sla/index` (4 tarjetas por prioridad + gráfico de cumplimiento) |
| `renderAssignments`, `kanbanCard`, `setupDragDrop` | 1000–1032 | `asignaciones/index` (columnas: Sin asignar, Asignado, En progreso, En espera, Resuelto) |
| `renderKnowledge`, `articleCard` | 1033–1054 | `conocimiento/index` (chips de categoría, búsqueda, "Buscar con IA") |
| `renderAssistant`, `quick`, `sendChat` | 1055–1097 | `asistente/index` (chat + acciones rápidas + chips de estado RAG) |
| `aiResponse` | 1098 | Solo como ejemplo del tono de respuesta del driver `simulado` |
| `renderLogs`, `analyzeLogs` | 1107–1142 | `logs/index` (textarea monoespaciada con el ejemplo precargado, resultados con niveles y diagnóstico) |
| `renderNotifications` | 1143 | `notificaciones/index` |
| `renderReports` | 1153 | `reportes/index` (6 tarjetas, descarga CSV) |
| `renderSettings`, `settingRow` | 1168 | `configuracion/index` (switches que guardan en `configuracion_organizacion` y `preferencias_notificaciones`) |
| `openModal`, `openCrudModal`, `openTicketModal`, `formField` | 1203–1260 | `<x-modal>` + formularios Blade |
| `toast`, `exportCSV` | 1286–1297 | toasts desde `session('ok')`; CSV desde el servidor |
| `renderNotificationDropdown`, `renderProfileDropdown` | 1298–1313 | parciales del header |
| `handleGlobalSearch`, `toggleSidebar` | 1314–1330 | `public/js/servicecloud.js` |

## Equivalencias de datos (mockup → BD)

- Ticket `id` → `tickets.codigo`; `title` → `titulo`; `org` → `organizacion.razon_social`; `tech` → `tecnico.nombre` o "Sin asignar"; `sla` → tiempo restante calculado hasta `vencimiento_resolucion` (formato `01h 12m`, "Vencido" en rojo, "Cumplido" como badge verde); `date` → `creado_en`.
- Prioridad/estado → enums con etiqueta en español y el color del mapa `badge()`.
- Organización `users`/`services` → conteos (`withCount`). `plan` → `planes_saas.nombre`.
- Servicio `availability` → `disponibilidad_objetivo` con `%`; `sla` (Gold/Silver…) → `politicas_sla.nombre`.
- Activo `owner` → `responsable.nombre`. Contrato `value` → `S/ ` + `monto` formateado.
- KB `views` → `vistas`; `updated` → `actualizado_en->diffForHumans()` en español.
- Asignación `assignee` → iniciales del técnico (`--` si no tiene).
- Insights IA del dashboard → % de `clasificaciones_ia` aceptadas, cantidad de `recomendaciones_ia`, tickets con prioridad sugerida, `analisis_logs_ia` del período.

## Checklist por vista

- [ ] Mismo título y descripción de `pageHeader` que el mockup.
- [ ] Mismas clases CSS y estructura de contenedores.
- [ ] Íconos Lucide idénticos.
- [ ] Badges con el color correcto.
- [ ] Filtros funcionan (servidor o cliente) y conservan la query string.
- [ ] Estado vacío con `.empty` cuando no hay registros.
- [ ] Probada en 1440 px y 390 px de ancho.
