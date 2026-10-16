# ServiceCloud AI — Instrucciones para agentes

Proyecto #9 del curso de Nubes (UNS). Plataforma SaaS multi-organización de **IT Service Management** (mesa de ayuda) con IA. Equipo de 5 personas, repositorio compartido en GitHub. Este archivo lo leen todos los agentes en cada tarea: respétalo siempre.

## 1. Qué se construye

ServiceCloud AI permite a organizaciones cliente registrar: clientes (organizaciones), usuarios, servicios, contratos, activos tecnológicos, tickets, incidencias, problemas, solicitudes y acuerdos de nivel de servicio (SLA). La IA clasifica y prioriza tickets automáticamente, sugiere soluciones usando la base de conocimiento (RAG) y analiza logs técnicos para apoyar el diagnóstico.

- **Roles:** Administrador SaaS (global), Administrador de organización, Supervisor, Técnico de soporte, Usuario solicitante.
- **Módulos (menú lateral, en este orden):** Dashboard, Organizaciones, Usuarios, Activos, Servicios, Contratos, Tickets, Incidencias, Problemas, Solicitudes, SLA, Asignaciones (kanban), Base de conocimiento, Asistente IA, Análisis de Logs, Notificaciones, Reportes, Configuración.
- **Enfoque de IA:** NLP + clasificación + RAG + análisis inteligente de logs.
- **Caso de ejemplo obligatorio (debe funcionar en la demo):** un usuario registra *"La aplicación demora demasiado y finalmente muestra error al consultar clientes"*. La IA lo clasifica (incidente de rendimiento / Aplicaciones), estima prioridad (Alta), consulta la base de conocimiento y muestra al técnico causas probables y artículos relacionados.
- El sistema se desplegará en varias nubes y se harán pruebas de rendimiento de la app, las APIs y la base de datos en cada una. Todo debe configurarse por variables de entorno, sin rutas ni hosts fijos.

## 2. Fuentes de verdad (leer antes de trabajar)

| Archivo | Para qué |
|---|---|
| `docs/mockup/index.html` | **Diseño obligatorio.** La app final debe verse y comportarse igual: colores, sidebar, header, tarjetas, tablas, badges, modales, toasts, kanban, chat IA, analizador de logs. |
| `database/sql/servicecloud_ai_schema.sql` | **Esquema de base de datos obligatorio** (45 tablas, PostgreSQL). No se rediseña; se usa tal cual. |
| `database/sql/servicecloud_ai_original.sql` | Script original para pgAdmin (con BEGIN/COMMIT). No tocar. |
| `docs/requerimientos.jpeg` | Enunciado del curso. |
| `docs/PLAN.md` | Plan de implementación vivo. Actualízalo al terminar cada fase. |
| `compose.yaml`, `Dockerfile` | Entorno de ejecución. No modificarlos sin anotarlo en `docs/PLAN.md`. |

## 3. Stack

- **Laravel 13** sobre **PHP 8.4** (contenedor Docker). No cambiar de versión sin pedirlo.
- **PostgreSQL 14+** (conexión `pgsql`). Base `servicecloud_ai`; base de pruebas `servicecloud_ai_test`.
- **Frontend:** Blade server-rendered + el CSS del mockup copiado en `public/css/servicecloud.css` + JavaScript vanilla en `public/js/servicecloud.js`. Chart.js y Lucide por CDN (mismas versiones del mockup). **Sin** React/Vue/Livewire/Tailwind/Bootstrap y sin starter kits de Laravel.
- **Autenticación propia** (controlador de login simple con sesión), no Breeze/Jetstream/Fortify.
- **IA:** capa propia `App\Services\AI\*` detrás de una interfaz, con driver `simulado` (reglas, sin internet, por defecto) y driver `gemini` (API REST vía `Http::`). Modelos y claves en `.env`.
- **Entorno: Docker.** Windows + Docker Desktop (WSL 2). Servicios `app` (PHP 8.4 + Nginx), `pgsql` (PostgreSQL 17) y `pgadmin` en `compose.yaml`. En Windows no hay PHP ni Composer: **todo comando se ejecuta con `docker compose exec -T app …`** (ver `.agents/rules/00-docker.md`). La app se ve en http://localhost:8000. La misma `Dockerfile` (stage `production`) se usará para desplegar en las nubes.

## 4. Reglas no negociables

