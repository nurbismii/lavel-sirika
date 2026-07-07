# SIRIKA Phase 5A Review dan Aktivasi Izin Design

Tanggal: 2026-07-07
Status: Draft untuk review user
Baseline: Phase 4 sudah merge ke `main`
Stack: PHP 7.4, Laravel 8, Blade, Alpine.js, Leaflet.js, MySQL/PostgreSQL, Laravel Excel, BaconQrCode, html5-qrcode

## 1. Tujuan

Phase 5A membuat workflow review dan aktivasi izin kendaraan yang aman sebelum Phase 5 dilanjutkan ke reporting dan hardening yang lebih luas.

Hasil akhir Phase 5A:

- Admin HR dan Super Admin dapat menemukan izin dengan status `needs_review`.
- Admin HR dan Super Admin dapat melihat detail izin, data karyawan, kendaraan, parkir, rute mentah, dan segmen rute tersimpan.
- Admin HR dan Super Admin dapat memperbaiki data review minimum: lokasi parkir, rute mentah, dan catatan review.
- Sistem memvalidasi rute sebelum izin bisa menjadi `active`.
- Sistem menolak aktivasi jika kendaraan masih memiliki izin aktif lain.
- Sistem menyimpan metadata review: user reviewer, waktu review, dan catatan review.
- Setelah izin aktif, mekanisme QR yang sudah ada tetap digunakan untuk generate QR digital dan kartu fisik.
- Auditor dapat melihat detail izin secara read-only.
- Security tetap hanya fokus pada scan QR, bukan review administrasi izin.

## 2. Konteks

Phase 2 sudah membuat data izin dari import Excel. Baris yang aman langsung menjadi `active`, sedangkan baris yang perlu koreksi menjadi `needs_review`.

Phase 3 sudah membuat QR digital, kartu kecil per izin, scan QR, renew QR, dan aturan masa aktif QR selama 1 tahun sejak generate. QR yang sudah kadaluwarsa tetap bisa discan, tetapi status scan menampilkan kadaluwarsa.

Phase 4 sudah membuat peta rute berbasis Leaflet dan static map internal VDNI. Izin yang punya relasi `permit_route_segments` dapat dilihat pada halaman peta rute.

Kondisi saat ini:

- `PermitTokenService` hanya mengizinkan QR dibuat untuk `vehicle_permits.status = active`.
- Banyak data import berada di status `needs_review`, sehingga tombol `Generate QR` tidak muncul. Ini benar secara keamanan, bukan bug UI.
- `PermitImportCommitService` hanya menyimpan `permit_route_segments` untuk izin yang langsung `active`. Izin `needs_review` perlu workflow terpisah untuk parsing ulang rute dan attach segmen saat aktivasi.
- Halaman `/permits` masih read-only dan belum memiliki filter status atau halaman detail review.

## 3. Scope Phase 5A

Phase 5A mencakup:

- Filter daftar izin berdasarkan status, status QR, warna izin, lokasi parkir, dan pencarian NIK/nama/plat.
- Halaman detail izin kendaraan.
- Halaman review izin untuk status `needs_review`.
- Form koreksi lokasi parkir, rute mentah, dan catatan review.
- Aktivasi izin dari `needs_review` menjadi `active` jika validasi lolos.
- Parsing ulang rute dari `route_raw` memakai parser yang sudah ada.
- Penyimpanan ulang `permit_route_segments` secara berurutan saat aktivasi.
- Validasi duplikasi izin aktif untuk kendaraan yang sama.
- Metadata review di tabel `vehicle_permits`.
- Authorization route untuk Admin HR, Super Admin, Auditor, dan Security.
- Feature test untuk filter, detail, aktivasi, validasi gagal, duplikasi izin aktif, dan authorization.

## 4. Non-Scope Phase 5A

Hal berikut tidak dikerjakan di Phase 5A:

- Auto-generate QR saat izin diaktifkan.
- Bulk activation.
- Bulk review edit.
- Workflow approval multi-level.
- Penggantian otomatis izin aktif lama.
- Audit log terpisah berbasis tabel baru untuk semua perubahan izin.
- Export laporan Excel/PDF.
- Dashboard reporting.
- Job queue untuk proses background.
- Perubahan aturan masa aktif QR.
- Perubahan mekanisme scan QR.
- Perubahan parser import Excel besar-besaran.

Non-scope ini sengaja ditunda agar Phase 5A fokus menyelesaikan bottleneck operasional: data import yang tertahan di `needs_review` bisa diperiksa dan diaktifkan secara aman.

## 5. Keputusan Produk

Keputusan untuk Phase 5A:

- Izin `needs_review` tidak boleh dibuatkan QR sebelum menjadi `active`.
- Aktivasi izin tidak otomatis membuat QR. Admin tetap menekan tombol `Generate QR` setelah status aktif.
- Lokasi parkir wajib dipilih sebelum aktivasi.
- Rute wajib diisi dan harus menghasilkan minimal 1 kode segmen resmi.
- Token rute tidak dikenal menahan aktivasi.
- Kendaraan yang masih memiliki izin `active` lain tidak boleh diaktifkan lagi.
- Phase 5A hanya mengaktifkan izin dari status `needs_review`.
- Catatan review wajib diisi saat aktivasi agar ada konteks administratif.
- Admin HR dan Super Admin dapat melakukan review dan aktivasi.
- Auditor hanya dapat melihat detail izin.
- Security tidak mendapat akses ke halaman review izin.

## 6. Pendekatan Arsitektur

Controller tetap tipis. Logic aktivasi dipindahkan ke service khusus agar aturan status, duplikasi, parsing rute, dan penyimpanan segmen tidak tersebar di controller atau Blade.

Unit utama:

- `PermitController`
  - Menampilkan daftar izin dengan filter.
  - Menampilkan detail izin read-only.

- `PermitReviewController`
  - Menampilkan form review.
  - Menyimpan koreksi draft review.
  - Mengaktifkan izin setelah validasi domain lolos.

- `PermitReviewService`
  - Mengunci record izin dengan `lockForUpdate`.
  - Memastikan status izin masih `needs_review`.
  - Memvalidasi lokasi parkir.
  - Parsing ulang `route_raw`.
  - Menolak rute kosong, rute tanpa segmen resmi, dan token tidak dikenal.
  - Menolak duplikasi izin aktif untuk kendaraan yang sama.
  - Menghapus relasi rute lama untuk izin tersebut.
  - Menyimpan `permit_route_segments` baru sesuai urutan hasil parser.
  - Mengubah status menjadi `active`.
  - Menyimpan `reviewed_by`, `reviewed_at`, dan `review_note`.

- `UpdatePermitReviewRequest`
  - Validasi request form sebelum masuk service.
  - Validasi format field umum, bukan validasi domain lintas tabel yang memerlukan lock.

Alasan menggunakan service:

- Aktivasi izin berdampak langsung ke eligibility QR.
- Proses harus transactional.
- Aturan ini dapat dipakai ulang oleh fitur bulk activation pada phase terpisah tanpa memindahkan logic dari controller.
- Test domain lebih jelas dan tidak tergantung detail Blade.

## 7. Data Model

Tambah kolom nullable ke `vehicle_permits`:

- `reviewed_by`
  - Foreign key ke `users.id`.
  - Nullable.
  - `nullOnDelete`.

- `reviewed_at`
  - Timestamp nullable.

- `review_note`
  - Text nullable.

Kolom ini aman untuk database production karena:

- Tidak mengubah data lama.
- Tidak membuat kolom wajib baru.
- Tidak mengubah enum/status lama.
- Tidak menghapus tabel atau kolom lama.

Tidak dibuat tabel audit baru di Phase 5A. Audit log penuh lebih tepat masuk Phase 5C hardening, setelah workflow review final stabil.

## 8. Validasi Aktivasi

Aktivasi izin harus gagal jika salah satu kondisi berikut terjadi:

