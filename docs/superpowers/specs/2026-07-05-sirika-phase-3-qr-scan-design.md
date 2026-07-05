# SIRIKA Phase 3 QR dan Scan Design

Tanggal: 2026-07-05
Status: Disetujui untuk review spec
Baseline: Phase 2 sudah merge ke `main`
Stack: PHP 7.4, Laravel 8, Blade, Alpine.js, BaconQrCode, html5-qrcode, MySQL/PostgreSQL

## 1. Tujuan

Phase 3 mengaktifkan alur QR code dan scan security untuk izin kendaraan yang sudah masuk ke tabel final.

Hasil akhir Phase 3:

- Admin HR dan Super Admin dapat generate QR manual per izin aktif.
- Admin HR dan Super Admin dapat bulk generate QR untuk izin aktif yang belum memiliki token aktif.
- Admin dapat melihat QR digital untuk izin aktif.
- Admin dapat membuka halaman print kartu kecil per izin.
- Security dapat scan QR menggunakan kamera perangkat.
- Security dapat input token manual sebagai fallback jika kamera gagal.
- Sistem memvalidasi token QR tanpa menyimpan token mentah di database.
- Sistem menampilkan status scan yang jelas: `valid`, `expired`, `revoked`, `inactive`, atau `invalid`.
- Semua hasil scan dicatat ke `scan_logs`.
- Token expired tetap dapat discan dan tercatat sebagai `expired`.
- Perpanjangan QR hanya dapat dilakukan oleh `admin_hr` dan `super_admin`.

## 2. Konteks

Phase 1 sudah menyediakan:

- Auth session dan role `super_admin`, `admin_hr`, `security`, dan `auditor`.
- Tabel `permit_tokens` dengan kolom `token_hash`, `status`, `expires_at`, dan `revoked_at`.
- Tabel `scan_logs` untuk mencatat hasil scan.
- Halaman `/scan` sebagai halaman sementara sebelum scanner aktif.

Phase 2 sudah menyediakan:

- Import Excel dan staging row.
- Commit row valid ke `vehicle_permits.status = active`.
- Commit row `needs_review` ke tabel final tanpa route segments aktif.
- Halaman `/permits` yang menampilkan daftar izin hasil import.

Keputusan produk untuk Phase 3:

- QR digital dan QR fisik kecil harus tersedia.
- QR berlaku 1 tahun sejak generate.
- QR expired tetap dapat discan, tetapi status yang tampil adalah kadaluwarsa.
- Saat QR expired discan, security hanya melihat status, plat, nama, dan lokasi parkir.
- Renewal tidak boleh otomatis saat scan.
- Renewal hanya boleh dilakukan oleh `admin_hr` dan `super_admin`.
- Manual generate dan bulk generate sama-sama dibutuhkan.

## 3. Scope Phase 3

Phase 3 mencakup:

- Install dan konfigurasi BaconQrCode.
- Install dan bundle `html5-qrcode` untuk scanner kamera.
- Service token QR yang menyimpan hash token, bukan token mentah.
- Generate QR manual untuk satu izin aktif.
- Bulk generate QR untuk izin aktif tanpa token aktif.
- Regenerate atau renew token dengan masa aktif 1 tahun.
- Revoke token lama saat regenerate atau renew.
- Endpoint validasi scan token.
- Halaman scan dengan kamera dan input token manual.
- Halaman detail hasil scan.
- Log scan untuk semua hasil validasi, termasuk token invalid.
- Halaman print kartu kecil per izin.
- Penyesuaian daftar izin agar admin dapat melihat status QR dan aksi QR.

## 4. Non-Scope Phase 3

Hal berikut tidak dikerjakan di Phase 3:

- Peta highlight rute dan Leaflet overlay.
- Editor koordinat road segment.
- Batch print banyak kartu dalam satu halaman A4.
- CRUD izin manual lengkap.
- Approval workflow multi-level.
- Audit log umum di luar `scan_logs` dan status token.
- Export laporan scan.
- Public scan tanpa login.
- Penyimpanan token mentah atau file QR permanen di storage.

Non-scope ini ditunda agar alur token, scan, expiry, dan logging matang lebih dulu.

## 5. Pendekatan Arsitektur

