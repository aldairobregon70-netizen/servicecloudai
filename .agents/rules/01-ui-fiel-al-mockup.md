---
trigger: always_on
---

# Regla: la interfaz debe ser idéntica al mockup

Referencia única: `docs/mockup/index.html`. Antes de crear o modificar cualquier vista, abre ese archivo y busca la función `render…` del módulo correspondiente.

## Cómo portar el mockup a Blade

1. **CSS:** copia el bloque `<style>` del mockup **tal cual** a `public/css/servicecloud.css`. No renombres clases ni cambies variables `:root`. Si necesitas estilos nuevos (paginación, errores de validación, login), agrégalos al final del archivo bajo el comentario `/* === Extensiones ServiceCloud === */` usando las mismas variables de color.
2. **Layout** `resources/views/layouts/app.blade.php`: reproduce `.app-shell`, `.sidebar` (marca, etiqueta "Operación", menú, estado "AI Engine Online"), `.main-wrap`, `.header` (botón contraer, breadcrumb, búsqueda global, botón "Asistente IA", campana con dropdown, bloque de usuario con dropdown), `.content`, `.modal-backdrop` y `.toast-stack`.
3. **Menú:** mismas 18 entradas, mismos íconos Lucide y mismo orden que el arreglo `menu` del mockup. Cada entrada es un enlace real a su ruta (`route('tickets.index')`…); la clase `active` se pone con `request()->routeIs()`. Oculta entradas según el rol (por ejemplo, Organizaciones solo para ADMIN_SAAS).
4. **Componentes Blade** que replican las funciones JS del mockup:
   - `badge()` → `<x-badge :valor="$enum" />` (mismo mapa de colores: Crítica=red, Alta=yellow, Media=blue, Baja=gray, etc.).
   - `pageHeader()` → `<x-page-header titulo descripcion>` con slot de acciones.
   - `kpi()`, `chartCard()`, `activity()`, `metaBox()`, `timeline()`, `rag()` → componentes con el mismo HTML.
   - `toast()` → los mensajes `session('ok')` / `session('error')` se muestran como `.toast` al cargar la página.
5. **Comportamiento JS** en `public/js/servicecloud.js` (vanilla, sin frameworks): contraer/expandir sidebar (y drawer en móvil), dropdowns, cerrar con Escape, modales, toasts, filtros instantáneos de tabla, búsqueda global (consulta a `/buscar?q=` y pinta `.search-results`), drag & drop del kanban (envía `PATCH` con `fetch` + token CSRF), chat IA (envía `POST` y muestra el indicador `.typing`), gráficos Chart.js con las mismas opciones de color que `initChart()`.
6. **Íconos:** `<i data-lucide="nombre"></i>` y llamar `lucide.createIcons()` después de cada inserción dinámica.
7. **Responsive:** respeta los breakpoints 1250px, 1024px, 760px y 480px del CSS. Prueba en ancho móvil.

## Lo que cambia respecto al mockup (y solo esto)

- Datos reales desde PostgreSQL en vez de `localStorage` y del objeto `defaults`.
- Los CRUD genéricos usan formularios que envían al servidor (pueden seguir abriéndose dentro del `.modal`).
- Tablas largas con paginación del servidor (estilo acorde a `.toolbar`/`.btn-sm`).
- Validación: errores debajo de cada campo con color `var(--danger)`.
- Se agrega una pantalla de login con la misma identidad visual (fondo, `.card`, `.brand-mark`, `.btn-primary`).
- Exportar CSV se hace en el servidor (respuesta `text/csv` con BOM UTF-8), con el mismo nombre `servicecloud_<modulo>_<fecha>.csv`.
- Usuario del header, avatar e iniciales = usuario autenticado; "Cerrar sesión" funciona de verdad.

## Prohibido

- Tailwind, Bootstrap, plantillas de admin, starter kits o cualquier otro sistema de diseño.
- Cambiar la paleta, tipografía, radios o sombras.
- Textos en inglés en la interfaz, salvo los que ya están en inglés en el mockup ("AI Classification", "Knowledge Suggestion", "AI Log Analyzer", "ServiceCloud AI Insights").
- Dejar números fijos en KPIs o gráficos: todo sale de consultas.

## Verificación visual

Al terminar una vista, ábrela en el navegador integrado junto a `docs/mockup/index.html` (mismo módulo) y compara: espaciados, colores de badges, íconos, columnas de tabla y acciones. Corrige diferencias antes de continuar.
