# SIRIKA Phase 2 Import Excel Design

Tanggal: 2026-07-05
Status: Disetujui untuk implementation plan
Baseline: Phase 1 sudah merge ke `main`
Stack: PHP 7.4, Laravel 8, Blade, Alpine.js, Laravel Excel, MySQL/PostgreSQL

## 1. Tujuan

Phase 2 mengaktifkan modul import Excel izin masuk kendaraan dengan alur yang aman untuk data produksi.

Hasil akhir Phase 2:

- Admin HR dapat upload file Excel database izin kendaraan.
- Sistem menyimpan file import asli di storage private.
- Sistem membuat batch import dan staging row sebelum menulis data ke tabel final.
- Sistem memvalidasi header wajib.
- Sistem menampilkan preview import dengan status `valid`, `invalid`, dan `needs_review`.
- Admin dapat commit row yang aman ke database final.
- Sistem menyimpan ringkasan import di `import_batches`.
- Row bermasalah tetap dapat dilihat di staging untuk koreksi pada fase berikutnya.

## 2. Konteks

Phase 1 sudah menyediakan:

- Auth session dan role `super_admin`, `admin_hr`, `security`, dan `auditor`.
- Route dan layout admin.
- Tabel inti: `employees`, `vehicles`, `parking_locations`, `road_segments`, `vehicle_permits`, `permit_route_segments`, `import_batches`, `permit_tokens`, dan `scan_logs`.
- Seeder 26 master road segment dari PDF.
- Halaman `/imports` masih berupa halaman read-only sementara.

Sample Excel berisi 477 row data dengan kolom utama:

- No
- Plat Motor
- Nama
- NIK
- Dep
- Bagian
- Jabatan
- Lokasi Parkir
- Rute Kendaraan
- Alasan Masuk
- Warna Kartu Izin Masuk
- Nomor Kontak
- Hasil Persetujuan
- KET
- Divisi

Temuan data yang memengaruhi desain:

- Ada 6 row tanpa plat motor.
- Ada 25 row tanpa rute kendaraan.
- Ada 6 row tanpa lokasi parkir.
- Ada 109 variasi string rute.
- Ada cell plat yang berisi lebih dari satu nomor plat.
- Semua approval pada sample saat ini bernilai disetujui.

## 3. Scope Phase 2

Phase 2 mencakup:

- Install dan konfigurasi Laravel Excel.
- Upload `.xlsx` dan `.xls` melalui form `/imports`.
- Validasi ukuran file, ekstensi, dan header wajib.
- Parsing sheet pertama dengan deteksi header yang konservatif.
- Staging hasil parsing ke tabel `import_rows`.
- Preview batch import dengan statistik dan daftar row.
- Normalisasi awal untuk NIK, nama, plat, warna kartu, approval, lokasi parkir, dan rute.
- Parser rute konservatif berbasis kode road segment resmi.
- Commit row `valid` ke tabel final dengan transaction.
- Penyimpanan row `needs_review` sebagai permit status `needs_review` jika data minimal masih cukup.
- Row `invalid` tidak masuk tabel final.
- Halaman daftar batch import sederhana.

## 4. Non-Scope Phase 2

Hal berikut tidak dikerjakan di Phase 2:

- Generate QR code.
- Scan kamera dengan `html5-qrcode`.
- Leaflet map route overlay.
- Koreksi row `needs_review` secara inline.
- Download error report Excel.
- CRUD izin manual lengkap.
- Approval workflow multi-level.
- Audit log lengkap di luar ringkasan import batch.
- Queue/job import.

Non-scope ini ditunda karena Phase 2 harus memastikan jalur data import aman dulu.

## 5. Rekomendasi Pendekatan

Gunakan pendekatan staging dan preview sebelum commit.

Alasan:

- File Excel sumber tidak sepenuhnya standar.
- Admin perlu melihat row yang ditolak atau butuh review sebelum data permanen dibuat.
- Data lama tidak boleh dioverwrite tanpa aturan matching eksplisit.
- Proses commit dapat dibungkus transaction agar kegagalan tidak meninggalkan data setengah masuk.

Alternatif langsung import ke tabel final ditolak untuk Phase 2 karena berisiko memasukkan rute kosong, plat kosong, atau string rute yang salah parse sebagai izin aktif.

## 6. Data Model Tambahan

### 6.1 import_batches

Tabel existing tetap dipakai, tetapi Phase 2 memperjelas status:

- `draft`: batch dibuat tetapi belum selesai diparse.
- `previewed`: parsing selesai dan preview tersedia.
- `committed`: row aman sudah dikomit ke tabel final.
- `failed`: parsing gagal secara batch-level.

`error_summary` menyimpan ringkasan error batch-level dalam JSON string atau teks terstruktur. Field row count existing dipakai:

- `total_rows`
- `success_rows`
- `failed_rows`
- `review_rows`

### 6.2 import_rows

Tambah tabel staging `import_rows`.

Kolom:

- `id`
- `import_batch_id`
- `row_number`
- `status`
- `raw_data`
- `normalized_data`
- `errors`
- `warnings`
- `created_employee_id`
- `created_vehicle_id`
- `created_permit_id`
- `created_at`
- `updated_at`

Status row:

- `valid`: data memenuhi syarat untuk izin aktif.
- `needs_review`: data cukup untuk disimpan sebagai izin review, tetapi belum boleh aktif.
- `invalid`: data kritis tidak lengkap atau tidak bisa dipercaya.
- `committed`: row sudah diproses ke tabel final.

`raw_data`, `normalized_data`, `errors`, dan `warnings` memakai kolom `json` agar tetap portable di MySQL/PostgreSQL melalui Laravel.

## 7. Validasi File dan Header

File upload:

- Wajib file.
- Ekstensi: `.xlsx` atau `.xls`.
- Maksimal awal: 10 MB.
- Disimpan di disk private, bukan public.

Header wajib:

- Plat Motor
- Nama
- NIK
- Dep
- Bagian
- Jabatan
- Lokasi Parkir
- Rute Kendaraan
- Alasan Masuk
- Warna Kartu Izin Masuk
- Nomor Kontak
- Hasil Persetujuan
- Divisi

Deteksi header:

- Sistem mencari row yang mengandung kombinasi label penting seperti `Plat Motor`, `Nama`, `Nik`, dan `Rute Kendaraan`.
- Jika header tidak ditemukan, batch menjadi `failed` dan tidak ada row final dibuat.
- Header bilingual boleh diterima selama label Indonesia penting masih ada.

## 8. Validasi Row

### 8.1 Invalid

Row menjadi `invalid` jika:

- NIK kosong.
- Nama kosong.
- Plat motor kosong.
- Approval tidak menunjukkan disetujui.
- Warna kartu tidak bisa dipetakan ke daftar warna valid.
- Format row tidak memiliki data utama yang cukup.

Row `invalid` tidak boleh masuk ke `employees`, `vehicles`, atau `vehicle_permits`.

### 8.2 Needs Review

Row menjadi `needs_review` jika:

- Rute kendaraan kosong.
- Rute mengandung kode yang tidak ada di `road_segments`.
- Rute mengandung teks instruksi panjang yang tidak bisa dipastikan sebagai kode segmen.
- Lokasi parkir kosong atau formatnya tidak standar.
- Satu cell plat motor berisi lebih dari satu plat.
- Ada potensi duplikasi NIK dan plat yang belum aman dioverwrite.

Row `needs_review` dapat dikomit sebagai `vehicle_permits.status = needs_review` jika NIK, nama, dan minimal satu plat bisa dinormalisasi. Row ini tidak boleh aktif dan tidak bisa menghasilkan QR pada fase berikutnya sebelum dikoreksi.

### 8.3 Valid

Row menjadi `valid` jika:

- NIK ada.
- Nama ada.
- Plat motor tunggal valid.
- Approval disetujui.
- Warna kartu valid.
- Lokasi parkir ada.
- Rute bisa diparse ke daftar `road_segments` aktif.

Row `valid` dikomit sebagai `vehicle_permits.status = active`.

## 9. Normalisasi Data

NIK:

- Trim whitespace.
- Simpan sebagai string agar leading zero tidak hilang.

Nama:

- Trim whitespace ganda.
- Simpan uppercase/literal mengikuti sumber Excel untuk Phase 2.

Plat motor:

- Trim whitespace dan line break.
- Normalisasi spasi berulang menjadi satu spasi.
- Jika ada pemisah `/`, koma, atau line break yang jelas berisi beberapa plat, row masuk `needs_review` pada Phase 2.
- Pemecahan otomatis beberapa plat ditunda agar tidak membuat izin aktif yang salah.

Warna kartu:

- Map raw bilingual ke nilai internal:
  - `biru`
  - `kuning`
  - `merah`
  - `hijau`

Approval:

- Nilai disetujui menerima tanda centang dan teks yang secara eksplisit berarti approved/disetujui.
- Nilai lain menjadi `invalid`.

Lokasi parkir:

- Trim dan simpan sebagai `parking_locations.code`.
- Jika belum ada, sistem membuat `parking_locations` dengan `code` dan `name` sama.
- Jika kosong, row `needs_review`.

Rute:

- Normalisasi separator aman seperti `->`, panah, koma, dan spasi antar kode.
- Extract hanya token yang cocok dengan kode road segment aktif, misalnya `Y1`, `D2`, `Z1`, `H2`.
- Jika semua token dari string rute dikenali dan urutannya jelas, row dapat `valid`.
- Jika ada teks panjang, catatan dalam kurung, atau token tidak dikenal, row menjadi `needs_review`.
- Lokasi parkir di ujung rute tidak dianggap road segment dan tidak membuat row invalid.

## 10. Commit Data Final

Commit dilakukan dari halaman detail batch.

Aturan commit:

- Hanya `admin_hr` dan `super_admin` yang dapat commit.
- Commit memakai database transaction.
- Row `invalid` tidak diproses.
- Row `valid` membuat atau mengambil:
  - `employees` berdasarkan NIK.
  - `vehicles` berdasarkan employee dan plat.
  - `parking_locations` berdasarkan code.
  - `vehicle_permits`.
  - `permit_route_segments`.
