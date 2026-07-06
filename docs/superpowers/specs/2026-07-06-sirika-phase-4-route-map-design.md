# SIRIKA Phase 4 Peta Rute dan Editor Koordinat Design

Tanggal: 2026-07-06
Status: Draft untuk review user
Baseline: Phase 3 sudah merge ke `main`
Stack: PHP 7.4, Laravel 8, Blade, Alpine.js, Leaflet.js, MySQL/PostgreSQL

## 1. Tujuan

Phase 4 mengaktifkan peta rute internal VDNI berbasis gambar peta resmi dan editor koordinat segmen jalan.

Hasil akhir Phase 4:

- Admin HR dan Super Admin dapat mengkurasi koordinat setiap `road_segments`.
- Sistem menyimpan koordinat segmen ke `road_segments.polyline_json`.
- Master Rute menampilkan preview peta VDNI dan status koordinat tiap segmen.
- Admin dapat melihat highlight rute izin berdasarkan urutan `permit_route_segments`.
- Security dapat melihat highlight rute saat hasil scan QR `valid`.
- Peta memakai gambar VDNI sebagai static asset, bukan peta publik online.
- Tidak ada koordinat GPS yang diwajibkan pada Phase 4.

## 2. Konteks

Phase 1 sudah menyediakan:

- Tabel `road_segments` dengan kolom `polyline_json`.
- Tabel `permit_route_segments` yang menyimpan urutan segmen per izin.
- Seeder 26 segmen rute VDNI.
- Halaman `/road-segments` read-only.

Phase 2 sudah menyediakan:

- Parser rute dari Excel berbasis kode `road_segments`.
- Commit data import ke `permit_route_segments` untuk row yang aman.
- Row rute tidak jelas masuk `needs_review`.

Phase 3 sudah menyediakan:

- QR digital dan kartu kecil.
- Scan QR oleh security.
- Hasil scan `valid` mengembalikan `route_raw`, tetapi belum menampilkan peta.

Keputusan produk untuk Phase 4:

- Koordinat rute dikurasi manual oleh admin, bukan ditebak otomatis.
- Background peta memakai gambar/PDF peta VDNI sebagai image overlay.
- File gambar peta disiapkan developer dari PDF dan disimpan sebagai static asset.
- Admin belum perlu fitur upload atau ganti gambar peta dari panel.

## 3. Scope Phase 4

Phase 4 mencakup:

- Konversi peta VDNI dari PDF ke PNG atau JPG static asset.
- Konfigurasi metadata peta: path asset, width, height, dan versi peta.
- Integrasi Leaflet dengan `CRS.Simple`.
- Editor koordinat per `road_segments`.
- Simpan, reset, dan preview polyline segmen.
- Validasi minimal 2 titik untuk segmen yang dianggap lengkap.
- Status koordinat di Master Rute: belum dibuat, draft, lengkap.
- Preview semua segmen yang sudah punya koordinat di Master Rute.
- Preview rute izin di halaman daftar/detail izin.
- Preview rute di hasil scan QR `valid`.
- Test backend untuk validasi dan penyimpanan koordinat.
- Test akses role untuk editor dan viewer.

## 4. Non-Scope Phase 4

Hal berikut tidak dikerjakan di Phase 4:

- Upload/ganti gambar peta dari admin panel.
- Georeferencing GPS, latitude, longitude, atau peta publik OpenStreetMap.
- Tracking posisi kendaraan real-time.
- Optimasi rute otomatis.
- Routing engine shortest path.
- Approval workflow untuk perubahan koordinat.
- Versioning multi-peta di database.
- Export peta ke PDF.
- Batch print kartu A4.
- Koreksi inline `needs_review` dari import.

Non-scope ini ditunda agar Phase 4 fokus pada peta rute yang stabil, bisa dikurasi, dan aman untuk operasional internal.

## 5. Pendekatan Arsitektur

Gunakan gambar peta VDNI sebagai sistem koordinat lokal.

Leaflet dikonfigurasi dengan:

