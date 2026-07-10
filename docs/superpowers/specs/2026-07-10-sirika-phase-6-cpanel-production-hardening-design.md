# SIRIKA Phase 6 cPanel Production Hardening Design

Tanggal: 2026-07-10
Status: Draft untuk review user
Baseline: Phase 5B operational reporting sudah merge dan push ke `main`
Stack: PHP 7.4, Laravel 8.83.29, Blade, Alpine.js, Leaflet.js, MySQL/PostgreSQL, Laravel Excel, BaconQrCode, html5-qrcode, shared hosting cPanel

## 1. Tujuan

Phase 6 membuat deployment production SIRIKA lebih aman, terdokumentasi, dan bisa diulang tanpa mengubah workflow domain izin kendaraan.

Hasil akhir Phase 6:

- Production deployment untuk `https://sirika.vdnisite.com` memiliki template environment yang aman.
- Struktur cPanel terdokumentasi jelas:
  - Source Laravel lengkap berada di luar `public_html`.
  - Web root hanya berisi isi folder Laravel `public/`.
  - Web root production adalah `public_html/prod-sirika`.
- `index.php` public memiliki panduan path yang benar untuk source Laravel di luar public web root.
- Session, cookie, debug, logging, trusted host, dan proxy behavior memiliki konfigurasi production yang eksplisit.
- Checklist deploy dan rollback tersedia untuk operator.
- Cache Laravel production (`config`, `route`, `view`) bisa divalidasi sebelum deploy dinyatakan siap.
- Risiko dependency Laravel 8 dari `composer audit` dicatat sebagai backlog upgrade terpisah, bukan dicampur dengan hardening Phase 6.

## 2. Konteks

Phase 1 sampai Phase 5B sudah membangun fitur utama SIRIKA:

- Auth dan role admin panel.
- Import Excel izin kendaraan.
- Review dan aktivasi izin.
- QR digital dan fisik.
- Scan QR security.
- Peta rute dan highlight.
- Laporan izin, laporan scan, export Excel, dan dashboard operasional.

Kondisi production yang dikunci untuk Phase 6:

- URL sistem: `https://sirika.vdnisite.com`.
- Public web folder cPanel: `public_html/prod-sirika`.
- `public_html/prod-sirika` hanya berisi isi folder `public/` Laravel, seperti `index.php`, `css/`, `js/`, `images/`, dan `mix-manifest.json`.
- Source Laravel lengkap berada di luar `public_html`.
- cPanel menyediakan Terminal/SSH.

Temuan hardening saat ini:

- `composer audit` menemukan advisory pada `laravel/framework` 8.83.29 dan package abandoned `fruitcake/laravel-cors` serta `swiftmailer/swiftmailer`.
- `npm audit --omit=dev` tidak menemukan vulnerability production.
- `.env.example` masih template local/default Laravel.
- README masih template Laravel, belum dokumentasi SIRIKA.
- `TrustHosts` tersedia tetapi belum aktif di global middleware.
- CORS masih default terbuka untuk `api/*`, sementara SIRIKA saat ini tidak membutuhkan API publik.
- Production deployment belum punya dokumen step-by-step khusus cPanel.

## 3. Scope Phase 6

Phase 6 mencakup:

- Membuat `.env.production.example` khusus SIRIKA.
- Membuat dokumentasi deploy cPanel di `docs/deployment/CPANEL-PRODUCTION.md`.
- Menyesuaikan `.env.example` agar tidak terlihat seperti konfigurasi production.
- Mengganti README default Laravel menjadi README SIRIKA yang ringkas.
- Mengaktifkan dan mengetes `TrustHosts` untuk production host `sirika.vdnisite.com`.
- Menambahkan konfigurasi env untuk host tambahan jika nanti staging/subdomain diperlukan.
- Meninjau konfigurasi CORS agar tidak terlalu terbuka untuk production.
- Menambahkan konfigurasi session/cookie production yang eksplisit.
- Menambahkan atau memperbarui test production-readiness:
  - Host middleware aktif.
  - Environment example berisi key wajib production.
  - Route tetap bisa dicache.
  - Config tetap bisa dicache.
  - Dokumentasi deployment menyebut struktur cPanel yang benar.
