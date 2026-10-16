---
trigger: always_on
---

# Regla: todo se ejecuta dentro de Docker

El desarrollador **no tiene PHP, Composer ni PostgreSQL instalados en Windows**. Todo corre en contenedores definidos en `compose.yaml`:

| Servicio | Qué es | Acceso desde Windows |
|---|---|---|
| `app` | PHP 8.4 + Nginx (imagen `serversideup/php`, usuario `www-data`), código montado en `/var/www/html` | http://localhost:8000 |
| `pgsql` | PostgreSQL 17 (bases `servicecloud_ai` y `servicecloud_ai_test`, usuario `servicecloud` / `secret`) | `localhost:5432` |
| `pgadmin` | pgAdmin 4 | http://localhost:5050 |

## Cómo ejecutar comandos (terminal PowerShell del IDE)

Siempre con `docker compose exec -T` desde la raíz del proyecto (el `-T` evita errores de TTY en la terminal del agente):

```powershell
docker compose exec -T app php artisan migrate:fresh --seed
docker compose exec -T app php artisan test
docker compose exec -T app php artisan make:model Ticket
docker compose exec -T app composer require paquete/nombre
docker compose exec -T pgsql psql -U servicecloud -d servicecloud_ai -c "select count(*) from tickets;"
```

- **Nunca** ejecutes `php`, `composer` o `psql` directamente en Windows: no existen o son otra versión.
- Cuando un workflow o regla diga "ejecuta `php artisan …`", tradúcelo a `docker compose exec -T app php artisan …`.
- Si los contenedores no están arriba (`docker compose ps`), ejecuta `docker compose up -d` y espera a que `pgsql` esté *healthy*.
- Si cambias `Dockerfile` o necesitas una extensión PHP nueva: edita el `Dockerfile` (stage `base`, línea `install-php-extensions`), luego `docker compose up -d --build`. Avisa al desarrollador.
- `vendor/` vive en el volumen Docker `vendor` (rendimiento en Windows). Instalar o agregar paquetes: `docker compose exec -T -u root app sh -c "composer require paquete/nombre && chown -R www-data:www-data vendor"`.
- El código montado aparece como `root` dentro del contenedor: comandos que escriben archivos del proyecto (`pint`, `make:*`, `key:generate`) van con `-u root`. Si `storage/` da error de permisos: `docker compose exec -T -u root app chmod -R a+rwX storage bootstrap/cache`.
- Logs: `docker compose logs app --tail=100` y `storage/logs/laravel.log`.
- Los archivos generados con `artisan make:*` aparecen directamente en la carpeta del proyecto (volumen montado).

## Configuración Laravel para Docker

- `.env`: `DB_HOST=pgsql` (nombre del servicio, no `localhost`), `DB_PORT=5432`, `DB_DATABASE=servicecloud_ai`, `DB_USERNAME=servicecloud`, `DB_PASSWORD=secret`, `APP_URL=http://localhost:8000`.
- `phpunit.xml`: `DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, `DB_DATABASE=servicecloud_ai_test`.
- No uses `php artisan serve`: Nginx del contenedor ya sirve la app en el puerto 8080 (mapeado a 8000).
- Archivos de Docker del proyecto: `Dockerfile` (stages `base`, `development`, `production`), `compose.yaml`, `.dockerignore`, `docker/pgsql/`. El stage `production` es la imagen que se desplegará en las nubes: todo lo que la app necesite en producción debe funcionar con variables de entorno, sin depender de archivos fuera de la imagen.
- `.dockerignore` excluye docs y archivos de agentes, pero **debe incluir** `database/sql/` (la migración del esquema lee de ahí).

## Prohibido

- `docker compose down -v` (borra la base de datos) sin pedir confirmación al desarrollador.
- `docker system prune` o borrar imágenes/volúmenes.
- Cambiar puertos o credenciales de `compose.yaml` sin anotarlo en `docs/PLAN.md` → "Decisiones".
