# ServiceCloud AI — Plan de implementación

Plan vivo. Se actualiza al cerrar cada fase (Hecho / Pendiente / Decisiones).

## Fases

- [x] **Fase 0 — Entorno:** contenedores, `.env` y `phpunit.xml` para PostgreSQL, este plan.
- [x] **Fase 1 — Base de datos:** migración del esquema SQL, enums, scope multi-tenant, modelos, seeders demo.
- [x] **Fase 2 — Base de la app:** CSS del mockup, layout, login, componentes Blade, menú por roles, JS base.
- [x] **Fase 3 — Maestros:** Organizaciones, Usuarios, Activos, Servicios, Contratos (CRUD completo).
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

### Fase 1 (16/10/2026)

- Migración `2026_01_01_000100_crear_esquema_servicecloud` carga el SQL tal cual (45 tablas). La migración por defecto de Laravel solo crea `sessions` (+ cache y jobs).
- 26 enums en `app/Enums` (label en español + color de badge del mockup + `valores()`/`opciones()`); `Prioridad::desdeMatriz()` implementa impacto × urgencia; enum `Rol` con los códigos de la tabla `roles`.
- Trait `PerteneceAOrganizacion`: global scope por la organización del usuario autenticado (ADMIN_SAAS y consola sin filtro) y asignación forzada de `organizacion_id` al crear. Lectura de registros globales (`organizacion_id NULL`) en políticas SLA, artículos, documentos RAG y notificaciones.
- 36 modelos con `$table`, `$fillable`, casts (enums, JSONB, fechas) y relaciones; `Usuario` autenticable con `password_hash`.
- `SlaService` básico (suma de minutos 24x7 y laboral L-V 08:00-18:00); se completa en la fase 4.
- Seeders: `PlataformaSeeder` (30 permisos por rol, 7 modelos IA, plantillas SLA Gold/Silver/Bronze, límites de planes), `DemoSeeder` (5 organizaciones, 21 usuarios, equipos, categorías, SLA por organización, 5 contratos, 11 servicios, 10 activos, 109 tickets de 30 días con SLA/historial/asignaciones, clasificaciones IA, incidencias, problemas, solicitudes, notificaciones), `ConocimientoSeeder` (8 artículos, 6 públicos con contenido extenso para RAG).
- Tests: `EsquemaTest`, `ModelosTest` (relaciones, aislamiento multi-tenant), `ReglasNegocioTest` (matriz de prioridad, SLA laboral). 16 tests en verde.

### Fase 2 (16/10/2026)

- `public/css/servicecloud.css`: bloque `<style>` del mockup copiado literalmente + sección `/* === Extensiones ServiceCloud === */` (login, paginación, errores de validación, toasts de error, logout móvil, corrección del overlay móvil).
- `config/servicecloud.php`: menú de 18 entradas (ruta, ícono, etiqueta, roles, título y descripción del `pageHeader`) y configuración de IA.
- Layout `layouts/app.blade.php` idéntico al mockup (sidebar, header con búsqueda global, Asistente IA, campana con dropdown real, perfil con logout real, modal y toasts). `layouts/guest.blade.php` + login con la misma identidad visual. Páginas de error 403/404/419.
- Componentes Blade: `badge`, `page-header`, `kpi`, `chart-card`, `activity`, `meta-box`, `timeline-item`, `rag-article`, `empty-state`, `form-field`; parciales de toasts, paginación y notificaciones.
- `public/js/servicecloud.js`: sidebar/drawer, dropdowns, Escape, modal genérico (`data-modal-url`, `data-modal-template`, `data-confirmar`), formularios AJAX con errores por campo (`form[data-ajax]`), filtros de listado, `scFetch()` con CSRF, `scInitChart()` con las opciones de `initChart()`, búsqueda global.
- Login propio (estado activo, organización no suspendida, `ultimo_acceso`, 5 intentos/min). Gate `modulo` + middleware `can:modulo,'<clave>'` en las 17 rutas de módulos (403 por URL).
- Dashboard con datos reales (`DashboardService`): 5 KPIs con tendencia, 4 gráficos, Insights IA y actividad reciente, según el alcance del rol. Búsqueda global `/buscar` (tickets, usuarios, activos; respeta tenant y rol).
- Tests: `AutenticacionTest`, `NavegacionTest`. 28 tests en verde.
- Verificación visual con capturas headless (`docs/pruebas/captura-ui.js`): dashboard y login comparados con el mockup en 1440 px y 390 px.

