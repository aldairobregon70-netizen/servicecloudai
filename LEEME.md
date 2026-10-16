# ServiceCloud AI — Guía de configuración (Windows + Docker + Antigravity)

Sigue los pasos en orden. Al final tendrás el proyecto Laravel corriendo en Docker con PostgreSQL y pgAdmin, subido a GitHub, y Antigravity listo para construir el sistema con los agentes del kit.

**No necesitas instalar PHP, Composer ni PostgreSQL en Windows.** Todo vive en contenedores. XAMPP puede quedarse instalado para tus otros proyectos; no interfiere.

---

## Paso 1 — Preparar Windows (una sola vez)

### 1.1 Virtualización y WSL 2

1. Abre el Administrador de tareas → Rendimiento → CPU y verifica **Virtualización: Habilitado**. Si dice deshabilitado, actívala en la BIOS (Intel VT-x / AMD SVM).
2. Abre **PowerShell como administrador** y ejecuta:
   ```powershell
   wsl --install
   ```
   Reinicia la PC cuando termine. Luego:
   ```powershell
   wsl --update
   wsl --version
   ```
   Debe mostrar una versión de WSL (2.x).

### 1.2 Docker Desktop

1. Descarga Docker Desktop para Windows desde docker.com e instálalo dejando marcada la opción **Use WSL 2 instead of Hyper-V**.
2. Ábrelo y espera a que diga *Engine running*.
3. En PowerShell normal comprueba:
   ```powershell
   docker version
   docker compose version
   docker run --rm hello-world
   ```
   Si `hello-world` imprime "Hello from Docker!", está listo.

> Docker Desktop debe estar **abierto** cada vez que trabajes en el proyecto.

### 1.3 Git

Si no lo tienes, instala Git for Windows. Luego:
```powershell
git config --global user.name "Tu Nombre"
git config --global user.email "tu-correo@ejemplo.com"
git config --global core.autocrlf input
```
`core.autocrlf input` evita que Windows meta finales de línea CRLF en archivos que leerán los contenedores Linux.

### 1.4 Antigravity

Instálalo si aún no lo tienes. En su configuración de terminal deja **PowerShell** como terminal por defecto.

---

## Paso 2 — Crear el proyecto Laravel con Docker

Usa una carpeta corta, sin espacios y **fuera de OneDrive / Escritorio sincronizado**:

```powershell
mkdir C:\proyectos
cd C:\proyectos
docker run --rm -v "${PWD}:/app" -w /app composer:2 create-project laravel/laravel servicecloudai
```

Esto descarga la imagen oficial de Composer (que trae PHP actual) y crea `C:\proyectos\servicecloudai` con la última versión de Laravel. Tarda unos minutos la primera vez.

> Si falla por una extensión de PHP faltante, repite el comando agregando `--ignore-platform-reqs` al final. El contenedor del proyecto sí tendrá todas las extensiones.

---

## Paso 3 — Copiar el kit dentro del proyecto

Descomprime el zip y copia **todo el contenido** de la carpeta `servicecloudai-kit` dentro de `C:\proyectos\servicecloudai` (acepta combinar la carpeta `database`). Debe quedar así:

```
C:\proyectos\servicecloudai\
├── .agents\            ← reglas, agentes, skills y workflows de Antigravity
├── app\  bootstrap\  config\  public\  resources\  routes\  ...   (Laravel)
├── database\
│   ├── migrations\     (Laravel)
│   └── sql\            ← tu esquema (original y versión para migración)
├── docker\pgsql\       ← crea la base de pruebas al iniciar PostgreSQL
├── docs\               ← mockup y enunciado
├── AGENTS.md
├── compose.yaml
├── Dockerfile
├── .dockerignore
├── PROMPT-INICIAL.md
└── artisan  composer.json  .env  ...
```

> En el Explorador de Windows activa *Vista → Mostrar → Elementos ocultos* para ver `.agents` y `.dockerignore`.

---

## Paso 4 — Levantar los contenedores

