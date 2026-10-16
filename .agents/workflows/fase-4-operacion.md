---
description: Fase 4 — Tickets con detalle y SLA, Asignaciones kanban, Incidencias, Problemas, Solicitudes y SLA
---

Agentes: `backend`, `frontend-ui`, `db-laravel` si se requiere. Skills: `servicecloud-ui`, `servicecloud-schema`.

1. `SlaService` (ver agente backend) + pantalla SLA: 4 tarjetas por prioridad con tiempos de la política vigente y % de cumplimiento real de los últimos 30 días; gráfico de barras; CRUD de políticas y reglas en modal.
2. `TicketService` + módulo Tickets: listado con filtros por prioridad y estado y columna SLA restante (rojo si crítico o vencido); crear (solicitante, tipo, categoría de `categorias_tickets`, servicio, activo, impacto, urgencia → prioridad por matriz); detalle idéntico a `renderTicketDetail` (descripción real, 6 meta-boxes, cambiar estado, agregar nota interna/pública, adjuntos, actividad desde `tickets_historial` + comentarios + asignaciones ordenados por fecha). El panel "AI Classification" muestra datos de `clasificaciones_ia`/`recomendaciones_ia` si existen o un estado "Pendiente de análisis" (la IA se conecta en la fase 5).
3. Asignaciones: kanban de 5 columnas con drag & drop (`PATCH /asignaciones/{ticket}` → `TicketService`), avatar con iniciales del técnico, "Autoasignar con IA" (menor carga). Recarga solo la columna afectada o la página con toast.
4. Incidencias (`incidencias`: vínculo opcional a ticket y problema, matriz impacto/urgencia, estados), Problemas (`problemas` + tickets relacionados en `problemas_tickets`, causa raíz, workaround) y Solicitudes (`solicitudes`: aprobar/rechazar por SUPERVISOR o ADMIN_ORGANIZACION, completar). Usa `/crear-modulo` como base y añade las acciones específicas.
5. Notificaciones generadas: ticket asignado, cambio de estado al solicitante, SLA a menos de 30 min (comando programado `sla:revisar` cada 5 min que también marca vencidos). Para que el programador corra, agrega a `compose.yaml` un servicio `scheduler` con la misma imagen que `app`, el mismo volumen, `command: ["php", "/var/www/html/artisan", "schedule:work"]` y `stop_signal: SIGTERM`, sin puertos. Levántalo con `docker compose up -d scheduler`.
6. Tests: crear ticket calcula vencimientos; `en_espera` pausa SLA; mover en kanban registra historial y asignación; técnico solo ve sus tickets; solicitante no ve notas internas.
7. `/verificar`. Commits por submódulo.
