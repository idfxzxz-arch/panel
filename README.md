# Harbor Panel

MVP panel hosting Debian berbasis Laravel 12, Docker Compose, MariaDB, dan Traefik. Mendukung repository GitHub public untuk HTML static, Laravel, dan React/Vite; setiap project menjadi Compose project terpisah.

Rancangan, alur deployment, Dockerfile/Compose yang dihasilkan aplikasi, alternatif Nginx, keamanan, skema data, dan roadmap ada di [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

Setup pemilih repository GitHub dan pembuatan subdomain otomatis melalui Cloudflare Zero Trust ada di [docs/CLOUDFLARE_GITHUB_SETUP.md](docs/CLOUDFLARE_GITHUB_SETUP.md).

## Development cepat

```bash
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan hosting:create-admin admin@example.com
docker network create hosting_proxy
php artisan serve
php artisan queue:work --tries=1 --timeout=1200
```

Worker harus dapat mengakses Docker daemon. Tanpa Traefik, build/deploy tetap berjalan tetapi domain belum dapat diakses.

## Instalasi Debian production

1. Instal Docker Engine dan Compose plugin dari repository resmi Docker. Arahkan DNS panel/domain project ke server dan buka TCP 80/443.
2. Checkout ke `/opt/myhosting-panel/panel`; buat `/opt/myhosting-panel/apps` dengan owner worker.
3. Salin `.env.example` ke `.env`; set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `QUEUE_CONNECTION=database`, dan:

```dotenv
PANEL_DOMAIN=panel.example.com
ACME_EMAIL=admin@example.com
DOCKER_GID=999
DB_CONNECTION=mysql
DB_HOST=database
DB_PORT=3306
DB_DATABASE=hosting_panel
DB_USERNAME=hosting_panel
DB_PASSWORD=long-random-value
DB_ROOT_PASSWORD=another-long-random-value
HOSTING_APPS_PATH=/opt/myhosting-panel/apps
```

4. Jalankan:

```bash
docker compose -f compose.production.yaml build
docker compose -f compose.production.yaml up -d database
docker compose -f compose.production.yaml run --rm panel php artisan migrate --force
docker compose -f compose.production.yaml run --rm panel php artisan hosting:create-admin admin@example.com
docker compose -f compose.production.yaml up -d
```

5. Di GitHub, tambahkan URL webhook dari halaman project, content type `application/json`, secret sekali-tampil, dan event `push`.

## Batas MVP

Isi `DOCKER_GID` dengan GID socket host (`stat -c '%g' /var/run/docker.sock`) agar user non-root panel dapat mengakses daemon.

Private repository, queue worker per project, backup, rollback otomatis, dan multi-user/RBAC belum production-ready. Editor environment variable tersedia dengan encrypted storage, tetapi secret manager eksternal tetap disarankan untuk production. Docker socket adalah trust boundary utama.

# panel