- Menambahkan checklist manual untuk:
  - Deploy awal.
  - Deploy update.
  - Cache Laravel.
  - Permission folder.
  - Backup.
  - Rollback.

## 4. Non-Scope Phase 6

Hal berikut tidak dikerjakan di Phase 6:

- Upgrade Laravel 8 ke Laravel 10, 11, 12, atau 13.
- Upgrade PHP production ke PHP 8.
- Migrasi dari SwiftMailer ke Symfony Mailer.
- Menghapus `fruitcake/laravel-cors`.
- Mengganti arsitektur hosting dari shared hosting cPanel ke VPS.
- Menambah CI/CD otomatis.
- Membuat backup automation penuh.
- Mengubah struktur database domain.
- Mengubah workflow import, review, QR, scan, route map, report, atau user CRUD.
- Mengubah masa aktif QR.
- Menambah audit log domain.
- Menambah queue/job export.

Alasan non-scope: dependency upgrade mayor perlu phase terpisah karena dapat memengaruhi PHP runtime, Laravel framework behavior, package compatibility, dan seluruh regression suite.

## 5. Keputusan Produk dan Teknis

Keputusan untuk Phase 6:

- Phase 6 adalah hardening deployment, bukan fitur operasional baru.
- Deployment cPanel memakai pendekatan split source/public:
  - Source Laravel lengkap di luar `public_html`.
  - Isi folder Laravel `public/` berada di `public_html/prod-sirika`.
- `APP_URL` production adalah `https://sirika.vdnisite.com`.
- `APP_ENV=production` dan `APP_DEBUG=false` wajib di production.
- `SESSION_SECURE_COOKIE=true` wajib untuk production HTTPS.
- `LOG_CHANNEL=daily` dan `LOG_LEVEL=warning` direkomendasikan untuk production shared hosting.
- `SIRIKA_SEED_USER_PASSWORD` wajib jika menjalankan seeder user di production.
- Public `index.php` harus menunjuk ke source Laravel di luar `public_html`.
- `php artisan migrate --force` hanya dijalankan saat ada migration baru.
- `npm run prod` sebaiknya dijalankan di lokal/CI sebelum upload asset jika cPanel tidak ideal untuk Node build.
- Laravel cache commands harus menjadi bagian deployment update:
  - `php artisan config:clear`
  - `php artisan route:clear`
  - `php artisan view:clear`
  - `php artisan config:cache`
  - `php artisan route:cache`
  - `php artisan view:cache`
- Advisory `composer audit` untuk Laravel 8 dicatat sebagai risiko residual. Resolusi utamanya adalah Phase upgrade dependency terpisah.

## 6. Struktur Deployment cPanel

Struktur production yang direkomendasikan:

```text
/home/CPANEL_USER/
  sirika-app/
    app/
    bootstrap/
    config/
    database/
    public/
    resources/
    routes/
    storage/
    vendor/
    .env
    artisan
    composer.json
    composer.lock

  public_html/
    prod-sirika/
      index.php
      css/
      js/
      images/
      mix-manifest.json
      favicon.ico
      .htaccess
```

`public_html/prod-sirika` tidak boleh berisi:

- `.env`
- `app/`
- `bootstrap/`
- `config/`
- `database/`
- `resources/`
- `routes/`
- `storage/`
- `tests/`
- `vendor/`
- `composer.json`
- `composer.lock`

Jika suatu saat source Laravel harus berada di dalam `public_html`, itu dianggap fallback tidak ideal dan wajib diberi proteksi `.htaccess` tambahan. Namun untuk deployment saat ini, source berada di luar `public_html`, sehingga fallback tersebut hanya dicatat sebagai emergency note.

## 7. Public Index Path

File `public_html/prod-sirika/index.php` harus menyesuaikan path ke source Laravel production.

Contoh jika source berada di `/home/CPANEL_USER/sirika-app`:

```php
require __DIR__.'/../../sirika-app/vendor/autoload.php';

$app = require_once __DIR__.'/../../sirika-app/bootstrap/app.php';
```

Path aktual harus dicek di Terminal cPanel dengan:

```bash
pwd
ls -la
```

