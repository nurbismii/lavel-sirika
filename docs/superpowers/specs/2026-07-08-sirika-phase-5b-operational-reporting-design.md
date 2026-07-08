# SIRIKA Phase 5B Operational Reporting Design

Tanggal: 2026-07-08
Status: Draft untuk review user
Baseline: Phase 5A review dan aktivasi izin sudah merge ke `main`
Stack: PHP 7.4, Laravel 8, Blade, Alpine.js, Leaflet.js, MySQL/PostgreSQL, Laravel Excel, BaconQrCode, html5-qrcode

## 1. Tujuan

Phase 5B membuat visibilitas operasional SIRIKA melalui dashboard yang lebih akurat, laporan izin, laporan scan QR, dan export Excel.

Hasil akhir Phase 5B:

- Admin HR, Super Admin, dan Auditor dapat melihat laporan izin kendaraan dengan filter operasional.
- Admin HR, Super Admin, dan Auditor dapat export laporan izin ke Excel.
- Admin HR, Super Admin, dan Auditor dapat melihat laporan scan QR dengan filter tanggal, hasil scan, scanner, dan pencarian kendaraan.
- Admin HR, Super Admin, dan Auditor dapat export laporan scan QR ke Excel tanpa mengekspos token QR, password, atau data sensitif yang tidak perlu.
- Dashboard menampilkan status sistem yang sesuai kondisi saat ini: import, review, QR, scan, dan peta rute sudah aktif.
- Dashboard menampilkan ringkasan operasional yang membantu Admin HR melihat pekerjaan tertunda dan aktivitas scan terbaru.
- Security tetap fokus pada halaman scan QR dan tidak mendapat akses laporan administrasi.

## 2. Konteks

Phase 1 sampai Phase 4 sudah membangun fondasi data, import Excel, QR, scan, dan peta rute. Phase 5A sudah menambahkan review dan aktivasi izin `needs_review`.

Kondisi saat ini:

- Dashboard masih sederhana dan memiliki copy lama yang menyebut QR, scanner, dan peta rute masih menunggu fase berikutnya.
- Daftar izin sudah punya filter dasar dan aksi review/QR, tetapi belum menjadi laporan formal dengan export.
- `scan_logs` sudah menyimpan aktivitas scan, hasil scan, scanner, waktu scan, device info, IP, dan catatan.
- `permit_tokens` sudah menyimpan status QR dan masa berlaku.
- `vehicle_permits` sudah menyimpan status izin, metadata review, route raw, parkir, warna, dan relasi ke employee/vehicle.
- Laravel Excel sudah tersedia di `composer.json`, sehingga Phase 5B tidak perlu menambah dependency baru.

## 3. Scope Phase 5B

Phase 5B mencakup:

- Perbaikan dashboard operasional.
- Halaman laporan izin di `/reports/permits`.
- Export Excel laporan izin di `/reports/permits/export`.
- Halaman laporan scan QR di `/reports/scans`.
- Export Excel laporan scan QR di `/reports/scans/export`.
- Filter laporan izin:
  - Status izin.
  - Status QR.
  - Lokasi parkir.
  - Warna izin.
  - Sumber data.
  - Status review.
  - Pencarian NIK, nama, atau plat.
- Filter laporan scan:
  - Rentang tanggal scan.
  - Hasil scan.
  - Scanner.
  - Pencarian NIK, nama, atau plat.
- Activity feed ringan di dashboard dari data existing:
  - Review izin terbaru.
  - QR token terbaru.
  - Scan terbaru.
- Authorization route untuk laporan dan export.
- Feature test untuk dashboard, laporan, filter, export, dan role access.

## 4. Non-Scope Phase 5B

Hal berikut tidak dikerjakan di Phase 5B:

- Tabel audit log penuh untuk semua perubahan data.
- PDF export.
- Chart library baru.
- Queue/job export.
- Scheduled report email.
- Notifikasi.
- Bulk activation.
- Bulk review edit.
- Perubahan aturan masa aktif QR.
- Perubahan mekanisme generate, renew, print, atau scan QR.
- Menampilkan atau export `token_hash`, plain QR token, password, remember token, atau credential lain.
- Menambahkan index database baru sebelum ada bukti performa dari data production.

Non-scope ini sengaja ditunda agar Phase 5B tetap fokus pada reporting operasional yang bisa langsung dipakai tanpa risiko schema besar.

## 5. Keputusan Produk

Keputusan untuk Phase 5B:

- Laporan menggunakan data existing, bukan audit table baru.
- Export memakai Laravel Excel.
- Export laporan scan dibatasi rentang tanggal maksimal 31 hari per request agar aman untuk tabel scan yang akan tumbuh cepat.
- Halaman laporan scan default menampilkan 7 hari terakhir.
- Laporan izin tidak wajib memakai rentang tanggal karena volume izin tumbuh lebih lambat daripada scan log.
- Export izin dan scan tetap memakai query/filter yang sama dengan halaman laporan.
- Export tidak menyertakan token QR atau hash token.
- Export scan tidak menyertakan IP address pada Phase 5B untuk mengurangi risiko penyebaran data teknis yang tidak dibutuhkan user operasional.
- Security tidak mendapat akses laporan karena laporan berisi data karyawan dan kendaraan secara massal.
- Auditor boleh melihat dan export laporan karena role auditor memang membutuhkan bukti operasional, tetapi tetap tidak mendapat akses mutasi data.
- Dashboard tidak memakai chart library baru. Visualisasi cukup memakai stat cards, tabel ringkas, dan bar/list sederhana berbasis CSS.

## 6. Pendekatan Arsitektur

Controller tetap tipis. Query filter dan export dipisahkan agar halaman HTML dan export Excel memakai sumber logic yang sama.

Unit utama:

- `DashboardController`
  - Menyiapkan ringkasan status izin, QR, scan, dan activity feed.
  - Tidak menjalankan query detail berat.

- `ReportPermitController`
  - Menampilkan laporan izin.
  - Mengirim export laporan izin.
  - Memakai query builder/service yang sama untuk index dan export.

- `ReportScanController`
  - Menampilkan laporan scan.
  - Mengirim export laporan scan.
  - Memvalidasi rentang tanggal scan maksimal 31 hari.

- `PermitReportQuery`
  - Menerapkan filter laporan izin.
  - Eager load relasi employee, vehicle, parking, reviewer, active token, dan latest token.
  - Menyediakan query yang bisa dipakai untuk pagination dan export.

- `ScanReportQuery`
  - Menerapkan filter laporan scan.
  - Eager load relasi permit, employee, vehicle, parking, dan scanner.
  - Menyediakan query yang bisa dipakai untuk pagination dan export.

- `PermitReportExport`
  - Export Excel laporan izin memakai query.
  - Menulis heading dan mapping field operasional.

- `ScanReportExport`
  - Export Excel laporan scan memakai query.
  - Menulis heading dan mapping field operasional.

Alasan pemisahan query dan export:

- Filter tidak terduplikasi antara halaman dan export.
- Risiko mismatch data lebih kecil.
- Test bisa menguji query behavior tanpa bergantung pada Blade.
- Export bisa memakai `FromQuery` agar tidak membaca semua data ke memory.

## 7. Data Source

Phase 5B membaca tabel existing berikut:

- `vehicle_permits`
  - Status izin, source, valid_from, valid_until, route_raw, review metadata.
- `employees`
  - NIK, nama, departemen.
- `vehicles`
  - Plat kendaraan dan tipe kendaraan.
- `parking_locations`
  - Kode dan nama parkir.
- `permit_tokens`
  - Status QR, masa berlaku, revoked_at, created_at.
- `scan_logs`
  - Waktu scan, hasil scan, scanner, device info, catatan, relasi permit.
- `users`
  - Nama dan role scanner/reviewer.

Phase 5B tidak menulis data domain baru. Data baru hanya file Excel response yang dihasilkan saat user export.

## 8. Laporan Izin

Route:

- `GET /reports/permits` dengan nama route `reports.permits.index`.
- `GET /reports/permits/export` dengan nama route `reports.permits.export`.

Kolom halaman laporan izin:

- NIK.
- Nama.
- Plat.
- Lokasi parkir.
- Warna.
- Status izin.
- Status QR.
- Masa berlaku QR.
- Status review.
- Reviewer.
- Waktu review.
- Sumber data.
- Jumlah segmen rute.

Kolom export laporan izin:

- NIK.
- Nama.
- Departemen.
- Plat.
- Tipe kendaraan.
- Lokasi parkir.
- Warna.
- Status izin.
- Status QR.
- QR berlaku sampai.
- Valid dari.
- Valid sampai.
- Status review.
- Reviewer.
- Waktu review.
- Catatan review.
- Sumber data.
- Rute mentah.
- Jumlah segmen rute.

Status QR dihitung dari relasi token:

- `missing`: tidak punya active token.
- `active`: punya active token dan `expires_at` kosong atau belum lewat.
- `expired`: punya active token tetapi `expires_at` sudah lewat.
- `revoked`: tidak punya active token tetapi pernah punya token revoked.

Status review:

- `reviewed`: `reviewed_at` tidak null.
- `pending`: `reviewed_at` null.

## 9. Laporan Scan QR

Route:

- `GET /reports/scans` dengan nama route `reports.scans.index`.
- `GET /reports/scans/export` dengan nama route `reports.scans.export`.

Default filter:

- `date_from`: hari ini dikurangi 6 hari.
- `date_to`: hari ini.

Validasi filter:

- `date_from` wajib format tanggal saat export.
- `date_to` wajib format tanggal saat export.
- `date_to` tidak boleh sebelum `date_from`.
- Rentang `date_from` sampai `date_to` maksimal 31 hari.
- `result` harus salah satu dari `valid`, `expired`, `revoked`, `inactive`, atau `invalid`.
- `scanner_id` harus user existing jika diisi.

Kolom halaman laporan scan:

- Waktu scan.
- Hasil scan.
- Scanner.
- NIK.
- Nama.
- Plat.
- Lokasi parkir.
- Status izin.
- Catatan.

Kolom export laporan scan:

- Waktu scan.
- Hasil scan.
- Scanner.
- NIK.
- Nama.
- Plat.
- Lokasi parkir.
- Warna.
- Status izin.
- Sumber izin.
- Catatan scan.
- Device info.

Export scan tidak menyertakan IP address pada Phase 5B.

## 10. Dashboard Operasional

Dashboard diperbarui agar mencerminkan fitur yang sudah aktif.

Stat cards utama:

- Izin aktif.
- Perlu review.
- QR aktif.
- QR kadaluwarsa.
- Scan hari ini.
- Scan invalid hari ini.

Panel tambahan:

- Ringkasan status izin.
- Ringkasan hasil scan 7 hari terakhir.
- Activity feed terbaru dari review, QR token, dan scan.
- Quick actions ke Import Excel, Kelola Izin, Laporan Izin, Laporan Scan, Master Rute, dan Scan QR sesuai role user.

Copy lama yang menyatakan QR, scanner, dan peta rute masih menunggu fase berikutnya harus dihapus.

## 11. Authorization

Role akses Phase 5B:

- `super_admin`
  - Bisa melihat dashboard.
  - Bisa melihat laporan izin dan scan.
  - Bisa export laporan izin dan scan.
  - Bisa tetap mengakses semua route lain.

- `admin_hr`
  - Bisa melihat dashboard.
  - Bisa melihat laporan izin dan scan.
  - Bisa export laporan izin dan scan.
  - Bisa tetap melakukan import, review, aktivasi, QR, dan master rute sesuai fitur existing.

- `auditor`
  - Bisa melihat dashboard.
  - Bisa melihat laporan izin dan scan.
  - Bisa export laporan izin dan scan.
  - Tidak bisa melakukan import, review, aktivasi, generate QR, renew QR, print QR, atau edit peta rute.

- `security`
  - Bisa melihat dashboard.
  - Bisa scan QR.
  - Tidak bisa melihat atau export laporan izin dan scan.

`User::routeRoles()` menjadi sumber utama authorization route.

## 12. UI dan UX

Navigasi sidebar menambahkan modul `Laporan` jika user berwenang.

Halaman laporan harus memiliki:

- Filter panel yang ringkas.
- Tombol `Terapkan Filter`.
- Tombol `Reset`.
- Tombol `Export Excel`.
- Table dengan pagination compact yang sudah dipakai project.
- Empty state saat tidak ada data.
- Validasi error yang tampil sebagai alert custom, bukan browser alert.

Dashboard harus tetap utilitarian dan tidak menjadi landing page marketing. Komponen memakai pola existing: panel, stat card, table, badge/status pill, dan quick actions.

## 13. Performance

Prinsip performance:

- Query laporan wajib memakai pagination pada halaman HTML.
- Query laporan wajib memakai eager loading untuk mencegah N+1.
- Export memakai Laravel Excel `FromQuery`, bukan `FromCollection` yang membaca semua row ke memory.
- Laporan scan dibatasi rentang maksimal 31 hari per export.
- Dashboard memakai query agregat ringan dan membatasi activity feed maksimal 10 item.
- Tidak menambahkan index baru sampai ada indikasi query lambat pada data production.

