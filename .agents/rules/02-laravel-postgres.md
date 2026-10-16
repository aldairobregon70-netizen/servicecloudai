---
trigger: always_on
---

# Regla: convenciones Laravel + PostgreSQL de ServiceCloud AI

## Base de datos

- `.env`: `DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, `DB_PORT=5432`, `DB_DATABASE=servicecloud_ai`, `DB_USERNAME=servicecloud`, `DB_PASSWORD=secret` (valores del `compose.yaml`; en la nube vendrán por variables de entorno). `APP_URL=http://localhost:8000`, `APP_TIMEZONE=America/Lima`, `APP_LOCALE=es`, `APP_FAKER_LOCALE=es_PE`.
- `phpunit.xml`: `DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, `DB_DATABASE=servicecloud_ai_test` (no SQLite: el esquema usa JSONB, GIN, identidades y FK compuestas).
- El esquema se crea solo con la migración `2026_01_01_000100_crear_esquema_servicecloud.php`, que ejecuta `database/sql/servicecloud_ai_schema.sql` con `DB::unprepared`. Su `down()` queda vacío con un comentario: el esquema no es reversible por partes; para reconstruir se usa `php artisan migrate:fresh --seed` (borra todas las tablas y la función `servicecloud_set_updated_at` se recrea con `CREATE OR REPLACE`).
- Ese archivo SQL ya no tiene `BEGIN;`/`COMMIT;` (Laravel envuelve la migración en transacción). No los vuelvas a poner.
- Inserción en tablas con identidad: deja que PostgreSQL asigne `id`. Si un seeder inserta ids explícitos, al final reajusta la secuencia con `SELECT setval(pg_get_serial_sequence('tabla','id'), (SELECT MAX(id) FROM tabla));`.
- Columnas JSONB → `$casts = ['campo' => 'array']`. Booleanos → `'boolean'`. Decimales → `'decimal:2'`. Fechas → `'datetime'`.
- Búsquedas de texto: usa los índices GIN existentes con `whereRaw("to_tsvector('spanish', coalesce(titulo,'') || ' ' || coalesce(descripcion,'')) @@ plainto_tsquery('spanish', ?)", [$q])`, con respaldo `ILIKE` para términos cortos.

## Modelos Eloquent

- Un modelo por tabla de negocio; pivotes como `BelongsToMany` con `->using()` solo si llevan columnas extra (`recomendaciones_fuentes.relevancia`).
- `$fillable` explícito (nunca `$guarded = []`).
- Relaciones nombradas en español: `organizacion()`, `solicitante()`, `tecnico()`, `servicio()`, `activo()`, `politicaSla()`, `comentarios()`, `historial()`, `asignaciones()`, `clasificaciones()`, `recomendaciones()`.
- Trait `App\Models\Concerns\PerteneceAOrganizacion`: agrega Global Scope por `organizacion_id` del usuario autenticado (salvo ADMIN_SAAS o consola) y asigna `organizacion_id` automáticamente en `creating`.
- Helpers en `Usuario`: `tieneRol(string $codigo)`, `esAdminSaas()`, `rolPrincipal()` (para mostrar "Administrador SaaS", "Técnico de soporte"...), `iniciales()`.

## Controladores y rutas

- `Route::resource` por módulo dentro de `middleware('auth')`. Nombres de ruta en español del módulo (`tickets.*`, `incidencias.*`, `activos.*`...).
- Controladores delgados: la lógica de negocio va en `app/Services`.
- Toda acción de escritura: FormRequest + `$this->authorize()` / Policy. Validaciones con `Rule::in(Enum::valores())` y `Rule::exists(...)->where('organizacion_id', $org)`.
- Respuestas: redirección con `->with('ok', 'Mensaje para toast')`. Endpoints AJAX (kanban, chat, logs, búsqueda) devuelven JSON y validan igual que los formularios.
- Paginación: 15 por página, conservando los filtros en la query string.
- Cambios de ticket (estado, prioridad, técnico) siempre pasan por `TicketService`, que registra `tickets_historial`, `tickets_asignaciones`, `auditoria` y genera `notificaciones`.

## Calidad

- PHP 8.4 con tipos en parámetros y retornos. PSR-12 (`docker compose exec -T app ./vendor/bin/pint`).
- Sin N+1: usa `with()` en listados. Revisa con `DB::enableQueryLog()` cuando dudes.
- Tests Feature con `RefreshDatabase` contra `servicecloud_ai_test`. Mínimos: login/logout, un técnico de la org A no ve tickets de la org B, crear ticket calcula vencimientos SLA, clasificación IA simulada del caso de ejemplo, kanban cambia estado.
- Todos los comandos van dentro del contenedor (regla `00-docker`): `docker compose exec -T app php artisan migrate:fresh --seed`, `… php artisan test`, `… php artisan route:list`.
