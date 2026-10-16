---
description: Verificación rápida antes de cerrar cualquier tarea — migraciones, tests, rutas, seguridad multi-tenant y comparación visual con el mockup
---

Ejecuta con el agente `qa`:

0. `docker compose ps` (app y pgsql arriba; pgsql *healthy*)
1. `docker compose exec -T app php artisan migrate:fresh --seed`
2. `docker compose exec -T app php artisan test`
3. `docker compose exec -T app php artisan route:list --except-vendor`
4. Busca `{!!` en `resources/views` y justifica cada uso.
5. Busca claves o contraseñas en el código (`GEMINI_API_KEY=`, `password` fuera de seeders/tests).
6. Abre en el navegador (http://localhost:8000) las pantallas tocadas en esta tarea y compáralas con `docs/mockup/index.html`.
7. Prueba con un usuario de otra organización que no ve los datos creados.
8. Reporta: OK / Problemas / Corregido. Si todo está OK, actualiza `docs/PLAN.md`.
