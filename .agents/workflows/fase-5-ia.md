---
description: Fase 5 — IA: clasificación y priorización de tickets, RAG sobre la base de conocimiento, Asistente IA y Análisis de Logs
---

Agente principal: `ia-rag`; vistas con `frontend-ui`.

1. Crea `app/Services/AI` con la interfaz y los drivers `Simulado` y `Gemini`, seleccionados por `AI_DRIVER` en `config/servicecloud.php`. Con `AI_DRIVER=simulado` todo debe funcionar sin internet.
2. Base de conocimiento: módulo idéntico a `renderKnowledge` (chips de categoría, búsqueda full-text, tarjetas, detalle con contenido completo y contador de vistas, crear/editar con estado y visibilidad, etiquetas). Al publicar → indexar (`documentos_rag`, `fragmentos_rag`). Comando `php artisan ia:indexar`. "Buscar con IA" usa el recomendador.
3. Clasificación: al crear un ticket (si `clasificacion_automatica` está activa) → `clasificaciones_ia`; si `prioridad_automatica` está activa aplica la prioridad sugerida; botón para aceptar/rechazar. Panel "AI Classification" del detalle con datos reales (clasificación, categoría, prioridad, barra de confianza, posibles causas, 3 artículos sugeridos con % de relevancia, "Aplicar recomendación" agrega los pasos como nota interna, "Preguntar a la IA" abre el asistente con el ticket precargado).
4. Asistente IA: pantalla idéntica a `renderAssistant`; conversación persistida; acciones rápidas; reconoce códigos `TKT-…` y consultas de SLA; chips de estado con número real de fragmentos indexados.
5. Análisis de Logs: pantalla idéntica a `renderLogs` con el mismo ejemplo precargado; resultado con eventos por nivel, diagnóstico, recomendaciones y "Crear ticket desde análisis" (prellenado).
6. Prueba de punta a punta el caso de ejemplo de `AGENTS.md` §1 y deja evidencia (captura) en `docs/evidencias/`.
7. Tests deterministas con el driver simulado: clasificación del caso de ejemplo, RAG devuelve el artículo de conexiones/SQL primero, analizador detecta timeout y pool > 80 %, fallback a simulado si Gemini falla (usa `Http::fake`).
8. `/verificar`. Commit: `feat(ia): clasificación, RAG, asistente y logs`.
