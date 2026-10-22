# ServiceCloud AI en Google Cloud — VM de Compute Engine

Arquitectura: una **VPC propia** con una subred, reglas de firewall mínimas (HTTP y SSH por IAP), una **IP externa estática** y una **VM Ubuntu con Docker** que ejecuta `compose.prod.yaml` (app + PostgreSQL + programador). PostgreSQL no se expone a internet.

```
Internet ──HTTP:80──▶ IP estática ──▶ VM servicecloud-vm (tag servicecloud-web)
                                       └─ Docker: app (80→8080) · pgsql (interno) · scheduler
SSH ──▶ Identity-Aware Proxy (35.235.240.0/20) ──tcp:22──▶ VM
```

Valores usados en la guía (puedes cambiarlos, pero mantenlos iguales en todos los pasos):

| Recurso | Nombre | Valor |
|---|---|---|
| Región / zona | — | `southamerica-west1` / `southamerica-west1-a` (Santiago) |
| VPC | `servicecloud-vpc` | modo personalizado |
| Subred | `servicecloud-subnet` | `10.10.0.0/24` |
| Firewall HTTP | `servicecloud-allow-http` | tcp:80,443 desde `0.0.0.0/0` → tag `servicecloud-web` |
| Firewall SSH | `servicecloud-allow-ssh-iap` | tcp:22 desde `35.235.240.0/20` → tag `servicecloud-web` |
| IP estática | `servicecloud-ip` | regional, en la misma región |
| VM | `servicecloud-vm` | e2-medium, Ubuntu 24.04 LTS, 30 GB |

## 1. Proyecto y API

1. En la consola (console.cloud.google.com) selecciona tu proyecto arriba a la izquierda (o crea uno: **Seleccionar proyecto → Proyecto nuevo**).
2. Confirma que tiene facturación: **Facturación → Mis proyectos**.
3. Menú ☰ → **Compute Engine → Instancias de VM**. Si es la primera vez, pulsa **Habilitar** en *Compute Engine API* y espera 1–2 minutos.

## 2. Red VPC y subred

Menú ☰ → **Red de VPC → Redes de VPC → Crear red de VPC**:

- **Nombre:** `servicecloud-vpc`
- **Modo de creación de subredes:** *Personalizado*
- **Nueva subred:**
  - Nombre: `servicecloud-subnet`
  - Región: `southamerica-west1`
  - Rango IPv4: `10.10.0.0/24`
  - Acceso privado a Google: **Activado**
  - Pulsa **Listo**
- **Reglas de firewall:** no marques ninguna de las sugeridas (las crearemos a medida).
- **Modo de enrutamiento dinámico:** *Regional*.
- **Crear**.

## 3. Reglas de firewall

Una VPC nueva bloquea todo el tráfico entrante. Abrimos solo lo necesario. Menú ☰ → **Red de VPC → Firewall → Crear regla de firewall**.

**Regla 1 — web**

- Nombre: `servicecloud-allow-http`
- Red: `servicecloud-vpc`
- Prioridad: `1000` · Dirección: **Entrada** · Acción: **Permitir**
- Destinos: **Etiquetas de destino especificadas** → `servicecloud-web`
- Filtro de origen: **Rangos IPv4** → `0.0.0.0/0`
- Protocolos y puertos: **Protocolos y puertos especificados** → TCP `80,443`
- **Crear**

**Regla 2 — SSH solo a través de Google (IAP)**

- Nombre: `servicecloud-allow-ssh-iap`
- Red: `servicecloud-vpc`, Entrada, Permitir
- Destinos: etiqueta `servicecloud-web`
- Rangos IPv4 de origen: `35.235.240.0/20` (rango de Identity-Aware Proxy, el que usa el botón **SSH** de la consola)
- TCP `22`
- **Crear**

No abras el 5432: la base solo se usa dentro de la VM.

## 4. IP externa estática

Menú ☰ → **Red de VPC → Direcciones IP → Reservar dirección IP estática externa**:

- Nombre: `servicecloud-ip`
- Nivel de servicio de red: *Premium*
- Versión: IPv4 · Tipo: **Regional** · Región: `southamerica-west1`
- Adjuntar a: *Ninguno* (se asigna al crear la VM)
- **Reservar** y anota la IP (por ejemplo `34.176.x.x`).

