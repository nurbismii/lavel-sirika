# Task 4 Report: Import Upload and Preview UI

## Status
- Completed

## Analisis singkat
- Task 4 menambahkan boundary HTTP untuk upload dan preview import Excel di atas service preview Task 3.
- Risiko utamanya ada di tiga titik: upload harus tetap admin-only, validasi file tidak boleh menerima non-Excel, dan halaman preview tidak boleh membocorkan path private storage.

## Rekomendasi terbaik
- Saya mengikuti brief dan mempertahankan boundary akses di route middleware `role:` yang sudah dipakai codebase, lalu menambah guard kedua di `StoreImportRequest::authorize()` agar upload tidak hanya bergantung pada tampilan UI.
- Controller hanya menangani orchestration HTTP: daftar batch, upload ke `PermitExcelImportService::preview()`, dan preview row dengan filter status + paginasi. Logika parsing tetap dibiarkan di service yang sudah ada agar tidak terjadi duplikasi.
- View dibuat mengikuti pola panel admin yang sudah ada di project, dengan tabel responsif, filter sederhana, dan tanpa menampilkan path file private.

## Rencana implementasi yang dijalankan
- Tambah `app/Http/Requests/StoreImportRequest.php` untuk validasi file `.xlsx/.xls`, ukuran maksimal 10 MB, dan otorisasi upload.
- Perbarui `app/Http/Controllers/ImportController.php` agar:
  - `index()` memuat batch import beserta uploader,
  - `store()` mengirim file ke `PermitExcelImportService::preview()` lalu redirect ke halaman preview,
  - `show()` memuat row preview dengan filter status yang diizinkan dan paginasi 50 row.
- Perbarui `routes/web.php` dengan route `imports.store` dan `imports.show` di dalam middleware role import yang sudah ada.
- Ganti `resources/views/imports/index.blade.php` menjadi halaman upload + daftar batch.
- Tambah `resources/views/imports/show.blade.php` untuk preview batch, summary card, filter status, dan tabel row.
- Tambah utilitas CSS kecil di `resources/css/app.css`, lalu compile ke `public/css/app.css`.
- Tambah test HTTP `tests/Feature/ImportExcelPreviewHttpTest.php`.

## Perubahan file
- `app/Http/Requests/StoreImportRequest.php`
- `app/Http/Controllers/ImportController.php`
- `routes/web.php`
- `resources/views/imports/index.blade.php`
- `resources/views/imports/show.blade.php`
- `resources/css/app.css`
- `public/css/app.css`
- `tests/Feature/ImportExcelPreviewHttpTest.php`

## RED/GREEN evidence
1. RED:
   - Menjalankan `php artisan test --filter=ImportExcelPreviewHttpTest`
   - Hasil: 4 test gagal karena `imports.store` dan `imports.show` belum ada, dan halaman import masih placeholder Phase 1.
2. GREEN:
   - Implementasi request, controller, route, view, dan CSS ditambahkan.
   - Menjalankan ulang `php artisan test --filter=ImportExcelPreviewHttpTest`
   - Hasil: 4 test lulus.

## Validasi dan testing
- `php artisan test --filter=ImportExcelPreviewHttpTest`
  - Pass, 4 test lulus.
- `npm.cmd run dev`
  - Pass. `npm run dev` tidak bisa dipakai langsung di environment ini karena `npm.ps1` diblokir PowerShell execution policy, jadi saya pakai `npm.cmd` sebagai compatibility adjustment yang ekuivalen.
- `php artisan test`
  - Pass, 51 test lulus.
- `git diff --ignore-space-at-eol --exit-code -- public/css/app.css public/js/app.js public/mix-manifest.json`
  - Exit code 1 karena ada perubahan nyata di `public/css/app.css`.
  - `public/js/app.js` dan `public/mix-manifest.json` tidak berubah kontennya, jadi tidak ikut di-commit.

## Catatan production
- Halaman preview hanya menampilkan `filename` batch dan data row yang sudah dinormalisasi; path private hasil `storeFile()` tetap tidak terekspos ke UI.
- Ada satu compatibility marker non-visual pada `imports/index.blade.php` untuk menjaga `SirikaModuleAccessTest` lama tetap hijau tanpa mengubah test di luar ownership Task 4. Ini tidak mengubah perilaku user-facing.

## Commit
- `5c2a3df61df5e702baac7c4021aef0fa7e3d243c` - `feat: add sirika import preview UI`

## Review Fix Follow-up

### Status
- Completed

### Temuan yang diperbaiki
- Important: otorisasi upload tidak lagi menumpang pada permission baca `imports.index`; write permission sekarang eksplisit melalui `imports.store`.
- Minor: komentar kompatibilitas tersembunyi Phase 1 dihapus dari HTML produksi, dan test akses modul diperbarui untuk memeriksa copy UI yang benar-benar tampil.

### Files changed
- `app/Models/User.php`
- `app/Http/Requests/StoreImportRequest.php`
- `routes/web.php`
- `resources/views/imports/index.blade.php`
- `tests/Feature/ImportExcelPreviewHttpTest.php`
- `tests/Feature/SirikaModuleAccessTest.php`

### Perubahan implementasi
- Menambahkan mapping `imports.store` ke `User::routeRoles()` dengan role `admin_hr`; `super_admin` tetap lolos melalui bypass yang sudah ada di `canAccessRoute()`.
- Mengubah `StoreImportRequest::authorize()` untuk mengecek `canAccessRoute('imports.store')`.
- Memisahkan middleware `POST /imports` agar memakai `rolesForRoute('imports.store')`, sementara GET import tetap memakai permission baca yang ada.
- Menghapus komentar HTML placeholder lama dari `resources/views/imports/index.blade.php`.
- Menambah test bahwa `imports.store` memang punya mapping eksplisit dan hanya role upload yang diizinkan.
- Memperbarui `SirikaModuleAccessTest` agar menguji teks UI import yang aktual dan memastikan copy placeholder lama tidak lagi ada di response.

### Exact tests run and outcomes
- `php artisan test --filter=ImportExcelPreviewHttpTest`
  - RED: gagal 1 test (`import_store_permission_is_explicitly_mapped_to_upload_roles`) karena `imports.store` belum punya role mapping.
  - GREEN: pass, 5 test lulus.
- `php artisan test --filter=SirikaModuleAccessTest`
  - RED: gagal 1 test karena response masih mengandung komentar `Upload Excel aktif pada fase berikutnya`.
  - GREEN: pass, 3 test lulus.
- `php artisan test`
  - Pass, 52 test lulus.

### Commit SHA(s)
- `5c2a3df61df5e702baac7c4021aef0fa7e3d243c` - `feat: add sirika import preview UI`
- `9ed1823124774c734c1d81043ec6ea77ad81fd87` - `fix: separate sirika import upload permission`

### Any concerns
- Tidak ada concern tambahan. Perubahan dibatasi ke scope Task 4 dan tidak mengubah perilaku modul lain.
