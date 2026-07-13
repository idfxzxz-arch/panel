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

Setelah repository di-clone ke `~/Projects/panel`, buat `.env` production. Password database, `APP_KEY`, dan Docker GID dibuat langsung di server dan tidak disimpan di GitHub:

```bash
cd ~/Projects/panel
cp .env.production.example .env

APP_KEY="base64:$(openssl rand -base64 32)"
DB_PASSWORD="$(openssl rand -hex 24)"
DB_ROOT_PASSWORD="$(openssl rand -hex 24)"
DOCKER_GID="$(stat -c '%g' /var/run/docker.sock)"

sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
sed -i "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}|" .env
sed -i "s|^DOCKER_GID=.*|DOCKER_GID=${DOCKER_GID}|" .env
chmod 600 .env

sudo mkdir -p /opt/myhosting-panel/apps
sudo chown -R "$USER:$USER" /opt/myhosting-panel/apps
docker network create hosting_proxy 2>/dev/null || true

docker compose -f compose.production.yaml build --pull
docker compose -f compose.production.yaml up -d database
docker compose -f compose.production.yaml run --rm panel php artisan migrate --force
docker compose -f compose.production.yaml run --rm panel php artisan hosting:create-admin tegardarmawan@gmail.com
docker compose -f compose.production.yaml up -d
docker compose -f compose.production.yaml ps
```

Jangan menjalankan `git add -f .env` atau mengunggah `.env` ke GitHub. Untuk domain berbeda, ubah `APP_URL` dan `PANEL_DOMAIN` di `.env` sebelum menjalankan Compose.

Jika Cloudflare Tunnel di host mengarah ke `http://localhost:8080`, biarkan `PANEL_WEB_BIND=127.0.0.1:8081` dan gunakan Nginx host sebagai reverse proxy:

```nginx
server {
    listen 127.0.0.1:8080;
    server_name panel-dev.idkxz.my.id;

    location / {
        proxy_pass http://127.0.0.1:8081;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
    }
}
```

Di GitHub, tambahkan URL webhook dari halaman project, content type `application/json`, secret sekali-tampil, dan event `push`.

## Batas MVP

Isi `DOCKER_GID` dengan GID socket host (`stat -c '%g' /var/run/docker.sock`) agar user non-root panel dapat mengakses daemon.

Private repository, queue worker per project, backup, rollback otomatis, dan multi-user/RBAC belum production-ready. Editor environment variable tersedia dengan encrypted storage, tetapi secret manager eksternal tetap disarankan untuk production. Docker socket adalah trust boundary utama.