- User tidak memiliki role `admin_hr` atau `super_admin`.
- Izin tidak berstatus `needs_review`.
- Izin tidak memiliki employee.
- Izin tidak memiliki vehicle.
- Lokasi parkir kosong atau tidak valid.
- `route_raw` kosong.
- Parser tidak menemukan kode segmen resmi.
- Parser mengembalikan warning token tidak dikenal.
- Ada kode segmen hasil parser yang tidak ditemukan di master `road_segments` aktif.
- Kendaraan yang sama sudah memiliki izin lain dengan status `active`.

Aktivasi izin harus berhasil jika:

- User memiliki role yang benar.
- Izin berstatus `needs_review`.
- Employee, vehicle, dan parking location valid.
- `route_raw` menghasilkan minimal 1 road segment resmi.
- Tidak ada warning parser yang bersifat blocking.
- Tidak ada izin aktif lain untuk kendaraan yang sama.

Saat berhasil:

- `vehicle_permits.status` menjadi `active`.
- `vehicle_permits.parking_location_id` tersimpan.
- `vehicle_permits.route_raw` tersimpan dari input review.
- `vehicle_permits.reviewed_by` berisi ID user reviewer.
- `vehicle_permits.reviewed_at` berisi waktu aktivasi.
- `vehicle_permits.review_note` berisi catatan reviewer.
- `permit_route_segments` berisi segmen hasil parser dengan sequence mulai dari 1.

## 9. UI dan UX

### Daftar Izin

Halaman `/permits` menjadi halaman kerja operasional, bukan hanya tabel read-only.

Komponen:

- Filter status izin.
- Filter status QR.
- Filter warna izin.
- Filter lokasi parkir.
- Search NIK, nama, atau plat.
- Ringkasan jumlah status utama: `needs_review`, `active`, `expired`, dan `revoked`.
- Aksi `Detail` untuk semua izin.
- Aksi `Review` hanya untuk izin `needs_review`.
- Aksi QR tetap hanya muncul untuk izin `active` sesuai aturan yang sudah ada.

### Detail Izin

Halaman detail menampilkan:

- Data karyawan.
- Data kendaraan.
- Lokasi parkir.
- Status izin.
- Status QR.
- Sumber data.
- Rute mentah.
- Segmen rute tersimpan.
- Metadata review jika sudah ada.
- Link ke peta rute.
- Link ke review jika status masih `needs_review` dan user berwenang.

### Form Review

Form review menampilkan:

- Identitas karyawan dan kendaraan sebagai read-only.
- Input lokasi parkir.
- Textarea rute mentah.
- Textarea catatan review.
- Tombol `Simpan Review` untuk menyimpan koreksi tanpa aktivasi.
- Tombol `Aktifkan Izin` untuk validasi dan aktivasi.

Feedback:

- Error validasi ditampilkan di dekat form.
- Flash success setelah simpan atau aktivasi.
- Tidak memakai browser alert.
- Setelah aktivasi berhasil, user diarahkan ke detail izin.

## 10. Authorization

Role akses Phase 5A:

- `super_admin`
  - Dapat melihat daftar izin.
  - Dapat melihat detail izin.
  - Dapat review izin.
  - Dapat aktivasi izin.
  - Dapat generate, renew, print QR sesuai fitur yang sudah ada.

- `admin_hr`
  - Dapat melihat daftar izin.
  - Dapat melihat detail izin.
  - Dapat review izin.
  - Dapat aktivasi izin.
  - Dapat generate, renew, print QR sesuai fitur yang sudah ada.

- `auditor`
  - Dapat melihat daftar izin.
  - Dapat melihat detail izin.
  - Dapat melihat peta rute.
  - Tidak dapat edit review, aktivasi, generate, renew, atau print QR.

- `security`
  - Dapat scan QR.
  - Tidak dapat mengakses daftar izin, detail izin, review, atau aktivasi.

`User::routeRoles()` perlu diperbarui untuk route baru. Middleware route tetap menjadi gate utama.

## 11. Error Handling

Domain exception dari `PermitReviewService` ditangkap controller dan dikembalikan sebagai validation error.

Pesan error harus operasional:

- `Izin ini tidak berada dalam status needs_review.`
- `Pilih lokasi parkir sebelum aktivasi izin.`
- `Rute kendaraan kosong.`
- `Rute tidak mengandung kode segmen resmi.`
- `Rute mengandung token tidak dikenal: X.`
- `Kendaraan ini masih memiliki izin aktif lain. Nonaktifkan izin lama sebelum aktivasi.`

Aktivasi menggunakan database transaction. Jika terjadi error, status izin dan relasi `permit_route_segments` tidak boleh berubah sebagian.

## 12. Testing

Feature test wajib mencakup:

- Admin HR dapat membuka daftar izin dan filter status `needs_review`.
- Auditor dapat membuka detail izin tetapi tidak dapat membuka form review.
- Security tidak dapat membuka daftar izin.
- Admin HR dapat menyimpan koreksi review tanpa aktivasi.
- Admin HR dapat mengaktifkan izin `needs_review` dengan lokasi parkir dan rute valid.
- Aktivasi menyimpan metadata review.
- Aktivasi membuat ulang `permit_route_segments` sesuai urutan parser.
- Aktivasi gagal jika rute kosong.
- Aktivasi gagal jika rute memiliki token tidak dikenal.
- Aktivasi gagal jika lokasi parkir kosong.
- Aktivasi gagal jika kendaraan sudah memiliki izin aktif lain.
- Setelah aktivasi, halaman daftar izin menampilkan aksi `Generate QR`.
- QR tetap tidak bisa dibuat untuk izin `needs_review`.

Command test utama:

```bash
php artisan test --filter=PermitReview
php artisan test --filter=PermitQr
php artisan test --filter=PermitList
```

## 13. Risiko dan Mitigasi

Risiko: admin mengaktifkan izin dengan rute yang sebenarnya masih salah.

Mitigasi: rute wajib parse ke master segmen resmi, token tidak dikenal menahan aktivasi, dan catatan review wajib.

Risiko: duplikasi izin aktif untuk kendaraan yang sama.

Mitigasi: service melakukan pengecekan dalam transaction dan memakai lock sebelum mengubah status.

Risiko: QR muncul untuk izin yang belum valid.

Mitigasi: aturan `PermitTokenService` tidak diubah. QR tetap hanya dibuat untuk status `active`.

Risiko: perubahan data route tersimpan sebagian.

Mitigasi: aktivasi menghapus dan menulis ulang `permit_route_segments` dalam transaction.

Risiko: auditor mendapat akses mutasi.

Mitigasi: route mutasi dipisahkan dan hanya diberi role `admin_hr` dan `super_admin`.

## 14. Rollout Production

Urutan rollout:

1. Deploy kode Phase 5A.
2. Jalankan migration nullable untuk metadata review.
3. Jalankan `php artisan route:clear`.
4. Jalankan `php artisan view:clear`.
5. Jalankan `php artisan config:clear`.
6. Login sebagai Admin HR.
7. Filter izin `needs_review`.
8. Review satu izin dengan rute dan parkir valid.
9. Aktifkan izin.
10. Generate QR dari daftar izin.
11. Scan QR untuk memastikan status valid dan peta rute tetap tampil.

Rollback aman:

- Kode bisa dikembalikan ke commit sebelumnya.
- Kolom metadata review nullable tidak mengganggu versi lama.
- Tidak ada data lama yang dihapus oleh migration.

## 15. Definition of Done

Phase 5A dianggap selesai jika:

- Daftar izin memiliki filter operasional.
- Detail izin tersedia dan read-only untuk Auditor.
- Form review tersedia untuk Admin HR dan Super Admin.
- Izin `needs_review` bisa dikoreksi dan diaktifkan jika valid.
- Aktivasi gagal dengan pesan jelas untuk data yang belum aman.
- `permit_route_segments` terisi saat izin diaktifkan.
- Tombol QR muncul setelah izin aktif.
- QR tetap tidak bisa dibuat untuk izin non-active.
- Test Phase 5A dan regresi QR lulus.
- Tidak ada perubahan breaking pada import, scan QR, dan peta rute.
