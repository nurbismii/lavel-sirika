# Shared vehicle permits Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow different NIKs to have independent active permits and QR tokens for one globally unique vehicle plate.

**Architecture:** Reuse one `vehicles` row by plate and create a permit per employee-vehicle pair. Keep the global plate and employee-vehicle unique constraints; remove only the different-NIK rejection and vehicle-wide active-permit restriction.

**Tech Stack:** Laravel 8, PHP 8, Eloquent, PHPUnit.

## Global Constraints

- `vehicles.plate_number` remains globally unique.
- `vehicle_permits.employee_id + vehicle_id` remains unique.
- Existing data is not deleted or migrated.
- Different employees may have active permits and QR tokens for the same vehicle.

---

### Task 1: Permit shared vehicles during import

**Files:**
- Modify: `app/Services/Imports/PermitExcelImportService.php`, `app/Services/Imports/PermitImportCommitService.php`
- Modify: `tests/Feature/ImportExcelPreviewTest.php`, `tests/Feature/ImportCommitTest.php`

**Interfaces:** `resolveVehicle(Employee $employee, string $plateNumber): Vehicle` reuses the globally unique vehicle regardless of its existing `employee_id`; duplicate protection remains `findExistingPermit($employee->id, $vehicle->id)`.

- [ ] Write failing preview and commit tests for two NIKs with the same plate, asserting preview has no different-NIK error, one vehicle row, and two permit rows.
- [ ] Run: `php artisan test --filter='(ImportExcelPreview|ImportCommit)'`; expect failure with `Plat kendaraan sudah terdaftar untuk NIK lain.`
- [ ] Remove different-NIK error checks from batch preview and locked commit/retry paths; reuse the locked vehicle and preserve unique employee-vehicle permit rejection.
- [ ] Run the focused tests; expect pass.
- [ ] Commit: `git add app/Services/Imports tests/Feature/ImportExcelPreviewTest.php tests/Feature/ImportCommitTest.php && git commit -m "feat: allow shared vehicle permits"`.

### Task 2: Permit concurrent active shared-vehicle permits

**Files:**
- Modify: `app/Services/Permits/PermitReviewService.php`
- Modify: `tests/Feature/PermitReviewServiceTest.php`
- Modify: `tests/Feature/PermitQrHttpTest.php`

**Interfaces:** `ensureNoOtherActivePermit(VehiclePermit $permit)` checks only duplicate active permit identity for that employee and vehicle; a different employee's permit does not block activation.

- [ ] Write failing tests that activate two permits owned by different employees but referencing one vehicle, then generate distinct QR tokens.
- [ ] Run: `php artisan test --filter='(PermitReviewService|PermitQrHttp)'`; expect failure because activation treats another employee's active permit as a conflict.
- [ ] Scope the active-permit query with `employee_id` in addition to `vehicle_id`, retaining locks and status checks.
- [ ] Run the focused tests; expect pass.
- [ ] Commit: `git add app/Services/Permits/PermitReviewService.php tests/Feature/PermitReviewServiceTest.php tests/Feature/PermitQrHttpTest.php && git commit -m "feat: activate shared vehicle permits"`.

### Task 3: Full regression verification

**Files:**
- Modify: `tests/Feature/SirikaDomainSchemaTest.php`

- [ ] Update migration expectations to assert the global plate unique index remains present while shared employee permits are allowed.
- [ ] Run: `$env:APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='; $env:CORS_ALLOWED_ORIGINS='https://sirika.vdnisite.com'; php artisan test` and `git diff --check`; expect all tests pass and no whitespace errors.
- [ ] Commit: `git add tests && git commit -m "test: cover shared vehicle permit rules"`.
