# Task 2 Report: Pembaruan Data Permit Atomik

## Perubahan

- Menambahkan `PermitDataEditService` untuk memperbarui identitas pegawai, nomor plat, lokasi parkir, dan rute dalam satu transaksi database.
- Permit, employee, dan vehicle dikunci menggunakan `lockForUpdate()` sebelum diubah.
- Service memvalidasi ulang bahwa lokasi parkir dan segmen jalan yang digunakan masih berstatus aktif.
- Primary parking mengikuti ID pertama yang dikirimkan; relasi parkir disinkronkan.
- Baris pivot rute sebelumnya diganti dan diberi sequence mulai dari 1; `route_raw` dibentuk dari kode segmen sesuai urutan input.
- Controller `PermitController::update` menerima `UpdatePermitDataRequest`, `VehiclePermit`, dan service; lalu mengarahkan kembali ke detail permit dengan flash message sukses.
- Menambahkan test HTTP yang mencakup pembaruan seluruh data serta memastikan status, metadata review, dan token QR tidak berubah.

## TDD

- RED: `php artisan test tests/Feature/PermitDataEditHttpTest.php --filter=admin_can_update_permit_identity_parking_and_ordered_route_without_changing_status` gagal karena `PermitController::update` belum ada.
- GREEN: command yang sama lulus setelah service dan controller ditambahkan.

## Validasi

```powershell
php artisan test tests/Feature/PermitDataEditHttpTest.php
```

Hasil: 2 tests passed.

## Catatan

- Tidak ada perubahan Blade atau tombol UI; itu tetap menjadi scope Task 3.
- Direktori `.composer-cache/` muncul sebagai untracked saat test berjalan dan sengaja tidak dimasukkan ke commit.
