# SIRIKA Security Exposure Inventory

Baseline: PHP 7.4.33 / Laravel 8.83.29

## Signed URL

Tidak ditemukan penggunaan `URL::signedRoute`, `URL::temporarySignedRoute`, `hasValidSignature`, atau middleware `signed` pada route SIRIKA. QR menggunakan random token 64 karakter; database hanya menyimpan SHA-256 hash. Route alias `signed` bawaan masih terdaftar di Kernel tetapi tidak dipakai route aplikasi.

## Email Validation

Email yang dapat diubah user hanya berada pada CRUD user Super Admin. `NoControlCharacters` menolak CR, LF, dan karakter kontrol sebelum rule `email` Laravel.

## File Import

Import hanya menerima XLSX/XLS sampai 10 MB, memeriksa extension dan MIME pada service boundary, wajib lolos parsing PhpSpreadsheet, wajib memiliki header SIRIKA, dibatasi 5000 baris termasuk header, dan menulis preview rows dalam transaction.

## API, Sanctum, and CORS

SIRIKA tidak memiliki API publik. Route scaffold `/api/user` dan trait `HasApiTokens` sudah dihapus. Package dan migration Sanctum dipertahankan sampai upgrade Laravel 12 agar Phase 7 tidak melakukan dependency atau schema churn. CORS default tidak memiliki path aktif dan credentials lintas origin nonaktif.

## Mail

Tidak ditemukan pengiriman email pada workflow aplikasi. SwiftMailer hadir secara transitive melalui Laravel 8 dan dihapus melalui upgrade Laravel 12, bukan lewat perubahan parsial pada PHP 7.4.
