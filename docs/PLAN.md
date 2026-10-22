# ServiceCloud AI — Plan de implementación

Plan vivo. Se actualiza al cerrar cada fase (Hecho / Pendiente / Decisiones).

## Fases

- [x] **Fase 0 — Entorno:** contenedores, `.env` y `phpunit.xml` para PostgreSQL, este plan.
- [x] **Fase 1 — Base de datos:** migración del esquema SQL, enums, scope multi-tenant, modelos, seeders demo.
- [x] **Fase 2 — Base de la app:** CSS del mockup, layout, login, componentes Blade, menú por roles, JS base.
- [x] **Fase 3 — Maestros:** Organizaciones, Usuarios, Activos, Servicios, Contratos (CRUD completo).
- [x] **Fase 4 — Operación:** Tickets + SLA, Asignaciones (kanban), Incidencias, Problemas, Solicitudes, SLA, notificaciones, scheduler.
- [x] **Fase 5 — IA:** drivers simulado/Gemini, base de conocimiento + RAG, clasificación, asistente, análisis de logs.
- [x] **Fase 6 — Dashboard y reportes:** KPIs y gráficos reales, notificaciones, reportes CSV, configuración, búsqueda global, auditoría.
- [x] **Fase 7 — Cierre y nube:** QA, rendimiento, imagen de producción, API, k6, documentación de despliegue.

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

### Fase 4 (16/10/2026)

- `SlaService` completo: política (servicio → contrato → defecto de la organización → primera activa), vencimientos 24x7/laboral, pausa en "En espera" (minutos pausados se suman al vencimiento), cumplimiento de respuesta/resolución en `tickets_sla`, texto de "SLA restante".
- `TicketService`: crear (código `TKT-AAAA-00001` correlativo por año, prioridad por matriz, SLA, historial, asignación), actualizar (historial con nombres legibles), cambiar prioridad (recalcula SLA), asignar/reasignar, cambiar estado con transiciones válidas (`EstadoTicket::transiciones()`), resolución con solución obligatoria, comentarios (primera respuesta), adjuntos privados en `storage/app/private/tickets/{id}`.
- `NotificacionService` (respeta `preferencias_notificaciones.in_app_activo`): asignación, cambio de estado al solicitante, comentarios, solicitudes, alertas SLA.
- **Tickets**: listado con filtros y columna SLA (rojo si crítico/en riesgo/vencido, "Cumplido" verde), CSV, alta en modal (adjunto opcional), detalle idéntico a `renderTicketDetail` (6 meta-boxes, cambiar estado, agregar nota interna/pública con adjunto, actividad unificada, panel AI Classification con datos de `clasificaciones_ia`/`recomendaciones_ia` o "Pendiente de análisis", "Aplicar recomendación" → nota interna con los pasos).
- **Asignaciones**: kanban de 5 columnas con drag & drop (`PATCH /asignaciones/{ticket}`), "Resuelto" pide la solución en un modal, técnico toma tickets sin asignar, "Autoasignar con IA" por menor carga activa.
- **SLA**: 4 tarjetas con tiempos de la política seleccionada y cumplimiento real (30 días), gráfico, tabla de políticas con CRUD en modal (4 reglas + calendario); plantillas globales solo para ADMIN_SAAS; en uso → se desactiva.
- **Incidencias** (prioridad por matriz, vínculo único a ticket, problema, responsable; técnico actualiza las suyas), **Problemas** (causa raíz, workaround, tickets relacionados), **Solicitudes** (aprobar/rechazar, iniciar/completar, cancelar; solicitante ve las suyas).
- Comando `sla:revisar` (cada 5 min, sin duplicar avisos) y servicio `scheduler` en `compose.yaml`.
- Tests: `TicketsTest`, `AsignacionesSlaTest`, `IncidenciasProblemasSolicitudesTest`. 98 tests en verde. Flujo del ticket probado en Chrome headless (alta en modal, nota, asignación, cambio de estado, error de solución en línea).

