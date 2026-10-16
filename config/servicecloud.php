<?php

use App\Enums\Rol;

$todos = array_column(Rol::cases(), 'value');
$gestion = [Rol::AdminSaas->value, Rol::AdminOrganizacion->value, Rol::Supervisor->value];
$operacion = [...$gestion, Rol::Tecnico->value];

return [

    /*
    |--------------------------------------------------------------------------
    | Menú lateral (mismo orden e íconos Lucide que el arreglo `menu` del mockup)
    |--------------------------------------------------------------------------
    | clave => ruta, ícono, etiqueta, roles que lo ven, y textos del pageHeader.
    */
    'menu' => [
        'dashboard' => [
            'ruta' => 'dashboard', 'icono' => 'layout-dashboard', 'etiqueta' => 'Dashboard', 'roles' => $todos,
            'titulo' => 'Dashboard',
            'descripcion' => 'Visión ejecutiva del servicio, salud de SLA y señales detectadas por ServiceCloud AI.',
        ],
        'organizaciones' => [
            'ruta' => 'organizaciones.index', 'icono' => 'building-2', 'etiqueta' => 'Organizaciones',
            'roles' => [Rol::AdminSaas->value, Rol::AdminOrganizacion->value],
            'titulo' => 'Organizaciones',
            'descripcion' => 'Gestiona clientes SaaS, planes, usuarios y servicios asociados.',
        ],
        'usuarios' => [
            'ruta' => 'usuarios.index', 'icono' => 'users', 'etiqueta' => 'Usuarios', 'roles' => $gestion,
            'titulo' => 'Usuarios',
            'descripcion' => 'Administra usuarios, roles, acceso y estado dentro de cada organización.',
        ],
        'activos' => [
            'ruta' => 'activos.index', 'icono' => 'monitor-cog', 'etiqueta' => 'Activos', 'roles' => $operacion,
            'titulo' => 'Activos',
            'descripcion' => 'Inventario centralizado de infraestructura, endpoints y recursos cloud.',
        ],
        'servicios' => [
            'ruta' => 'servicios.index', 'icono' => 'server-cog', 'etiqueta' => 'Servicios', 'roles' => $operacion,
            'titulo' => 'Servicios',
            'descripcion' => 'Catálogo de servicios TI con disponibilidad, responsables y SLA.',
        ],
        'contratos' => [
            'ruta' => 'contratos.index', 'icono' => 'file-signature', 'etiqueta' => 'Contratos', 'roles' => $operacion,
            'titulo' => 'Contratos',
            'descripcion' => 'Contratos vigentes, cobertura de soporte y acuerdos comerciales.',
        ],
        'tickets' => [
            'ruta' => 'tickets.index', 'icono' => 'ticket-check', 'etiqueta' => 'Tickets', 'roles' => $todos,
            'titulo' => 'Tickets',
            'descripcion' => 'Gestión centralizada de tickets, prioridades, SLA y asignaciones técnicas.',
        ],
        'incidencias' => [
            'ruta' => 'incidencias.index', 'icono' => 'triangle-alert', 'etiqueta' => 'Incidencias', 'roles' => $operacion,
            'titulo' => 'Incidencias',
            'descripcion' => 'Control de incidentes operativos por impacto, urgencia, prioridad y servicio afectado.',
        ],
        'problemas' => [
            'ruta' => 'problemas.index', 'icono' => 'bug', 'etiqueta' => 'Problemas', 'roles' => $operacion,
            'titulo' => 'Problemas',
            'descripcion' => 'Gestión de causa raíz, errores conocidos y workarounds.',
        ],
        'solicitudes' => [
            'ruta' => 'solicitudes.index', 'icono' => 'clipboard-list', 'etiqueta' => 'Solicitudes', 'roles' => $todos,
            'titulo' => 'Solicitudes',
            'descripcion' => 'Solicitudes de servicio, accesos, software y requerimientos de usuarios.',
        ],
        'sla' => [
            'ruta' => 'sla.index', 'icono' => 'timer', 'etiqueta' => 'SLA', 'roles' => $gestion,
            'titulo' => 'Acuerdos de nivel de servicio (SLA)',
            'descripcion' => 'Políticas de respuesta y resolución por nivel de prioridad, con seguimiento de cumplimiento.',
        ],
        'asignaciones' => [
            'ruta' => 'asignaciones.index', 'icono' => 'columns-3', 'etiqueta' => 'Asignaciones', 'roles' => $operacion,
            'titulo' => 'Asignaciones',
            'descripcion' => 'Tablero Kanban para distribuir y mover trabajo entre técnicos y estados.',
        ],
        'conocimiento' => [
            'ruta' => 'conocimiento.index', 'icono' => 'book-open-check', 'etiqueta' => 'Base de conocimiento', 'roles' => $todos,
            'titulo' => 'Base de conocimiento',
            'descripcion' => 'Artículos técnicos indexados para búsqueda semántica y sugerencias RAG.',
        ],
        'asistente' => [
            'ruta' => 'asistente.index', 'icono' => 'bot', 'etiqueta' => 'Asistente IA', 'roles' => $todos,
            'titulo' => 'ServiceCloud AI Assistant',
            'descripcion' => 'Asistente empresarial con contexto ITSM, clasificación NLP y recuperación RAG.',
        ],
        'logs' => [
            'ruta' => 'logs.index', 'icono' => 'scroll-text', 'etiqueta' => 'Análisis de Logs', 'roles' => $operacion,
            'titulo' => 'AI Log Analyzer',
            'descripcion' => 'Pega logs técnicos para detectar errores, patrones, severidad y recomendaciones asistidas por IA.',
        ],
        'notificaciones' => [
            'ruta' => 'notificaciones.index', 'icono' => 'bell-ring', 'etiqueta' => 'Notificaciones', 'roles' => $todos,
            'titulo' => 'Notificaciones',
            'descripcion' => 'Centro de alertas de SLA, asignaciones, IA y cambios operativos.',
        ],
        'reportes' => [
            'ruta' => 'reportes.index', 'icono' => 'chart-no-axes-combined', 'etiqueta' => 'Reportes', 'roles' => $gestion,
            'titulo' => 'Reportes',
            'descripcion' => 'Genera reportes operativos y ejecutivos con información de ServiceCloud AI.',
        ],
        'configuracion' => [
            'ruta' => 'configuracion.index', 'icono' => 'settings', 'etiqueta' => 'Configuración', 'roles' => $todos,
            'titulo' => 'Configuración',
            'descripcion' => 'Preferencias de automatización, IA, notificaciones y seguridad del entorno SaaS.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Inteligencia artificial
    |--------------------------------------------------------------------------
    | driver: simulado (reglas locales, sin internet) | gemini (API REST).
    */
    'ia' => [
        'driver' => env('AI_DRIVER', 'simulado'),
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'modelo_texto' => env('GEMINI_MODELO_TEXTO'),
            'modelo_embedding' => env('GEMINI_MODELO_EMBEDDING'),
            'url_base' => env('GEMINI_URL_BASE', 'https://generativelanguage.googleapis.com/v1beta'),
            'timeout' => (int) env('GEMINI_TIMEOUT', 20),
        ],
    ],

    'paginacion' => 15,

    /*
    | API JSON (pruebas de rendimiento): límites por minuto. Súbelos solo para las pruebas de carga.
    */
    'api' => [
        'limite_por_minuto' => (int) env('API_RATE_LIMIT', 600),
        'limite_login_por_minuto' => (int) env('API_LOGIN_RATE_LIMIT', 30),
    ],

    /*
    | Disco para adjuntos de tickets: local (storage/app/private) o s3 (compatible: AWS S3, MinIO,
    | GCS en modo interoperable, Azure con gateway S3). En varias instancias usar s3.
    */
    'adjuntos_disco' => env('ADJUNTOS_DISK', 'local'),
];
