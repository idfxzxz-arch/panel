# Arsitektur Harbor Panel

## Keputusan utama

Traefik dipilih untuk jalur utama MVP. Ia membaca label Docker, membuat route tanpa file konfigurasi per project, dan menangani ACME/Let's Encrypt secara terpusat. Nginx manual cocok bila operator memerlukan konfigurasi khusus, tetapi menambah proses tulis file, `nginx -t`, reload, Certbot, dan locking. Untuk project Docker dinamis, Traefik mengurangi state yang harus disinkronkan panel.

```text
Internet :80/:443
        │
     Traefik ───── hosting_proxy (external network)
        │                    │
   panel-web          project-a:web / project-b:web
                            │
                    internal network per project
                            │
                  PHP-FPM (khusus Laravel)
```

Hanya service web yang masuk `hosting_proxy`; PHP-FPM dan database tidak menerbitkan port host. Compose memberi tiap project namespace dan network internal tersendiri. Panel dan worker berbagi database, storage panel, `/opt/myhosting-panel/apps`, dan akses daemon Docker.

> Mount `/var/run/docker.sock` secara efektif memberi worker hak setara root host. Production sebaiknya memakai runner terpisah dengan API sempit/socket proxy allowlist, AppArmor/SELinux, dan audit log. Jangan beri akses panel kepada user tak tepercaya pada tahap MVP.

## Struktur server

```text
/opt/myhosting-panel/
├── panel/                  # checkout aplikasi ini
├── apps/<slug>/            # checkout repo + artefak generated
├── traefik/                # data/config edge jika dipisah
├── logs/                   # export/rotasi log deployment
├── backups/                # backup terenkripsi
└── secrets/                # secret host mode 0700, tidak di Git
```

Implementasi menyimpan log utama di MariaDB (`deployment_logs`) dan aplikasi di `HOSTING_APPS_PATH`.

## Struktur database

- `users`: akun panel.
- `projects`: tipe, Git URL/branch, status dan commit terakhir.
- `project_domains`: domain unik dan status SSL.
- `github_accounts`: fondasi token terenkripsi untuk private repo.
- `deployments` dan `deployment_logs`: satu run dan output tiap langkah.
- `environment_variables`: key unik per project; value memakai encrypted cast Laravel.
- `docker_containers`: snapshot container untuk monitoring lanjutan.
- `webhooks`: UUID endpoint, secret terenkripsi, status, penerimaan terakhir.

## Alur deploy

Operasi panjang masuk database queue. Worker clone/fetch, hard reset ke `FETCH_HEAD`, membersihkan file untracked, membuat Dockerfile/Compose/config Nginx, menulis `.env` mode `0600`, build image, dan `up -d --remove-orphans`. SHA, status, waktu, stdout, dan stderr disimpan.

### HTML static

Repo dicopy ke image `nginx-unprivileged` yang mendengar port 8080. Traefik mengirim domain ke `web:8080`.

### React/Vite

Stage Node menjalankan `npm ci` dan `npm run build`. `.env` berada pada build context Vite. Stage runtime hanya menerima `dist`, jadi source/tooling tidak masuk image akhir. Nginx memakai fallback `index.html` untuk client-side routing.

### Laravel

Stage Composer menginstal dependency production. PHP-FPM berjalan sebagai `www-data`; root Nginx `/public`. Setelah start, worker menjalankan `migrate --force`, `config:cache`, dan `storage:link --force`. Queue project dan persistent upload adalah fase lanjutan; production sebaiknya menggunakan object storage atau volume yang dibackup.

## Command allowlist dan keamanan input

Panel tidak menerima command bebas. `ProcessRunner` menjalankan executable dengan array argumen:

```text
git clone --branch <branch> --single-branch --depth 1 <github-url> <derived-path>
git fetch origin <branch> --depth 1
git reset --hard FETCH_HEAD
docker compose -p <slug> build --pull
docker compose -p <slug> up -d --remove-orphans
docker compose -p <slug> start|stop|restart
docker compose -p <slug> logs --no-color --tail 300
```

Tidak ada `sh -c`. Slug, branch, repository, dan domain divalidasi; path diturunkan dari slug. Webhook memakai `hash_hmac` + `hash_equals`, memeriksa event/branch, dan menolak delivery ID duplikat selama 24 jam. Token dan environment value menggunakan encrypted cast serta tidak dicetak ke log.

## Alternatif Nginx + Certbot

Jika Traefik tidak digunakan, publish service ke port loopback unik, lalu buat konfigurasi berikut:

```nginx
server {
    listen 80;
    server_name app.example.com;
    location / {
        proxy_pass http://127.0.0.1:18080;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

```bash
sudo nginx -t
sudo systemctl reload nginx
sudo certbot --nginx -d app.example.com --redirect --non-interactive --agree-tos -m admin@example.com
```

Panel wajib mengalokasikan port dengan transaksi/lock, menulis hanya ke directory allowlist, dan tidak reload jika `nginx -t` gagal.

## Roadmap

1. **MVP saat ini:** login admin, tambah/lihat project, environment variable terenkripsi, deploy GitHub public, ketiga tipe build, Traefik+ACME, lifecycle, container/deploy log, webhook push.
2. **Hardening beta:** rotasi webhook, GitHub App atau PAT private repo tanpa token di argv, deploy lock, health check dan rollback, container inventory, rate limit, 2FA.
3. **Workloads:** queue/scheduler Laravel, persistent volume, database per project, secret manager, backup/restore teruji, retention log.
4. **Production multi-user:** tenant/RBAC/quota, build isolation tanpa Docker socket, registry, agent per node, audit immutable, metrics/alerting, HA dan billing bila dibutuhkan.

Sebelum public production: batasi permission GitHub App, batasi egress build, scan image/dependency, pin image dengan digest, firewall hanya 22/80/443, backup database+ACME, dan uji restore/rollback.
