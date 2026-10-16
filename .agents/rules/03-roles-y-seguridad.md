---
trigger: always_on
---

# Regla: roles, permisos y seguridad

## Matriz de acceso (implementar con Policies + Gates)

| Módulo | ADMIN_SAAS | ADMIN_ORGANIZACION | SUPERVISOR | TECNICO | SOLICITANTE |
|---|---|---|---|---|---|
| Dashboard | global | su org | su org | sus tickets | sus tickets |
| Organizaciones | CRUD | ver la suya | — | — | — |
| Usuarios | CRUD todas | CRUD su org | ver | — | — |
| Activos / Servicios / Contratos | CRUD | CRUD | ver | ver | — |
| Tickets | todo | todo su org | todo su org | asignados + crear | crear + ver los suyos + comentar |
| Incidencias / Problemas | todo | todo | todo | ver + actualizar asignadas | — |
| Solicitudes | todo | aprobar | aprobar | atender | crear + ver las suyas |
| SLA (políticas y reglas) | CRUD | CRUD su org | ver | — | — |
| Asignaciones (kanban) | todo | todo | mover y asignar | mover los suyos | — |
| Base de conocimiento | CRUD | CRUD | CRUD | crear borrador + ver | ver publicados (públicos/su org) |
| Asistente IA / Análisis de logs | sí | sí | sí | sí | asistente sí, logs no |
| Notificaciones | propias | propias | propias | propias | propias |
| Reportes | global | su org | su org | — | — |
| Configuración | plataforma | su org | — | preferencias propias | preferencias propias |

Ocultar en el menú lo que el rol no puede ver y devolver 403 si accede por URL.

## Seguridad obligatoria

- Contraseñas: `Hash::make`, mínimo 8 caracteres. Bloquear login si `usuarios.estado` ≠ `activo`. Registrar `ultimo_acceso`.
- Rate limiting en login (5 intentos/minuto) y en endpoints de IA (20/minuto por usuario).
- Comentarios con `es_interno = true` nunca se muestran al SOLICITANTE.
- Adjuntos: guardar en `storage/app/private/tickets/{id}`, validar mime y tamaño (máx. 10 MB), descargar solo vía controlador autorizado.
- Auditoría: observer que registra en `auditoria` (entidad, entidad_id, acción, datos_antes, datos_despues, ip_origen) para crear/editar/eliminar en organizaciones, usuarios, contratos, SLA, tickets.
- Eliminar: preferir cambio de estado (`inactivo`, `retirado`, `cancelado`) cuando la FK es RESTRICT; mostrar mensaje claro si no se puede borrar.
- IA: nunca enviar contraseñas, correos ni datos personales al proveedor externo; solo título, descripción, categoría y fragmentos de logs. `GEMINI_API_KEY` solo en `.env`. Si el proveedor falla o no hay clave, usar el driver `simulado` y avisar con un toast discreto.
- Escapar todo en Blade. En el chat IA, la respuesta del modelo se convierte de Markdown a HTML con un conversor que escape HTML (`Str::markdown($texto, ['html_input' => 'escape'])`).
