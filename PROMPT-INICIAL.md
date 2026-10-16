# Prompt inicial para Antigravity

Copia el bloque de abajo en el panel del Agente (modo **Planning**) la primera vez que abras el proyecto. Después, para continuar en otra sesión, basta con escribir `/construir-todo` (retoma desde la primera fase sin marcar en `docs/PLAN.md`).

---

```
Vas a construir ServiceCloud AI, un sistema web completo de IT Service Management con IA, en este proyecto Laravel recién creado.

Antes de escribir código:
1. Lee AGENTS.md, todas las reglas de .agents/rules/ (empieza por 00-docker), las skills servicecloud-ui y servicecloud-schema, y revisa docs/mockup/index.html y database/sql/servicecloud_ai_schema.sql.
2. Resume en 10 líneas qué entendiste (stack, módulos, roles, esquema, IA, cómo debe verse) y genera un Implementation Plan por fases basado en los workflows /fase-0-entorno a /fase-7-cierre-nube.
3. Espera mi aprobación del plan.

Cuando lo apruebe, ejecuta /construir-todo actuando como el agente "arquitecto" y delegando en db-laravel, backend, frontend-ui, ia-rag y qa.

Condiciones:
- La interfaz debe quedar idéntica a docs/mockup/index.html (mismo CSS, estructura, íconos, textos y comportamiento), pero con datos reales de PostgreSQL.
- El esquema SQL no se rediseña: se carga tal cual con una migración.
- Al terminar cada fase: /verificar, actualiza docs/PLAN.md, haz commit y dame un resumen corto con cómo probarlo.
- Solo detente para pedirme credenciales, instalar software del sistema o cambiar el esquema. En todo lo demás decide tú lo más simple y anótalo en "Decisiones".
- Estoy en Windows sin PHP local: todo corre en Docker (compose.yaml). Ejecuta cada comando de PHP, Composer o Artisan con "docker compose exec -T app ..." y la base con "docker compose exec -T pgsql ...". La app se ve en http://localhost:8000.
```

---

## Si prefieres ir fase por fase (recomendado la primera vez)

Escribe un workflow a la vez y revisa el resultado antes del siguiente:

1. `/fase-0-entorno`
2. `/fase-1-base-datos`
3. `/fase-2-base-app`
4. `/fase-3-maestros`
5. `/fase-4-operacion`
6. `/fase-5-ia`
7. `/fase-6-dashboard-reportes`
8. `/fase-7-cierre-nube`

En cualquier momento: `/verificar`, o `/crear-modulo Activos` (u otro módulo) para rehacer un módulo concreto.

## Prompts útiles para correcciones

- "Abre /tickets en el navegador y compáralo con la sección Tickets de docs/mockup/index.html. Lista todas las diferencias visuales y corrígelas."
- "Inicia sesión con el usuario técnico de Grupo NovaTech e intenta abrir un ticket de AndesMarket por URL, por la búsqueda global y por el CSV. Debe fallar en los tres casos; si no, corrígelo con un test."
- "Registra el ticket de ejemplo 'La aplicación demora demasiado y finalmente muestra error al consultar clientes' y muéstrame la clasificación, prioridad y artículos sugeridos por la IA."