```powershell
cd C:\proyectos\servicecloudai
docker compose up -d --build
docker compose ps
```

La primera vez construye la imagen (unos minutos). Debes ver tres servicios: `app`, `pgsql` (*healthy*) y `pgadmin`.

| Servicio | Qué es | Dirección |
|---|---|---|
| `app` | PHP 8.4 + Nginx con tu código | http://localhost:8000 |
| `pgsql` | PostgreSQL 17 | `localhost:5432` |
| `pgadmin` | Administrador web de PostgreSQL | http://localhost:5050 |

Comprueba PHP dentro del contenedor:
```powershell
docker compose exec app php -v
docker compose exec app php -m
docker compose exec app php artisan --version
```
En `php -m` deben aparecer `pdo_pgsql`, `intl` y `bcmath`.

Abre http://localhost:8000: verás la página de bienvenida de Laravel (todavía usa SQLite; el agente lo cambiará a PostgreSQL en la fase 0).

### Conectar pgAdmin

1. Abre http://localhost:5050 (si pide credenciales: `admin@servicecloud.ai` / `admin`).
2. *Add New Server* → pestaña General: nombre `ServiceCloud`. Pestaña Connection:
   - Host: `pgsql` · Puerto: `5432`
   - Base: `servicecloud_ai` · Usuario: `servicecloud` · Contraseña: `secret`
3. Verás también la base `servicecloud_ai_test` (la usan los tests).

> Desde programas en Windows (DBeaver, DataGrip, pgAdmin de escritorio) el host es `localhost` y el puerto `5432`. Si ese puerto ya está ocupado por otro PostgreSQL, agrega `FORWARD_DB_PORT=5433` al `.env` y ejecuta `docker compose up -d`.

### Atajos opcionales para ti (PowerShell)

Ejecuta `notepad $PROFILE` y pega:
```powershell
function art { docker compose exec app php artisan @args }
function dcomposer { docker compose exec app composer @args }
```
Desde ese momento puedes escribir `art migrate` o `dcomposer require …` dentro de la carpeta del proyecto.

---

## Paso 5 — Subir a GitHub (antes de que trabaje el agente)

```powershell
cd C:\proyectos\servicecloudai
git init
git add .
git commit -m "chore: proyecto Laravel con Docker y kit de agentes"
git branch -M main
git remote add origin git@github.com:TU_USUARIO/servicecloudai.git
git push -u origin main
```

Crea antes el repositorio vacío en GitHub e invita a tus 4 compañeros. El `.env` no se sube (Laravel ya lo ignora).

**Tus compañeros, para empezar**, solo necesitan Docker Desktop y Git:
```powershell
git clone git@github.com:TU_USUARIO/servicecloudai.git
cd servicecloudai
copy .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed
```
(Los que usen Linux agregan `USER_ID=1000` y `GROUP_ID=1000` a su `.env` antes del `up`, para evitar problemas de permisos.)

---

## Paso 6 — Configurar Antigravity

1. *Open Folder* → `C:\proyectos\servicecloudai` (ábrelo en Windows directamente, no por WSL).
2. Revisa que detecte la configuración del kit:
   - Panel **Rules**: 4 reglas (`00-docker`, `01-ui-fiel-al-mockup`, `02-laravel-postgres`, `03-roles-y-seguridad`) en modo *Always On*.
   - Panel **Workflows** o escribiendo `/` en el chat del agente: `/fase-0-entorno` … `/fase-7-cierre-nube`, `/crear-modulo`, `/verificar`, `/construir-todo`.
   - `/agents`: arquitecto, db-laravel, backend, frontend-ui, ia-rag, qa.
   - Si alguna carpeta no aparece en tu versión, renombra `.agents` a `.agent`.