### Fase 5 (16/10/2026)

- Capa `app/Services/AI`: interfaz `ProveedorIA`, `SolicitudIA` (prompt de texto + datos estructurados solo para el motor local), `RespuestaIA`, `GestorIA` (elige driver por `AI_DRIVER`, cae al simulado si Gemini falla y avisa con toast), drivers `Simulado` (`Nlp/MotorReglas` + `Nlp/Texto`: palabras clave ponderadas, matriz impacto × urgencia ajustada por severidad, embeddings de bolsa de palabras de 384 dimensiones) y `Gemini` (REST `generateContent` / `batchEmbedContents`, clave en cabecera, correos y teléfonos enmascarados).
- `ClasificadorTickets`: al crear un ticket (si `clasificacion_automatica`) guarda `clasificaciones_ia` + `recomendaciones_ia` con 3 fuentes RAG; si `prioridad_automatica` aplica prioridad y categoría. Botones "Analizar/Reanalizar con IA", "Aceptar"/"Rechazar" y "Aplicar recomendación" en el detalle.
- `IndexadorConocimiento` (fragmentos de ~800 caracteres con solapamiento de 150, `embedding_json`, `token_count`) + comando `php artisan ia:indexar [--forzar]`; se indexa al publicar y se desindexa al archivar/eliminar.
- `RecomendadorRag`: full-text PostgreSQL (OR de términos, índice GIN) + coseno sobre fragmentos del mismo modelo de embedding; respeta organización y visibilidad.
- **Base de conocimiento** idéntica a `renderKnowledge` (chips, búsqueda, "Buscar con IA", tarjetas), detalle con contador de vistas y estado de indexación, CRUD en modal con etiquetas; técnicos crean borradores.
- **Asistente IA** idéntico a `renderAssistant`: conversación persistida, acciones rápidas, reconoce `TKT-…` (solo tickets visibles), preguntas de SLA, RAG; chips con fragmentos reales; Markdown escapado.
- **Análisis de Logs** idéntico a `renderLogs` con el mismo ejemplo: parser `fecha NIVEL [origen] mensaje`, patrones (timeout, pool > 80 %, latencia > 3000 ms, cola, reintentos, caché, memoria), guarda `logs_tecnicos` + `analisis_logs_ia` + detalle, "Crear ticket desde análisis" prellenado y vinculado.
- Rate limit `throttle:ia` (20/min por usuario) en asistente, análisis de logs y reanálisis.
- Seeder: indexa la base, genera la IA de TKT-2026-00452 con el pipeline real y siembra 2 análisis de logs.
- Tests deterministas `IaTest` (21): caso de ejemplo de punta a punta, RAG, logs, fallback de Gemini con `Http::fake`, anonimización, asistente y aislamiento, conocimiento. 119 tests en verde. Evidencia en `docs/evidencias/`.

### Fase 6 (16/10/2026)

- **Dashboard**: datos reales (desde la fase 2) con caché de 60 s por alcance: `dashboard:global` (Admin SaaS), `dashboard:org:{id}` (admin de organización y supervisor), `dashboard:usuario:{id}` (técnico y solicitante).
- **Notificaciones**: centro idéntico a `renderNotifications` con filtro "Solo no leídas", paginación, "Marcar todo como leído"; abrir una notificación la marca leída y lleva al ticket; punto rojo de la campana solo con no leídas.
- **Reportes**: las 6 tarjetas del mockup descargan CSV reales (`ReporteService`): mesa de ayuda, cumplimiento SLA, efectividad de IA, disponibilidad de servicios (estimada por incidencias alta/crítica), productividad técnica e incidencias críticas; filtro de período (por defecto 30 días, máx. 1 año); respetan organización y rol.
- **Configuración**: interruptores que guardan `configuracion_organizacion` (clasificación automática, priorización, RAG, política SLA por defecto) y `preferencias_notificaciones` (correo, push, en la app); ADMIN_SAAS elige la organización y ve los planes SaaS.
- **Auditoría**: `AuditoriaObserver` en organizaciones, usuarios, contratos, políticas/reglas SLA y tickets (crear, actualizar, eliminar; antes/después; IP; nunca el hash de la contraseña) y vista con filtros en Configuración → Auditoría (ADMIN_SAAS y ADMIN_ORGANIZACION).
- Búsqueda global (desde la fase 2): tickets, usuarios y activos, máx. 8, respeta tenant y rol.
- Tests: `DashboardReportesConfiguracionTest` (16). 135 tests en verde.

