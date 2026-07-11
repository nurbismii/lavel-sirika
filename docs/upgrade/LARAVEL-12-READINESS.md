# SIRIKA Laravel 12 Upgrade Readiness

Target: PHP 8.2 atau lebih baru dan Laravel 12 versi security-supported.

Constraint: runtime hanya boleh diubah untuk `sirika.vdnisite.com`. PHP global cPanel dan project Laravel 8 lain tidak boleh berubah.

## Hosting Prerequisites

- [ ] MultiPHP Manager atau mekanisme hosting setara menyediakan PHP 8.2+ khusus `sirika.vdnisite.com`.
- [ ] CLI PHP pada folder source SIRIKA menunjuk ke versi yang sama dengan runtime domain.
- [ ] Extension `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `session`, `tokenizer`, `xml`, `zip`, dan driver database tersedia.
- [ ] Composer yang kompatibel berjalan dengan PHP target.

## Staging and Backup

- [ ] Buat staging terisolasi dengan salinan database yang sudah disanitasi.
- [ ] Backup database production dan verifikasi file backup dapat dibaca.
- [ ] Backup source, `.env`, folder public, storage yang dibutuhkan, dan release aktif.
- [ ] Catat path source dan document root production dari Phase 6.
- [ ] Buat branch upgrade terpisah; jangan upgrade langsung pada `main` atau production.

## Dependency Work

- [ ] Ikuti Laravel upgrade guide secara bertahap hingga Laravel 12.
- [ ] Targetkan Laravel 12 terbaru yang masih menerima security fixes.
- [ ] Putuskan apakah Sanctum dibutuhkan; hapus package dan config bila tetap tidak digunakan, atau upgrade ke versi Laravel 12-compatible.
- [ ] Hapus `fruitcake/laravel-cors` dan gunakan mekanisme CORS framework target.
- [ ] Ganti dependency Ignition, Collision, dan Sail dengan versi Laravel 12-compatible.
- [ ] Verifikasi SwiftMailer sudah digantikan Symfony Mailer.
- [ ] Verifikasi Laravel Excel, PhpSpreadsheet, dan BaconQrCode kompatibel dengan PHP/framework target.
- [ ] Jalankan `composer validate` dan `composer audit` tanpa advisory yang belum dinilai.

## Application Verification

- [ ] Jalankan `php artisan test` dan catat jumlah test lulus.
- [ ] Jalankan `php artisan config:cache`, `route:cache`, dan `view:cache`.
- [ ] Uji login dan seluruh role.
- [ ] Uji import preview/commit untuk workbook valid dan invalid.
- [ ] Uji review, aktivasi, QR generate/bulk/show/print/renew, dan scan semua status.
- [ ] Uji route map, report, dan export.
- [ ] Pastikan `/api/user` tetap tidak tersedia kecuali ada desain API baru yang disetujui.

## Cutover and Rollback

- [ ] Aktifkan maintenance mode dan verifikasi HTTP 503 sebelum cutover.
- [ ] Deploy source, vendor, dan public assets tanpa menimpa entrypoint cPanel yang sudah dipatch.
- [ ] Jalankan migration hanya setelah review dan backup database.
- [ ] Bangun ulang cache dengan PHP target.
- [ ] Jalankan smoke test sebelum membuka traffic.
- [ ] Simpan release lama sampai periode observasi selesai.
- [ ] Jika smoke test gagal, pulihkan source/vendor/public release lama dan database hanya dari backup yang telah diverifikasi.
