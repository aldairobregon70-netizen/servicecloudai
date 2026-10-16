# ServiceCloud AI — instrucciones para Claude Code

Las instrucciones del proyecto están en `AGENTS.md`. Léelo antes de cualquier tarea, junto con:

- `.agents/rules/` — reglas siempre activas (empieza por `00-docker.md`).
- `.agents/skills/servicecloud-schema/SKILL.md` — resumen del esquema PostgreSQL.
- `.agents/skills/servicecloud-ui/SKILL.md` — mapa del mockup `docs/mockup/index.html`.
- `.agents/workflows/` — fases de construcción y verificación.
- `docs/PLAN.md` — estado actual, decisiones y pendientes.

Resumen mínimo:

- Windows sin PHP local: todo comando de PHP, Composer o Artisan va con `docker compose exec -T app …`; la base con `docker compose exec -T pgsql …`. App en http://localhost:8000.
- El esquema `database/sql/servicecloud_ai_schema.sql` no se rediseña; se carga con una migración.
- La interfaz debe ser idéntica a `docs/mockup/index.html`, con datos reales de PostgreSQL.
- No se usa Laravel Boost (decisión registrada en `docs/PLAN.md`).
