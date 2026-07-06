# Task 1 Report: Route Map Config and Polyline Service Foundation

## What you implemented

- Menambahkan konfigurasi `sirika.route_map` di `config/sirika.php` dengan key, image URL, width, dan height berbasis env.
- Menambahkan `App\Support\RouteMapConfig` untuk membaca konfigurasi peta dan menurunkan bounds.
- Menambahkan `App\Services\Routes\RoadSegmentPolylineService` untuk:
  - membangun payload polyline dengan validasi mode simpan dan batas koordinat,
  - menghitung status / jumlah titik,
  - mengubah koordinat ke format Leaflet,
  - membentuk DTO segment,
  - merangkum status koordinat segment.
- Menambahkan test feature `RoadSegmentPolylineServiceTest` sesuai brief.

## TDD evidence

### RED

Command:

```bash
php artisan test --filter=RoadSegmentPolylineServiceTest
```

Relevant failing output:

```text
FAIL  Tests\Feature\RoadSegmentPolylineServiceTest
Illuminate\Contracts\Container\BindingResolutionException
Target class [App\Services\Routes\RoadSegmentPolylineService] does not exist.
Tests: 7 failed
```

Why expected:

- Test baru memang memanggil service yang belum dibuat.
- Failure ini membuktikan test gagal karena fondasi implementasi belum ada, bukan karena assertion typo.

### GREEN

Command:

```bash
php artisan test --filter=RoadSegmentPolylineServiceTest
```

Passing output:

```text
PASS  Tests\Feature\RoadSegmentPolylineServiceTest
Tests: 7 passed
```

## Tests run and results

1. `php artisan test --filter=RoadSegmentPolylineServiceTest`
   - RED: 7 failed, expected karena `RoadSegmentPolylineService` belum ada.
2. `php artisan test --filter=RoadSegmentPolylineServiceTest`
   - GREEN: 7 passed.
3. `php artisan test`
   - Full suite passed: 95 passed.

## Files changed

- `config/sirika.php`
- `app/Support/RouteMapConfig.php`
- `app/Services/Routes/RoadSegmentPolylineService.php`
- `tests/Feature/RoadSegmentPolylineServiceTest.php`

## Self-review findings

- Implementasi tetap dalam scope file yang diizinkan.
- Interface, validasi inti, file path, command, dan commit message mengikuti brief.
- Tidak ada perubahan skema database atau kontrak existing lain.
- Validasi koordinat memakai batas `0..width` dan `0..height` sesuai kebutuhan test.

## Concerns, if any

- Tidak ada concern fungsional untuk Task 1.
- Ada warning Git lokal soal normalisasi line ending `LF -> CRLF` pada `config/sirika.php`, tetapi tidak memengaruhi perilaku aplikasi.

## Fix implemented

- Memperbaiki `RoadSegmentPolylineService::normalizePoints()` agar validasi batas peta memakai nilai float mentah terlebih dahulu.
- Rounding `x` dan `y` tetap dilakukan, tetapi hanya saat membentuk payload simpan.
- Menambahkan regression test untuk memastikan nilai pecahan di luar batas seperti `1600.004` dan `1000.004` ditolak.

## RED command/output for the new failing test

Command:

```bash
php artisan test --filter=it_rejects_fractional_points_outside_map_bounds_before_rounding
```

Output:

```text
FAIL  Tests\Feature\RoadSegmentPolylineServiceTest
⨯ it rejects fractional points outside map bounds before rounding

Failed asserting that exception of type "Illuminate\Validation\ValidationException" is thrown.
Tests: 1 failed
```

## GREEN command/output

Command:

```bash
php artisan test --filter=it_rejects_fractional_points_outside_map_bounds_before_rounding
```

Output:

```text
PASS  Tests\Feature\RoadSegmentPolylineServiceTest
✓ it rejects fractional points outside map bounds before rounding
Tests: 1 passed
```

## Tests run

1. `php artisan test --filter=it_rejects_fractional_points_outside_map_bounds_before_rounding`
   - RED: 1 failed, expected karena nilai pecahan di luar batas masih lolos validasi.
2. `php artisan test --filter=it_rejects_fractional_points_outside_map_bounds_before_rounding`
   - GREEN: 1 passed.
3. `php artisan test --filter=RoadSegmentPolylineServiceTest`
   - PASS: 8 passed.
4. `php artisan test`
   - PASS: 96 passed.

## Files changed

- `app/Services/Routes/RoadSegmentPolylineService.php`
- `tests/Feature/RoadSegmentPolylineServiceTest.php`
- `.superpowers/sdd/task-1-report.md`