Una IP reservada y sin usar tiene costo: asígnala a la VM en el paso siguiente.

## 5. Máquina virtual

Menú ☰ → **Compute Engine → Instancias de VM → Crear instancia**:

- **Nombre:** `servicecloud-vm`
- **Región / zona:** `southamerica-west1` / `southamerica-west1-a`
- **Configuración de máquina:** serie **E2**, tipo **e2-medium** (2 vCPU, 4 GB)
- **SO y almacenamiento → Cambiar:** *Ubuntu*, versión **Ubuntu 24.04 LTS** (x86/64), disco *Balanceado* de **30 GB** → **Seleccionar**
- **Herramientas de redes:**
  - Etiquetas de red: `servicecloud-web`
  - No marques las casillas "Permitir tráfico HTTP/HTTPS" (crean reglas en la red *default*; ya tenemos las nuestras)
  - **Interfaces de red:** edita la existente → Red `servicecloud-vpc`, Subred `servicecloud-subnet`, **Dirección IPv4 externa:** `servicecloud-ip` → **Listo**
- **Crear** y espera a que aparezca el ícono verde.

## 6. Entrar por SSH

En la lista de instancias pulsa **SSH** en `servicecloud-vm`. Se abre una terminal en el navegador (pasa por IAP gracias a la regla 2). Todos los comandos siguientes se ejecutan ahí.

## 7. Preparar el servidor (Docker + swap)

```bash
sudo apt-get update && sudo apt-get -y upgrade
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER

# 2 GB de swap: evita quedarse sin memoria al construir la imagen
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

Cierra la ventana SSH y vuelve a abrirla (para que tu usuario use Docker sin `sudo`). Comprueba:

```bash
docker --version && docker compose version
```

## 8. Descargar el proyecto

**Repositorio público:**

```bash
git clone https://github.com/aldairobregon70-netizen/servicecloudai.git
cd servicecloudai
```

**Repositorio privado:** crea un token en GitHub (**Settings → Developer settings → Personal access tokens → Fine-grained tokens → Generate new token**, acceso solo a `servicecloudai`, permiso *Contents: Read-only*) y clona; cuando pida *Password*, pega el token:

```bash
git clone https://aldairobregon70-netizen@github.com/aldairobregon70-netizen/servicecloudai.git
cd servicecloudai
git config credential.helper store   # guarda el token para los próximos git pull
```

## 9. Variables del servidor

```bash
cp .env.produccion.example .env
echo "APP_KEY=base64:$(openssl rand -base64 32)"   # copia la línea que aparece
echo "DB_PASSWORD=$(openssl rand -hex 16)"         # copia la línea que aparece
nano .env
```

En `nano` reemplaza:

- `APP_KEY=<base64:...>` → la línea generada
- `APP_URL=<http://IP_PUBLICA>` → `http://TU_IP_ESTATICA`
- `DB_PASSWORD=<contraseña-segura>` → la línea generada

Guarda con **Ctrl+O**, Enter, y sal con **Ctrl+X**. Este `.env` solo existe en la VM (no se sube a GitHub).

## 10. Levantar el sistema

```bash
docker compose -f compose.prod.yaml up -d --build
docker compose -f compose.prod.yaml ps
```

La primera vez tarda unos minutos (descarga imágenes y construye). Los tres servicios deben quedar `healthy`. Al arrancar, `app` crea las tablas solo (`docker compose -f compose.prod.yaml logs app` muestra `Running migrations`).

## 11. Cargar los datos demo (solo una vez)

```bash
docker compose -f compose.prod.yaml exec app php artisan db:seed --force
```

Debe terminar con `DemoSeeder ... DONE`. No lo repitas: el seeder no se puede ejecutar dos veces sobre la misma base.

## 12. Probar

- `http://TU_IP_ESTATICA/up` → página de estado sin error.
- `http://TU_IP_ESTATICA` → inicia sesión con `admin@servicecloud.ai` / `password`.

## 13. Actualizar después de un `git push`

```bash
cd ~/servicecloudai
git pull
docker compose -f compose.prod.yaml up -d --build
```