### Fase 3 (16/10/2026)

- Infraestructura CRUD reutilizable: `FormularioTenant` (organización resuelta en el servidor + `exists` acotado a la organización por las FK compuestas), `PoliticaMaestro`, `Controller::guardado()` / `vistaModal()` / `opcionesRelacion()`, `Csv` (BOM UTF-8, anti-inyección de fórmulas), `Codigos` (correlativos ORG-006, AST-2011, SRV-12, CTR-006), `ColorBadge` (mapa del mockup para textos como planes y SLA).
- Componentes: `modal-form`, `modal-detalle`, `acciones-fila`, `toolbar-filtros`, `form-relacion` (opciones con `data-org`, filtradas por JS cuando el Admin SaaS elige organización).
- **Organizaciones** (solo ADMIN_SAAS gestiona; ADMIN_ORGANIZACION ve la suya): alta con `OrganizacionService` que crea configuración, copia las 3 plantillas SLA (por defecto según el plan), categorías y Mesa de Ayuda. Eliminar con operación → Inactiva.
- **Usuarios**: rol en `usuarios_roles_organizacion` (o `usuarios_roles` para ADMIN_SAAS), contraseña opcional al editar, no puede eliminarse a sí mismo, con historial → Inactivo.
- **Activos**: servicios N:M en `servicios_activos` (+ columna heredada `servicio_id`), datos cloud.
- **Servicios**: owner, contrato, política SLA, disponibilidad, activos N:M; con tickets → Retirado.
- **Contratos**: monto en S/, estado automático por fechas (vigente / por vencer ≤ 30 días / vencido); comando `contratos:actualizar-estados` programado a diario.
- Traducciones `lang/es` (validación, auth, paginación).
- Tests: `OrganizacionesTest`, `UsuariosTest`, `ActivosServiciosContratosTest` (listar, crear, validar, editar, eliminar/desactivar, otro tenant 404, rol 403, CSV). 60 tests en verde. Flujo del modal probado en Chrome headless (errores por campo, guardado, toast, confirmación de eliminación).
- **Rendimiento del entorno**: `vendor/` en volumen Docker + OPcache en desarrollo → de 2-5 s a 0,1-0,4 s por página.

## Decisiones

