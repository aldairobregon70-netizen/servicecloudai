---
description: Construye ServiceCloud AI de forma autónoma ejecutando las fases 0 a 7 en orden, con verificación y commit al final de cada una
---

Actúa como el agente `arquitecto` y delega en los demás agentes.

1. Lee `AGENTS.md`, las reglas, las skills y `docs/PLAN.md` (si existe, continúa desde la primera fase no marcada).
2. Ejecuta en orden: `/fase-0-entorno`, `/fase-1-base-datos`, `/fase-2-base-app`, `/fase-3-maestros`, `/fase-4-operacion`, `/fase-5-ia`, `/fase-6-dashboard-reportes`, `/fase-7-cierre-nube`.
3. Al final de cada fase: `/verificar`, actualizar `docs/PLAN.md`, commit, y un resumen de 5 líneas para el desarrollador (qué se hizo, cómo probarlo, qué decisiones se tomaron).
4. Detente y pregunta solo si: falta una credencial (contraseña de PostgreSQL, clave de IA), un comando requiere instalar software en el sistema operativo, o una decisión cambiaría el esquema SQL. En cualquier otro caso decide lo más simple coherente con el mockup, anótalo en "Decisiones" y continúa.
5. No pases a la siguiente fase con tests en rojo.