### Fase 7 (16/10/2026)

- **QA**: 119 rutas con nombre y bajo autenticación (salvo login, token y `/up`); único `{!! !!}` justificado (Markdown escapado del asistente); sin secretos en el código; CSRF en todos los `fetch`; `AislamientoQaTest` (NovaTech no ve un ticket de AndesMarket por URL, búsqueda, CSV, otros módulos, API ni asistente); `RendimientoTest` (listados ≤ 15 consultas, sin N+1).
- **Rendimiento BD**: `EXPLAIN` confirma que se usan `idx_tickets_org_estado`, `idx_tickets_tecnico` y el GIN `idx_articulos_busqueda_fts` (con los datos demo PostgreSQL prefiere un recorrido secuencial, correcto para tablas pequeñas).
- **Error de concurrencia corregido** (encontrado con k6): códigos correlativos duplicados (`TKT-AAAA-#####`, etc.) al crear en paralelo → bloqueo asesor `pg_advisory_xact_lock` dentro de la transacción del alta (`Codigos::bloquear()`).
- **Imagen de producción**: stage `production` con valores por defecto 12-factor (`APP_ENV=production`, `LOG_CHANNEL=stderr`, sesión/caché/cola en BD); probada con `docker run` contra una base vacía: migra sola, cachea config/rutas/vistas, siembra con `db:seed --force`, responde `/up` y todas las pantallas (40–90 ms).
- **Configuración por entorno**: `TRUSTED_PROXIES` (balanceadores), `ADJUNTOS_DISK` (local o S3 compatible; se agregó `league/flysystem-aws-s3-v3`), `API_RATE_LIMIT`, `API_LOGIN_RATE_LIMIT`, `SANCTUM_EXPIRATION`, `GEMINI_URL_BASE`, `GEMINI_TIMEOUT`; todo documentado en `.env.example`.
- **API JSON v1** (Laravel Sanctum): token, tickets (listar, ver, crear, clasificar), clasificación de texto sin persistir; `docs/API.md`. Tests `ApiTest`.
- **Prueba de carga** `docs/pruebas/carga.js` (k6 en Docker: login web, dashboard, tickets, API, creación, IA). Resultado local con la imagen de producción, 20 VUs, 30 s: 2.323 peticiones, 0 % errores, p95 web 72 ms, API 190 ms, IA 129 ms.
- **Documentación**: `README.md` en español, `docs/DESPLIEGUE.md` (pasos genéricos + notas AWS, Azure y Google Cloud), `docs/evidencias/`.
- 145 tests en verde.

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
- **Servicio `scheduler` en `compose.yaml`** (misma imagen y volúmenes que `app`, `schedule:work`, healthcheck `healthcheck-schedule`).
- **Tickets no se eliminan** (trazabilidad): se cancelan. La primera respuesta se registra al pasar a "En progreso"/"En espera"/"Resuelto" o con el primer comentario público del técnico.
- **Clasificación mostrada en el panel IA**: el esquema no tiene columna para "Incidente de rendimiento"; por convención `clasificaciones_ia.explicacion` empieza con `"<Clasificación>: <detalle>"` y el panel muestra el texto antes de los dos puntos.
- **Kanban**: "Resuelto" muestra los resueltos de los últimos 7 días; mover a "Asignado"/"En progreso" sin técnico solo lo permite el propio técnico (se lo autoasigna); el supervisor debe asignar primero o usar "Autoasignar con IA".
- **Solicitudes** no crean un ticket automáticamente al aprobarse; el técnico las atiende desde el propio módulo (iniciar/completar).
- La pantalla de SLA agrega (extensión) un selector de política y una tabla de políticas con CRUD, porque el mockup solo simulaba "Nueva política SLA".
- **Privacidad IA**: a un proveedor externo solo viaja texto técnico (título, descripción, categoría, SLA, fragmentos de logs y de artículos); nunca nombres, correos ni contraseñas. Los datos estructurados (`SolicitudIA::datos`) solo los usa el motor local.
- **Asistente del Admin SaaS sin historial**: la FK compuesta `conversaciones_ia(organizacion_id, usuario_id)` exige que el usuario pertenezca a la organización; el ADMIN_SAAS no tiene organización, así que su conversación no se persiste.
- **Relevancia RAG mostrada** = puntaje híbrido (65 % coseno + 35 % ts_rank) normalizado respecto del mejor resultado (0,55–0,99), para que sea legible como en el mockup.
- Si se cambia de proveedor de embeddings, ejecutar `php artisan ia:indexar --forzar` (solo se comparan vectores del mismo modelo; mientras tanto la búsqueda usa full-text).
- Eventos del analizador: el uso del pool se muestra como INFO (igual que el mockup) aunque supere el 80 %; el umbral sí eleva la severidad del análisis.
- **Configuración por rol**: ADMIN_ORGANIZACION configura la IA y el SLA por defecto de su organización; supervisor, técnico y solicitante solo sus preferencias de notificación (la matriz marcaba "—" para el supervisor, pero las preferencias personales aplican a todos).
- **"Tema oscuro SaaS"** se muestra fijo (activo y deshabilitado): no hay columna para persistirlo y la identidad oscura es la única del mockup. Se agrega "Notificaciones en la app" (`in_app_activo`), que sí existe en el esquema.
- **Auditoría solo con usuario autenticado**: seeders, comandos y el programador no generan registros.
- **Tabla `personal_access_tokens`** (Sanctum) agregada con una migración propia de Laravel, igual que `sessions`/`cache`/`jobs`: no modifica las 45 tablas del esquema.
- **Cambio en `Dockerfile`** (stage `production`): variables por defecto para la nube. No cambia el comportamiento en desarrollo.
- **Despliegue**: `docs/DESPLIEGUE.md` trae notas para AWS, Azure y Google Cloud porque el plan no indicaba qué nubes usará el equipo; ajustar cuando se definan.
- **Despliegue en VM (GCP)**: `compose.prod.yaml` usa la imagen `production`, no publica PostgreSQL ni incluye pgAdmin, persiste adjuntos en el volumen `storage` y toma secretos de un `.env` que solo existe en el servidor (plantilla `.env.produccion.example`). Probado localmente: arranque, migraciones, siembra, login y escritura de adjuntos.
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
docker compose exec -T app php artisan sla:revisar
docker compose exec -T app php artisan ia:indexar --forzar          # reindexar la base de conocimiento
docker compose up -d scheduler                          # programador (sla:revisar cada 5 min)
docker build --target production -t servicecloudai:prod .   # imagen para la nube (docs/DESPLIEGUE.md)
docker run --rm -i -e BASE_URL=http://host.docker.internal:8000 grafana/k6 run - < docs/pruebas/carga.js   # carga
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

- Confirmar con el equipo en qué nubes se desplegará y completar `docs/DESPLIEGUE.md` con los pasos exactos (registro, base administrada, secretos).
- Si se activa Gemini: definir `GEMINI_MODELO_TEXTO` / `GEMINI_MODELO_EMBEDDING`, cargar `GEMINI_API_KEY` y ejecutar `php artisan ia:indexar --forzar`.
- Ejecutar `docs/pruebas/carga.js` en cada nube con el mismo número de VUs y registrar resultados (p95, errores, req/s, tamaño de instancia y de base).
