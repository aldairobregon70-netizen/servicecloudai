---
description: Fase 7 — revisión final, documentación e imagen Docker de producción lista para desplegar en varias nubes y hacer pruebas de rendimiento
---

1. Ejecuta el agente `qa` sobre todo el sistema con su checklist completo; corrige lo encontrado.
2. Rendimiento: revisa con `EXPLAIN ANALYZE` (vía `docker compose exec -T pgsql psql …`) los listados más pesados, elimina N+1.
3. Imagen de producción: construye `docker build --target production -t servicecloudai:prod .` y pruébala localmente con `docker run` apuntando a la base del compose (`--network` del proyecto, `DB_HOST=pgsql`, `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`). Debe arrancar, ejecutar migraciones (AUTORUN) y responder en `/up`. Revisa que `.dockerignore` no excluya nada necesario (sobre todo `database/sql/`).
4. Configuración 12-factor: todo por variables de entorno; `LOG_CHANNEL=stderr` en producción, `TRUSTED_PROXIES=*`, `SESSION_DRIVER=database`, almacenamiento de adjuntos configurable (`FILESYSTEM_DISK=local` o `s3` compatible). Documenta cada variable en `.env.example`.
5. Ejemplo de despliegue: `docs/DESPLIEGUE.md` con los pasos genéricos (subir imagen a un registro, base PostgreSQL administrada, variables, puerto 8080, comando de migración) y notas por nube según las que use el equipo (pregunta cuáles si no están en `docs/PLAN.md`).
6. API para pruebas de rendimiento: endpoints JSON autenticados con token (Sanctum) para listar/crear tickets y clasificar, documentados en `docs/API.md`.
7. Script de carga en `docs/pruebas/` (k6, ejecutable con `docker run grafana/k6`) que golpee login, listado de tickets, detalle, creación y clasificación IA.
8. `README.md` en español: requisitos (Docker Desktop), instalación, comandos frecuentes, usuarios demo, estructura, cómo activar Gemini, cómo construir la imagen de producción.
9. Actualiza `docs/PLAN.md` con el estado final. Commit: `docs: cierre e imagen de producción`.