- Row `needs_review` yang masih memiliki NIK, nama, dan plat membuat permit status `needs_review` tanpa route segments aktif jika rutenya tidak valid.
- Sistem tidak mengubah permit lama yang sudah ada pada Phase 2. Jika ditemukan kombinasi employee, vehicle, dan route yang mirip, row diberi warning duplikasi dan masuk `needs_review`.
- Setelah commit, `import_rows.created_*_id` diisi agar hasil commit bisa diaudit dari batch.
- Batch berubah menjadi `committed`.

## 11. UI dan Route

Route Phase 2:

- `GET /imports`: daftar batch dan form upload.
- `POST /imports`: upload dan parse Excel.
- `GET /imports/{importBatch}`: preview detail batch.
- `POST /imports/{importBatch}/commit`: commit row aman.

UI `/imports`:

- Form upload file.
- Informasi format file yang diterima.
- Daftar batch import terbaru.
- Badge status batch.
- Ringkasan count: total, valid, invalid, needs review, committed.

UI preview batch:

- Ringkasan statistik.
- Tabel row dengan filter status.
- Kolom utama: row number, NIK, nama, plat, parking, route, warna, status, issue.
- Tombol commit hanya muncul jika batch `previewed` dan ada row yang bisa diproses.
- Empty state jelas jika tidak ada batch.

## 12. Authorization dan Security

- Upload dan commit hanya untuk `admin_hr` dan `super_admin`.
- File Excel disimpan di storage private.
- Nama file asli disimpan untuk audit, tetapi path internal tidak ditampilkan bebas ke semua role.
- Error validasi tidak boleh membocorkan path server.
- Row preview hanya dapat diakses role admin import.
- Tidak ada data dari Excel yang dimasukkan ke QR di Phase 2.

## 13. Error Handling

Batch-level failure:

- File bukan Excel.
- Header wajib tidak ditemukan.
- Sheet kosong.
- Laravel Excel gagal membaca file.

Row-level failure:

- Data wajib kosong.
- Approval tidak valid.
- Warna tidak valid.
- Rute tidak bisa dipetakan.

Semua error row disimpan di `import_rows.errors`, sedangkan warning disimpan di `import_rows.warnings`.

## 14. Testing

Automated tests:

- Admin HR dapat membuka halaman import.
- Security tidak dapat upload import.
- Upload file non-Excel ditolak.
- Header tidak valid membuat batch `failed`.
- Excel valid membuat batch `previewed`.
- Row valid menjadi `valid`.
- Row tanpa plat menjadi `invalid`.
- Row tanpa rute menjadi `needs_review`.
- Rute dengan kode resmi tersimpan sebagai daftar road segment.
- Commit batch membuat employee, vehicle, parking location, permit, dan route segments.
- Commit batch tidak memproses row invalid.
- Commit batch tidak bisa dijalankan dua kali.

Manual verification:

- `php artisan test`
- `php artisan migrate:fresh --seed`
- Upload sample Excel.
- Preview menampilkan total sekitar 477 row.
- Ringkasan menampilkan row valid, invalid, dan needs review.
- Commit tidak error.
- Halaman izin menampilkan data hasil import pada fase berikutnya atau minimal count dashboard berubah jika controller Phase 2 menambahkan count.

## 15. Risiko dan Mitigasi

### Risiko: Parser rute terlalu agresif

Mitigasi: parser dibuat konservatif. Jika tidak yakin, row masuk `needs_review`, bukan `active`.

### Risiko: Data lama tertimpa

Mitigasi: Phase 2 tidak overwrite permit lama. Matching employee dan vehicle hanya memakai `firstOrCreate` untuk master dasar, sedangkan permit duplikat masuk review.

### Risiko: File import besar

Mitigasi: Phase 2 memakai batas 10 MB dan parsing sinkron. Queue/job ditunda sampai volume data lebih besar atau proses sinkron terbukti lambat.

### Risiko: Header Excel berubah

Mitigasi: header detection berbasis label penting dan error batch-level jelas jika format tidak cocok.

### Risiko: Row staging menyimpan data pribadi

Mitigasi: staging hanya dapat diakses role admin import dan disimpan di database aplikasi yang sama dengan data final. QR tetap tidak menyimpan data pribadi.

## 16. Acceptance Criteria Phase 2

Phase 2 dianggap selesai jika:

- Admin HR dapat upload sample Excel.
- Sistem membuat batch import dan staging row.
- Header wajib divalidasi.
- Preview batch menampilkan status dan issue per row.
- Row valid dapat dikomit ke tabel final.
- Row invalid tidak masuk tabel final.
- Row needs review tidak menjadi izin aktif.
- Batch tidak bisa dikomit dua kali.
- File import disimpan di storage private.
- Test otomatis untuk import dan commit berjalan.
- `php artisan test`, `php artisan migrate:fresh --seed`, dan `npm run dev` lulus.
