---
description: Crea un módulo CRUD completo (modelo existente → controlador, requests, policy, vistas idénticas al mockup, exportación CSV y tests). Indica el nombre del módulo al invocarlo.
---

Entrada: nombre del módulo (por ejemplo, "Activos"). Si no se indicó, pregunta cuál.

1. Ubica en `docs/mockup/index.html` su entrada en `moduleMeta` (o su función `render…`) y anota columnas, campos del formulario, filtros y textos del `pageHeader`.
2. Revisa en la skill `servicecloud-schema` la tabla, sus valores permitidos y relaciones. Las columnas del mockup que no existen en la BD se calculan (conteos, relaciones) o se reemplazan por la columna equivalente.
3. Backend: rutas `Route::resource`, controlador con index (búsqueda, filtro por estado, paginación, `with()`), store, update, destroy (o desactivar si hay FK RESTRICT), `exportar` (CSV). FormRequests `Guardar…Request`/`Actualizar…Request`. Policy registrada.
4. Vistas: `index` con `page-header`, `toolbar` (búsqueda, filtro de estado, Exportar) y tabla con las mismas columnas y acciones (ver, editar, eliminar) que `genericRow()`; formularios en `<x-modal>` con `form-grid`; vista de detalle con `meta-box`.
5. Mensajes de éxito/error como toast. Confirmación de eliminación con el mismo texto del mockup adaptado ("¿Deseas eliminar …?").
6. Tests Feature: listar, crear, validar error, editar, eliminar/desactivar, otro tenant no accede, rol sin permiso recibe 403, CSV descarga.
7. Revisión visual en el navegador contra el mockup.
8. Commit: `feat(<modulo>): CRUD completo`.
