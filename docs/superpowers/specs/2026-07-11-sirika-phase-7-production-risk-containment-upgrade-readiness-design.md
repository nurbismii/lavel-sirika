# SIRIKA Phase 7 Production Risk Containment and Upgrade Readiness Design

Tanggal: 2026-07-11
Status: Disetujui secara konsep, menunggu review spec user
Baseline: Phase 6 cPanel production hardening sudah merge ke `main`
Stack saat ini: PHP 7.4.33, Laravel 8.83.29, Blade, Alpine.js, Leaflet.js, MySQL/PostgreSQL, Laravel Excel, BaconQrCode, html5-qrcode, shared hosting cPanel

## 1. Tujuan

Phase 7 mengurangi paparan risiko dependency selama SIRIKA masih wajib berjalan pada PHP 7.4 dan Laravel 8, sekaligus menyiapkan jalur upgrade yang dapat dieksekusi ketika hosting menyediakan PHP 8.2 atau lebih baru untuk domain SIRIKA.

Phase ini tidak dapat membuat `composer audit` bersih. Laravel 12 membutuhkan PHP 8.2, sedangkan runtime hosting SIRIKA saat ini belum dapat ditingkatkan. Karena itu, hasil Phase 7 adalah kontrol kompensasi yang teruji, catatan risiko yang eksplisit, dan kesiapan upgrade; bukan pengganti patch framework.

## 2. Latar Belakang

Audit dependency pada baseline menemukan:

- Laravel Framework 8.83.29 terkena advisory:
  - `GHSA-crmm-hgp2-wgrp`: temporary signed URL path confusion.
  - `GHSA-5vg9-5847-vvmq`: CRLF injection pada default email validation rule.
  - `GHSA-78fx-h6xr-vch4`: file validation bypass.
- `fruitcake/laravel-cors` sudah abandoned.
- `swiftmailer/swiftmailer` sudah abandoned dan penggantinya adalah Symfony Mailer.
- `npm audit --omit=dev` tidak menemukan vulnerability dependency production Node.

Laravel 10 dan 11 bukan target perantara production karena masa security support-nya sudah berakhir. Target upgrade minimum adalah Laravel 12 pada PHP 8.2, ketika runtime tersebut tersedia khusus untuk SIRIKA.

Constraint hosting:

- Banyak project lain pada akun cPanel masih memakai PHP 7.4 dan Laravel 8.
- PHP global akun tidak boleh dinaikkan karena dapat merusak project lain.
- Upgrade hanya boleh dilakukan setelah PHP 8.2 atau lebih baru dapat dipilih secara terisolasi untuk `sirika.vdnisite.com`.

## 3. Scope

Phase 7 mencakup:

- Inventarisasi penggunaan signed URL, temporary signed URL, validasi email, upload/import file, Sanctum, endpoint API, dan CORS.
- Menonaktifkan endpoint API bawaan yang tidak digunakan setelah dibuktikan tidak menjadi dependency fitur SIRIKA.
- Mengurangi surface Sanctum yang tidak digunakan tanpa migrasi database destruktif.
- Mengetatkan CORS sesuai kebutuhan aplikasi web berbasis session.
- Menambahkan kontrol validasi eksplisit pada import/upload yang relevan, termasuk ekstensi, MIME, ukuran, struktur header, dan penolakan file kosong atau tidak valid.
- Menambahkan validasi email yang mencegah karakter kontrol CR/LF sebelum aturan email Laravel dijalankan.
- Memastikan route QR dan scan tidak bergantung pada signed URL yang rentan. Jika signed URL digunakan, validasi path dan parameter domain harus dilakukan secara eksplisit di luar kepercayaan tunggal pada signature.
- Menambahkan automated regression tests untuk kontrol kompensasi tersebut.
- Membuat dependency risk register dengan status, exposure, mitigasi, pemilik keputusan, dan exit condition.
- Membuat checklist upgrade menuju PHP 8.2 dan Laravel 12.
- Memperbarui runbook deployment agar `composer audit` tetap menjadi release gate yang membutuhkan penerimaan risiko eksplisit.

## 4. Non-Scope

Phase 7 tidak mencakup:

