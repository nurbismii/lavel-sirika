# Task 5 Report - Permit Route Map DTO and Admin Preview

## What I implemented

- Added `App\Services\Routes\PermitRouteMapService` with contract `forPermit(VehiclePermit $permit): array`.
- Added admin-only preview controller `App\Http\Controllers\PermitRouteMapController`.
- Added route `permits.route-map.show` in `routes/web.php`.
- Added role mapping in `App\Models\User` so only `admin_hr` can access the route map preview.
- Updated `PermitController@index` to eager load `routeSegments`.
- Updated permit list view to:
  - keep existing QR actions intact,
  - add `Rute` column,
  - show raw route and segment count,
  - add `Lihat Rute` action link.
- Added preview page `resources/views/permits/route-map/show.blade.php`.
- Added TDD coverage:
  - `tests/Feature/PermitRouteMapServiceTest.php`
  - `tests/Feature/PermitRouteMapHttpTest.php`
- Extended `tests/Feature/PermitListAfterImportTest.php` to verify route column/action remain visible alongside QR actions.

## TDD evidence

### RED

Command:

```bash
php artisan test --filter=PermitRouteMapServiceTest
```

Output:

```text
FAIL  Tests\Feature\PermitRouteMapServiceTest
⨯ it builds route map data in permit route sequence
⨯ it marks segments without complete coordinates as missing

Illuminate\Contracts\Container\BindingResolutionException
Target class [App\Services\Routes\PermitRouteMapService] does not exist.
```

Command:

```bash
php artisan test --filter=PermitRouteMapHttpTest
```

Output:

```text
FAIL  Tests\Feature\PermitRouteMapHttpTest
⨯ admin hr can open permit route map preview from permit list
⨯ admin hr can open preview page and only sees route map fields
⨯ security cannot open admin permit route map preview

Route [permits.route-map.show] not defined.
```

### GREEN

Command:

```bash
php artisan test --filter=PermitRouteMapServiceTest
```

Output:

```text
PASS  Tests\Feature\PermitRouteMapServiceTest
✓ it builds route map data in permit route sequence
✓ it marks segments without complete coordinates as missing

Tests: 2 passed
```

Command:

```bash
php artisan test --filter=PermitRouteMapHttpTest
```

Output:

```text
PASS  Tests\Feature\PermitRouteMapHttpTest
✓ admin hr can open permit route map preview from permit list
✓ admin hr can open preview page and only sees route map fields
✓ security cannot open admin permit route map preview

Tests: 3 passed
```

## Tests run and results

```bash
php artisan test --filter=PermitRouteMapServiceTest
php artisan test --filter=PermitRouteMapHttpTest
php artisan test --filter=PermitListAfterImportTest
```

Results:

```text
PermitRouteMapServiceTest: PASS (2 tests)
PermitRouteMapHttpTest: PASS (3 tests)
PermitListAfterImportTest: PASS (3 tests)
```

## Files changed

- `app/Services/Routes/PermitRouteMapService.php`
- `app/Http/Controllers/PermitRouteMapController.php`
- `app/Http/Controllers/PermitController.php`
- `app/Models/User.php`
- `routes/web.php`
- `resources/views/permits/index.blade.php`
- `resources/views/permits/route-map/show.blade.php`
- `tests/Feature/PermitRouteMapServiceTest.php`
- `tests/Feature/PermitRouteMapHttpTest.php`
- `tests/Feature/PermitListAfterImportTest.php`
- `.superpowers/sdd/task-5-report.md`

## Self-review findings

- DTO payload was kept minimal on purpose: only map metadata, route label, complete segment codes with `lat_lngs` and `sequence`, plus missing segment codes.
- Preview page does not expose NIK or permit source, and service DTO does not include employee/vehicle personal fields.
- QR behavior on permit list remains unchanged; route preview is an additional action, not a replacement.
- Access control is explicit at route level and verified with a security-forbidden HTTP test.

## Concerns

- No functional concern found during this task.
