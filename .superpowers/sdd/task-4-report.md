# Task 4 Report: Admin QR HTTP Flow

## Status
DONE

## Analisis singkat
- Task 4 menambahkan flow HTTP admin untuk generate, show, print, renew, dan bulk-generate QR permit.
- Constraint paling penting adalah raw token tidak disimpan, jadi QR lama tidak bisa direkonstruksi dari `token_hash`.
- Risiko utamanya ada pada dua area:
  - Salah expose route QR admin ke role `security`.
  - Salah perilaku pada `show` atau `print`, misalnya mencoba render ulang QR lama dari hash atau melakukan reprint tanpa renew token.

## Rekomendasi terbaik yang dipakai
- Mengikuti brief secara ketat:
  - `POST permits.qr.generate` membuat token baru dan langsung render SVG.
  - `GET permits.qr.show` hanya menampilkan metadata token aktif dan pesan bahwa QR lama tidak bisa dirender ulang.
  - `POST permits.qr.print` selalu renew/create token baru lalu render kartu print kecil.
  - `POST permits.qr.renew` selalu revoke token aktif lama lalu render SVG baru.
- Guard akses dibatasi ke `admin_hr` lewat route-role mapping. `security` tetap bisa untuk area scan, tetapi tidak untuk route admin QR.
- Implementasi dibuat tipis di controller dan tetap mendelegasikan logika token ke `PermitTokenService` supaya tidak menduplikasi aturan transaksi/row lock dari Task 2.

## File yang diubah
- `app/Http/Controllers/PermitQrController.php`
- `app/Models/User.php`
- `routes/web.php`
- `resources/views/permits/qr/show.blade.php`
- `resources/views/permits/qr/print.blade.php`
- `tests/Feature/PermitQrHttpTest.php`

## Urutan implementasi
1. Menulis test baru `PermitQrHttpTest`.
2. Menjalankan `php artisan test --filter=PermitQrHttpTest` untuk memastikan RED.
3. Menambahkan `PermitQrController`.
4. Menambahkan route QR admin dan mapping role di `User::routeRoles()`.
5. Menambahkan view `show` dan `print` sesuai constraint produk.
6. Menjalankan ulang test target sampai GREEN.
7. Menjalankan full suite untuk cek regresi lintas Task 1-3.

## Hasil TDD
### RED
Command:

```bash
php artisan test --filter=PermitQrHttpTest
```

Hasil awal gagal sesuai ekspektasi:
- `Route [permits.qr.generate] not defined.`
- `Route [permits.qr.bulk-generate] not defined.`

### GREEN
Command:

```bash
php artisan test --filter=PermitQrHttpTest
```

Hasil:
- 3 test pass

### Full suite
Command:

```bash
php artisan test
```

Hasil:
- 81 passed

## Perubahan perilaku yang sekarang tersedia
- Admin HR bisa generate QR untuk permit aktif dan langsung melihat SVG.
- Admin HR bisa membuka halaman show QR untuk melihat:
  - nama pemilik permit,
  - plat kendaraan,
  - lokasi parkir,
  - status token,
  - expiry token,
  - penjelasan bahwa QR lama tidak bisa direkonstruksi dari hash.
- Admin HR bisa print kartu QR, dan proses itu selalu menghasilkan token baru yang printable.
- Admin HR bisa renew QR dan langsung mendapatkan SVG token baru.
- Security mendapat `403 Forbidden` untuk seluruh route admin QR.
- Bulk generate hanya membuat token untuk permit aktif yang belum punya token aktif.

## Catatan production
- Flow `show` sengaja tidak mencoba render QR bila raw token sudah tidak tersedia. Ini sesuai model keamanan yang diminta.
- Flow `print` sengaja berupa `POST` dan selalu renew token. Ini bukan efek samping tak sengaja, tetapi keputusan produk yang benar karena kartu print butuh raw token baru.
- Saya tidak menambah route QR ke sidebar/menu atau daftar permit karena brief Task 4 hanya meminta HTTP flow, controller, routes, role mapping, views, dan test. Itu menjaga scope tetap aman dan kecil.

## Commit
- Akan dicatat setelah commit dibuat di branch task ini.