- Upgrade PHP global cPanel.
- Upgrade Laravel, Sanctum, Collision, Ignition, atau dependency mayor lainnya.
- Mengklaim seluruh advisory dependency sudah diremediasi.
- Menghapus tabel `personal_access_tokens` melalui migration destruktif.
- Migrasi SwiftMailer ke Symfony Mailer secara mandiri di Laravel 8.
- Menambah API publik atau autentikasi token baru.
- Mengubah workflow domain izin, review, aktivasi, QR, scan, peta rute, laporan, export, atau CRUD user selain validasi keamanan yang diperlukan.
- Mengubah struktur deployment split source/public dari Phase 6.
- Mengubah project cPanel lain.

## 5. Pendekatan Teknis

### 5.1 Security Exposure Inventory

Implementasi dimulai dengan pencarian kode dan route untuk membuktikan exposure aktual. Setiap advisory dipetakan ke entry point aplikasi, kontrol yang sudah ada, perubahan minimum, dan test yang membuktikannya.

Audit wajib mencakup:

- `URL::signedRoute`, `URL::temporarySignedRoute`, middleware `signed`, dan signature validation manual.
- Aturan validasi `email` pada login, user CRUD, import, dan request lain.
- `UploadedFile`, aturan `file`, `mimes`, `mimetypes`, serta alur Laravel Excel.
- Route `api.php`, middleware `auth:sanctum`, trait `HasApiTokens`, dan pemakaian token.
- Konfigurasi dan middleware CORS.
- Pemakaian Mail, notification, atau queue email.

Tidak ada dependency atau fitur yang dihapus hanya berdasarkan asumsi bahwa fitur tersebut tidak digunakan.

### 5.2 API, Sanctum, and CORS Containment

SIRIKA saat ini merupakan aplikasi web berbasis session. Jika audit mengonfirmasi `/api/user` hanya route bawaan dan tidak dipakai frontend maupun integrasi eksternal, route tersebut dinonaktifkan dan diuji tidak muncul di `route:list`.

Trait atau package Sanctum hanya dihapus apabila dependency analysis membuktikan penghapusan kompatibel dengan Laravel 8 dan tidak memengaruhi boot aplikasi. Jika penghapusan package menimbulkan risiko dependency yang lebih besar, package tetap dipin pada baseline dan dicatat untuk upgrade Laravel 12. Tabel token tidak dihapus pada phase ini.

CORS dibatasi pada path dan origin yang benar-benar diperlukan. Bila tidak ada browser client lintas origin, tidak ada origin publik yang diizinkan. Perubahan harus mempertahankan login session, CSRF protection, dan seluruh route web.

### 5.3 Email Validation Containment

Semua input email yang dapat dikontrol user harus menolak `\r`, `\n`, dan karakter kontrol terkait sebelum menjalankan aturan email Laravel. Normalisasi hanya boleh memangkas whitespace tepi; implementasi tidak boleh diam-diam mengubah alamat yang berbahaya menjadi valid.

Kontrol dibuat reusable mengikuti pola request validation project agar user create dan update konsisten. Login yang tidak memakai email tidak diubah.

### 5.4 File and Import Validation Containment

Upload Excel harus melewati beberapa lapisan:

- Batas ukuran file yang eksplisit.
- Ekstensi yang diizinkan sesuai format import yang benar-benar didukung.
- MIME diperiksa sebagai sinyal tambahan, bukan satu-satunya dasar kepercayaan.
- Workbook harus dapat dibuka oleh parser Laravel Excel/PhpSpreadsheet.
- Header wajib diverifikasi terhadap template SIRIKA.
- Workbook kosong, sheet yang salah, baris malformed, dan data yang melampaui batas ditolak dengan pesan yang aman.
- Import tidak boleh melakukan partial write bila validasi tingkat file atau header gagal.

Validasi lama yang sudah lebih ketat dipertahankan. Phase ini hanya menutup gap yang terbukti dari audit dan menambahkan regression test.

### 5.5 QR and Signed URL Containment

QR SIRIKA tetap dapat dipindai setelah kedaluwarsa untuk menampilkan status kedaluwarsa, sesuai keputusan produk Phase 3. Kontrol Phase 7 tidak mengubah masa aktif atau status domain tersebut.

