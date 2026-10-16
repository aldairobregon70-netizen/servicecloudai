---
name: servicecloud-schema
description: Resumen del esquema PostgreSQL de ServiceCloud AI (45 tablas) con valores permitidos, timestamps y alcance multi-tenant. Úsala al crear modelos, enums, validaciones, seeders o consultas.
---

# ServiceCloud — esquema de base de datos

Fuente completa: `database/sql/servicecloud_ai_schema.sql`. Esta skill es un resumen para no leer las 530 líneas cada vez. Si hay diferencia, manda el SQL.

## Tablas

Columnas: **Tenant** = tiene `organizacion_id` (aplicar trait `PerteneceAOrganizacion`). **Timestamps** = columnas que mapean a `CREATED_AT`/`UPDATED_AT`. **Valores** = CHECK del SQL → Enum PHP (los nombres de caso en PascalCase, el valor exacto del SQL).

| Tabla | Tenant | Timestamps | Valores permitidos |
|---|---|---|---|
| `planes_saas` | — | creado_en | — |
| `organizaciones` | — | creado_en, actualizado_en | estado: evaluacion,activa,suspendida,inactiva |
| `roles` | — | — | — |
| `permisos` | — | — | — |
| `roles_permisos` | — | — | — |
| `usuarios` | sí | creado_en, actualizado_en | estado: activo,inactivo,bloqueado |
| `usuarios_roles` | — | — | — |
| `politicas_sla` | sí | creado_en | — |
| `reglas_sla` | — | — | prioridad: baja,media,alta,critica; horario_servicio: 24x7,laboral |
| `contratos` | sí | creado_en | estado: borrador,vigente,por_vencer,vencido,cancelado |
| `servicios` | sí | creado_en | estado: operativo,degradado,interrumpido,mantenimiento,retirado |
| `activos` | sí | creado_en | tipo: servidor,laptop,switch,firewall,vm_cloud,desktop,impresora,otro; estado: operativo,asignado,degradado,mantenimiento,fuera_servicio,retirado |
| `equipos_soporte` | sí | — | — |
| `equipos_miembros` | — | — | — |
| `tickets` | sí | creado_en, actualizado_en | tipo: incidente,solicitud,consulta,cambio; prioridad: baja,media,alta,critica; impacto: bajo,medio,alto; urgencia: baja,media,alta; estado: abierto,asignado,en_progreso,en_espera,resuelto,cerrado,cancelado; origen: portal,email,telefono,api,monitorizacion |
| `tickets_asignaciones` | — | — | — |
| `tickets_historial` | — | — | — |
| `tickets_comentarios` | — | creado_en | — |
| `tickets_adjuntos` | — | creado_en | — |
| `tickets_sla` | — | — | — |
| `problemas` | sí | creado_en | estado: abierto,en_analisis,con_workaround,resuelto,cerrado |
| `incidencias` | sí | — | impacto: bajo,medio,alto; urgencia: baja,media,alta; prioridad: baja,media,alta,critica; estado: abierta,investigando,en_progreso,monitoreando,resuelta,cerrada |
| `problemas_tickets` | — | — | — |
| `solicitudes` | sí | creado_en | prioridad: baja,media,alta,critica; estado: pendiente_aprobacion,aprobada,rechazada,en_progreso,completada,cancelada |
| `articulos_conocimiento` | sí | creado_en, actualizado_en | visibilidad: publica,organizacion,interna; estado: borrador,publicado,archivado |
| `etiquetas` | — | — | — |
| `articulos_etiquetas` | — | — | — |
| `documentos_rag` | sí | creado_en | estado: pendiente,indexado,error |
| `fragmentos_rag` | — | — | — |
| `modelos_ia` | — | — | tipo: nlp,clasificacion,generativo,embedding,logs |
| `clasificaciones_ia` | — | creado_en | prioridad_sugerida: baja,media,alta,critica |
| `recomendaciones_ia` | — | creado_en | — |
| `recomendaciones_fuentes` | — | — | — |
| `conversaciones_ia` | sí | creado_en | — |
| `mensajes_ia` | — | creado_en | rol: system,user,assistant,tool |
| `logs_tecnicos` | sí | — | nivel: debug,info,warning,error,critical |
| `analisis_logs_ia` | sí | creado_en | severidad: info,warning,error,critical |
| `analisis_logs_detalle` | — | — | — |
| `notificaciones` | sí | creado_en | tipo: ticket,sla,ia,sistema,asignacion; canal: in_app,email,push |
| `preferencias_notificaciones` | — | — | — |
| `configuracion_organizacion` | sí | — | — |
| `auditoria` | sí | — | — |
| `categorias_tickets` | sí | — | — |
| `servicios_activos` | sí | — | — |
| `usuarios_roles_organizacion` | sí | — | — |
## Casos especiales

