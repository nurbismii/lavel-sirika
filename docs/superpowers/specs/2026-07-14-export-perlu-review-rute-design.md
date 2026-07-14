# Export Izin Perlu Review dengan Validasi Rute

## Tujuan

Menyediakan file Excel khusus untuk tindak lanjut izin kendaraan berstatus
`needs_review`. File menunjukkan kode rute pada `route_raw` yang tidak tersedia
di Master Rute aktif pada saat export dibuat.

## Ruang lingkup

- Tambahkan tombol **Export Perlu Review** pada Laporan Izin.
- Export selalu membatasi data ke `VehiclePermit::STATUS_NEEDS_REVIEW`.
- Filter laporan yang relevan (QR, parkir, warna, sumber, status review, dan
  pencarian) tetap diterapkan. Parameter status dari URL tidak boleh dapat
  mengganti pembatasan `needs_review`.
- Acuan rute tersedia adalah `road_segments` dengan status `active`.
- `route_raw` diparsing menggunakan aturan token rute yang sama seperti proses
  import. Kode yang tidak ada pada Master Rute aktif dicatat sebagai rute tidak
  tersedia.
- Excel menambahkan kolom `Rute Tidak Tersedia` dan `Status Validasi Rute`.
- Jika ada rute tidak tersedia, sel `Rute Mentah` diberi kuning dan sel status
  validasi diberi merah muda. Rute valid tidak diberi highlight.

## Komponen

- `routes/web.php`: endpoint export khusus yang memakai otorisasi export
  laporan izin yang sudah ada.
- `ReportPermitController`: method yang mengunci filter status dan mengunduh
  file XLSX.
- `PermitNeedsReviewExport`: query, pemetaan kolom, dan styling file Excel.
- `PermitReportQuery`: helper pembentukan filter khusus bila diperlukan, tanpa
  mengubah hasil export laporan izin reguler.
- `resources/views/reports/permits/index.blade.php`: tombol export baru.
- Feature test: cakupan filter status, validasi kode aktif/tidak tersedia,
  highlight, dan pencegahan data sensitif.

## Alur data

1. Pengguna berwenang membuka Laporan Izin dan menekan Export Perlu Review.
2. Controller memvalidasi query lalu mengunci `status` menjadi `needs_review`.
3. Export mengambil daftar kode Master Rute aktif sekali untuk seluruh file.
4. Setiap `route_raw` dibandingkan dengan daftar tersebut dan dipetakan ke
   kolom status validasi.
5. File Excel dikirim langsung ke pengguna; data basis data tidak diubah.

## Penanganan kasus tepi

- Rute kosong menghasilkan status perlu perbaikan dengan keterangan rute
  kosong/tidak memiliki kode resmi.
- Kode Master Rute berstatus draft atau inactive dianggap tidak tersedia.
- Teks bebas pada rute dicatat sebagai catatan yang perlu review, tetapi tidak
  diperlakukan sebagai kode rute yang tidak tersedia.
- Export tidak memuat `token_hash` maupun atribut QR sensitif lainnya.

## Validasi

Feature test membuktikan hanya data `needs_review` yang diexport, kode aktif
tidak ditandai, kode tidak aktif atau tidak terdaftar ditandai, dan metadata
styling menyorot data yang harus ditindaklanjuti.
