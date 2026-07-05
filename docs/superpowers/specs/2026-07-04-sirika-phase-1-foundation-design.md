# SIRIKA Phase 1 Foundation Design

Tanggal: 2026-07-04
Status: Disiapkan untuk review
Stack: PHP 7.4, Laravel 8, Blade, Alpine.js, Leaflet.js, MySQL/PostgreSQL

## 1. Tujuan

Phase 1 membangun fondasi aplikasi SIRIKA agar fase import Excel, QR code, scan security, dan peta rute dapat dikembangkan tanpa mengubah struktur dasar secara besar.

Hasil akhir Phase 1:

- Aplikasi memiliki halaman login.
- User memiliki role dasar.
- Admin panel SIRIKA memiliki layout Blade yang konsisten.
- Route awal untuk modul utama sudah tersedia.
- Database inti untuk izin kendaraan sudah tersedia.
- Master 26 segmen jalan dari PDF sudah tersedia lewat seeder.
- Dashboard awal menampilkan struktur informasi operasional dengan data seed dan nilai sementara yang diberi label jelas.

## 2. Konteks Project Saat Ini

Project saat ini masih Laravel 8 default:

- `routes/web.php` hanya berisi route `/`.
- `resources/views/welcome.blade.php` masih halaman default Laravel.
- Belum ada auth scaffold.
- Belum ada layout admin.
- Belum ada model domain SIRIKA.
- Belum ada package Laravel Excel, QR, Leaflet asset, atau html5-qrcode.
- Folder project bukan git repository.

Karena itu Phase 1 harus konservatif: membangun pondasi yang jelas, bukan langsung semua fitur PRD.

## 3. Scope Phase 1

### 3.1 Auth dan Role

Gunakan auth sederhana berbasis session Laravel.

Role awal:

- `super_admin`
- `admin_hr`
- `security`
- `auditor`

Aturan akses awal:

- User belum login hanya dapat mengakses halaman login.
- `super_admin` dapat mengakses semua halaman awal.
- `admin_hr` dapat mengakses dashboard, izin, import, dan master rute.
- `security` dapat mengakses dashboard terbatas dan halaman scan.
- `auditor` dapat mengakses dashboard dan data read-only.

Phase 1 cukup memakai kolom `role` di tabel `users`. Package seperti Spatie Permission ditunda agar fondasi tidak terlalu berat.

### 3.2 Layout Admin

Layout Blade dibuat custom, bukan memakai template admin besar.

Struktur UI:

- Sidebar desktop.
- Topbar dengan nama aplikasi, user, dan logout.
- Mobile header dengan menu collapse sederhana.
- Area konten utama.
- Komponen alert/session message.
- Komponen card statistik.
- Komponen table shell untuk halaman list.

Gaya visual:

- Profesional, operasional, data-dense.
- Warna netral terang dengan aksen biru dan status hijau/kuning/merah.
- Tidak memakai hero marketing.
- Tidak memakai dekorasi visual berlebihan.
- Fokus pada kecepatan baca data oleh admin dan security.

### 3.3 Route Awal

Route web awal:

- `GET /` redirect ke `/dashboard` jika login, ke `/login` jika belum login.
- `GET /login`
- `POST /login`
- `POST /logout`
- `GET /dashboard`
- `GET /road-segments`
- `GET /permits`
- `GET /imports`
- `GET /scan`

Route selain login wajib melalui middleware auth.

### 3.4 Data Model Awal

Phase 1 membuat tabel inti sesuai PRD, tetapi belum semua workflow diaktifkan.

Tabel:

- `users`
- `employees`
- `vehicles`
- `parking_locations`
- `road_segments`
- `vehicle_permits`
- `permit_route_segments`
- `permit_tokens`
- `import_batches`
- `scan_logs`

Kolom penting:

#### users

Gunakan migration bawaan Laravel, ditambah:

- `role`
- `status`
- `last_login_at`

Nilai `status` awal:

- `active`
- `inactive`

#### employees

- `nik`
- `name`
- `department`
- `section`
- `position`
- `division`
- `contact_number`
- `status`

`nik` harus unik jika tersedia. Data import yang nanti menunjukkan duplikasi harus masuk proses review, bukan memaksa overwrite.

#### vehicles

- `employee_id`
- `plate_number`
- `vehicle_type`
- `status`

Satu employee dapat memiliki banyak vehicle.

#### parking_locations

- `code`
- `name`
- `status`

