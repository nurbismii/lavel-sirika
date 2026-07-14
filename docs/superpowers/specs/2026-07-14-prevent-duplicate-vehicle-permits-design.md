# Pencegahan Izin Kendaraan Duplikat saat Import

## Tujuan

Mencegah import Excel membuat lebih dari satu izin kendaraan untuk kombinasi
NIK dan plat kendaraan yang sama, tanpa memblokir satu karyawan yang memiliki
plat kendaraan berbeda.

## Aturan bisnis

- NIK sama dan plat sama tidak boleh memiliki lebih dari satu izin kendaraan.
- NIK sama dan plat berbeda diperbolehkan.
- Plat yang sama untuk NIK berbeda tidak diperbolehkan; satu kendaraan hanya
  boleh terkait dengan satu karyawan.
- Baris duplikat tidak boleh dikomit sebagai izin baru.

## Desain validasi

1. Saat preview, normalisasi NIK dan plat lalu cek duplikat dalam file yang
   sama berdasarkan pasangan NIK + plat.
2. Cek pasangan tersebut terhadap izin yang telah tersimpan pada aplikasi.
3. Duplikat diberi status `invalid` dengan pesan yang membedakan sumbernya:
   duplikat dalam file atau izin sudah terdaftar.
4. Saat commit, lakukan pengecekan ulang di transaction sebelum menciptakan
   `VehiclePermit`; kondisi balapan tidak boleh membuat izin kedua.
5. Tambahkan indeks unik `vehicle_permits(employee_id, vehicle_id)` sebagai
   proteksi database. Migration memeriksa data lama lebih dahulu dan gagal
   dengan pesan aman apabila ada duplikat; tidak ada penghapusan otomatis.

## Dampak dan kompatibilitas

- Kendaraan tetap unik per pemilik dan plat melalui indeks yang sudah ada.
- Izin lama yang valid tetap tidak berubah.
- Data lama yang sudah duplikat menghalangi migration dan harus ditinjau
  manual sebelum deployment production.
- Data valid dari NIK dengan plat berbeda tetap dapat diimport.

## Pengujian

- Preview menolak duplikat pasangan NIK + plat di dalam satu file.
- Preview menolak pasangan yang izinnya telah ada.
- NIK sama dengan plat berbeda tetap valid.
- Commit menolak duplikat yang muncul setelah preview.
- Migration menolak data duplikat lama dan indeks database menolak insert
  duplikat.
