# ServiceCloud AI

Plataforma SaaS multi-organización de **IT Service Management** (mesa de ayuda) con IA: tickets con SLA, incidencias, problemas, solicitudes, activos, servicios y contratos; clasificación y priorización automática de tickets (NLP), recomendaciones con la base de conocimiento (RAG), asistente conversacional y análisis inteligente de logs.

Proyecto #9 del curso de Nubes (UNS). Laravel 13 · PHP 8.4 · PostgreSQL 17 · Blade + JavaScript vanilla · Docker.

## Requisitos

- **Docker Desktop** (en Windows con WSL 2) y **Git**. No hace falta instalar PHP, Composer ni PostgreSQL.

## Instalación

```powershell
git clone <url-del-repositorio> servicecloudai
cd servicecloudai
copy .env.example .env
docker compose up -d --build
docker compose exec -u root app sh -c "composer install && chown -R www-data:www-data vendor && chmod -R a+rwX storage bootstrap/cache"
docker compose exec -u root app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed
```

| Servicio | URL |
|---|---|
| Aplicación | http://localhost:8000 |
| pgAdmin | http://localhost:5050 (`admin@servicecloud.ai` / `admin`) |
| PostgreSQL | `localhost:5432` (`servicecloud` / `secret`, bases `servicecloud_ai` y `servicecloud_ai_test`) |

El servicio `scheduler` ejecuta las tareas programadas (`sla:revisar` cada 5 min, estado de contratos a diario).

## Usuarios demo

Contraseña de todos: **`password`**.

| Rol | Correo | Organización |
|---|---|---|
| Administrador SaaS | `admin@servicecloud.ai` | Plataforma |
| Administrador de organización | `rosa.villanueva@andesmarket.pe` | AndesMarket S.A.C. |
| Supervisor | `ana.torres@andesmarket.pe` | AndesMarket S.A.C. |
| Técnico de soporte | `maria.pena@andesmarket.pe` | AndesMarket S.A.C. |
| Solicitante | `lucia.vega@andesmarket.pe` | AndesMarket S.A.C. |
| Técnico (otra organización) | `luis.ramirez@novatech.pe` | Grupo NovaTech |

Lista completa en `docs/PLAN.md`.

## Caso de ejemplo de IA

Inicia sesión como `lucia.vega@andesmarket.pe`, ve a **Tickets → Nuevo ticket** y registra: *"La aplicación demora demasiado y finalmente muestra error al consultar clientes"*. La IA lo clasifica como **incidente de rendimiento / Aplicaciones**, estima prioridad **Alta**, consulta la base de conocimiento y muestra al técnico las causas probables y los artículos relacionados. Evidencia en `docs/evidencias/`.

## Comandos frecuentes

Todo se ejecuta dentro de los contenedores:

| Tarea | Comando |
|---|---|
| Encender / apagar | `docker compose up -d` / `docker compose stop` |
| Reconstruir la base con datos demo | `docker compose exec app php artisan migrate:fresh --seed` |
| Tests (143, contra `servicecloud_ai_test`) | `docker compose exec app php artisan test` |
| Formato PSR-12 | `docker compose exec -u root app ./vendor/bin/pint` |
| Reindexar la base de conocimiento (RAG) | `docker compose exec app php artisan ia:indexar --forzar` |
| Revisar SLA a mano | `docker compose exec app php artisan sla:revisar` |
| Agregar un paquete | `docker compose exec -u root app sh -c "composer require vendor/paquete && chown -R www-data:www-data vendor"` |
| Logs | `docker compose logs app --tail=100` |

## Activar Gemini

Por defecto la IA usa el motor local `simulado` (reglas y embeddings sin internet). Para usar Gemini, en `.env`:

```dotenv
AI_DRIVER=gemini
GEMINI_API_KEY=tu-clave
GEMINI_MODELO_TEXTO=<modelo de texto de Gemini>
GEMINI_MODELO_EMBEDDING=<modelo de embeddings de Gemini>
```

Luego `docker compose exec app php artisan config:clear` y `php artisan ia:indexar --forzar` (los embeddings de cada proveedor no son comparables). Si Gemini falla o no responde, la aplicación usa el motor local y lo avisa con un aviso discreto. A Gemini solo se envía texto técnico (correos y teléfonos se enmascaran).

## Imagen de producción y nube

```bash
docker build --target production -t servicecloudai:prod .
```

La imagen arranca en el puerto **8080**, ejecuta las migraciones y cachea la configuración automáticamente. Guía completa (variables, PostgreSQL administrado, programador y notas por nube) en `docs/DESPLIEGUE.md`. API JSON para pruebas de rendimiento en `docs/API.md` y script de carga k6 en `docs/pruebas/carga.js`.

## Estructura

```
app/Enums/                Estados, prioridades y tipos (etiqueta + color de badge)
app/Models/               Un modelo por tabla; Concerns/PerteneceAOrganizacion (multi-tenant)
app/Http/Controllers/     Un controlador por módulo (+ Api/ para la API JSON)
app/Http/Requests/        Validación (FormularioTenant valida relaciones de la misma organización)
app/Policies/             Permisos por rol
app/Services/             Tickets, SLA, asignaciones, notificaciones, reportes, dashboard
app/Services/AI/          Gestor de IA, drivers Simulado/Gemini, clasificador, RAG, asistente, logs
database/sql/             Esquema oficial (45 tablas) cargado por una migración
database/seeders/         Datos demo
resources/views/          Vistas Blade idénticas a docs/mockup/index.html
public/css, public/js     CSS del mockup + JavaScript vanilla
docs/                     Plan, API, despliegue, mockup, evidencias y pruebas
```

## Documentación del proyecto

- `AGENTS.md` y `.agents/` — instrucciones y reglas para los agentes de IA del equipo.
- `docs/PLAN.md` — plan por fases, decisiones tomadas y pendientes.
- `docs/mockup/index.html` — diseño de referencia.
