// Prueba de carga de ServiceCloud AI con k6 (https://k6.io).
// Golpea: login web, dashboard, listado y detalle de tickets (web) y la API JSON
// (listado, detalle, creación de tickets y clasificación IA).
//
// Uso (desde la raíz del proyecto, sin instalar nada: k6 corre en Docker):
//   docker run --rm -i -e BASE_URL=https://mi-app.ejemplo.com -e VUS=20 -e DURACION=2m \
//     grafana/k6 run - < docs/pruebas/carga.js
// Contra el entorno local de Docker Desktop:
//   docker run --rm -i --add-host=host.docker.internal:host-gateway -e BASE_URL=http://host.docker.internal:8000 grafana/k6 run - < docs/pruebas/carga.js
//
// Variables: BASE_URL, VUS (usuarios virtuales), DURACION, EMAIL, PASSWORD, CREAR (1 = crea tickets por API), TICKET (código para el detalle).
// IMPORTANTE: en el servidor sube API_RATE_LIMIT (p. ej. 100000) durante la prueba; por defecto la API limita a 600 req/min por usuario.

import http from 'k6/http';
import { check, group, sleep } from 'k6';

const BASE = (__ENV.BASE_URL || 'http://localhost:8000').replace(/\/$/, '');
const VUS = parseInt(__ENV.VUS || '10', 10);
const DURACION = __ENV.DURACION || '1m';
const EMAIL = __ENV.EMAIL || 'ana.torres@andesmarket.pe';
const PASSWORD = __ENV.PASSWORD || 'password';
const TICKET = __ENV.TICKET || 'TKT-2026-00452';
const CREAR = __ENV.CREAR === '1';

export const options = {
  scenarios: {
    web: { executor: 'constant-vus', vus: Math.max(1, Math.ceil(VUS / 2)), duration: DURACION, exec: 'web' },
    api: { executor: 'constant-vus', vus: Math.max(1, Math.floor(VUS / 2)), duration: DURACION, exec: 'api' },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{escenario:web}': ['p(95)<1500'],
    'http_req_duration{escenario:api}': ['p(95)<1000'],
    'http_req_duration{nombre:ia_clasificar}': ['p(95)<2000'],
  },
};

// Token Bearer compartido por los VUs de la API
export function setup() {
  const r = http.post(`${BASE}/api/v1/token`, JSON.stringify({ email: EMAIL, password: PASSWORD, dispositivo: 'k6' }),
    { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } });
  check(r, { 'token emitido': (res) => res.status === 201 });
  return { token: r.json('token') };
}

// ------------------------------------------------------------------ web
let sesionIniciada = false;

export function web() {
  const tags = { escenario: 'web' };
  if (!sesionIniciada) {
    group('login web', () => {
      const pagina = http.get(`${BASE}/login`, { tags: { ...tags, nombre: 'login_get' } });
      const token = (pagina.body.match(/name="_token" value="([^"]+)"/) || [])[1];
      const r = http.post(`${BASE}/login`, { _token: token, email: EMAIL, password: PASSWORD }, { tags: { ...tags, nombre: 'login_post' }, redirects: 0 });
      sesionIniciada = check(r, { 'login 302': (res) => res.status === 302 });
    });
  }
  group('navegación', () => {
    check(http.get(`${BASE}/`, { tags: { ...tags, nombre: 'dashboard' } }), { 'dashboard 200': (r) => r.status === 200 });
    check(http.get(`${BASE}/tickets`, { tags: { ...tags, nombre: 'tickets' } }), { 'tickets 200': (r) => r.status === 200 });
    check(http.get(`${BASE}/tickets/${TICKET}`, { tags: { ...tags, nombre: 'ticket_detalle' } }), { 'detalle 200': (r) => r.status === 200 });
  });
  sleep(1);
}

// ------------------------------------------------------------------ API
export function api(datos) {
  const tags = { escenario: 'api' };
  const headers = { Authorization: `Bearer ${datos.token}`, Accept: 'application/json', 'Content-Type': 'application/json' };

  check(http.get(`${BASE}/api/v1/tickets?por_pagina=15`, { headers, tags: { ...tags, nombre: 'api_tickets' } }), { 'listado 200': (r) => r.status === 200 });
  check(http.get(`${BASE}/api/v1/tickets/${TICKET}`, { headers, tags: { ...tags, nombre: 'api_ticket' } }), { 'detalle 200': (r) => r.status === 200 });
  check(http.post(`${BASE}/api/v1/ia/clasificar`, JSON.stringify({
    titulo: 'La aplicación demora demasiado y finalmente muestra error al consultar clientes',
    descripcion: 'Prueba de carga del motor de clasificación y RAG.',
  }), { headers, tags: { ...tags, nombre: 'ia_clasificar' } }), { 'clasificación 200': (r) => r.status === 200 && r.json('categoria') !== null });

  if (CREAR) {
    check(http.post(`${BASE}/api/v1/tickets`, JSON.stringify({
      titulo: `Prueba de carga ${__VU}-${__ITER}`, descripcion: 'Ticket generado por k6.', tipo: 'consulta', impacto: 'bajo', urgencia: 'baja',
    }), { headers, tags: { ...tags, nombre: 'api_crear' } }), { 'creación 201': (r) => r.status === 201 });
  }
  sleep(1);
}
