---
description: Fase 1 — carga el esquema SQL, crea modelos Eloquent, enums, scope multi-tenant y seeders con los datos del mockup
---

Agente principal: `db-laravel`. Skill: `servicecloud-schema`.

1. Edita `database/migrations/0001_01_01_000000_create_users_table.php` para que cree solo la tabla `sessions` (quita `users` y `password_reset_tokens`). Conserva las migraciones de cache y jobs.
2. Crea `database/migrations/2026_01_01_000100_crear_esquema_servicecloud.php` que ejecute `DB::unprepared(file_get_contents(database_path('sql/servicecloud_ai_schema.sql')))`. `down()` vacío con comentario.
3. Ejecuta `php artisan migrate:fresh` y confirma que existen 45 tablas del esquema (consulta `information_schema.tables`).
4. Crea los Enums en `app/Enums` para cada CHECK del SQL, con `label()` (español con tildes) y `badge()` (blue, cyan, green, yellow, red, purple, gray, igual que el mapa `badge()` del mockup) y un método estático `valores()`.
5. Crea el trait `app/Models/Concerns/PerteneceAOrganizacion.php`.
6. Crea todos los modelos con `$table`, `$fillable`, `$casts` (incluye enums), constantes de timestamps y relaciones. Modelo `Usuario` autenticable (ver `AGENTS.md` §5) y actualiza `config/auth.php`.
7. Crea seeders: `PlataformaSeeder` (permisos, modelos_ia, políticas SLA plantilla), `DemoSeeder` (datos del objeto `defaults` del mockup + equipos, categorías, contratos, SLA por organización, 7 días de tickets con distintos estados y prioridades, clasificaciones IA y artículos KB publicados con contenido de 3–5 párrafos útil para RAG). Contraseña de todos los usuarios demo: `password`.
8. Ejecuta `php artisan migrate:fresh --seed`. Corrige hasta que no haya errores. Muestra un conteo de filas por tabla principal.
9. Escribe tests: `EsquemaTest` (45 tablas existen) y `ModelosTest` (relaciones principales cargan sin error; el scope oculta datos de otra organización).
10. Ejecuta `/verificar`. Registra los usuarios demo en `docs/PLAN.md`. Commit: `feat(db): esquema, modelos y datos demo`.