Gunakan service terpisah agar logic token tidak tersebar di controller.

Service utama:

- `PermitTokenService`
  - Membuat token acak.
  - Menyimpan `hash('sha256', token)` ke `permit_tokens.token_hash`.
  - Membuat QR SVG dari token mentah hanya saat response render.
  - Revoke token aktif lama ketika regenerate atau renew.
  - Menentukan token aktif terakhir untuk sebuah izin.

- `PermitScanService`
  - Menerima token mentah dari QR atau input manual.
  - Hash token dan mencari `permit_tokens.token_hash`.
  - Memvalidasi status token, expiry token, status izin, dan periode izin jika tersedia.
  - Membuat `scan_logs` untuk semua hasil scan.
  - Mengembalikan DTO atau array hasil scan yang aman ditampilkan sesuai result.

- `PermitRenewalService`
  - Memperpanjang QR 1 tahun dari waktu renewal.
  - Revoke token lama.
  - Membuat token baru.
  - Dipanggil hanya dari route yang dibatasi untuk `admin_hr` dan `super_admin`.

Controller tetap tipis:

- `PermitController` tetap fokus pada daftar izin.
- `PermitQrController` menangani generate, bulk generate, show QR, print card, dan renew.
- `ScanController` menangani halaman scanner dan submit token scan.

## 6. Data Model

Tabel existing tetap dipakai.

### 6.1 permit_tokens

Kolom existing:

- `vehicle_permit_id`
- `token_hash`
- `status`
- `expires_at`
- `revoked_at`

Status token Phase 3:

- `active`: token dapat digunakan sampai `expires_at`.
- `revoked`: token dicabut karena regenerate, renewal, atau tindakan admin.

Tidak perlu status `expired` permanen di database. Expired dihitung dari `expires_at < now()`, supaya token lama tetap bisa dicari dan scan expired tetap tercatat.

Aturan:

- Satu permit boleh memiliki banyak token historis.
- Hanya token dengan status `active` dan belum revoked yang dipertimbangkan valid.
- Saat renewal atau regenerate, token aktif lama diubah ke `revoked` dan `revoked_at = now()`.
- Token hash harus unik.
- Token mentah tidak disimpan.

### 6.2 scan_logs

Kolom existing:

- `permit_id`
- `scanned_by`
- `scanned_at`
- `result`
- `device_info`
- `ip_address`
- `notes`

Result Phase 3:

- `valid`: token aktif, belum expired, permit aktif.
- `expired`: token ditemukan tetapi lewat masa 1 tahun atau permit melewati `valid_until`.
- `revoked`: token ditemukan tetapi status token `revoked`.
- `inactive`: token ditemukan tetapi izin bukan `active`, misalnya `needs_review`, `suspended`, atau `revoked`.
- `invalid`: token tidak ditemukan atau format token tidak valid.

`permit_id` boleh null untuk token invalid karena tidak ada izin yang cocok.

## 7. Token dan QR

Format token mentah:

- String acak minimal 32 byte menggunakan `Str::random(64)` atau random bytes yang di-encode aman.
- Token ditampilkan hanya sebagai payload QR dan tidak ditampilkan sebagai teks panjang di UI kecuali untuk fallback internal.
- QR payload untuk Phase 3 cukup token mentah, bukan URL berisi data pribadi.

Alasan payload token saja:

- QR tetap aman dipindai oleh halaman internal.
- Tidak ada NIK, nama, nomor kontak, atau plat di dalam QR.
- Endpoint scan internal dapat memproses token dari kamera atau input manual.

Masa berlaku:

- `expires_at = now()->addYear()` saat generate, regenerate, atau renew.
- Jika token expired, scan tetap menghasilkan detail terbatas dan log `expired`.

Generate manual:

- Hanya untuk `vehicle_permits.status = active`.
- Jika izin belum memiliki token aktif, buat token baru.
- Jika izin sudah memiliki token aktif, tombol generate tidak membuat token ganda. Admin diarahkan memakai renew/regenerate.

Bulk generate:

- Hanya memproses izin `active`.
- Hanya membuat token untuk izin yang belum punya token aktif.
- Izin `needs_review`, `suspended`, `expired`, dan `revoked` dilewati.
- Hasil bulk menampilkan jumlah dibuat dan jumlah dilewati.

