## Task 6 Report: Add Route Map Data to Valid QR Scan Results

### What I implemented

- Menambahkan `route_map` ke payload hasil scan QR yang `valid` di `PermitScanService` melalui `PermitRouteMapService`.
- Menjaga perilaku hasil non-valid tetap ketat:
  - `expired`, `revoked`, `inactive`, dan `invalid` tetap tanpa `route_map`.
  - Jika pembentukan peta gagal pada scan valid, hasil scan tetap `valid` dan hanya menambahkan `route_map_warning`.
- Menambahkan render peta rute di UI scanner hanya ketika `result.permit.route_map` tersedia.
- Menambahkan warning UI untuk `missing_segments` dan `route_map_warning`.
- Menambahkan coverage TDD di level service dan HTTP.

### TDD evidence

#### RED

Command:

```bash
php artisan test --filter=PermitScanServiceTest
php artisan test --filter=ScanQrHttpTest
```

Output:

```text
FAIL Tests\Feature\PermitScanServiceTest
- scan service accepts valid token and logs valid result
Failed asserting that an array has the key 'route_map'.
```

```text
FAIL Tests\Feature\ScanQrHttpTest
- security can verify valid token via http and scan is logged
Failed asserting that null is identical to 'vdni-road-map-v1'.
```

Catatan kompatibilitas:

- Brief menyebut `assertJsonMissingPath('permit.route_map')`, tetapi method itu tidak tersedia pada versi Laravel project ini.
- Saya pakai pemeriksaan ekuivalen: `assertNull(data_get($response->json(), 'permit.route_map'))`.

#### GREEN

Command:

```bash
php artisan test --filter=PermitScanServiceTest
php artisan test --filter=ScanQrHttpTest
```

Output:

```text
PASS Tests\Feature\PermitScanServiceTest
Tests: 5 passed
```

```text
PASS Tests\Feature\ScanQrHttpTest
Tests: 4 passed
```

### Tests/build run and results

Commands run:

```bash
npm.cmd run dev
php artisan test --filter=PermitScanServiceTest
php artisan test --filter=ScanQrHttpTest
```

Results:

- `npm.cmd run dev`: sukses, Mix compile berhasil.
- `PermitScanServiceTest`: 5/5 passed.
- `ScanQrHttpTest`: 4/4 passed.

### Files changed

- `app/Services/Permits/PermitScanService.php`
- `resources/views/scan/index.blade.php`
- `resources/css/app.css`
- `public/css/app.css`
- `tests/Feature/PermitScanServiceTest.php`
- `tests/Feature/ScanQrHttpTest.php`

Tidak diubah:

- `resources/js/route-map.js`
- `public/js/app.js`

### Self-review findings

- DTO `route_map` tetap berasal dari `PermitRouteMapService`, jadi tidak menambah data personal sensitif baru.
- `limitedPermitData()` tidak disentuh, sehingga expired tetap hanya mengembalikan detail terbatas.
- Query scan sekarang eager-load `permit.routeSegments` untuk menghindari query tambahan saat build route map.
- UI scanner hanya merender komponen peta bila `result.permit.route_map` ada, sesuai brief.
- Perubahan frontend scoped; hanya menambah layout wrapper `scan-route-map` dan reuse komponen preview peta yang sudah ada.

### Concerns, if any

- `assertJsonMissingPath()` dari brief tidak tersedia di versi Laravel project ini, jadi saya pakai assertion ekuivalen berbasis `data_get()` untuk menjaga acceptance behavior tetap sama.