Phase 1 hanya menyediakan tabel. Seeder parking location dapat ditunda sampai data import diproses.

#### road_segments

- `code`
- `name`
- `start_location`
- `end_location`
- `polyline_json`
- `status`

`code` wajib unik.

`polyline_json` boleh null pada Phase 1 karena koordinat peta akan dikurasi pada fase peta.

#### vehicle_permits

- `employee_id`
- `vehicle_id`
- `parking_location_id`
- `permit_color`
- `reason`
- `approval_status`
- `valid_from`
- `valid_until`
- `status`
- `source`
- `source_import_id`
- `route_raw`

Status izin awal:

- `draft`
- `needs_review`
- `active`
- `suspended`
- `expired`
- `revoked`

Phase 1 belum mengaktifkan create/edit izin penuh. Tabel disiapkan agar fase berikutnya tidak perlu migration ulang besar.

#### permit_route_segments

- `vehicle_permit_id`
- `road_segment_id`
- `sequence`

Tabel ini menyimpan hasil parsing rute pada fase import/permit.

#### permit_tokens

- `vehicle_permit_id`
- `token_hash`
- `status`
- `expires_at`
- `revoked_at`

Token mentah tidak disimpan di database.

#### import_batches

- `filename`
- `uploaded_by`
- `total_rows`
- `success_rows`
- `failed_rows`
- `review_rows`
- `status`
- `error_summary`

Phase 1 hanya menyiapkan tabel dan halaman import read-only yang menjelaskan bahwa upload Excel aktif pada fase berikutnya.

#### scan_logs

- `permit_id`
- `scanned_by`
- `scanned_at`
- `result`
- `device_info`
- `ip_address`
- `notes`

Phase 1 hanya menyiapkan tabel dan halaman scan read-only yang menjelaskan bahwa kamera scanner aktif pada fase berikutnya.

### 3.5 Seeder Road Segment

Seeder wajib membuat 26 segmen resmi dari PDF:

- `Y1`
- `Y2`
- `WL1`
- `WL2`
- `WL3`
- `T1`
- `T2`
- `T3`
- `T4`
- `D1`
- `D2`
- `D3`
- `D4`
- `D5`
- `D6`
- `C1`
- `C2`
- `C3`
- `XL1`
- `Z1`
- `Z2`
- `Z3`
- `Z4`
- `S1`
- `H1`
- `H2`

Nama dan lokasi awal-akhir mengikuti tabel PDF halaman 2. Jika nama Mandarin dan Indonesia tersedia, nama utama memakai format yang mudah dibaca admin Indonesia, sementara lokasi menyimpan teks Indonesia dari PDF.

### 3.6 Dashboard Awal

Dashboard Phase 1 menampilkan:

- Total segmen rute aktif.
- Total user aktif.
- Jumlah izin aktif sementara bernilai 0 dengan label "Belum ada data izin".
- Jumlah izin perlu review sementara bernilai 0 dengan label "Import belum dijalankan".
- Jumlah scan hari ini sementara bernilai 0 dengan label "Scanner belum aktif".
- Panel quick actions:
  - Import Excel
  - Kelola Izin
  - Master Rute
  - Scan QR
- Panel peringatan bahwa import, QR, dan peta highlight belum aktif sampai fase berikutnya.

Dashboard harus tetap berguna walaupun belum ada data izin.

## 4. Non-Scope Phase 1

Hal berikut tidak dikerjakan di Phase 1:

- Import Excel aktual.
- Preview import dan validasi row.
- Parser rute dari Excel.
- Generate QR code.
- Scan kamera dengan html5-qrcode.
- Leaflet map highlight route.
- CRUD izin kendaraan penuh.
- CRUD employee dan vehicle penuh.
- Audit log lengkap.
- Export laporan.
- Queue/job import.
- Approval workflow multi-level.

Non-scope ini sengaja ditunda agar fondasi stabil dulu.

## 5. Arsitektur

Gunakan pola Laravel 8 standar:

- Controller tipis untuk HTTP flow.
- Model Eloquent untuk relasi data.
- Middleware role sederhana untuk authorization awal.
- Blade layout dan partial untuk UI.
- Seeder untuk data master road segment.

Service class belum wajib pada Phase 1 kecuali untuk bagian yang jelas akan dipakai ulang. Import, route parser, token, dan map overlay akan dibuat pada fase terkait.

## 6. File Utama yang Akan Dibuat atau Diubah

Perkiraan file:

- `routes/web.php`
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/RoadSegmentController.php`
- `app/Http/Controllers/ImportController.php`
- `app/Http/Controllers/PermitController.php`
- `app/Http/Controllers/ScanController.php`
- `app/Http/Middleware/EnsureUserHasRole.php`
- `app/Models/Employee.php`
- `app/Models/Vehicle.php`
- `app/Models/ParkingLocation.php`
- `app/Models/RoadSegment.php`
- `app/Models/VehiclePermit.php`
- `app/Models/PermitRouteSegment.php`
- `app/Models/PermitToken.php`
- `app/Models/ImportBatch.php`
- `app/Models/ScanLog.php`
- `database/migrations/*_add_sirika_fields_to_users_table.php`
- `database/migrations/*_create_employees_table.php`
- `database/migrations/*_create_vehicles_table.php`
- `database/migrations/*_create_parking_locations_table.php`
- `database/migrations/*_create_road_segments_table.php`
- `database/migrations/*_create_vehicle_permits_table.php`
- `database/migrations/*_create_permit_route_segments_table.php`
- `database/migrations/*_create_permit_tokens_table.php`
- `database/migrations/*_create_import_batches_table.php`
- `database/migrations/*_create_scan_logs_table.php`
- `database/seeders/RoadSegmentSeeder.php`
- `database/seeders/UserSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/dashboard/index.blade.php`
- `resources/views/road-segments/index.blade.php`
- `resources/views/imports/index.blade.php`
- `resources/views/permits/index.blade.php`
- `resources/views/scan/index.blade.php`
- `resources/views/components/stat-card.blade.php`
- `resources/views/components/alert.blade.php`
- `resources/css/app.css`
- `resources/js/app.js`

## 7. Validasi dan Testing

Testing Phase 1:

- Migration dapat berjalan dari database kosong.
- Seeder membuat user awal dan 26 road segment.
- Login berhasil untuk user seed.
- User tanpa login tidak dapat mengakses dashboard.
- Role `security` dapat membuka `/scan`.
- Role `security` tidak dapat membuka halaman admin yang dibatasi.
- Dashboard menampilkan jumlah road segment aktif.
- Halaman modul utama yang belum aktif menampilkan status read-only, daftar fitur fase berikutnya, dan tidak menyediakan tombol submit palsu.

Command verifikasi:

- `php artisan migrate:fresh --seed`
- `php artisan route:list`
- `php artisan test`

Jika test suite bawaan Laravel belum banyak, minimal buat feature test untuk auth, dashboard, dan role middleware.

## 8. Risiko dan Mitigasi

### Risiko: Role custom terlalu sederhana

Mitigasi: untuk Phase 1 cukup karena role masih statis. Jika permission makin kompleks, migrasi ke Spatie Permission bisa dilakukan pada fase hardening.

### Risiko: Migration terlalu banyak sebelum fitur aktif

Mitigasi: tabel yang dibuat adalah tabel inti PRD dan akan dipakai semua fase. Ini lebih aman daripada membuat tabel parsial lalu melakukan breaking migration saat data sudah masuk.

### Risiko: Database target MySQL/PostgreSQL belum dipilih final

Mitigasi: gunakan tipe kolom Laravel portable seperti `string`, `text`, `json`, `foreignId`, dan `timestamp`. Hindari fitur spesifik database pada Phase 1.

### Risiko: Project belum git repository

Mitigasi: dokumentasikan bahwa commit tidak bisa dilakukan. Jika project akan dikembangkan serius, sebaiknya inisialisasi git sebelum implementasi besar.

### Risiko: PHP 7.4 sudah tidak ideal untuk production

Mitigasi: Phase 1 tidak memakai syntax PHP 8-only. Untuk production, tetap perlu rencana upgrade runtime atau patching environment.

## 9. Acceptance Criteria Phase 1

Phase 1 dianggap selesai jika:

- Admin dapat login.
- Admin melihat dashboard SIRIKA, bukan halaman default Laravel.
- User seed tersedia untuk minimal `super_admin`, `admin_hr`, `security`, dan `auditor`.
- Role middleware membatasi halaman sesuai role.
- Tabel inti berhasil dibuat lewat migration.
- Seeder membuat 26 road segment resmi.
- Halaman `/road-segments` menampilkan daftar 26 segmen.
- Halaman `/imports`, `/permits`, dan `/scan` tersedia sebagai halaman read-only dengan status fitur yang jelas.
- Test auth dan role dasar berjalan.