Renew:

- Hanya admin HR dan super admin.
- Revoke token aktif lama.
- Buat token baru dengan `expires_at = now()->addYear()`.
- Tidak bisa dipicu oleh security saat scan.

## 8. Authorization dan Security

Route admin QR:

- `permits.qr.generate`: `admin_hr`, `super_admin`.
- `permits.qr.bulk-generate`: `admin_hr`, `super_admin`.
- `permits.qr.show`: `admin_hr`, `super_admin`.
- `permits.qr.print`: `admin_hr`, `super_admin`.
- `permits.qr.renew`: `admin_hr`, `super_admin`.

Route scan:

- `scan.index`: `security`, `admin_hr`, `super_admin`.
- `scan.verify`: `security`, `admin_hr`, `super_admin`.

Data sensitif:

- QR tidak menyimpan data pribadi.
- Security untuk result `expired` hanya melihat status, plat, nama, dan lokasi parkir.
- Security untuk result `valid` dapat melihat data operasional yang dibutuhkan: nama, plat, lokasi parkir, warna kartu, status, dan rute mentah. Nomor kontak tidak ditampilkan pada Phase 3.
- Result `invalid`, `revoked`, dan `inactive` tidak menampilkan data detail melebihi pesan status kecuali permit dapat diidentifikasi dan status perlu dijelaskan secara ringkas.

Rate limit:

- `scan.verify` diberi throttle agar brute force token lebih sulit.
- Batas awal: 60 request per menit per user/IP untuk penggunaan internal.

## 9. UI Admin

### 9.1 Halaman Daftar Izin

Tambahan kolom:

- Status QR: belum dibuat, aktif, expired, revoked.
- Expired at.
- Aksi QR.

Aksi per izin:

- Generate QR jika izin active dan belum punya token aktif.
- Lihat QR jika izin punya token aktif.
- Print kartu jika izin punya token aktif.
- Renew jika token expired atau perlu diperpanjang.

Bulk action:

- Tombol `Generate QR Aktif` untuk membuat QR semua izin active yang belum punya token aktif.
- Setelah bulk selesai, tampilkan alert jumlah berhasil dan dilewati.

### 9.2 Halaman QR Digital

Menampilkan:

- QR SVG.
- Nama.
- Plat.
- Lokasi parkir.
- Warna kartu.
- Status token.
- Expired at.
- Tombol print kartu.
- Tombol renew jika token expired atau admin ingin regenerate.

### 9.3 Kartu Fisik Kecil

Ukuran tampilan awal:

- Rasio kartu kecil landscape, cocok untuk dicetak satu izin per halaman atau dipotong manual.
- QR besar di sisi kanan atau tengah.
- Informasi ringkas:
  - SIRIKA VDNI.
  - Plat.
  - Nama.
  - Lokasi parkir.
  - Warna kartu.
  - Masa berlaku sampai tanggal token `expires_at`.

Kartu tidak menampilkan nomor kontak.

## 10. UI Security Scan

Halaman `/scan` berisi:

- Panel kamera scanner menggunakan `html5-qrcode`.
- Tombol mulai/stop kamera.
- Select kamera jika browser menyediakan daftar device.
- Input token manual sebagai fallback.
- Loading state saat validasi.
- Hasil scan di panel yang mudah dibaca.

Result display:

- `valid`: panel hijau, tampil data operasional izin.
- `expired`: panel kuning, tampil status kadaluwarsa, plat, nama, lokasi parkir.
- `revoked`: panel merah, tampil bahwa QR telah dicabut.
- `inactive`: panel merah/kuning, tampil bahwa izin tidak aktif.
- `invalid`: panel merah, tampil QR tidak dikenal.

Jika kamera tidak tersedia atau permission ditolak, UI tetap berguna lewat input token manual.

## 11. Error Handling

Generate QR:

- Jika permit bukan `active`, request ditolak dengan pesan jelas.
- Jika permit sudah punya token aktif, request tidak membuat duplikasi.
- Jika QR gagal dibuat, tampilkan error aman tanpa membocorkan stack trace.

Bulk generate:

- Proses tidak boleh gagal total hanya karena satu izin dilewati.
- Ringkasan harus mencatat jumlah dibuat dan dilewati.