Las migraciones nuevas se aplican solas; los datos y los adjuntos se conservan (volúmenes `pgdata` y `storage`).

## 14. Operación

| Tarea | Comando (en la VM, dentro de `~/servicecloudai`) |
|---|---|
| Estado | `docker compose -f compose.prod.yaml ps` |
| Logs | `docker compose -f compose.prod.yaml logs -f app` |
| Reiniciar | `docker compose -f compose.prod.yaml restart` |
| Respaldo de la base | `docker compose -f compose.prod.yaml exec -T pgsql pg_dump -U servicecloud servicecloud_ai > respaldo_$(date +%F).sql` |
| Activar Gemini | editar `.env` (`AI_DRIVER=gemini`, clave y modelos) → `up -d` → `exec app php artisan ia:indexar --forzar` |

- La VM se reinicia sola con los contenedores (`restart: unless-stopped`).
- **Costos:** detener la VM (**Detener** en la consola) deja de cobrar CPU/RAM; siguen el disco y la IP estática. Para borrar todo: elimina la VM, libera `servicecloud-ip`, borra las reglas de firewall y la VPC.
- Programa snapshots del disco en **Compute Engine → Snapshots → Crear programa de snapshots** si quieres respaldos automáticos.

## 15. Pruebas de rendimiento

Antes de medir, en la VM pon `API_RATE_LIMIT=100000` en `.env` y ejecuta `docker compose -f compose.prod.yaml up -d`. Desde tu PC:

```powershell
docker run --rm -i -e BASE_URL=http://TU_IP_ESTATICA -e VUS=20 -e DURACION=2m grafana/k6 run - < docs/pruebas/carga.js
```

Anota tipo de máquina (e2-medium), región y resultados (p95, errores, peticiones/s). Al terminar, vuelve `API_RATE_LIMIT` a 600.

## Alternativa por línea de comandos (gcloud)

Equivale a los pasos 2 a 5 (en Cloud Shell o con el SDK instalado):

```bash
REGION=southamerica-west1; ZONA=southamerica-west1-a
gcloud services enable compute.googleapis.com
gcloud compute networks create servicecloud-vpc --subnet-mode=custom
gcloud compute networks subnets create servicecloud-subnet --network=servicecloud-vpc --region=$REGION --range=10.10.0.0/24 --enable-private-ip-google-access
gcloud compute firewall-rules create servicecloud-allow-http --network=servicecloud-vpc --direction=INGRESS --action=ALLOW --rules=tcp:80,tcp:443 --source-ranges=0.0.0.0/0 --target-tags=servicecloud-web
gcloud compute firewall-rules create servicecloud-allow-ssh-iap --network=servicecloud-vpc --direction=INGRESS --action=ALLOW --rules=tcp:22 --source-ranges=35.235.240.0/20 --target-tags=servicecloud-web
gcloud compute addresses create servicecloud-ip --region=$REGION
gcloud compute instances create servicecloud-vm --zone=$ZONA --machine-type=e2-medium \
  --image-family=ubuntu-2404-lts-amd64 --image-project=ubuntu-os-cloud --boot-disk-size=30GB --boot-disk-type=pd-balanced \
  --network=servicecloud-vpc --subnet=servicecloud-subnet --address=servicecloud-ip --tags=servicecloud-web
```

## Problemas frecuentes

- **El botón SSH no conecta:** revisa que la regla `servicecloud-allow-ssh-iap` exista en `servicecloud-vpc` y que la VM tenga la etiqueta `servicecloud-web`.
- **La IP no abre la página:** revisa la regla `servicecloud-allow-http`, la etiqueta de la VM y `docker compose -f compose.prod.yaml ps`.
- **`Define DB_PASSWORD en el archivo .env`:** falta `DB_PASSWORD` en `.env` (paso 9).
- **Error 500 al entrar:** `docker compose -f compose.prod.yaml logs app --tail=50`; casi siempre es `APP_KEY` vacía o mal copiada.
- **Cambiaste `DB_PASSWORD` después del primer arranque:** PostgreSQL conserva la contraseña con la que se creó su volumen; vuelve a la original en `.env`.