Jika QR memakai token acak yang dicari melalui controller dan tidak memakai Laravel signed URL, exposure advisory signed URL didokumentasikan sebagai tidak reachable dari alur QR. Jika signed URL ditemukan, controller harus memvalidasi resource identifier, route purpose, dan path yang diharapkan sebelum mengembalikan data izin. Signature tidak boleh menjadi satu-satunya authorization check.

Response scan tidak boleh mengekspos data sensitif tambahan saat token invalid, revoked, atau resource tidak ditemukan.

## 6. Dependency Risk Register

Dokumen risk register dibuat di `docs/security/DEPENDENCY-RISK-REGISTER.md` dan minimal memuat:

- Advisory atau package abandoned.
- Dependency dan versi terdampak.
- Entry point atau exposure aktual di SIRIKA.
- Kontrol kompensasi yang diterapkan.
- Residual risk setelah mitigasi.
- Keputusan release: block, accept temporarily, atau not exposed.
- Pihak yang menyetujui penerimaan risiko dan tanggal review berikutnya.
- Exit condition berupa PHP 8.2 tersedia dan upgrade Laravel 12 selesai.

Nama orang atau persetujuan organisasi tidak di-hardcode oleh developer. Dokumen menyediakan field yang harus diisi pemilik sistem sebelum deployment production.

## 7. Upgrade Readiness

Checklist upgrade disimpan di `docs/upgrade/LARAVEL-12-READINESS.md` dan mencakup:

1. Pastikan cPanel dapat menetapkan PHP 8.2 atau lebih baru hanya untuk `sirika.vdnisite.com`.
2. Clone production ke staging terisolasi beserta salinan database yang sudah disanitasi.
3. Verifikasi extension PHP yang dibutuhkan Composer, Laravel Excel, QR, database, dan image/file processing.
4. Backup source, `.env`, database, public assets, dan release aktif.
5. Buat branch upgrade terpisah dan lakukan upgrade bertahap dengan Laravel upgrade guides.
6. Targetkan Laravel 12 versi terbaru yang masih mendapat security fixes, bukan Laravel 10 atau 11 sebagai tujuan production akhir.
7. Evaluasi atau upgrade Sanctum; hapus bila tetap tidak digunakan.
8. Hapus `fruitcake/laravel-cors` dan gunakan mekanisme CORS yang didukung framework target.
9. Ganti Ignition, Collision, Sail, dan development dependency sesuai Laravel 12.
10. Pastikan migrasi SwiftMailer ke Symfony Mailer terjadi melalui framework/dependency target.
11. Jalankan seluruh automated tests, Composer audit, cache verification, dan manual smoke test sebelum cutover.
12. Siapkan rollback release dan database sebelum mengubah runtime domain production.

Upgrade readiness tidak menjalankan perubahan Composer pada Phase 7. Tujuannya adalah menghilangkan ketidakjelasan dan menyiapkan prasyarat yang dapat diverifikasi.

## 8. Error Handling and Observability

- Validation failure dikembalikan sebagai pesan yang dapat ditindaklanjuti tanpa mengekspos stack trace atau path server.
- File yang gagal parsing tidak diproses lebih lanjut dan tidak menghasilkan partial import.
- Security-relevant rejection menggunakan logging yang tidak menyimpan password, QR token utuh, isi file, atau data pribadi berlebihan.
- Error log mengikuti konfigurasi production Phase 6: `daily`, level minimum `warning`, dan `APP_DEBUG=false`.
- Kegagalan kontrol kompensasi pada test atau smoke test menjadi alasan menghentikan release.

## 9. Testing Strategy

Automated tests minimal:

- Route API bawaan tidak tersedia jika dikonfirmasi tidak digunakan.
- Route web, login session, dan CSRF tetap bekerja setelah perubahan CORS/API.
- Email dengan CR, LF, atau karakter kontrol ditolak pada user create dan update.
- Email valid yang sudah didukung tetap diterima.
- Import menolak ekstensi tidak didukung, MIME mencurigakan, file kosong, workbook rusak, header salah, dan file terlalu besar.
- Import valid tetap berhasil dan tidak mengalami regresi.
- Import invalid tidak menghasilkan partial database write.
- QR valid, kedaluwarsa, revoked, invalid, dan tidak ditemukan tetap menghasilkan status yang benar tanpa data leakage.
- Test signed URL ditambahkan hanya jika audit menemukan penggunaan signed URL.
- Authorization Super Admin, Admin HR, dan Security tetap sesuai baseline.