Operator tidak boleh menebak path production. Kesalahan path `index.php` akan menyebabkan error 500.

## 8. Environment Production

`.env.production.example` wajib memuat key berikut:

```dotenv
APP_NAME=SIRIKA
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://sirika.vdnisite.com

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

SIRIKA_SEED_USER_PASSWORD=
SIRIKA_TRUSTED_HOSTS=sirika.vdnisite.com
```

Nilai yang wajib diisi operator:

- `APP_KEY`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `SIRIKA_SEED_USER_PASSWORD` jika akan menjalankan seeder user

Nilai yang tidak boleh dipakai di production:

- `APP_ENV=local`
- `APP_DEBUG=true`
- Password seed kosong
- Database credential default local tanpa sengaja

## 9. Trusted Host dan Proxy

`TrustHosts` perlu aktif agar request dengan host yang tidak dikenal tidak dilayani sebagai aplikasi SIRIKA.

Host utama:

- `sirika.vdnisite.com`

Jika dibutuhkan, host tambahan dapat dikonfigurasi lewat env:

```dotenv
SIRIKA_TRUSTED_HOSTS=sirika.vdnisite.com,www.sirika.vdnisite.com
```

`TrustProxies` tetap dipertahankan karena shared hosting atau Cloudflare/cPanel SSL proxy dapat mengirim header `X-Forwarded-*`. Phase 6 tidak mengubah semua proxy menjadi trusted secara agresif. Jika production memakai Cloudflare atau reverse proxy eksplisit, daftar proxy harus dikonfigurasi terpisah dan tidak ditebak.

## 10. CORS

Saat ini route SIRIKA adalah web/session based. API publik belum menjadi scope.

Keputusan Phase 6:

- CORS untuk `api/*` tidak perlu mengizinkan semua origin secara longgar di production.
- Jika API belum digunakan, CORS bisa dibuat konservatif melalui env.
- Jika endpoint `api/user` bawaan Laravel tidak dipakai, route tersebut dapat dipertimbangkan untuk dinonaktifkan pada implementation plan, dengan test route-list agar tidak memecah fitur web.

## 11. File Permission

Permission production yang direkomendasikan:

- Folder umum: `755`
- File umum: `644`
- `storage/`: writable oleh user hosting
- `bootstrap/cache/`: writable oleh user hosting
- `.env`: tidak berada di public web root dan permission dibatasi sebisa mungkin

Shared hosting dapat memiliki variasi permission. Dokumentasi harus memberi prinsip dan command contoh, bukan memaksa command yang berisiko merusak akses.

## 12. Deployment Flow

Deploy awal:

1. Backup database dan file lama.
2. Upload source Laravel ke folder di luar `public_html`.
3. Upload isi folder `public/` ke `public_html/prod-sirika`.
4. Sesuaikan `public_html/prod-sirika/index.php`.
5. Buat `.env` production dari `.env.production.example`.
6. Jalankan:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --show
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

7. Jalankan migration hanya jika schema belum ada atau ada migration baru:

```bash
php artisan migrate --force
```

8. Jalankan seeder hanya saat benar-benar dibutuhkan:

```bash
php artisan db:seed --force
```

Deploy update:

1. Backup database.
2. Upload source baru dan public assets baru.
3. Jalankan:

```bash
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

4. Buka `/login`.
5. Login sebagai role test yang aman.
6. Cek dashboard, izin, report, scan, dan export.

Catatan: `php artisan key:generate` tidak boleh dijalankan ulang pada aplikasi production yang sudah memiliki data dan session aktif, kecuali sedang membuat environment baru.

## 13. Rollback

Rollback minimal:

1. Simpan backup release sebelumnya.
2. Jika deploy gagal sebelum migration, restore file source dan public assets release sebelumnya.
3. Jalankan cache clear/cache ulang:

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

4. Jika migration baru sudah dijalankan, rollback database tidak boleh dilakukan otomatis tanpa backup dan evaluasi manual.

Phase 6 harus menulis dokumentasi rollback yang menekankan backup database sebelum migration.

## 14. Risiko dan Mitigasi

Risiko: file source Laravel terekspos di web root.

Mitigasi: source wajib di luar `public_html`; web root hanya isi folder `public/`.

Risiko: `.env` production salah atau masih debug.

Mitigasi: `.env.production.example`, checklist deploy, dan test dokumen/env key wajib.

Risiko: `index.php` public salah path.

Mitigasi: dokumentasi path contoh dan instruksi cek `pwd` di cPanel Terminal.

Risiko: route cache gagal karena route tidak cacheable.

Mitigasi: test atau command verification untuk `php artisan route:cache`.

Risiko: config cache memakai env lama.

Mitigasi: deploy flow selalu clear cache sebelum cache ulang.

Risiko: Composer audit tetap merah.

Mitigasi: catat sebagai residual risk Laravel 8 dan buat backlog Phase dependency upgrade.

Risiko: shared hosting tidak mengizinkan command tertentu.

Mitigasi: dokumentasi mencantumkan fallback File Manager untuk upload asset, tetapi tetap mengandalkan Terminal untuk Composer/Artisan karena user menyatakan Terminal tersedia.

## 15. Acceptance Criteria

Phase 6 dianggap selesai jika:

- `.env.production.example` tersedia dan tidak berisi credential rahasia.
- Dokumentasi `docs/deployment/CPANEL-PRODUCTION.md` tersedia dan sesuai struktur `sirika.vdnisite.com`.
- README project tidak lagi template Laravel default.
- `TrustHosts` aktif dan hanya mempercayai host production/configured host.
- Session cookie production dapat dikonfigurasi secure via env.
- CORS tidak lebih longgar dari kebutuhan aplikasi saat ini.
- `php artisan config:cache` lulus.
- `php artisan route:cache` lulus.
- `php artisan view:cache` lulus.
- `php artisan test` lulus.
- Dokumentasi mencatat `composer audit` baseline Laravel 8 sebagai risiko residual dan tidak mengklaim dependency PHP sudah bersih.
- Tidak ada migration baru kecuali implementation plan menemukan kebutuhan kecil yang benar-benar tidak bisa dihindari.
- Tidak ada perubahan behavior import, izin, review, QR, scan, route map, report, atau export.

## 16. Testing Plan

Automated tests:

- Production environment example test:
  - Memastikan `.env.production.example` ada.
  - Memastikan key wajib production ada.
  - Memastikan `APP_DEBUG=false`.
  - Memastikan `APP_URL=https://sirika.vdnisite.com`.
  - Memastikan file tidak berisi placeholder credential nyata.

- Middleware/config test:
  - Memastikan `TrustHosts` aktif di global middleware.
  - Memastikan trusted host membaca `sirika.vdnisite.com`.
  - Memastikan session secure cookie bisa dikonfigurasi via env.

- Deployment documentation test:
  - Memastikan dokumen menyebut `public_html/prod-sirika`.
  - Memastikan dokumen menyebut source Laravel di luar `public_html`.
  - Memastikan dokumen memuat command cache production.
  - Memastikan dokumen memuat rollback dan backup.

- Cache command verification:
  - `php artisan config:cache`
  - `php artisan route:cache`
  - `php artisan view:cache`
  - clear ulang cache setelah test agar local dev tidak terganggu.

Manual smoke test production setelah deploy:

1. Buka `https://sirika.vdnisite.com/login`.
2. Login sebagai Super Admin atau Admin HR.
3. Buka dashboard.
4. Buka daftar izin.
5. Buka laporan izin dan export.
6. Buka laporan scan dan export.
7. Login sebagai Security.
8. Scan QR valid dan QR invalid.
9. Pastikan Security tidak bisa membuka laporan.

## 17. Backlog Setelah Phase 6

Backlog yang sengaja dipisah:

- Phase dependency upgrade:
  - Evaluasi upgrade PHP ke versi yang didukung hosting.
  - Upgrade Laravel dari 8 ke versi yang masih didukung security.
  - Migrasi mailer dari SwiftMailer ke Symfony Mailer.
  - Evaluasi penghapusan `fruitcake/laravel-cors`.

- Phase operational reliability:
  - Backup automation database.
  - Health check endpoint internal.
  - Audit log domain.
  - Queue untuk export besar.
  - Monitoring error dan storage usage.
