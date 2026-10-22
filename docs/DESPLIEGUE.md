# ServiceCloud AI — Despliegue en la nube

La misma imagen Docker (stage `production` del `Dockerfile`) se despliega en cualquier nube. Toda la configuración llega por **variables de entorno**: no hay rutas ni hosts fijos.

## 1. Qué necesitas en cada nube

| Pieza | Para qué | Notas |
|---|---|---|
| Registro de imágenes | Guardar `servicecloudai:prod` | ECR, ACR, Artifact Registry, Docker Hub… |
| Contenedor web | La app (Nginx + PHP-FPM, puerto **8080**) | 1+ réplicas detrás de un balanceador HTTPS |
| PostgreSQL 14+ administrado | Base de datos | RDS / Azure Database for PostgreSQL / Cloud SQL |
| Contenedor programador (opcional) | `sla:revisar` cada 5 min y estado de contratos | Misma imagen, otro comando |
| Almacenamiento de objetos (opcional) | Adjuntos con varias réplicas | `ADJUNTOS_DISK=s3` (S3 o compatible) |

La sesión, la caché y la cola usan la base de datos (`SESSION_DRIVER=database`…), así que **varias réplicas comparten estado sin Redis**.

## 2. Construir y subir la imagen

```bash
docker build --target production -t servicecloudai:prod .
docker tag servicecloudai:prod <registro>/servicecloudai:1.0.0
docker push <registro>/servicecloudai:1.0.0
```

## 3. Variables de entorno

Obligatorias:

| Variable | Ejemplo |
|---|---|
| `APP_KEY` | `base64:…` (genera una con `docker run --rm servicecloudai:prod php artisan key:generate --show`) |
| `APP_URL` | `https://servicecloud.midominio.com` |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` / `DB_PORT` | host del PostgreSQL administrado / `5432` |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | credenciales de la base |
| `TRUSTED_PROXIES` | `*` (detrás del balanceador de la nube) |

Recomendadas: `APP_TIMEZONE=America/Lima`, `APP_LOCALE=es`, `DB_SSLMODE=require` si la base lo exige.

Ya vienen en la imagen (se pueden cambiar): `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr`, `LOG_LEVEL=info`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `AI_DRIVER=simulado`.

Opcionales: `AI_DRIVER=gemini` + `GEMINI_API_KEY`, `GEMINI_MODELO_TEXTO`, `GEMINI_MODELO_EMBEDDING`; `ADJUNTOS_DISK=s3` + `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`/`AWS_USE_PATH_STYLE_ENDPOINT` (S3 compatible); `API_RATE_LIMIT`, `SANCTUM_EXPIRATION`. Lista completa comentada en `.env.example`.

Las claves (`APP_KEY`, `DB_PASSWORD`, `GEMINI_API_KEY`) van en el gestor de secretos de la nube, nunca en el repositorio.

## 4. Arranque, migraciones y datos demo

Al iniciar, el contenedor ejecuta automáticamente (`AUTORUN_ENABLED=true`): `php artisan migrate --force`, `storage:link` y la caché de configuración, rutas, vistas y eventos. Con varias réplicas, las migraciones se protegen con el bloqueo de Laravel; si prefieres, despliega primero una réplica.

Datos de demostración (una sola vez, sobre una base nueva):

```bash
php artisan db:seed --force      # desde una tarea/consola del contenedor
php artisan ia:indexar           # (re)indexa la base de conocimiento para RAG
```

> Nunca ejecutes `migrate:fresh` ni `db:wipe` contra una base de la nube.

## 5. Programador de tareas

Segundo servicio con la misma imagen y variables, sin puerto expuesto:

```
command: php /var/www/html/artisan schedule:work
healthcheck: healthcheck-schedule
```

Alternativa: un job programado que ejecute `php artisan schedule:run` cada minuto.

## 6. Comprobación

- `GET /up` → `200` (health check del balanceador, puerto 8080).
- `GET /login` → pantalla de inicio de sesión.
- `POST /api/v1/token` con un usuario demo → `201` (ver `docs/API.md`).
- Logs: salida estándar del contenedor (`LOG_CHANNEL=stderr`).

## 7. Notas por nube

Sustituye por las nubes que use el equipo.

### AWS
- **App Runner** (más simple) o **ECS Fargate** + ALB. Puerto 8080, health check `/up`.
- **RDS for PostgreSQL** en la misma VPC; `DB_SSLMODE=require`.
- Secretos en **Secrets Manager**; adjuntos en **S3** (`ADJUNTOS_DISK=s3`, rol IAM con acceso al bucket).
- Programador: segundo servicio ECS con `schedule:work`, o EventBridge Scheduler → tarea ECS `schedule:run`.

### Microsoft Azure
- **Azure Container Apps** (ingreso HTTP, puerto destino 8080, sonda `/up`) o App Service for Containers (`WEBSITES_PORT=8080`).
- **Azure Database for PostgreSQL – Flexible Server**; habilita el acceso desde la red de Container Apps.
- Secretos en Key Vault / secretos de Container Apps. Adjuntos: Blob Storage mediante un gateway S3 compatible, o una sola réplica con `ADJUNTOS_DISK=local` y volumen de Azure Files.
- Programador: segunda Container App con `schedule:work` (sin ingreso).

### Google Cloud
- **VM de Compute Engine con Docker Compose** (VPC propia, firewall, IP estática): guía paso a paso en `docs/DESPLIEGUE-GCP-VM.md` con `compose.prod.yaml`.
- **Cloud Run** (puerto 8080, `/up`) con **Cloud SQL for PostgreSQL** (conector de Cloud SQL o IP privada).
- Secretos en Secret Manager. Adjuntos: Cloud Storage con interoperabilidad S3 (`AWS_ENDPOINT=https://storage.googleapis.com`).
- Programador: Cloud Scheduler → Cloud Run Job con `php artisan schedule:run`, o un servicio con `min-instances=1` y `schedule:work`.

## 8. Pruebas de rendimiento

Ver `docs/pruebas/carga.js` (k6 en Docker). Antes de medir, sube `API_RATE_LIMIT` (por ejemplo, `100000`) y siembra los datos demo. Registra por nube: p95 de web y API, tasa de errores, peticiones/s y tamaño de instancia/base.

Referencia local (Docker Desktop, imagen `production`, 20 usuarios virtuales, 30 s): 2.323 peticiones, 0 % de errores, p95 web 72 ms, p95 API 190 ms, p95 clasificación IA 129 ms.
