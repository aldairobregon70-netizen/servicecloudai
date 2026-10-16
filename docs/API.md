# ServiceCloud AI — API JSON v1

API pensada para integraciones y para las **pruebas de rendimiento en cada nube**. Aplica las mismas reglas que la interfaz web: aislamiento por organización, permisos por rol, validación y SLA.

- **Base:** `{APP_URL}/api/v1`
- **Formato:** JSON (`Accept: application/json`). Fechas en ISO 8601 (zona `America/Lima`).
- **Autenticación:** token Bearer de Laravel Sanctum (`Authorization: Bearer <token>`). Vida por defecto: 480 min (`SANCTUM_EXPIRATION`).
- **Límites:** `API_RATE_LIMIT` peticiones/min por usuario (600 por defecto) y `API_LOGIN_RATE_LIMIT` emisiones de token/min por IP (30). Respuesta `429` al superarlos.
- **Errores:** `401` sin token o token vencido · `403` sin permiso · `404` no existe **o pertenece a otra organización** · `422` validación (`{"message": "...", "errors": {"campo": ["..."]}}`).

## Autenticación

### `POST /token`

```json
{ "email": "ana.torres@andesmarket.pe", "password": "password", "dispositivo": "k6" }
```

`201`:

```json
{
  "token": "1|TlCie2Cy...",
  "tipo": "Bearer",
  "expira_en": "2026-10-17T00:46:07-05:00",
  "usuario": { "nombre": "Ana Torres", "rol": "SUPERVISOR", "organizacion": "AndesMarket S.A.C." }
}
```

Usuarios inactivos o de organizaciones suspendidas reciben `422`.

### `DELETE /token`

Revoca el token usado en la petición.

### `GET /yo`

Datos del usuario autenticado (nombre, correo, rol, organización).

## Tickets

### `GET /tickets`

Lista paginada según el rol (gestión: toda la organización; técnico: asignados y propios; solicitante: los suyos; Admin SaaS: todas).

Parámetros: `estado` (`abierto|asignado|en_progreso|en_espera|resuelto|cerrado|cancelado`), `prioridad` (`baja|media|alta|critica`), `q` (código o título), `por_pagina` (1–100, por defecto 15), `page`.

```json
{
  "data": [{
    "codigo": "TKT-2026-00452",
    "titulo": "La aplicación demora demasiado y finalmente muestra error al consultar clientes.",
    "tipo": "incidente", "categoria": "Aplicaciones", "prioridad": "alta", "impacto": "alto", "urgencia": "media",
    "estado": "en_progreso", "origen": "portal",
    "organizacion": "AndesMarket S.A.C.", "tecnico": "María Peña", "servicio": "ERP Corporativo",
    "sla": { "vence_respuesta": "...", "vence_resolucion": "...", "restante": "01h 12m" },
    "clasificacion_ia": null,
    "creado_en": "...", "actualizado_en": "..."
  }],
  "links": { "first": "...", "next": "..." },
  "meta": { "current_page": 1, "per_page": 15, "total": 50 }
}
```

### `GET /tickets/{codigo}`

Detalle con descripción y la última clasificación IA (`clasificacion_ia`: clasificación, categoría, prioridad, confianza, aceptada).

### `POST /tickets`

Crea un ticket (origen `api` salvo que se indique). Calcula prioridad por impacto × urgencia, SLA y clasificación IA automática (si la organización la tiene activa).

```json
{
  "titulo": "La aplicación demora demasiado y finalmente muestra error al consultar clientes",
  "descripcion": "Pasa desde esta mañana en el módulo de clientes.",
  "tipo": "incidente",
  "impacto": "medio",
  "urgencia": "media",
  "servicio_id": 1,
  "categoria_id": null,
  "activo_id": null
}
```

Campos solo para supervisión/administración: `prioridad`, `tecnico_id`, `solicitante_id`, `origen`. El Admin SaaS debe enviar `organizacion_id`. Devuelve `201` con el ticket (incluida `clasificacion_ia`).

### `POST /tickets/{codigo}/clasificar`

(Re)clasifica con IA un ticket que el usuario puede editar.

```json
{
  "ticket": "TKT-2026-00452",
  "clasificacion": "Incidente de rendimiento",
  "categoria": "Aplicaciones",
  "prioridad": "alta",
  "confianza": 0.95,
  "causas": ["consulta SQL lenta", "saturación del pool de conexiones", "..."],
  "articulos": [{ "codigo": "KB-001", "titulo": "Optimización de consultas SQL", "relevancia": 0.99 }]
}
```

## IA

### `POST /ia/clasificar`

Clasifica un texto **sin crear ningún registro** (para medir el motor de IA y el RAG).

```json
{ "titulo": "VPN se desconecta al iniciar sesión remota", "descripcion": "Opcional", "impacto": "alto", "urgencia": "media" }
```

```json
{
  "clasificacion": "Incidente de disponibilidad", "categoria": "Redes", "prioridad": "alta", "confianza": 0.95,
  "causas": ["MTU o fragmentación en el túnel", "..."],
  "articulos": [{ "codigo": "KB-004", "titulo": "VPN: desconexiones y pérdida de paquetes", "relevancia": 0.99 }],
  "proveedor": "simulado",
  "duracion_ms": 61.3
}
```

## Ejemplo con curl

```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/token -H 'Accept: application/json' \
  -H 'Content-Type: application/json' -d '{"email":"ana.torres@andesmarket.pe","password":"password"}' | jq -r .token)

curl -s http://localhost:8000/api/v1/tickets?prioridad=alta -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```

## Salud

`GET /up` (fuera de `/api`): `200` si la aplicación arrancó. Úsalo como *health check* del balanceador.
