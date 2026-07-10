# SIRIKA cPanel Production Deployment

Panduan ini untuk deployment SIRIKA di shared hosting cPanel.

Production URL: `https://sirika.vdnisite.com`
Public web root: `public_html/prod-sirika`
Source Laravel: di luar `public_html`

## Struktur Folder

Struktur yang digunakan:

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

`public_html/prod-sirika` hanya boleh berisi isi folder Laravel `public/`. Source Laravel lengkap berada di luar public_html.

Catatan: source Laravel lengkap berada di luar public_html.

Folder/file berikut tidak boleh berada di `public_html/prod-sirika`:

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

## Persiapan Deploy Awal

1. Backup database jika sudah ada data.
2. Upload source Laravel lengkap ke folder di luar `public_html`, misalnya `/home/CPANEL_USER/sirika-app`.
3. Upload isi folder `public/` Laravel ke `public_html/prod-sirika`.
4. Buat `.env` production dari `.env.production.example`.
5. Isi credential database cPanel di `.env`.
6. Pastikan `APP_DEBUG=false`.
7. Pastikan `APP_URL=https://sirika.vdnisite.com`.
8. Pastikan `SESSION_SECURE_COOKIE=true`.

## Konfigurasi Domain cPanel

Di cPanel, buka menu **Domains** atau **Subdomains**, lalu konfigurasi domain `sirika.vdnisite.com` dengan Document Root `public_html/prod-sirika`.

Langkah verifikasi mapping domain sebelum deployment dilanjutkan:

1. Pastikan `sirika.vdnisite.com` tercantum di cPanel Domains/Subdomains.
2. Pastikan Document Root yang ditampilkan adalah `public_html/prod-sirika`.
3. Buat atau upload file uji sementara ke `public_html/prod-sirika`, akses `https://sirika.vdnisite.com`, lalu pastikan file tersebut dilayani dari folder itu. Hapus file uji setelah verifikasi.

## Menyesuaikan public index.php

File `public_html/prod-sirika/index.php` harus menunjuk ke source Laravel di luar `public_html`.

Gunakan file `index.php` cPanel yang sudah ada. Jangan mengganti seluruh isi file dengan snippet di bawah; operator harus ubah hanya tiga ekspresi path berikut pada entrypoint existing: path `maintenance.php`, path `vendor/autoload.php`, dan path `bootstrap/app.php`.

Contoh jika source berada di `/home/CPANEL_USER/sirika-app`:

```php
if (file_exists($maintenance = __DIR__.'/../../sirika-app/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../../sirika-app/vendor/autoload.php';

$app = require_once __DIR__.'/../../sirika-app/bootstrap/app.php';
```

Jangan menghapus atau mengubah blok `maintenance.php`. Path tersebut harus menunjuk ke `storage/framework/maintenance.php` pada source Laravel di luar `public_html`, bukan ke folder public cPanel.

Cek path aktual lewat Terminal cPanel:

```bash
pwd
ls -la
```

Jangan menebak path. Path salah akan menyebabkan error 500.

## Command Deploy Awal

Jalankan dari folder source Laravel, bukan dari `public_html/prod-sirika`:

```bash
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Generate APP_KEY hanya untuk environment baru:

```bash
php artisan key:generate --show
```

Copy hasilnya ke `APP_KEY=` di `.env`. Jangan menjalankan `php artisan key:generate` ulang pada production yang sudah berjalan kecuali sedang membuat environment baru.

Jalankan migration hanya jika database belum dibuat atau ada migration baru:

```bash
php artisan migrate --force
```

Jalankan seeder hanya saat dibutuhkan dan setelah `SIRIKA_SEED_USER_PASSWORD` diisi:

```bash
php artisan db:seed --force
```

## Deploy Update

1. Backup database.
2. Simpan copy source, asset `public/`, dan cache release aktif untuk pemulihan.
3. Aktifkan maintenance mode sebelum mengubah source atau asset live:

```bash
php artisan down
```

4. Upload source baru ke folder source Laravel.
5. Upload asset baru dari folder `public/` ke `public_html/prod-sirika`, tetapi jangan meng-upload atau menimpa `index.php`. File `public_html/prod-sirika/index.php` adalah file yang sudah disesuaikan untuk path cPanel.
6. Jika proses upload tidak dapat mengecualikan `index.php`, upload seluruh isi `public/` terlebih dahulu lalu segera terapkan kembali patch path cPanel pada `index.php` sebelum traffic diarahkan ke release.
7. Jalankan:

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

Jika tidak ada migration baru, `php artisan migrate --force` tetap aman secara Laravel, tetapi operator harus membaca daftar migration sebelum deploy besar.

8. Saat maintenance mode masih aktif, jalankan smoke check CLI berikut dan pastikan tidak ada error:

```bash
php artisan about
php artisan migrate:status
```

9. Kembalikan traffic setelah smoke check berhasil:

```bash
php artisan up
```

10. Jalankan smoke test web pada bagian **Smoke Test Setelah Deploy**. Jika salah satu langkah gagal, jangan biarkan aplikasi terkunci: restore source, asset, dan cache release sebelumnya, lalu jalankan `php artisan up` ketika aplikasi sudah aman dilayani kembali.

## Permission

Rekomendasi umum shared hosting:

- Folder umum: `755`
- File umum: `644`
- `storage/` harus writable oleh user hosting.
- `bootstrap/cache/` harus writable oleh user hosting.
- `.env` tidak boleh berada di public web root.
- Permission `.env`: `600` lebih disarankan; gunakan `640` hanya jika model group hosting membutuhkannya.

Jika permission berbeda karena aturan hosting, ikuti prinsip: public hanya membaca file public, Laravel hanya menulis ke `storage/` dan `bootstrap/cache/`.

## Smoke Test Setelah Deploy

1. Buka `https://sirika.vdnisite.com/login`.
2. Login sebagai Super Admin atau Admin HR.
3. Buka dashboard.
4. Buka daftar izin.
5. Buka laporan izin dan export Excel.
6. Buka laporan scan dan export Excel.
7. Login sebagai Security.
8. Scan QR valid dan QR invalid.
9. Pastikan Security tidak bisa membuka `/reports/permits` dan `/reports/scans`.

## Rollback

Rollback aman membutuhkan backup.

1. Simpan copy release source dan public asset sebelum deploy update.
2. Backup database sebelum menjalankan migration.
3. Aktifkan maintenance mode sebelum memulihkan release:

```bash
php artisan down
```

4. Restore source, asset, dan cache release sebelumnya.
5. Jalankan cache ulang:

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

6. Saat maintenance mode masih aktif, jalankan `php artisan about` dan `php artisan migrate:status` sebagai smoke check.
7. Setelah smoke check berhasil, jalankan `php artisan up`, lalu ulangi smoke test web.

Jika rollback atau cache rebuild gagal, tetap pulihkan source, asset, dan cache release sebelumnya; jalankan `php artisan up` ketika aplikasi sudah aman dilayani kembali. Jika migration baru sudah berjalan, jangan rollback database tanpa backup dan evaluasi manual.

## Audit Dependency

`npm audit --omit=dev` terakhir bersih untuk dependency production Node.

`composer audit` masih melaporkan 3 advisory `laravel/framework` pada Laravel 8 dan 2 package abandoned: `fruitcake/laravel-cors` serta `swiftmailer/swiftmailer`. Sebelum release, tunda deployment production atau terima risiko secara eksplisit sampai dependency tersebut diremediasi pada phase upgrade dependency berikutnya. Risiko ini tidak diselesaikan di Phase 6 karena membutuhkan upgrade Laravel/PHP terpisah.