- `L.CRS.Simple`
- Bounds berbasis dimensi image: `[[0, 0], [mapHeight, mapWidth]]`
- Image overlay dari asset static, misalnya `/images/maps/vdni-road-map-v1.png`

Koordinat disimpan sebagai koordinat pixel relatif terhadap gambar peta:

- `x`: posisi horizontal dari kiri gambar.
- `y`: posisi vertikal dari atas gambar.

Alasan menyimpan `x/y`, bukan `lat/lng`:

- Area VDNI adalah area internal dan peta resmi berupa gambar.
- Admin lebih mudah mengklik titik pada gambar.
- Data tetap valid selama versi gambar dan dimensinya tidak berubah.
- Format `x/y` menghindari kebingungan Leaflet `lat/lng` pada `CRS.Simple`.

Di frontend, `x/y` dikonversi ke Leaflet LatLng:

- Store: `{ "x": 120.5, "y": 240.25 }`
- Render Leaflet: `[y, x]`

Controller tetap tipis:

- `RoadSegmentController` menampilkan daftar, preview, editor, dan menerima update koordinat.
- Service baru menangani validasi struktur polyline dan normalisasi titik.
- View Blade hanya mengirim data konfigurasi ke Alpine/Leaflet.

## 6. Data Model

Tidak perlu tabel baru untuk Phase 4.

Kolom existing dipakai:

- `road_segments.polyline_json`

### 6.1 Format polyline_json

Format disarankan:

```json
{
  "version": 1,
  "map_key": "vdni-road-map-v1",
  "status": "complete",
  "points": [
    { "x": 120.5, "y": 240.25 },
    { "x": 180.75, "y": 260.5 }
  ],
  "updated_by": 1,
  "updated_at": "2026-07-06T12:00:00+08:00"
}
```

Field:

- `version`: versi struktur JSON, awalnya `1`.
- `map_key`: key static map yang dipakai saat koordinat dibuat.
- `status`: `draft` atau `complete`.
- `points`: daftar titik rute dalam koordinat pixel gambar.
- `updated_by`: user id admin terakhir yang menyimpan koordinat.
- `updated_at`: timestamp terakhir simpan.

Aturan status:

- `null`: belum ada koordinat.
- `draft`: ada titik, tetapi kurang dari 2 titik atau sengaja disimpan sebagai draft.
- `complete`: minimal 2 titik valid dan siap dipakai untuk preview rute.

### 6.2 Konfigurasi peta

Tambah config, misalnya `config/sirika.php`:

```php
'route_map' => [
    'key' => 'vdni-road-map-v1',
    'image_url' => '/images/maps/vdni-road-map-v1.png',
    'width' => 1600,
    'height' => 1000,
],
```

Nilai `width` dan `height` harus sama dengan dimensi pixel file image final.

Jika gambar diganti dengan dimensi berbeda, koordinat lama tidak otomatis valid. Perubahan map key harus disengaja dan perlu proses kurasi ulang atau migrasi koordinat.

## 7. Asset Peta VDNI

Source:

- PDF VDNI road map yang sudah diberikan pada fase awal.

Output Phase 4:

- Static image, misalnya:
  - `public/images/maps/vdni-road-map-v1.png`

Aturan asset:

- File image harus cukup tajam untuk editor titik rute.
- File tidak boleh terlalu besar sampai memperlambat halaman admin.
- Jika hasil konversi PDF terlalu besar, gunakan resize yang tetap terbaca jelas.
- Image map tidak memuat data pribadi.

Catatan produksi:

- Peta image menjadi bagian dari source aplikasi.
- Tidak ada upload dari admin di Phase 4.
- Jika peta VDNI resmi berubah, developer perlu mengganti asset dan mengatur ulang `route_map.key`.

## 8. Authorization dan Role

Viewer:

- `road-segments.index`: `admin_hr`, `auditor`, `super_admin`.
- Preview peta rute izin untuk admin: `admin_hr`, `super_admin`.
- Preview peta hasil scan valid: `security`, `admin_hr`, `super_admin`.

Editor:

- `road-segments.map.edit`: `admin_hr`, `super_admin`.
- `road-segments.map.update`: `admin_hr`, `super_admin`.
- `road-segments.map.reset`: `admin_hr`, `super_admin`.

Auditor:

- Boleh melihat master rute dan preview peta.
- Tidak boleh mengubah koordinat.

Security:

- Tidak boleh membuka editor master rute.
- Hanya melihat route highlight dari hasil scan QR `valid`.

## 9. Route dan Controller

Route Phase 4:

- `GET /road-segments`
  - Daftar segmen dan preview status koordinat.
- `GET /road-segments/{roadSegment}/map`
  - Editor koordinat satu segmen.
- `POST /road-segments/{roadSegment}/map`
  - Simpan koordinat segmen.
- `DELETE /road-segments/{roadSegment}/map`
  - Reset koordinat segmen.

Opsional jika diperlukan untuk JSON:

- `GET /road-segments/map-data`
  - Mengembalikan semua segmen aktif dengan polyline lengkap.
- `GET /permits/{permit}/route-map`
  - Mengembalikan route map data untuk izin tertentu.

Namun Phase 4 sebaiknya mengutamakan data langsung dari controller ke Blade agar scope tetap kecil. Endpoint JSON dibuat hanya jika diperlukan untuk menjaga view tetap rapi.

## 10. Service dan Request

### 10.1 RouteMapConfig

Helper atau config reader untuk:

- `map_key`
- `image_url`
- `width`
- `height`
- Leaflet bounds

### 10.2 RoadSegmentPolylineService

Tanggung jawab:

- Validasi struktur points.
- Normalisasi angka `x/y`.
- Clamp titik agar tetap di dalam bounds image.
- Menentukan status `draft` atau `complete`.
- Membuat payload `polyline_json`.
- Membuat data render untuk Leaflet.

### 10.3 UpdateRoadSegmentPolylineRequest

Rules:

- `points`: required array.
- `points.*.x`: required numeric min 0 max map width.
- `points.*.y`: required numeric min 0 max map height.
- `save_mode`: required in `draft`, `complete`.

Aturan:

- Draft boleh kurang dari 2 titik.
- Complete wajib minimal 2 titik.
- Maksimal titik awal: 200 titik per segmen.

Maksimal titik mencegah payload terlalu besar dan menghindari input tidak sengaja.

## 11. UI Master Rute

Halaman `/road-segments` ditingkatkan menjadi:

- Ringkasan:
  - Total segmen.
  - Segmen lengkap.
  - Segmen draft.
  - Segmen belum dibuat.
- Preview peta semua segmen lengkap.
- Tabel segmen dengan kolom:
  - Kode.
  - Nama.
  - Lokasi awal.
  - Lokasi akhir.
  - Status koordinat.
  - Jumlah titik.
  - Aksi.

Aksi:

- `Edit Peta` untuk admin HR dan super admin.
- `Lihat Peta` untuk auditor jika segmen punya koordinat.
- `Reset` hanya admin HR dan super admin, dengan konfirmasi.

Empty state:

- Jika belum ada koordinat sama sekali, tampilkan peta dasar dan pesan bahwa segmen perlu dikurasi.

## 12. UI Editor Koordinat

Halaman editor satu segmen menampilkan:

- Peta VDNI full panel dengan Leaflet.
- Polyline segmen yang sedang diedit.
- Marker titik urut.
- Panel informasi segmen:
  - Kode.
  - Nama.
  - Lokasi awal.
  - Lokasi akhir.
  - Status koordinat.
  - Jumlah titik.
- Tombol:
  - Simpan Draft.
  - Simpan Complete.
  - Undo titik terakhir.
  - Hapus semua titik.
  - Kembali.

Interaksi:

- Klik peta menambah titik baru.
- Klik marker titik dapat dipilih.
- Tombol hapus titik terpilih boleh dibuat jika scope masih sederhana.
- Drag marker boleh ditunda jika implementasi Leaflet marker drag terlalu banyak risiko.

Rekomendasi Phase 4:

- MVP editor cukup klik tambah titik, undo titik terakhir, hapus semua, simpan.
- Drag marker masuk hardening setelah editor dasar stabil.

Validasi UI:

- Jika user klik `Simpan Complete` dengan kurang dari 2 titik, tampilkan error.
- Jika image map gagal load, editor menampilkan error dan tidak submit data.
- Jika user meninggalkan halaman dengan perubahan belum disimpan, tampilkan konfirmasi browser sederhana.

## 13. UI Preview Rute Izin

Halaman izin perlu menampilkan preview rute jika permit punya route segments lengkap.

Sumber data:

- `vehicle_permits.routeSegments` berurutan dari pivot `sequence`.
- Ambil `polyline_json` setiap `road_segments`.

Render:

- Peta VDNI sebagai background.
- Setiap segmen route diberi warna highlight yang sama.
- Tampilkan label kode segmen di midpoint sederhana.
- Urutan segmen ditampilkan sebagai list: `Y1 -> D2 -> H2`.

Jika beberapa segmen belum punya koordinat:

- Peta tetap tampil untuk segmen yang lengkap.
- Panel warning menampilkan daftar kode segmen yang belum dikurasi.
- Jangan membuat route terlihat lengkap jika ada segmen missing.

Jika permit tidak punya route segments:

- Tampilkan `Rute belum tersedia atau perlu review`.

## 14. UI Scan QR Valid

Saat scan result `valid`, security melihat:

- Status `QR valid`.
- Nama.
- Plat.
- Lokasi parkir.
- Warna kartu.
- Rute raw.
- Peta highlight jika route segments lengkap atau sebagian lengkap.

Data scan untuk map:

- `scan.verify` perlu mengembalikan data rute aman untuk result `valid`.
- Data rute berisi kode segmen dan points, bukan data pribadi tambahan.

Untuk result lain:

- `expired`: tetap tampil detail terbatas sesuai Phase 3. Peta tidak wajib tampil.
- `revoked`: tidak tampil peta.
- `inactive`: tidak tampil peta.
- `invalid`: tidak tampil peta.

Alasan:

- Untuk expired, Phase 3 sengaja membatasi detail. Menampilkan route map expired bisa membuka informasi operasional lebih banyak dari kebutuhan status kadaluwarsa.

## 15. Data Flow

### 15.1 Editor segmen

1. Admin membuka `/road-segments/{roadSegment}/map`.
2. Controller memuat segment, config peta, dan existing `polyline_json`.
3. Blade menginisialisasi Alpine + Leaflet.
4. Admin klik titik pada peta.
5. Alpine menyimpan points di state browser.
6. Admin submit draft atau complete.
7. Request memvalidasi points.
8. Service menormalisasi points dan membuat payload JSON.
9. Model `RoadSegment` menyimpan `polyline_json`.
10. User kembali ke editor dengan alert sukses.

### 15.2 Preview izin

1. Controller memuat permit dengan `routeSegments`.
2. Service mengubah route segments menjadi map DTO.
3. Blade render image overlay dan polylines.
4. Jika ada segmen missing, Blade render warning.

### 15.3 Scan valid

1. Security scan QR.
2. `PermitScanService` validasi token.
3. Jika result `valid`, service/controller menambahkan route map DTO.
4. Alpine scanner render result dan peta.
5. `scan_logs` tetap dibuat seperti Phase 3.

## 16. Error Handling

Editor:

- Points kosong saat save draft: boleh disimpan sebagai draft kosong atau ditolak. Rekomendasi: tolak dan sarankan reset jika ingin mengosongkan.
- Complete kurang dari 2 titik: validasi gagal.
- Titik di luar bounds: request ditolak atau titik di-clamp oleh service. Rekomendasi: request ditolak agar input tidak diam-diam berubah.
- Map key mismatch: tampilkan warning bahwa koordinat dibuat untuk peta versi lain.

Preview:

- `polyline_json` rusak: abaikan segmen tersebut dan tampilkan warning.
- Asset peta gagal load: tampilkan fallback list rute teks.
- Segmen missing coordinate: tampilkan warning, bukan error 500.