1. **El esquema SQL manda.** Se carga con UNA migración que ejecuta `DB::unprepared(file_get_contents(database_path('sql/servicecloud_ai_schema.sql')))`. Nunca reescribas esas 45 tablas como migraciones con Schema Builder. Cambios posteriores al esquema = nuevas migraciones pequeñas y explicadas en `docs/PLAN.md`.
2. **Nombres en español**, igual que las tablas: modelos `Organizacion`, `Usuario`, `Ticket`, `Activo`, `Servicio`, `Contrato`, `Incidencia`, `Problema`, `Solicitud`, `ArticuloConocimiento`, etc. Cada modelo declara `$table` explícito.
3. **Timestamps propios:** tablas con `creado_en` y `actualizado_en` → `const CREATED_AT = 'creado_en'; const UPDATED_AT = 'actualizado_en';`. Solo `creado_en` → `UPDATED_AT = null`. Ninguno → `$timestamps = false`.
4. **Multi-tenant:** todo dato con `organizacion_id` se filtra por la organización del usuario mediante un Global Scope (trait `PerteneceAOrganizacion`). Solo `ADMIN_SAAS` ve todas las organizaciones. Nunca confíes en `organizacion_id` enviado por formulario.
5. **Valores de BD vs etiquetas de UI:** la BD guarda `en_progreso`, `critica`, `pendiente_aprobacion`...; la UI muestra "En progreso", "Crítica", "Pendiente aprobación". Usa Enums PHP (`app/Enums`) con `label()` y `badge()` que reproduzcan el mapa de colores de la función `badge()` del mockup.
6. **Seguridad:** FormRequest para toda escritura, Policies por rol, CSRF en formularios y fetch, `{{ }}` (nunca `{!! !!}` con datos de usuario), contraseñas con `Hash::make`, ninguna clave API en código ni en el repo.
7. **No inventes datos en las vistas.** Lo que el mockup tiene "hardcodeado" (KPIs, gráficos, insights, actividad) se calcula desde la BD. Los datos de ejemplo del mockup se convierten en **seeders**.
8. **Idioma:** UI, comentarios, mensajes de commit y documentación en español.

## 5. Detalles técnicos que rompen si se olvidan

- Modelo `Usuario` (tabla `usuarios`) extiende `Authenticatable`. La contraseña está en `password_hash`: sobrescribe `getAuthPassword()` y también `getAuthPasswordName()` para que devuelva `'password_hash'` (Laravel 11+ re-hashea al iniciar sesión y fallaría buscando `password`). No hay `remember_token`: desactiva "recordarme" o agrega la columna con una migración nueva.
- `config/auth.php` → provider `users` con `model => App\Models\Usuario::class`.
- La migración por defecto `0001_01_01_000000_create_users_table.php` crea `users`, `password_reset_tokens` y `sessions`: deja **solo** `sessions`. Conserva las de `cache` y `jobs`.
- `ADMIN_SAAS` tiene `organizacion_id = NULL` y su rol va en `usuarios_roles`. Los demás roles van en `usuarios_roles_organizacion`. La tabla heredada `usuarios_roles` solo se usa para roles globales.
- Las tablas de catálogo nuevas (`categorias_tickets`, `servicios_activos`) son las preferidas; las columnas heredadas `tickets.categoria`, `tickets.subcategoria` y `activos.servicio_id` se llenan por compatibilidad pero no se usan como fuente principal.
- Código de ticket: `TKT-AAAA-00001` (correlativo por año). Incidencias `INC-0001`, problemas `PRB-001`, solicitudes `REQ-0001`, artículos `KB-001`.
- Fechas: guardar en zona `America/Lima` (`APP_TIMEZONE`), mostrar como `03/10/2026 21:18`. Moneda `S/`.
- Las FK compuestas `(organizacion_id, x_id)` exigen que el registro relacionado sea de la misma organización: valida eso en los FormRequest para dar un error legible en lugar de una excepción SQL.

## 6. Estructura esperada

```
app/Enums/                 Estados, prioridades, tipos (label + badge)
app/Models/                Un modelo por tabla principal + pivotes con BelongsToMany
app/Models/Concerns/       PerteneceAOrganizacion (global scope)
app/Http/Controllers/      Un controlador por módulo (resource)
app/Http/Requests/         FormRequests
app/Policies/              Políticas por rol
app/Services/              TicketService, SlaService, AsignacionService, NotificacionService, AuditoriaService
app/Services/AI/           ClasificadorTickets, RecomendadorRag, AnalizadorLogs, AsistenteChat, Drivers/{Simulado,Gemini}
resources/views/layouts/   app.blade.php (sidebar + header + toasts + modal)
resources/views/components/ badge, kpi, chart-card, page-header, modal, tabla...
resources/views/<modulo>/  index, show, create/edit (o modal)
public/css/servicecloud.css · public/js/servicecloud.js
database/seeders/          Datos demo del mockup
tests/Feature/             Login, aislamiento multi-tenant, tickets + SLA, IA simulada
```

## 7. Forma de trabajar

- Trabaja por fases (ver `docs/PLAN.md` y los workflows `/fase-…`). Al inicio de cada fase genera un plan corto; al final ejecuta `/verificar` y actualiza `docs/PLAN.md` con lo hecho y lo pendiente.
- Antes de dar algo por terminado: `docker compose exec -T app php artisan migrate:fresh --seed` sin errores, `docker compose exec -T app php artisan test` en verde, y revisión visual en el navegador comparando con `docs/mockup/index.html`.
- Haz commits pequeños por módulo con mensajes en español (`feat(tickets): …`).
- **Nunca** ejecutes `migrate:fresh`, `db:wipe` ni borres datos contra una base que no sea local. Nunca subas `.env`.
- Si un requisito es ambiguo, elige la opción más simple coherente con el mockup, anótala en `docs/PLAN.md` en "Decisiones" y continúa.