Scan:

- Token kosong atau terlalu pendek menjadi `invalid`.
- Token tidak ditemukan menjadi `invalid`.
- Token revoked menjadi `revoked`.
- Token expired menjadi `expired`.
- Permit nonaktif menjadi `inactive`.
- Semua hasil tetap membuat `scan_logs`.

Renew:

- Jika permit bukan `active`, renew ditolak.
- Jika user bukan `admin_hr` atau `super_admin`, request ditolak.
- Renewal memakai transaction agar revoke token lama dan create token baru tidak setengah jalan.

## 12. Testing

Automated tests:

- Admin HR dapat generate QR untuk permit active.
- Security tidak dapat generate QR.
- QR token mentah tidak disimpan di database.
- Token hash tersimpan dan unik.
- Generate manual tidak membuat token ganda jika token aktif sudah ada.
- Bulk generate membuat token hanya untuk permit active tanpa token aktif.
- Bulk generate melewati permit needs_review dan permit yang sudah punya token aktif.
- Renew hanya dapat dilakukan admin HR dan super admin.
- Renew revoke token lama dan membuat token baru dengan expiry 1 tahun.
- Scan valid token menghasilkan result `valid` dan membuat scan log.
- Scan expired token menghasilkan result `expired`, tetap membuat scan log, dan data security dibatasi.
- Scan revoked token menghasilkan result `revoked`.
- Scan permit nonaktif menghasilkan result `inactive`.
- Scan token tidak dikenal menghasilkan result `invalid` dengan `permit_id = null`.
- Halaman scan bisa dibuka role security.
- Endpoint scan dibatasi auth dan role.

Manual verification:

- `php artisan test`
- `npm run dev`
- Login sebagai admin HR.
- Generate QR untuk satu permit active.
- Buka halaman QR digital.
- Buka halaman print kartu kecil.
- Login sebagai security.
- Scan QR dari layar atau submit token fallback.
- Pastikan hasil scan valid tampil jelas.
- Ubah token menjadi expired lewat database lokal atau test fixture, lalu scan ulang dan pastikan status kadaluwarsa tampil.
- Pastikan `scan_logs` bertambah untuk setiap scan.

## 13. Risiko dan Mitigasi

### Risiko: QR hilang atau disalin

Mitigasi: token dapat direvoke dan renewal membuat token baru. Token lama tidak aktif lagi setelah renewal/regenerate.

### Risiko: Token bocor dari database

Mitigasi: database hanya menyimpan hash SHA-256 token, bukan token mentah.

### Risiko: Auto-renew melemahkan kontrol akses

Mitigasi: Phase 3 tidak menyediakan auto-renew saat scan. Renewal hanya admin-controlled.

### Risiko: Kamera browser gagal

Mitigasi: halaman scan menyediakan input token manual sebagai fallback.

### Risiko: Bulk generate membuat beban terlalu besar

Mitigasi: Phase 3 awal memproses jumlah data MVP secara sinkron. Jika data jauh bertambah, bulk generate dapat dipindah ke queue pada fase hardening.

### Risiko: Data sensitif terlalu banyak tampil ke security

Mitigasi: hasil expired dibatasi ke status, plat, nama, dan lokasi parkir. Nomor kontak tidak ditampilkan pada Phase 3.

## 14. Acceptance Criteria Phase 3

Phase 3 dianggap selesai jika:

- Admin HR dapat generate QR manual untuk izin active.
- Admin HR dapat bulk generate QR untuk izin active yang belum punya token aktif.
- QR digital tampil dari halaman izin.
- Kartu kecil per izin dapat dicetak.
- QR berlaku 1 tahun sejak generate.
- Token mentah tidak tersimpan di database.
- Security dapat scan QR menggunakan kamera atau input manual.
- Scan valid menampilkan status valid dan detail operasional izin.
- Scan expired tetap berhasil diproses, menampilkan status kadaluwarsa, dan membatasi detail.
- Scan revoked, inactive, dan invalid menampilkan pesan jelas.
- Semua scan membuat record `scan_logs`.
- Renewal hanya dapat dilakukan oleh admin HR dan super admin.
- `php artisan test` dan `npm run dev` lulus.
