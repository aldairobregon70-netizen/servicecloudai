---
description: Fase 2 — login, layout idéntico al mockup, componentes Blade, menú por roles, CSS y JS base
---

Agentes: `frontend-ui` (vistas) y `backend` (auth, rutas). Skill: `servicecloud-ui`. Regla: `01-ui-fiel-al-mockup`.

1. Copia el `<style>` del mockup a `public/css/servicecloud.css` sin cambios y agrega al final la sección de extensiones (login, paginación, errores de validación).
2. Crea `config/servicecloud.php` con el menú (18 entradas: clave, ruta, ícono Lucide, etiqueta, roles permitidos) en el mismo orden del mockup.
3. Crea `layouts/app.blade.php` (sidebar, header con búsqueda, botón Asistente IA, campana, perfil; contenido; modal; toasts) y `layouts/guest.blade.php` + `auth/login.blade.php` con la misma identidad visual.
4. Crea los componentes Blade listados en la regla 01.
5. Crea `public/js/servicecloud.js` con: sidebar colapsable/drawer, dropdowns, Escape, modal genérico (abrir con `data-modal-url` que carga un formulario por `fetch`), toasts, filtros de tabla, helper `scFetch()` con CSRF, `scInitChart()` con las opciones de `initChart()` del mockup, y `lucide.createIcons()`.
6. Implementa `LoginController` (login/logout, estado activo, `ultimo_acceso`, rate limit) y Policies/Gates según `.agents/rules/03-roles-y-seguridad.md`.
7. Crea rutas y controladores vacíos (vista con `page-header` y estado vacío) para los 18 módulos, para que todo el menú navegue.
8. Dashboard provisional con la estructura completa del mockup.
9. Abre el navegador, inicia sesión con `admin@servicecloud.ai` / `password` y compara el layout con `docs/mockup/index.html` en escritorio y móvil. Corrige diferencias.
10. Tests: login correcto/incorrecto, usuario inactivo no entra, invitado redirigido a login, SOLICITANTE recibe 403 en Organizaciones.
11. `/verificar`. Commit: `feat(ui): layout, login y navegación`.