Scan:

- Jika route map gagal dibuat, scan valid tetap sukses.
- UI menampilkan data scan teks dan warning peta tidak tersedia.
- Jangan menggagalkan scan hanya karena koordinat rute belum lengkap.

## 17. Testing

Automated tests:

- Admin HR dapat membuka editor koordinat segmen.
- Auditor tidak dapat membuka route update/reset.
- Security tidak dapat membuka editor koordinat.
- Admin HR dapat menyimpan draft dengan titik valid.
- Admin HR dapat menyimpan complete dengan minimal 2 titik.
- Complete dengan kurang dari 2 titik ditolak.
- Titik di luar bounds ditolak.
- Reset koordinat menghapus `polyline_json`.
- Master Rute menampilkan status koordinat.
- Permit route map DTO menampilkan segmen sesuai sequence.
- Permit route map DTO menandai segmen missing coordinate.
- Scan valid mengembalikan route map data jika koordinat tersedia.
- Scan expired tidak mengembalikan route map data.

Manual verification:

- `php artisan test`
- `npm.cmd run dev`
- `php artisan migrate:fresh --seed`
- Buka Master Rute sebagai admin HR.
- Edit segmen `Y1`, klik minimal 2 titik, simpan complete.
- Pastikan status `Y1` menjadi lengkap.
- Buka preview Master Rute dan lihat garis `Y1`.
- Import/seed permit dengan route segments yang memakai `Y1`.
- Buka izin dan lihat highlight rute.
- Login security, scan QR valid, dan pastikan peta rute tampil.
- Scan QR expired dan pastikan peta tidak tampil.

## 18. Risiko dan Mitigasi

### Risiko: Koordinat tidak cocok jika gambar peta berubah

Mitigasi:

- Simpan `map_key` di `polyline_json`.
- Simpan width/height di config.
- Jika asset diganti, ubah `map_key` dan tampilkan warning untuk koordinat lama.

### Risiko: Admin salah klik titik

Mitigasi:

- Editor menyediakan undo dan hapus semua.
- Simpan draft sebelum complete.
- Preview langsung terlihat sebelum simpan.

### Risiko: Peta terlalu besar dan lambat

Mitigasi:

- Resize image ke ukuran yang masih terbaca.
- Gunakan static asset dan cache browser.
- Jangan load data semua permit pada halaman Master Rute.

### Risiko: Rute terlihat lengkap padahal ada segmen missing

Mitigasi:

- Preview menampilkan warning daftar segmen tanpa koordinat.
- Acceptance criteria mewajibkan partial route diberi warning.

### Risiko: Peta internal terekspos publik

Mitigasi:

- Aplikasi tetap membutuhkan login.
- Asset static memang dapat diakses jika path diketahui, tetapi tidak memuat data pribadi. Jika peta internal dianggap sensitif, Phase hardening perlu memindahkannya ke private storage dengan controller authorized.

### Risiko: Data peta memperluas informasi security untuk scan expired

Mitigasi:

- Route map hanya dikirim untuk result `valid`.
- Expired tetap mengikuti pembatasan Phase 3.

## 19. Acceptance Criteria Phase 4

Phase 4 dianggap selesai jika:

- Peta VDNI static asset tersedia dan dapat dirender dengan Leaflet `CRS.Simple`.
- Admin HR dapat membuka editor koordinat segmen.
- Admin HR dapat menyimpan draft dan complete polyline.
- Complete wajib minimal 2 titik valid.
- Auditor hanya bisa melihat, tidak bisa edit/reset.
- Security tidak bisa mengakses editor.
- Master Rute menampilkan status koordinat dan preview segmen.
- Halaman izin menampilkan highlight route berdasarkan `permit_route_segments`.
- Scan QR valid menampilkan route map jika koordinat tersedia.
- Scan expired/revoked/inactive/invalid tidak menampilkan route map.
- Missing coordinate tidak menyebabkan error 500 dan ditampilkan sebagai warning.
- `php artisan test` dan `npm.cmd run dev` lulus.

