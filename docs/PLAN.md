# ServiceCloud AI — Plan de implementación

Plan vivo. Se actualiza al cerrar cada fase (Hecho / Pendiente / Decisiones).

## Fases

- [x] **Fase 0 — Entorno:** contenedores, `.env` y `phpunit.xml` para PostgreSQL, este plan.
- [ ] **Fase 1 — Base de datos:** migración del esquema SQL, enums, scope multi-tenant, modelos, seeders demo.
- [ ] **Fase 2 — Base de la app:** CSS del mockup, layout, login, componentes Blade, menú por roles, JS base.
- [ ] **Fase 3 — Maestros:** Organizaciones, Usuarios, Activos, Servicios, Contratos (CRUD completo).
- [ ] **Fase 4 — Operación:** Tickets + SLA, Asignaciones (kanban), Incidencias, Problemas, Solicitudes, SLA, notificaciones, scheduler.
- [ ] **Fase 5 — IA:** drivers simulado/Gemini, base de conocimiento + RAG, clasificación, asistente, análisis de logs.
- [ ] **Fase 6 — Dashboard y reportes:** KPIs y gráficos reales, notificaciones, reportes CSV, configuración, búsqueda global, auditoría.
- [ ] **Fase 7 — Cierre y nube:** QA, rendimiento, imagen de producción, API, k6, documentación de despliegue.

## Hecho

### Fase 0 (16/10/2026)

- Contenedores `app` (PHP 8.4.26, `pdo_pgsql`, `intl`, `bcmath`, Composer 2.10, Laravel 13.37), `pgsql` (PostgreSQL 17.11) y `pgadmin` arriba y sanos.
- `.env` / `.env.example`: PostgreSQL (`DB_HOST=pgsql`), `APP_TIMEZONE=America/Lima`, `APP_LOCALE=es`, `APP_FAKER_LOCALE=es_PE`, variables `AI_DRIVER`, `GEMINI_*`.
- `config/app.php`: `timezone` lee `APP_TIMEZONE` (por defecto `America/Lima`).
- `phpunit.xml`: tests contra `servicecloud_ai_test` en el servicio `pgsql`.
- Eliminado `database/database.sqlite`.
- `CLAUDE.md` reemplazado por un puntero a `AGENTS.md` y `.agents/`.

## Decisiones

- **Sin Laravel Boost.** `boost:install` reescribe `AGENTS.md`/`CLAUDE.md`; el kit de `.agents/` ya cumple esa función.
- **Agentes de `.agents/agents/`** se aplican como roles por fase en una sola sesión (no en paralelo: tocan los mismos archivos).
- **Permisos del volumen en Windows:** el código montado aparece como `root` (755) dentro del contenedor y `www-data` no podía escribir en `storage/` ni `bootstrap/cache` (error 500 `tempnam()`). Se corrige una vez con `docker compose exec -T -u root app chmod -R a+rwX storage bootstrap/cache` (persiste en el volumen). Comandos que escriben código (`pint`, `make:*`, `composer require`) se ejecutan con `-u root`.
- **Admin demo:** `admin@servicecloud.ai` (Jorge Espinoza) en lugar del correo del mockup.
- **Sin "recordarme"** en el login: `usuarios` no tiene `remember_token` y no se cambia el esquema.
- Commits directos en `main`, sin push.

## Usuarios demo

Se completa en la fase 1. Contraseña de todos: `password`.

## Comandos útiles

```powershell
docker compose up -d                                   # levantar
docker compose ps                                      # estado
docker compose exec -T app php artisan migrate:fresh --seed
docker compose exec -T app php artisan test
docker compose exec -T app php artisan route:list --except-vendor
docker compose exec -T -u root app ./vendor/bin/pint   # formato PSR-12
docker compose exec -T -u root app chmod -R a+rwX storage bootstrap/cache   # si aparece error de permisos
docker compose exec -T pgsql psql -U servicecloud -d servicecloud_ai
docker compose logs app --tail=100
```

- App: http://localhost:8000 · pgAdmin: http://localhost:5050 (`admin@servicecloud.ai` / `admin`)

## Pendiente

- Fase 1 en adelante.
- Fase 7: confirmar con el equipo en qué nubes se desplegará.