Index existing yang sudah membantu:

- `vehicle_permits.status`.
- `vehicle_permits.source`.
- `permit_tokens.status`.
- `permit_tokens.expires_at`.
- `scan_logs.scanned_at`.
- `scan_logs.result`.

## 14. Error Handling

Error validasi filter harus ditampilkan jelas:

- `Tanggal akhir tidak boleh sebelum tanggal awal.`
- `Rentang laporan scan maksimal 31 hari.`
- `Hasil scan tidak valid.`
- `Scanner tidak ditemukan.`

Jika export gagal karena validasi, sistem kembali ke halaman laporan dengan error.

Jika export menghasilkan 0 row, file Excel tetap boleh dibuat dengan heading saja. Halaman HTML tetap menampilkan empty state agar user memahami filter tidak menemukan data.

## 15. Testing

Feature test wajib mencakup:

- Admin HR dapat membuka laporan izin.
- Auditor dapat membuka laporan izin.
- Security tidak dapat membuka laporan izin.
- Filter laporan izin berdasarkan status izin.
- Filter laporan izin berdasarkan status QR.
- Search laporan izin berdasarkan NIK, nama, atau plat.
- Export laporan izin memakai filter yang sama.
- Export laporan izin tidak memuat token hash.
- Admin HR dapat membuka laporan scan.
- Auditor dapat membuka laporan scan.
- Security tidak dapat membuka laporan scan.
- Filter laporan scan berdasarkan rentang tanggal.
- Filter laporan scan berdasarkan result.
- Filter laporan scan berdasarkan scanner.
- Export laporan scan menolak rentang lebih dari 31 hari.
- Export laporan scan tidak memuat IP address.
- Dashboard menampilkan stat QR dan scan yang benar.
- Dashboard tidak lagi menampilkan copy lama yang menyebut QR, scanner, dan peta rute menunggu fase berikutnya.

Command test utama:

```bash
php artisan test --filter=Report
php artisan test --filter=Dashboard
php artisan test --filter=AuthAndRoleAccess
php artisan test
```

## 16. Risiko dan Mitigasi

Risiko: export scan menjadi berat ketika data scan besar.

Mitigasi: export scan wajib punya rentang tanggal maksimal 31 hari dan memakai `FromQuery`.

Risiko: laporan mengekspos data sensitif yang tidak diperlukan.

Mitigasi: export tidak menyertakan token QR, token hash, password, remember token, atau IP address.

Risiko: filter halaman dan export menghasilkan data berbeda.

Mitigasi: halaman dan export memakai query class/service yang sama.

Risiko: auditor mendapatkan akses mutasi data.

Mitigasi: route laporan dipisah dari route mutasi, dan `User::routeRoles()` hanya memberi auditor akses read/export report.

Risiko: dashboard menjadi lambat.

Mitigasi: dashboard hanya memakai agregasi ringan dan membatasi activity feed.

## 17. Rollout Production

Urutan rollout:

1. Deploy kode Phase 5B.
2. Jalankan build asset jika CSS atau JS berubah.
3. Jalankan `php artisan route:clear`.
4. Jalankan `php artisan view:clear`.
5. Jalankan `php artisan config:clear`.
6. Login sebagai Admin HR.
7. Buka dashboard dan cek stat QR/scan.
8. Buka `/reports/permits`, filter status `active`, export Excel.
9. Buka `/reports/scans`, filter 7 hari terakhir, export Excel.
10. Login sebagai Auditor dan cek akses laporan read/export.
11. Login sebagai Security dan pastikan laporan tidak bisa diakses.

Rollback aman:

- Tidak ada migration baru.
- Revert kode Phase 5B akan menghapus route dan UI laporan.
- Data domain existing tidak berubah.

## 18. Definition of Done

Phase 5B dianggap selesai jika:

- Dashboard menampilkan ringkasan operasional terbaru.
- Copy lama dashboard sudah dihapus.
- Laporan izin tersedia dengan filter dan pagination.
- Laporan izin bisa export Excel sesuai filter.
- Laporan scan tersedia dengan filter dan pagination.
- Laporan scan bisa export Excel sesuai filter dan validasi 31 hari.
- Export tidak menyertakan token hash, plain QR token, password, atau IP address.
- Admin HR, Super Admin, dan Auditor bisa mengakses laporan sesuai role.
- Security tidak bisa mengakses laporan.
- Test Phase 5B dan regresi authorization lulus.
- Full test suite lulus.
