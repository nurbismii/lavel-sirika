# Multi-parking import parser Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Support multiple parking locations per permit and import the final SIRIKA route format safely.

**Architecture:** An additive pivot stores permit parking locations while the legacy foreign key keeps the first value. Import normalizes parking lists before the route parser removes parking codes and validates road segments.

**Tech Stack:** Laravel 8, PHP 8, Eloquent, migrations, PHPUnit, Blade, Laravel Excel.

## Global Constraints

- Retain `vehicle_permits.parking_location_id` as the first selected location.
- Migration copies legacy data without deletion.
- Multi-location separators are comma, newline, and `/`.
- `→` and spaced ` - ` separate route segments; hyphens inside parking codes are never separators.
- Unknown route text remains `needs_review`.

---

### Task 1: Add permit-parking data model

**Files:**
- Create: `database/migrations/2026_07_15_000004_create_vehicle_permit_parking_locations_table.php`
- Modify: `app/Models/VehiclePermit.php`, `app/Models/ParkingLocation.php`
- Test: `tests/Feature/SirikaDomainSchemaTest.php`, `tests/Feature/SirikaModelRelationshipTest.php`

**Interfaces:** `VehiclePermit::parkingLocations()` and `ParkingLocation::vehiclePermits()` are `BelongsToMany` relations. The pivot has unique `vehicle_permit_id + parking_location_id`.

- [ ] Write a failing test that attaches two locations and asserts both relations plus a migration-backfilled legacy location.
- [ ] Run: `php artisan test --filter='Sirika(DomainSchema|ModelRelationship)'`; expect failure because pivot/table do not exist.
- [ ] Create the pivot migration, foreign keys, unique key, and `insertOrIgnore` backfill for each non-null legacy location; add both Eloquent relations.
- [ ] Run the focused tests; expect pass.
- [ ] Commit: `git add app/Models database/migrations tests/Feature/Sirika* && git commit -m "feat: support multiple permit parking locations"`.

### Task 2: Normalize Excel locations and routes

**Files:**
- Modify: `app/Services/Imports/PermitImportRowNormalizer.php`, `app/Services/Imports/RouteSegmentParser.php`
- Test: `tests/Unit/PermitImportRowNormalizerTest.php`, `tests/Unit/RouteSegmentParserTest.php`

**Interfaces:** `normalized_data['parking_location_codes']` is a unique `string[]`; `RouteSegmentParser::parse($rawRoute, array $activeCodes, array $parkingCodes = [])` returns `codes` and `warnings`.

- [ ] Write failing tests for `CY-CC-P02 / CY-CC-P03` and `GA-MES1-P01 → Y1 → D2 → PLTU-PC-6-P10`, expecting locations `['CY-CC-P02', 'CY-CC-P03']`, segments `['Y1', 'D2']`, and no parking warning.
- [ ] Run: `php artisan test tests/Unit/RouteSegmentParserTest.php tests/Unit/PermitImportRowNormalizerTest.php`; expect failure due missing array/parser argument.
- [ ] Split locations with `preg_split('/[\\r\\n,]+|\\s+\\/\\s+/', $value)`, trim/deduplicate them, remove supplied parking codes with `preg_quote`, then remove residual hierarchical `/\\b[A-Z0-9]+(?:-[A-Z0-9]+){2,}\\b/i` tokens before road validation. Normalize spaced ` - ` to `→`.
- [ ] Run the focused tests; expect pass.
- [ ] Commit: `git add app/Services/Imports tests/Unit && git commit -m "feat: parse multi-parking import routes"`.

### Task 3: Persist imported and reviewed collections

**Files:**
- Modify: `app/Services/Imports/PermitImportCommitService.php`, `app/Services/Permits/PermitReviewService.php`, `app/Http/Requests/UpdatePermitReviewRequest.php`, `resources/views/permits/review/edit.blade.php`
- Test: `tests/Feature/ImportCommitTest.php`, `tests/Feature/PermitReviewServiceTest.php`, `tests/Feature/PermitReviewHttpTest.php`

**Interfaces:** Review submits `parking_location_ids[]`; import and review `sync()` all valid locations and write the first ID into `parking_location_id`.

- [ ] Write failing tests that import two parking codes and activate review using two IDs, then assert `parkingLocations->pluck('code')` is `['P1', 'P2']`.
- [ ] Run: `php artisan test --filter='(ImportCommit|PermitReviewService|PermitReviewHttp)'`; expect failure because services accept one ID.
- [ ] Resolve each import code with `firstOrCreate`, validate all active review IDs, `sync()` IDs inside the existing transaction, update the legacy field from the first ID, and change the review select to `multiple name="parking_location_ids[]"`.
- [ ] Run the focused tests; expect pass.
- [ ] Commit: `git add app/Services app/Http/Requests resources/views/permits/review tests/Feature && git commit -m "feat: persist multiple permit parking locations"`.

### Task 4: Render and filter all parking locations

**Files:**
- Modify: `app/Http/Controllers/PermitController.php`, `app/Services/Reports/PermitReportQuery.php`, `app/Services/Permits/PermitScanService.php`
- Modify: `app/Exports/PermitReportExport.php`, `app/Exports/PermitNeedsReviewExport.php`, `app/Exports/ScanReportExport.php`
- Modify: permit, report, scan, QR, and route-map Blade files that read `parkingLocation`
- Test: `tests/Feature/PermitReportQueryTest.php`, `tests/Feature/PermitReportHttpTest.php`, `tests/Feature/PermitScanServiceTest.php`, `tests/Feature/ScanReportQueryTest.php`

**Interfaces:** `VehiclePermit::parkingLocationCodes(): string` returns eager-loaded unique codes joined by `, `; filters use `whereHas('parkingLocations')`.

- [ ] Write failing tests asserting QR/export output `P1, P2` and a filter matches a permit through its second location.
- [ ] Run: `php artisan test --filter='(PermitReport|PermitScan|ScanReport)'`; expect failure because readers use the singular relation.
- [ ] Add the display helper, eager-load `parkingLocations`, replace singular displays, and filter with `whereHas('parkingLocations', fn ($query) => $query->whereKey($id))`.
- [ ] Run the focused tests; expect pass.
- [ ] Commit: `git add app resources/views tests/Feature && git commit -m "feat: display multiple permit parking locations"`.

### Task 5: Final-format regression and verification

**Files:**
- Modify: `tests/Feature/ImportExcelPreviewTest.php`, `tests/Feature/PermitListAfterImportTest.php`, `tests/Feature/PermitQrHttpTest.php`

- [ ] Add an Excel fixture row with `GA-MES1-P01, GA-MES3-P02` and `GA-MES1-P01 → Y1 → D2 → GA-MES3-P02`; assert preview is valid/reviewable only for genuine master-data issues and commit creates two pivot records.
- [ ] Run: `php artisan test --filter='(ImportExcelPreview|PermitListAfterImport|PermitQrHttp)'`; expect pass.
- [ ] Run: `$env:CORS_ALLOWED_ORIGINS='https://sirika.vdnisite.com'; php artisan test` and `git diff --check`; expect all tests pass and no whitespace errors.
- [ ] Commit: `git add tests && git commit -m "test: cover final multi-parking import format"`.