- **Sin Laravel Boost.** `boost:install` reescribe `AGENTS.md`/`CLAUDE.md`; el kit de `.agents/` ya cumple esa función.
- **Agentes de `.agents/agents/`** se aplican como roles por fase en una sola sesión (no en paralelo: tocan los mismos archivos).
- **Permisos del volumen en Windows:** el código montado aparece como `root` (755) dentro del contenedor y `www-data` no podía escribir en `storage/` ni `bootstrap/cache` (error 500 `tempnam()`). Se corrige una vez con `docker compose exec -T -u root app chmod -R a+rwX storage bootstrap/cache` (persiste en el volumen). Comandos que escriben código (`pint`, `make:*`, `composer require`) se ejecutan con `-u root`.
- **Admin demo:** `admin@servicecloud.ai` (Jorge Espinoza) en lugar del correo del mockup.
- **Sin "recordarme"** en el login: `usuarios` no tiene `remember_token` y no se cambia el esquema.
- Commits directos en `main`, sin push.
- **SLA por organización:** las FK compuestas `(organizacion_id, politica_sla_id)` impiden que un ticket/servicio/contrato use una plantilla global (`organizacion_id NULL`). Cada organización recibe su copia de las plantillas; la política por defecto se guarda en `configuracion_organizacion.configuracion->politica_sla_defecto_id`.
- **Técnicos del mockup reasignados por organización:** el mockup asigna a Luis Ramírez (NovaTech) un ticket de AndesMarket, lo que el esquema prohíbe (FK compuesta). TKT-2026-00452 se asigna a María Peña (técnica de AndesMarket); Luis Ramírez queda como técnico de NovaTech.
- **Fechas demo relativas:** los tickets del mockup se crean relativos a la hora del seed para que el "SLA restante" coincida con el mockup (00452 → 01h 12m, 00451 → 21 min). Los contratos usan fechas fijas de 2026.
- **Colores de badges** no definidos en el mockup: Bloqueado/Suspendida/Rechazada = red, Cancelado/Retirado/Archivado = gray, Aprobada = cyan, En análisis = purple.
- **Tests** siembran los datos demo (`$seed = true` en `Tests\TestCase`) porque `RefreshDatabase` migra una sola vez por proceso. Caché de PHPUnit en `storage/framework/cache/phpunit`.
- **Overlay móvil del mockup:** en ≤1024 px el mockup mostraba `.mobile-overlay` siempre (pantalla borrosa). Se corrige en las extensiones CSS para que solo aparezca con el menú abierto.
- **Logout en móvil:** en ≤760 px el header oculta el bloque de usuario; se agrega "Cerrar sesión" al pie del sidebar solo en ese ancho.
- **Sidebar contraído** se recuerda entre páginas con `localStorage` (preferencia local; sin ella todo funciona igual).
- **Formularios en modal** se envían por `fetch` (`form[data-ajax]`): los errores 422 se pintan debajo de cada campo sin cerrar el modal; si todo va bien el servidor responde `{redirect}` y el toast llega por `session('ok')`.
- **Filtros de listados** en el servidor (paginación correcta): los selects envían al cambiar y la búsqueda tras 450 ms de pausa.
- Dashboard y búsqueda global se adelantaron a la fase 2 para no mostrar números fijos; la fase 6 agrega caché de 60 s y ajustes.
- "Ver reportes" del dashboard solo se muestra a roles con acceso a Reportes.
- **Cambio en `compose.yaml`:** volumen `vendor` montado en `/var/www/html/vendor` y OPcache activo en desarrollo (`PHP_OPCACHE_ENABLE=1`, `VALIDATE_TIMESTAMPS=1`, `REVALIDATE_FREQ=0`). Motivo: en Windows cada petición tardaba 2-5 s leyendo miles de archivos del volumen montado. Consecuencia: `composer install/require` se ejecuta con `-u root` y luego `chown -R www-data:www-data vendor` (documentado en `LEEME.md` y en la regla 00-docker).
- **Maestros: campos reales en lugar de los del mockup.** Los formularios del mockup tenían "Usuarios"/"Servicios" como números o "Organización" como texto libre; aquí los conteos se calculan y las relaciones se eligen de listas. La columna "ID" muestra el código (`ORG-001`, `AST-2001`...); los usuarios no tienen código y se muestran como `USR-101` (100 + id).
- El **Admin SaaS** elige la organización al crear registros de tenant; al editar, la organización no cambia (evita romper FK compuestas).
- `APP_FALLBACK_LOCALE=en` con `lang/en` publicado: si falta una traducción se ve en inglés en lugar de la clave cruda.
- Rutas de ticket y artículo usan `codigo` como clave (`/tickets/TKT-2026-00452`). Un registro de otra organización devuelve 404 (el scope lo oculta) en lugar de 403, para no revelar su existencia.

## Usuarios demo

Contraseña de todos: `password`.

