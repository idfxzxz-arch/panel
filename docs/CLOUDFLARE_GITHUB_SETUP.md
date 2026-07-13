# Setup GitHub dan Cloudflare Zero Trust

## GitHub

1. Buat fine-grained personal access token di GitHub.
2. Batasi repository yang dapat diakses.
3. Berikan permission **Metadata: Read** dan **Contents: Read**.
4. Di Harbor Control buka **Integrations**, masukkan token, lalu klik **Connect GitHub**.
5. Halaman **New Deployment** akan menampilkan repository public/private yang dapat diakses token.

Token disimpan memakai encrypted cast Laravel. Saat clone private repository, token dikirim melalui environment Git config dan tidak dimasukkan ke URL repository atau deployment log.

## Cloudflare Zero Trust

Prasyarat: zone `idkxz.my.id` sudah aktif di Cloudflare dan satu remotely-managed Cloudflare Tunnel sudah dibuat.

1. Buat Cloudflare API Token dengan scope minimum:
   - Zone / DNS / Read
   - Zone / DNS / Edit
   - Account / Cloudflare Tunnel / Read
   - Account / Cloudflare Tunnel / Edit
2. Ambil Account ID dan Zone ID dari dashboard Cloudflare.
3. Ambil Tunnel UUID dan connector token dari Zero Trust > Networks > Tunnels.
4. Buka **Integrations** dan isi kelima nilai tersebut. Base domain sudah diisi `idkxz.my.id`.
5. Panel memverifikasi zone+tunnel. Jika connector token diberikan, panel menjalankan container `harbor-cloudflared` pada network `hosting_proxy`.

Saat deployment `blog.idkxz.my.id`, worker melakukan:

1. Membuat atau memperbarui CNAME proxied `blog.idkxz.my.id` ke `<TUNNEL_UUID>.cfargotunnel.com`.
2. Menambahkan ingress remotely-managed Tunnel untuk hostname tersebut menuju `https://traefik:443`.
3. Membuat label router Traefik untuk hostname.
4. Clone repository, build image, dan start container.

Provisioning bersifat idempotent: redeploy memperbaiki CNAME/ingress yang berubah dan tidak membuat record duplikat. Menghapus alias domain juga membersihkan DNS record serta ingress. Semua token tersimpan terenkripsi.

## Catatan operasional

- Network Docker `hosting_proxy` dan service Traefik harus aktif.
- Container cloudflared dan Traefik harus berada pada network yang sama.
- Domain project tidak memerlukan IP publik atau port-forward router; tunnel membuat koneksi outbound.
- Jangan memakai Global API Key Cloudflare.
- Setelah mengganti `APP_KEY`, encrypted token lama tidak dapat dibaca.