- `organizaciones` es el tenant: no lleva el trait; ADMIN_ORGANIZACION solo ve la suya.
- `usuarios.organizacion_id` es NULL para ADMIN_SAAS. `politicas_sla.organizacion_id` NULL = plantilla global; `articulos_conocimiento.organizacion_id` NULL = artículo público de plataforma; `notificaciones` y `auditoria` pueden tener NULL. Para estas tablas el scope debe permitir `organizacion_id = X OR organizacion_id IS NULL` cuando corresponda (lectura), nunca para escritura de usuarios no admin.
- `configuracion_organizacion`: `$primaryKey = 'organizacion_id'`, `$incrementing = false`, `$timestamps = false`.
- `preferencias_notificaciones`: `$primaryKey = 'usuario_id'`, `$incrementing = false`, `$timestamps = false`.
- Tablas con fechas propias y `DEFAULT CURRENT_TIMESTAMP` (`fecha`, `inicio_en`, `recibido_en`, `calculado_en`): `$timestamps = false`; la BD pone la fecha.
- Pivotes sin `id` (`roles_permisos`, `usuarios_roles`, `equipos_miembros`, `problemas_tickets`, `articulos_etiquetas`, `analisis_logs_detalle`, `servicios_activos`, `usuarios_roles_organizacion`, `recomendaciones_fuentes`): usar `belongsToMany`; para `servicios_activos` y `usuarios_roles_organizacion` pasar `organizacion_id` en `attach()`.
- Triggers en `organizaciones`, `usuarios`, `tickets`, `articulos_conocimiento` actualizan `actualizado_en` solos; no hace daño que Eloquent también lo haga.
- Roles sembrados por el SQL: `ADMIN_SAAS` (global), `ADMIN_ORGANIZACION`, `SUPERVISOR`, `TECNICO`, `SOLICITANTE`. Planes sembrados: Starter, Business, Enterprise (precio 0 = marcador).
- FK compuestas `(organizacion_id, x_id)`: la relación debe ser de la misma organización; validarlo en FormRequest con `Rule::exists('tabla','id')->where('organizacion_id', $org)`.
- Columnas heredadas (no usar como fuente principal): `tickets.categoria`, `tickets.subcategoria` (usar `categoria_id` → `categorias_tickets`), `activos.servicio_id` (usar `servicios_activos`), `usuarios_roles` para roles no globales (usar `usuarios_roles_organizacion`).

## Prioridad por impacto × urgencia (para tickets e incidencias)

| Impacto \ Urgencia | baja | media | alta |
|---|---|---|---|
| bajo | baja | baja | media |
| medio | baja | media | alta |
| alto | media | alta | critica |

## Flujo de estados del ticket

`abierto → asignado → en_progreso ⇄ en_espera → resuelto → cerrado` (y `cancelado` desde cualquier estado previo a `resuelto`). `en_espera` pausa el SLA (`tickets_sla.minutos_pausados`). Al pasar a `resuelto` exige `solucion`. Columnas del kanban: Sin asignar (`abierto` sin técnico), Asignado, En progreso, En espera, Resuelto.