3. **No necesitas `/init`**: el `AGENTS.md` ya está escrito. Si lo ejecutas, pide que complete el existente sin reemplazarlo.
4. Permisos recomendados del agente:
   - Modo **Planning**.
   - Revisión: pedir revisión del plan al inicio; luego puedes darle más autonomía.
   - Terminal: permitir sin preguntar `docker compose exec`, `docker compose ps`, `docker compose logs`, `git status/add/commit`. Pedir confirmación para `docker compose down`, cualquier `rm`/`Remove-Item` y `git push`.
5. Modelo: el más capaz disponible para las fases 0, 1 y 5; uno rápido sirve para las vistas repetitivas de la fase 3.

---

## Paso 7 — Arrancar el trabajo del agente

Abre `PROMPT-INICIAL.md`, copia el bloque del prompt y pégalo en el agente. Revisa el plan que propone y apruébalo.

Recomendación para la primera vez: ve fase por fase (`/fase-0-entorno`, luego `/fase-1-base-datos`…) y revisa http://localhost:8000 entre fase y fase. Cuando confíes en el flujo, usa `/construir-todo`.

Después de la fase 1 podrás entrar con `admin@servicecloud.ai` / `password` (Administrador SaaS). Los usuarios de cada rol quedan en `docs/PLAN.md`.

### IA real (opcional, cuando quieras)

La IA funciona sin clave con el modo `simulado`. Para usar Gemini: crea una clave en Google AI Studio y en `.env` pon `AI_DRIVER=gemini`, `GEMINI_API_KEY=…` y los nombres de modelo vigentes que indique AI Studio. Luego `docker compose exec app php artisan config:clear`.

---

## Comandos del día a día

| Qué | Comando |
|---|---|
| Encender el entorno | `docker compose up -d` |
| Apagar (sin borrar datos) | `docker compose stop` |
| Ver estado | `docker compose ps` |
| Ver logs de la app | `docker compose logs app --tail=100 -f` |
| Artisan | `docker compose exec app php artisan …` |
| Composer | `docker compose exec app composer …` |
| Tests | `docker compose exec app php artisan test` |
| Reconstruir BD con datos demo | `docker compose exec app php artisan migrate:fresh --seed` |
| Consola SQL | `docker compose exec pgsql psql -U servicecloud -d servicecloud_ai` |
| Reconstruir imagen (tras cambiar Dockerfile) | `docker compose up -d --build` |
| **Borrar todo, incluida la BD** (cuidado) | `docker compose down -v` |

## Problemas frecuentes

- **`error during connect` / `cannot find the file specified`**: Docker Desktop no está abierto.
- **Puerto 8000, 5432 o 5050 ocupado**: agrega `APP_PORT=8001`, `FORWARD_DB_PORT=5433` o `PGADMIN_PORT=5051` al `.env` y `docker compose up -d`.
- **La base de pruebas no existe**: el script de `docker/pgsql` solo corre la primera vez que se crea el volumen. Créala con `docker compose exec pgsql createdb -U servicecloud servicecloud_ai_test`.
- **Página en blanco o error 500 después de clonar**: falta `composer install` o `php artisan key:generate` (ver Paso 5).
- **La app va lenta**: es normal que Docker en Windows lea más lento los archivos de `C:\`. Para este proyecto es suficiente; si se vuelve molesto, coméntalo y se puede optimizar (por ejemplo, mover `vendor` a un volumen).

## Qué resuelve el kit por ti

- El esquema usa `usuarios.password_hash` y `creado_en/actualizado_en`: los modelos están instruidos para mapearlos (incluido `getAuthPasswordName()`, sin el cual falla el login en Laravel 11+).
- La migración por defecto de Laravel crea `users`, que no se usa: se deja solo `sessions`.
- El script SQL tenía `BEGIN;`/`COMMIT;`; la copia para migración no los tiene porque Laravel ya envuelve la migración en una transacción. Está probado: crea las 45 tablas y los 5 roles en PostgreSQL.
- Los tests corren sobre PostgreSQL (`servicecloud_ai_test`), no SQLite, porque el esquema usa JSONB, índices GIN y claves compuestas.
- El mismo `Dockerfile` tiene un stage `production` que se usará para desplegar en las nubes del curso (fase 7).