| Rol | Correo | Nombre | Organización |
|---|---|---|---|
| Administrador SaaS | admin@servicecloud.ai | Jorge Espinoza | — (plataforma) |
| Admin. organización | rosa.villanueva@andesmarket.pe | Rosa Villanueva | AndesMarket S.A.C. |
| Supervisor | ana.torres@andesmarket.pe | Ana Torres | AndesMarket S.A.C. |
| Técnico | maria.pena@andesmarket.pe | María Peña | AndesMarket S.A.C. |
| Técnico | kevin.rojas@andesmarket.pe | Kevin Rojas | AndesMarket S.A.C. |
| Solicitante | lucia.vega@andesmarket.pe | Lucía Vega | AndesMarket S.A.C. |
| Solicitante | daniela.paredes@andesmarket.pe | Daniela Paredes | AndesMarket S.A.C. |
| Admin. organización | patricia.gomez@novatech.pe | Patricia Gómez | Grupo NovaTech |
| Supervisor | ricardo.flores@novatech.pe | Ricardo Flores | Grupo NovaTech |
| Técnico | luis.ramirez@novatech.pe | Luis Ramírez | Grupo NovaTech |
| Técnico | carla.medina@novatech.pe | Carla Medina | Grupo NovaTech |
| Solicitante | marco.rios@novatech.pe | Marco Ríos | Grupo NovaTech |
| Admin. organización | oscar.mendoza@logisticanorte.pe | Óscar Mendoza | Logística Norte Perú |
| Técnico | diana.paz@logisticanorte.pe | Diana Paz | Logística Norte Perú |
| Solicitante | maria.salazar@logisticanorte.pe | María Salazar | Logística Norte Perú |
| Admin. organización | sofia.herrera@pacifico.pe | Sofía Herrera | Pacífico Centro Odontológico |
| Técnico | hugo.vargas@pacifico.pe | Hugo Vargas | Pacífico Centro Odontológico |
| Solicitante | carmen.lozano@pacifico.pe | Carmen Lozano | Pacífico Centro Odontológico |
| Admin. organización | valeria.nunez@horizonte.edu.pe | Valeria Núñez | Instituto Horizonte |
| Técnico (**inactivo**) | diego.campos@horizonte.edu.pe | Diego Campos | Instituto Horizonte |
| Solicitante | andres.soto@horizonte.edu.pe | Andrés Soto | Instituto Horizonte |

## Comandos útiles

```powershell
docker compose up -d                                   # levantar
docker compose ps                                      # estado
docker compose exec -T app php artisan migrate:fresh --seed
docker compose exec -T app php artisan test
docker compose exec -T app php artisan route:list --except-vendor
docker compose exec -T -u root app ./vendor/bin/pint   # formato PSR-12
docker compose exec -T -u root app sh -c "composer require paquete/nombre && chown -R www-data:www-data vendor"
docker compose exec -T app php artisan contratos:actualizar-estados
docker compose exec -T -u root app chmod -R a+rwX storage bootstrap/cache   # si aparece error de permisos
docker compose exec -T pgsql psql -U servicecloud -d servicecloud_ai
docker compose logs app --tail=100
```

- App: http://localhost:8000 · pgAdmin: http://localhost:5050 (`admin@servicecloud.ai` / `admin`)

Capturas de pantalla para comparar con el mockup (Chrome headless en Docker; las imágenes quedan en `capturas/`):

```bash
docker run --rm --network servicecloudai_default -v "$PWD:/work:ro" -v "$PWD/capturas:/out" \
  -v "$PWD/docs/pruebas/captura-ui.js:/usr/src/app/captura.js:ro" zenika/alpine-chrome:with-puppeteer \
  node captura.js admin@servicecloud.ai /,/tickets 1440 dashboard,tickets
```

## Pendiente

- Fase 4 en adelante.
- Logs técnicos y análisis de logs de ejemplo: se siembran en la fase 5 junto con el analizador.
- Indexación RAG (`documentos_rag`, `fragmentos_rag`): fase 5 (`php artisan ia:indexar`).
- Fase 7: confirmar con el equipo en qué nubes se desplegará.
