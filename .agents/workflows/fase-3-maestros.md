---
description: Fase 3 — módulos maestros Organizaciones, Usuarios, Activos, Servicios y Contratos con CRUD completo
---

Ejecuta `/crear-modulo` para cada uno, en este orden, y haz commit al terminar cada uno:

1. Organizaciones (`organizaciones`; plan, estado, conteos de usuarios y servicios; al crear, crea también su `configuracion_organizacion`). Solo ADMIN_SAAS crea/elimina.
2. Usuarios (`usuarios` + rol en `usuarios_roles_organizacion` o `usuarios_roles` si es ADMIN_SAAS; contraseña opcional al editar; estado activo/inactivo/bloqueado).
3. Activos (`activos`; tipo, IP/hostname, responsable, servicios que soporta vía `servicios_activos`, datos cloud).
4. Servicios (`servicios`; responsable, contrato, política SLA, disponibilidad objetivo; activos asociados).
5. Contratos (`contratos`; fechas, monto en S/, política SLA; estado `por_vencer` automático si faltan ≤ 30 días: comando programado `contratos:actualizar-estados`).

Al final: `/verificar` y marca la fase 3 en `docs/PLAN.md`.
