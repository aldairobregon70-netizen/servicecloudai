---
description: Fase 0 — verifica los contenedores Docker (app, pgsql, pgadmin), configura .env y phpunit para PostgreSQL y crea docs/PLAN.md
---

1. Lee `AGENTS.md`, `.agents/rules/*` (empieza por `00-docker`) y las skills `servicecloud-ui` y `servicecloud-schema`.
2. Ejecuta `docker compose ps`. Si `app` o `pgsql` no están corriendo, ejecuta `docker compose up -d --build` y espera a que `pgsql` esté *healthy*. Si Docker no responde, DETENTE y pide al desarrollador que abra Docker Desktop.
3. Verifica dentro del contenedor y reporta: `docker compose exec -T app php -v` (8.4), `docker compose exec -T app php -m` (deben aparecer `pdo_pgsql`, `intl`, `bcmath`), `docker compose exec -T app composer -V`, `docker compose exec -T app php artisan --version`.
4. Configura `.env` según las reglas `00-docker` y `02-laravel-postgres` (pgsql con host `pgsql`, usuario `servicecloud`, America/Lima, es, `APP_URL=http://localhost:8000`). Agrega al final: `AI_DRIVER=simulado`, `GEMINI_API_KEY=`, `GEMINI_MODELO_TEXTO=`, `GEMINI_MODELO_EMBEDDING=`. Replica las claves en `.env.example` (sin secretos reales).
5. Borra `database/database.sqlite` si existe (lo creó el instalador de Laravel y no se usa).
6. Ejecuta `docker compose exec -T app php artisan config:clear` y luego `docker compose exec -T app php artisan db:show`: debe mostrar PostgreSQL 17 y la base `servicecloud_ai`. Comprueba que existe `servicecloud_ai_test` con `docker compose exec -T pgsql psql -U servicecloud -l`; si no existe, créala con `docker compose exec -T pgsql createdb -U servicecloud servicecloud_ai_test`.
7. Configura `phpunit.xml` (`DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, `DB_DATABASE=servicecloud_ai_test`; elimina las líneas de sqlite/:memory:).
8. Abre http://localhost:8000 en el navegador integrado: debe verse la página de bienvenida de Laravel.
9. Crea `docs/PLAN.md` con: fases 0–7 (de los workflows `/fase-…`) como checklist, secciones "Decisiones", "Usuarios demo", "Comandos útiles" y "Pendiente".
10. Verifica que `.gitignore` incluya `.env`, `/vendor`, `/node_modules`. Si no hay repositorio git, `git init`. Commit: `chore: entorno Docker y configuración inicial`.
11. Marca la fase 0 como hecha en `docs/PLAN.md`.
