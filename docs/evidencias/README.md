# Evidencia — caso de ejemplo de IA

Capturas generadas con `docs/pruebas/evidencia-ia.js` (Chrome headless en Docker) sobre los datos demo y el driver `simulado`.

| Archivo | Qué muestra |
|---|---|
| `01_registro_ticket.png` | Lucía Vega (solicitante, AndesMarket) registra *"La aplicación demora demasiado y finalmente muestra error al consultar clientes"* con impacto y urgencia medios. |
| `02_ticket_clasificado_ia.png` | Detalle del ticket: **Incidente de rendimiento · Aplicaciones · prioridad Alta · confianza 95 %**, causas probables (consulta SQL lenta, saturación del pool de conexiones…) y artículos RAG (KB-001 Optimización de consultas SQL, KB-007, KB-002). La prioridad se aplicó sola porque la organización tiene la priorización automática activa. |
| `03_asistente_ia.png` | El asistente reconoce el código del ticket, da el diagnóstico, los pasos y cita los artículos con su relevancia. |
| `04_analisis_logs.png` | AI Log Analyzer con el ejemplo del mockup: timeout de base de datos, reintentos, latencia de 5230 ms, pool al 92 %, diagnóstico y recomendaciones. |
| `05_busqueda_rag.png` | Búsqueda semántica en la base de conocimiento con el mismo texto. |

Para regenerarlas (desde la raíz del proyecto, con los contenedores arriba):

```bash
docker run --rm --network servicecloudai_default -v "$PWD/docs/evidencias:/out" \
  -v "$PWD/docs/pruebas/evidencia-ia.js:/usr/src/app/evidencia.js:ro" zenika/alpine-chrome:with-puppeteer node evidencia.js
```
