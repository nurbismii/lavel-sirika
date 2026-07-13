# SIRIKA Dependency Risk Register

Baseline audit: PHP 7.4.33 / Laravel 8.83.29
Review cadence: sebelum setiap release dan paling lambat 30 hari setelah penerimaan risiko

## GHSA-crmm-hgp2-wgrp - Temporary Signed URL Path Confusion

- Dependency: `laravel/framework` 8.83.29
- Exposure aktual: tidak ditemukan signed route, temporary signed route, signature validation, atau middleware `signed` pada route aplikasi.
- Kontrol kompensasi: QR menggunakan random token 64 karakter dan menyimpan SHA-256 hash; regression test memastikan payload bukan signed URL.
- Residual risk: framework tetap vulnerable bila signed URL diperkenalkan sebelum upgrade.
- Keputusan release: belum diisi oleh pemilik sistem.
- Exit condition: upgrade ke Laravel 12 versi security-supported dan ulangi exposure audit.

## GHSA-5vg9-5847-vvmq - Email Validation CRLF Injection

- Dependency: `laravel/framework` 8.83.29
- Exposure aktual: email dapat diubah oleh Super Admin melalui CRUD user.
- Kontrol kompensasi: `NoControlCharacters` menolak CR, LF, NUL, dan karakter kontrol lain sebelum rule `email` Laravel.
- Residual risk: input email baru di masa depan harus memakai rule yang sama.
- Keputusan release: belum diisi oleh pemilik sistem.
- Exit condition: upgrade framework dan pertahankan regression test.

## GHSA-78fx-h6xr-vch4 - File Validation Bypass

- Dependency: `laravel/framework` 8.83.29
- Exposure aktual: Admin HR dan Super Admin dapat mengunggah workbook import XLSX/XLS.
- Kontrol kompensasi: role authorization, batas 10 MB, extension dan MIME allowlist pada service boundary, parsing PhpSpreadsheet, header wajib, batas 5000 baris, dan transaction untuk preview rows.
- Residual risk: MIME bukan bukti tunggal format file dan parser dependency tetap memproses input tidak tepercaya.
- Keputusan release: belum diisi oleh pemilik sistem.
- Exit condition: upgrade framework, jalankan corpus invalid-file test, dan pastikan audit bersih.

## Abandoned Package - fruitcake/laravel-cors

- Dependency: `fruitcake/laravel-cors` 2.2.0.
- Exposure aktual: middleware global aktif, tetapi default `cors.paths` kosong dan cross-origin credentials nonaktif.
- Kontrol kompensasi: tidak ada API publik atau origin lintas domain yang diizinkan.
- Residual risk: package tidak menerima maintenance upstream.
- Keputusan release: belum diisi oleh pemilik sistem.
- Exit condition: hapus package saat upgrade Laravel 12 dan gunakan CORS framework target.

## Abandoned Package - swiftmailer/swiftmailer

- Dependency: `swiftmailer/swiftmailer`, transitive melalui Laravel 8.
- Exposure aktual: tidak ditemukan pengiriman email pada workflow SIRIKA.
- Kontrol kompensasi: fitur mail baru dilarang sebelum upgrade dependency ditinjau.
- Residual risk: package abandoned tetap terpasang pada vendor production.
- Keputusan release: belum diisi oleh pemilik sistem.
- Exit condition: migrasi ke Symfony Mailer melalui upgrade Laravel 12.

## Persetujuan Release Sementara

Status keputusan: Belum diisi
Pemilik penerimaan risiko:
Tanggal persetujuan:
Tanggal review ulang:
Alasan bisnis:

Tanpa isian tersebut, release production tetap berstatus tertahan. Developer tidak mengisi persetujuan atas nama pemilik sistem.

## Exit Condition

Residual risk ditutup setelah `sirika.vdnisite.com` memiliki runtime PHP 8.2 terisolasi, upgrade Laravel 12 selesai, regression suite lulus, dan `composer audit` tidak lagi melaporkan advisory yang belum dinilai.