Verification commands:

```bash
php artisan test
php artisan route:list
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer audit
npm audit --omit=dev
```

`composer audit` diperkirakan tetap non-zero sampai upgrade framework selesai. Output-nya harus cocok dengan risk register; advisory baru yang belum dinilai menghentikan release.

Manual regression test:

1. Login untuk setiap role utama.
2. CRUD user dengan email valid dan invalid.
3. Import workbook valid dan beberapa workbook invalid.
4. Review dan aktivasi izin.
5. Generate QR manual dan bulk.
6. Scan QR valid, kedaluwarsa, revoked, dan invalid.
7. Buka route map dan highlight segmen.
8. Buka laporan serta export Excel.
9. Verifikasi endpoint API yang dinonaktifkan tidak dibutuhkan UI.

## 10. Release Policy

Phase 7 tidak mengubah fakta bahwa dependency framework belum mendapat security patch. Sebelum production deployment, pemilik sistem harus memilih salah satu:

- Menunda release sampai runtime dan framework dapat di-upgrade; atau
- Menerima residual risk secara eksplisit pada risk register untuk periode terbatas.

Penerimaan risiko harus memiliki pemilik, alasan bisnis, tanggal persetujuan, dan tanggal review ulang. Developer tidak boleh menandai risiko diterima atas nama pemilik sistem.

Advisory baru, exposure signed URL yang tidak memiliki kontrol tambahan, file import yang dapat melewati validasi, atau regression test security yang gagal otomatis memblokir release.

## 11. Acceptance Criteria

Phase 7 dianggap selesai jika:

- Exposure inventory untuk signed URL, email, file import, API, Sanctum, CORS, dan mail terdokumentasi berdasarkan kode aktual.
- Endpoint API bawaan yang tidak digunakan sudah dinonaktifkan atau keputusan mempertahankannya dijelaskan dengan bukti.
- CORS tidak mengizinkan origin atau path yang tidak dibutuhkan.
- Input email user menolak CR/LF dan karakter kontrol.
- Import file memiliki validasi berlapis dan tidak melakukan partial write saat validasi file/header gagal.
- Alur QR tetap memenuhi behavior Phase 3 dan tidak hanya mengandalkan signed URL untuk authorization.
- Risk register dependency tersedia, lengkap, dan tidak mengklaim advisory framework sudah selesai.
- Checklist upgrade Laravel 12 tersedia dan menjaga project cPanel lain tetap pada runtime masing-masing.
- Seluruh automated test lulus.
- Config, route, dan view cache berhasil dibuat.
- `npm audit --omit=dev` tidak memiliki vulnerability production.
- Output `composer audit` dicatat sebagai residual risk; advisory baru yang belum dinilai tidak diterima.
- Tidak ada breaking change pada workflow utama SIRIKA.

## 12. Rollback

Perubahan Phase 7 harus dapat di-rollback sebagai satu release aplikasi tanpa rollback database destruktif. Karena tabel token tidak dihapus dan tidak ada migration destructive, rollback utama adalah memulihkan source, dependency lock, public assets, dan cache release sebelumnya sesuai runbook Phase 6.

Jika implementasi nanti menemukan kebutuhan migration, migration tersebut harus additive dan backward-compatible atau dikeluarkan dari Phase 7 untuk desain terpisah.

## 13. Referensi

- Laravel release and support policy: `https://laravel.com/docs/13.x/releases`
- Laravel 12 upgrade guide: `https://laravel.com/docs/12.x/upgrade`
- cPanel MultiPHP Manager: `https://docs.cpanel.net/cpanel/software/multiphp-manager-for-cpanel/`
- SwiftMailer end-of-life notice: `https://swiftmailer.symfony.com/docs/introduction.html`
- GitHub advisory `GHSA-crmm-hgp2-wgrp`: `https://github.com/advisories/GHSA-crmm-hgp2-wgrp`
- GitHub advisory `GHSA-5vg9-5847-vvmq`: `https://github.com/advisories/GHSA-5vg9-5847-vvmq`
- GitHub advisory `GHSA-78fx-h6xr-vch4`: `https://github.com/advisories/GHSA-78fx-h6xr-vch4`
