# SIRIKA Phase 5B Operational Reporting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build dashboard visibility, permit reporting, scan reporting, and Excel export for SIRIKA without changing core permit, QR, scan, or route-map rules.

**Architecture:** Reporting is read-only and uses existing tables. Filter logic lives in focused query services so HTML pages and Excel exports use the same source of truth. Exports use Laravel Excel `FromQuery` to avoid loading full datasets into memory.

**Tech Stack:** PHP 7.4, Laravel 8, Blade, Alpine.js, Leaflet.js, MySQL/PostgreSQL, Laravel Excel, BaconQrCode, html5-qrcode.

## Global Constraints

- Baseline: Phase 5A review dan aktivasi izin sudah merge ke `main`.
- Laporan menggunakan data existing, bukan audit table baru.
- Export memakai Laravel Excel.
- Export laporan scan dibatasi rentang tanggal maksimal 31 hari per request.
- Halaman laporan scan default menampilkan 7 hari terakhir.
- Export tidak menyertakan token QR, token hash, password, remember token, atau IP address.
- Security tidak mendapat akses laporan izin dan scan.
- Auditor boleh melihat dan export laporan, tetapi tidak mendapat akses mutasi data.
- Dashboard tidak memakai chart library baru.
- Query halaman HTML wajib memakai pagination.
- Query laporan wajib memakai eager loading untuk mencegah N+1.
- Tidak membuat migration baru di Phase 5B.
- Tidak mengubah aturan masa aktif QR.
- Tidak mengubah mekanisme generate, renew, print, atau scan QR.
- Ikuti pola Laravel 8 dan PHP 7.4 yang sudah dipakai project.

---

## File Structure

- Create `tests/Feature/ReportAuthorizationTest.php`
  - Mengunci kontrak role untuk route laporan.

- Modify `app/Models/User.php`
  - Menambah route role `reports.permits.index`, `reports.permits.export`, `reports.scans.index`, dan `reports.scans.export`.

- Create `tests/Feature/PermitReportQueryTest.php`
  - Menguji filter laporan izin dan status QR.

- Create `app/Services/Reports/PermitReportQuery.php`
  - Menormalisasi filter laporan izin.
  - Membangun query laporan izin.
  - Menghitung label/status QR untuk halaman dan export.
  - Menyediakan option filter.

- Create `tests/Feature/PermitReportHttpTest.php`
  - Menguji akses halaman laporan izin, filter HTTP, export Excel, dan proteksi token hash.

- Create `app/Http/Requests/ReportPermitRequest.php`
  - Validasi filter laporan izin.

- Create `app/Http/Controllers/ReportPermitController.php`
  - Menampilkan laporan izin dan menjalankan export Excel.

- Create `app/Exports/PermitReportExport.php`
  - Export Excel laporan izin memakai `FromQuery`.

- Create `resources/views/reports/permits/index.blade.php`
  - UI filter, tabel, pagination, export button, dan empty state laporan izin.

- Modify `routes/web.php`
  - Menambah route laporan izin.

- Modify `resources/views/layouts/app.blade.php`
  - Menambah link navigasi `Laporan Izin` sesuai role.

- Create `tests/Feature/ScanReportQueryTest.php`
  - Menguji filter laporan scan, default date range, dan validasi range export.

- Create `app/Services/Reports/ScanReportQuery.php`
  - Menormalisasi filter laporan scan.
  - Membangun query laporan scan.
  - Memvalidasi rentang export maksimal 31 hari.
  - Menyediakan option result dan scanner.

- Create `tests/Feature/ScanReportHttpTest.php`
  - Menguji akses halaman laporan scan, filter HTTP, export Excel, batas 31 hari, dan proteksi IP address.

- Create `app/Http/Requests/ReportScanRequest.php`
  - Validasi filter laporan scan.

- Create `app/Http/Controllers/ReportScanController.php`
  - Menampilkan laporan scan dan menjalankan export Excel.

- Create `app/Exports/ScanReportExport.php`
  - Export Excel laporan scan memakai `FromQuery`.

- Create `resources/views/reports/scans/index.blade.php`
  - UI filter, tabel, pagination, export button, dan empty state laporan scan.

- Modify `routes/web.php`
  - Menambah route laporan scan.

- Modify `resources/views/layouts/app.blade.php`
  - Menambah link navigasi `Laporan Scan` sesuai role.

- Modify `tests/Feature/DashboardUiTest.php`
  - Mengganti assertion dashboard lama dengan dashboard operasional Phase 5B.

- Modify `app/Http/Controllers/DashboardController.php`
  - Menambah metric QR, scan, status summary, dan activity feed.

- Modify `resources/views/dashboard/index.blade.php`
  - Menampilkan dashboard operasional baru dan menghapus copy stale.

- Modify `resources/css/app.css`
  - Menambah style kecil untuk activity feed/dashboard summary jika class existing belum cukup.

- Modify `public/css/app.css`
  - Hasil build dari `npm.cmd run dev`.

---

### Task 1: Report Route Authorization Contract

**Files:**
- Create: `tests/Feature/ReportAuthorizationTest.php`
- Modify: `app/Models/User.php`

**Interfaces:**
- Consumes: `App\Models\User::rolesForRoute(string $routeName): array`
- Consumes: `App\Models\User::canAccessRoute(string $routeName): bool`
- Produces route role entries:
  - `reports.permits.index`
  - `reports.permits.export`
  - `reports.scans.index`
  - `reports.scans.export`

- [ ] **Step 1: Write the failing authorization contract test**

Create `tests/Feature/ReportAuthorizationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function report_routes_are_mapped_to_admin_hr_and_auditor_roles()
    {
        $expected = [
            User::ROLE_ADMIN_HR,
            User::ROLE_AUDITOR,
        ];

        $this->assertSame($expected, User::rolesForRoute('reports.permits.index'));
        $this->assertSame($expected, User::rolesForRoute('reports.permits.export'));
        $this->assertSame($expected, User::rolesForRoute('reports.scans.index'));
        $this->assertSame($expected, User::rolesForRoute('reports.scans.export'));
    }

    /** @test */
    public function report_navigation_access_follows_report_roles()
    {
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $auditor = $this->user(User::ROLE_AUDITOR);
        $security = $this->user(User::ROLE_SECURITY);
        $superAdmin = $this->user(User::ROLE_SUPER_ADMIN);

        foreach (['reports.permits.index', 'reports.scans.index'] as $routeName) {
            $this->assertTrue($admin->canAccessRoute($routeName));
            $this->assertTrue($auditor->canAccessRoute($routeName));
            $this->assertTrue($superAdmin->canAccessRoute($routeName));
            $this->assertFalse($security->canAccessRoute($routeName));
        }
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }
}
```

- [ ] **Step 2: Run the authorization test and verify it fails**

Run:

```bash
php artisan test --filter=ReportAuthorizationTest
```

Expected result:

```text
FAIL  Tests\Feature\ReportAuthorizationTest
Failed asserting that two arrays are identical.
```

The failure must be caused by missing `reports.*` route role entries.

- [ ] **Step 3: Add report route role entries**

Modify `app/Models/User.php` inside `routeRoles()` and add these entries after the existing permit/scan entries:

```php
'reports.permits.index' => [
    self::ROLE_ADMIN_HR,
    self::ROLE_AUDITOR,
],
'reports.permits.export' => [
    self::ROLE_ADMIN_HR,
    self::ROLE_AUDITOR,
],
'reports.scans.index' => [
    self::ROLE_ADMIN_HR,
    self::ROLE_AUDITOR,
],
'reports.scans.export' => [
    self::ROLE_ADMIN_HR,
    self::ROLE_AUDITOR,
],
```

Do not add `security` to these arrays. `super_admin` does not need to be listed because `canAccessRoute()` and the role middleware already treat it as an override role.

- [ ] **Step 4: Run the authorization test and verify it passes**

Run:

```bash
php artisan test --filter=ReportAuthorizationTest
```

Expected result:

```text
PASS  Tests\Feature\ReportAuthorizationTest
```

- [ ] **Step 5: Commit Task 1**

Run:

```bash
git add app/Models/User.php tests/Feature/ReportAuthorizationTest.php
git commit -m "feat: add report route authorization"
```

---

### Task 2: Permit Report Query Service

**Files:**
- Create: `tests/Feature/PermitReportQueryTest.php`
- Create: `app/Services/Reports/PermitReportQuery.php`

**Interfaces:**
- Produces: `PermitReportQuery::filters(array $input): array`
- Produces: `PermitReportQuery::query(array $filters)`
- Produces: `PermitReportQuery::qrStatusValue(VehiclePermit $permit): string`
- Produces: `PermitReportQuery::qrStatusLabel(VehiclePermit $permit): string`
- Produces: `PermitReportQuery::statusOptions(): array`
- Produces: `PermitReportQuery::qrStatusOptions(): array`
- Produces: `PermitReportQuery::reviewStatusOptions(): array`
- Produces: `PermitReportQuery::colorOptions(): array`
- Produces: `PermitReportQuery::sourceOptions(): array`

- [ ] **Step 1: Write the failing permit report query tests**

Create `tests/Feature/PermitReportQueryTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\PermitToken;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Reports\PermitReportQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitReportQueryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_filters_permits_by_status_review_status_source_color_parking_and_search()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $parking = $this->parking('P1');
        $reviewer = $this->user(User::ROLE_ADMIN_HR);

        $matching = $this->permit([
            'name' => 'MATCH REPORT USER',
            'nik' => '15090001',
            'plate' => 'DT 9001 MR',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'parking_location_id' => $parking->id,
            'permit_color' => 'biru',
            'source' => 'import',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $this->permit([
            'name' => 'BLOCKED REPORT USER',
            'nik' => '15090002',
            'plate' => 'DT 9002 BR',
            'status' => VehiclePermit::STATUS_NEEDS_REVIEW,
            'permit_color' => 'merah',
            'source' => 'manual',
        ]);

        $reports = app(PermitReportQuery::class);

        $results = $reports->query($reports->filters([
            'status' => VehiclePermit::STATUS_ACTIVE,
            'review_status' => 'reviewed',
            'source' => 'import',
            'permit_color' => 'biru',
            'parking_location_id' => $parking->id,
            'search' => '9001',
        ]))->get();

        $this->assertCount(1, $results);
        $this->assertSame($matching->id, $results->first()->id);
        $this->assertSame(0, (int) $results->first()->route_segments_count);
    }

    /** @test */
    public function it_filters_permits_by_qr_status()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $active = $this->permit(['name' => 'QR ACTIVE USER', 'plate' => 'DT 9101 QA']);
        $expired = $this->permit(['name' => 'QR EXPIRED USER', 'plate' => 'DT 9102 QE']);
        $revoked = $this->permit(['name' => 'QR REVOKED USER', 'plate' => 'DT 9103 QR']);
        $missing = $this->permit(['name' => 'QR MISSING USER', 'plate' => 'DT 9104 QM']);

        $this->token($active, PermitToken::STATUS_ACTIVE, now()->addYear());
        $this->token($expired, PermitToken::STATUS_ACTIVE, now()->subDay());
        $this->token($revoked, PermitToken::STATUS_REVOKED, now()->addYear(), now());

        $reports = app(PermitReportQuery::class);

        $this->assertSame([$active->id], $this->idsForQrStatus($reports, 'active'));
        $this->assertSame([$expired->id], $this->idsForQrStatus($reports, 'expired'));
        $this->assertSame([$revoked->id], $this->idsForQrStatus($reports, 'revoked'));
        $this->assertSame([$missing->id], $this->idsForQrStatus($reports, 'missing'));
    }

    /** @test */
    public function it_resolves_qr_status_labels_from_loaded_tokens()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $permit = $this->permit(['name' => 'QR LABEL USER', 'plate' => 'DT 9201 QL']);
        $this->token($permit, PermitToken::STATUS_ACTIVE, now()->addYear());

        $permit = $permit->fresh(['activeToken', 'latestToken']);
        $reports = app(PermitReportQuery::class);

        $this->assertSame('active', $reports->qrStatusValue($permit));
        $this->assertSame('QR Aktif', $reports->qrStatusLabel($permit));
    }

    private function idsForQrStatus(PermitReportQuery $reports, string $qrStatus): array
    {
        return $reports->query($reports->filters(['qr_status' => $qrStatus]))
            ->orderBy('vehicle_permits.id')
            ->pluck('vehicle_permits.id')
            ->all();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function parking(string $code): ParkingLocation
    {
        return ParkingLocation::create([
            'code' => $code,
            'name' => 'Parkir ' . $code,
            'status' => 'active',
        ]);
    }

    private function permit(array $overrides = []): VehiclePermit
    {
        $employee = Employee::create([
            'nik' => $overrides['nik'] ?? 'EMP-' . uniqid(),
            'name' => $overrides['name'] ?? 'REPORT USER',
            'department' => $overrides['department'] ?? 'GA',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => $overrides['plate'] ?? 'DT 9000 RP',
            'vehicle_type' => $overrides['vehicle_type'] ?? 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $overrides['parking_location_id'] ?? null,
            'permit_color' => $overrides['permit_color'] ?? 'biru',
            'approval_status' => 'approved',
            'valid_from' => $overrides['valid_from'] ?? now()->toDateString(),
            'valid_until' => $overrides['valid_until'] ?? now()->addYear()->toDateString(),
            'status' => $overrides['status'] ?? VehiclePermit::STATUS_ACTIVE,
            'source' => $overrides['source'] ?? 'import',
            'route_raw' => $overrides['route_raw'] ?? 'Y1',
            'reviewed_by' => $overrides['reviewed_by'] ?? null,
            'reviewed_at' => $overrides['reviewed_at'] ?? null,
            'review_note' => $overrides['review_note'] ?? null,
        ]);
    }

    private function token(VehiclePermit $permit, string $status, ?Carbon $expiresAt, ?Carbon $revokedAt = null): PermitToken
    {
        return PermitToken::create([
            'vehicle_permit_id' => $permit->id,
            'token_hash' => hash('sha256', uniqid('report-token-', true)),
            'status' => $status,
            'expires_at' => $expiresAt,
            'revoked_at' => $revokedAt,
        ]);
    }
}
```

- [ ] **Step 2: Run the query test and verify it fails**

Run:

```bash
php artisan test --filter=PermitReportQueryTest
```

Expected result:

```text
FAIL  Tests\Feature\PermitReportQueryTest
Class "App\Services\Reports\PermitReportQuery" not found
```

- [ ] **Step 3: Implement `PermitReportQuery`**

Create directory `app/Services/Reports` and file `app/Services/Reports/PermitReportQuery.php`:

```php
<?php

namespace App\Services\Reports;

use App\Models\PermitToken;
use App\Models\VehiclePermit;
use Illuminate\Support\Facades\DB;

class PermitReportQuery
{
    public function filters(array $input): array
    {
        return [
            'status' => $this->nullableString($input['status'] ?? null),
            'qr_status' => $this->nullableString($input['qr_status'] ?? null),
            'permit_color' => $this->nullableString($input['permit_color'] ?? null),
            'parking_location_id' => $input['parking_location_id'] ?? null,
            'source' => $this->nullableString($input['source'] ?? null),
            'review_status' => $this->nullableString($input['review_status'] ?? null),
            'search' => $this->nullableString($input['search'] ?? null),
        ];
    }

    public function query(array $filters)
    {
        $filters = $this->filters($filters);

        $query = VehiclePermit::query()
            ->with([
                'employee',
                'vehicle',
                'parkingLocation',
                'reviewer',
                'activeToken',
                'latestToken',
            ])
            ->withCount('routeSegments')
            ->orderByDesc('vehicle_permits.created_at')
            ->orderByDesc('vehicle_permits.id');

        $this->applyFilters($query, $filters);

        return $query;
    }

    public function statusOptions(): array
    {
        return [
            VehiclePermit::STATUS_NEEDS_REVIEW => 'Perlu Review',
            VehiclePermit::STATUS_ACTIVE => 'Aktif',
            VehiclePermit::STATUS_DRAFT => 'Draft',
            VehiclePermit::STATUS_SUSPENDED => 'Ditangguhkan',
            VehiclePermit::STATUS_EXPIRED => 'Kadaluwarsa',
            VehiclePermit::STATUS_REVOKED => 'Dicabut',
        ];
    }

    public function qrStatusOptions(): array
    {
        return [
            'missing' => 'Belum dibuat',
            'active' => 'QR Aktif',
            'expired' => 'QR Kadaluwarsa',
            'revoked' => 'QR Dicabut',
        ];
    }

    public function reviewStatusOptions(): array
    {
        return [
            'pending' => 'Belum direview',
            'reviewed' => 'Sudah direview',
        ];
    }

    public function colorOptions(): array
    {
        return VehiclePermit::query()
            ->whereNotNull('permit_color')
            ->where('permit_color', '!=', '')
            ->orderBy('permit_color')
            ->distinct()
            ->pluck('permit_color', 'permit_color')
            ->all();
    }

    public function sourceOptions(): array
    {
        return VehiclePermit::query()
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->orderBy('source')
            ->distinct()
            ->pluck('source', 'source')
            ->all();
    }

    public function statusSummary(): array
    {
        return VehiclePermit::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    public function qrStatusValue(VehiclePermit $permit): string
    {
        $activeToken = $permit->activeToken;
        $latestToken = $permit->latestToken;

        if ($activeToken && $activeToken->expires_at && $activeToken->expires_at->isPast()) {
            return 'expired';
        }

        if ($activeToken) {
            return 'active';
        }

        if ($latestToken && $latestToken->status === PermitToken::STATUS_REVOKED) {
            return 'revoked';
        }

        return 'missing';
    }

    public function qrStatusLabel(VehiclePermit $permit): string
    {
        $value = $this->qrStatusValue($permit);

        return $this->qrStatusOptions()[$value] ?? $value;
    }

    private function applyFilters($query, array $filters): void
    {
        if ($filters['status']) {
            $query->where('vehicle_permits.status', $filters['status']);
        }

        if ($filters['permit_color']) {
            $query->where('vehicle_permits.permit_color', $filters['permit_color']);
        }

        if ($filters['parking_location_id']) {
            $query->where('vehicle_permits.parking_location_id', $filters['parking_location_id']);
        }

        if ($filters['source']) {
            $query->where('vehicle_permits.source', $filters['source']);
        }

        if ($filters['review_status'] === 'reviewed') {
            $query->whereNotNull('vehicle_permits.reviewed_at');
        }

        if ($filters['review_status'] === 'pending') {
            $query->whereNull('vehicle_permits.reviewed_at');
        }

        if ($filters['search']) {
            $this->applySearchFilter($query, $filters['search']);
        }

        if ($filters['qr_status']) {
            $this->applyQrStatusFilter($query, $filters['qr_status']);
        }
    }

    private function applySearchFilter($query, string $search): void
    {
        $query->where(function ($subQuery) use ($search) {
            $subQuery->whereHas('employee', function ($employeeQuery) use ($search) {
                $employeeQuery->where('nik', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%');
            })->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                $vehicleQuery->where('plate_number', 'like', '%' . $search . '%');
            });
        });
    }

    private function applyQrStatusFilter($query, string $qrStatus): void
    {
        if ($qrStatus === 'active') {
            $query->whereHas('tokens', function ($tokenQuery) {
                $tokenQuery->where('status', PermitToken::STATUS_ACTIVE)
                    ->where(function ($dateQuery) {
                        $dateQuery->whereNull('expires_at')
                            ->orWhere('expires_at', '>=', now());
                    });
            });
        }

        if ($qrStatus === 'expired') {
            $query->whereHas('tokens', function ($tokenQuery) {
                $tokenQuery->where('status', PermitToken::STATUS_ACTIVE)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', now());
            });
        }

        if ($qrStatus === 'missing') {
            $query->whereDoesntHave('tokens', function ($tokenQuery) {
                $tokenQuery->where('status', PermitToken::STATUS_ACTIVE);
            })->whereDoesntHave('tokens', function ($tokenQuery) {
                $tokenQuery->where('status', PermitToken::STATUS_REVOKED);
            });
        }

        if ($qrStatus === 'revoked') {
            $query->whereDoesntHave('tokens', function ($tokenQuery) {
                $tokenQuery->where('status', PermitToken::STATUS_ACTIVE);
            })->whereHas('tokens', function ($tokenQuery) {
                $tokenQuery->where('status', PermitToken::STATUS_REVOKED);
            });
        }
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
```

- [ ] **Step 4: Run the query test and verify it passes**

Run:

```bash
php artisan test --filter=PermitReportQueryTest
```

Expected result:

```text
PASS  Tests\Feature\PermitReportQueryTest
```

- [ ] **Step 5: Commit Task 2**

Run:

```bash
git add app/Services/Reports/PermitReportQuery.php tests/Feature/PermitReportQueryTest.php
git commit -m "feat: add permit report query service"
```

---

### Task 3: Permit Report Page and Excel Export

**Files:**
- Create: `tests/Feature/PermitReportHttpTest.php`
- Create: `app/Http/Requests/ReportPermitRequest.php`
- Create: `app/Http/Controllers/ReportPermitController.php`
- Create: `app/Exports/PermitReportExport.php`
- Create: `resources/views/reports/permits/index.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/app.blade.php`

**Interfaces:**
- Consumes: `PermitReportQuery::query(array $filters)`
- Consumes: `PermitReportQuery::qrStatusLabel(VehiclePermit $permit): string`
- Produces route `reports.permits.index`
- Produces route `reports.permits.export`
- Produces `PermitReportExport::__construct(PermitReportQuery $reports, array $filters)`

- [ ] **Step 1: Write the failing permit report HTTP tests**

Create `tests/Feature/PermitReportHttpTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Exports\PermitReportExport;
use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\PermitToken;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class PermitReportHttpTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_and_auditor_can_open_permit_report_but_security_cannot()
    {
        $permit = $this->permit([
            'name' => 'PERMIT REPORT ACCESS',
            'plate' => 'DT 9301 PA',
        ]);

        $this->actingAs($this->user(User::ROLE_ADMIN_HR))
            ->get(route('reports.permits.index'))
            ->assertOk()
            ->assertSee('Laporan Izin')
            ->assertSee('PERMIT REPORT ACCESS')
            ->assertSee('DT 9301 PA');

        $this->actingAs($this->user(User::ROLE_AUDITOR))
            ->get(route('reports.permits.index'))
            ->assertOk()
            ->assertSee('PERMIT REPORT ACCESS');

        $this->actingAs($this->user(User::ROLE_SECURITY))
            ->get(route('reports.permits.index'))
            ->assertForbidden();

        $this->assertNotNull($permit->id);
    }

    /** @test */
    public function permit_report_uses_filters_from_query_string()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $admin = $this->user(User::ROLE_ADMIN_HR);
        $matching = $this->permit([
            'name' => 'FILTERED PERMIT REPORT',
            'plate' => 'DT 9401 FP',
            'status' => VehiclePermit::STATUS_ACTIVE,
        ]);
        $blocked = $this->permit([
            'name' => 'BLOCKED PERMIT REPORT',
            'plate' => 'DT 9402 BP',
            'status' => VehiclePermit::STATUS_NEEDS_REVIEW,
        ]);

        $this->token($matching, PermitToken::STATUS_ACTIVE, now()->addYear());

        $this->actingAs($admin)
            ->get(route('reports.permits.index', [
                'status' => VehiclePermit::STATUS_ACTIVE,
                'qr_status' => 'active',
                'search' => '9401',
            ]))
            ->assertOk()
            ->assertSee('FILTERED PERMIT REPORT')
            ->assertSee('QR Aktif')
            ->assertDontSee('BLOCKED PERMIT REPORT')
            ->assertDontSee('DT 9402 BP');

        $this->assertNotNull($blocked->id);
    }

    /** @test */
    public function permit_report_export_uses_filters_and_does_not_expose_token_hash()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');
        Excel::fake();

        $admin = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit([
            'name' => 'EXPORT PERMIT REPORT',
            'plate' => 'DT 9501 EP',
            'status' => VehiclePermit::STATUS_ACTIVE,
        ]);
        $token = $this->token($permit, PermitToken::STATUS_ACTIVE, now()->addYear());

        $this->actingAs($admin)
            ->get(route('reports.permits.export', [
                'status' => VehiclePermit::STATUS_ACTIVE,
                'search' => '9501',
            ]));

        Excel::assertDownloaded('sirika-laporan-izin-20260708-100000.xlsx', function (PermitReportExport $export) use ($permit, $token) {
            $rows = $export->query()->get();
            $this->assertTrue($rows->contains('id', $permit->id));

            $mapped = $export->map($permit->fresh([
                'employee',
                'vehicle',
                'parkingLocation',
                'reviewer',
                'activeToken',
                'latestToken',
            ]));

            $this->assertContains('EXPORT PERMIT REPORT', $mapped);
            $this->assertContains('DT 9501 EP', $mapped);
            $this->assertNotContains($token->token_hash, $mapped);

            return true;
        });
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function permit(array $overrides = []): VehiclePermit
    {
        $parking = ParkingLocation::first() ?: ParkingLocation::create([
            'code' => 'P1',
            'name' => 'Parkir P1',
            'status' => 'active',
        ]);

        $employee = Employee::create([
            'nik' => $overrides['nik'] ?? 'EMP-' . uniqid(),
            'name' => $overrides['name'] ?? 'REPORT PERMIT USER',
            'department' => $overrides['department'] ?? 'GA',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => $overrides['plate'] ?? 'DT 9300 RP',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parking->id,
            'permit_color' => $overrides['permit_color'] ?? 'biru',
            'approval_status' => 'approved',
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'status' => $overrides['status'] ?? VehiclePermit::STATUS_ACTIVE,
            'source' => $overrides['source'] ?? 'import',
            'route_raw' => 'Y1',
        ]);
    }

    private function token(VehiclePermit $permit, string $status, $expiresAt): PermitToken
    {
        return PermitToken::create([
            'vehicle_permit_id' => $permit->id,
            'token_hash' => hash('sha256', uniqid('permit-report-token-', true)),
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
    }
}
```

- [ ] **Step 2: Run the permit report HTTP test and verify it fails**

Run:

```bash
php artisan test --filter=PermitReportHttpTest
```

Expected result:

```text
FAIL  Tests\Feature\PermitReportHttpTest
Route [reports.permits.index] not defined.
```

- [ ] **Step 3: Add request validation for permit report filters**

Create `app/Http/Requests/ReportPermitRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\VehiclePermit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportPermitRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'status' => [
                'nullable',
                Rule::in([
                    VehiclePermit::STATUS_DRAFT,
                    VehiclePermit::STATUS_NEEDS_REVIEW,
                    VehiclePermit::STATUS_ACTIVE,
                    VehiclePermit::STATUS_SUSPENDED,
                    VehiclePermit::STATUS_EXPIRED,
                    VehiclePermit::STATUS_REVOKED,
                ]),
            ],
            'qr_status' => ['nullable', Rule::in(['missing', 'active', 'expired', 'revoked'])],
            'permit_color' => ['nullable', 'string', 'max:32'],
            'parking_location_id' => ['nullable', 'integer', 'exists:parking_locations,id'],
            'source' => ['nullable', 'string', 'max:32'],
            'review_status' => ['nullable', Rule::in(['pending', 'reviewed'])],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
```

- [ ] **Step 4: Add permit report export class**

Create `app/Exports/PermitReportExport.php`:

```php
<?php

namespace App\Exports;

use App\Models\VehiclePermit;
use App\Services\Reports\PermitReportQuery;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PermitReportExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    private PermitReportQuery $reports;
    private array $filters;

    public function __construct(PermitReportQuery $reports, array $filters)
    {
        $this->reports = $reports;
        $this->filters = $filters;
    }

    public function query()
    {
        return $this->reports->query($this->filters);
    }

    public function headings(): array
    {
        return [
            'NIK',
            'Nama',
            'Departemen',
            'Plat',
            'Tipe Kendaraan',
            'Lokasi Parkir',
            'Warna',
            'Status Izin',
            'Status QR',
            'QR Berlaku Sampai',
            'Valid Dari',
            'Valid Sampai',
            'Status Review',
            'Reviewer',
            'Waktu Review',
            'Catatan Review',
            'Sumber Data',
            'Rute Mentah',
            'Jumlah Segmen Rute',
        ];
    }

    public function map($permit): array
    {
        /** @var VehiclePermit $permit */
        $activeToken = $permit->activeToken;

        return [
            optional($permit->employee)->nik ?? '-',
            optional($permit->employee)->name ?? '-',
            optional($permit->employee)->department ?? '-',
            optional($permit->vehicle)->plate_number ?? '-',
            optional($permit->vehicle)->vehicle_type ?? '-',
            optional($permit->parkingLocation)->code ?? '-',
            $permit->permit_color ?? '-',
            $permit->status ?? '-',
            $this->reports->qrStatusLabel($permit),
            $activeToken && $activeToken->expires_at ? $activeToken->expires_at->format('Y-m-d H:i:s') : '-',
            $permit->valid_from ? $permit->valid_from->format('Y-m-d') : '-',
            $permit->valid_until ? $permit->valid_until->format('Y-m-d') : '-',
            $permit->reviewed_at ? 'Sudah direview' : 'Belum direview',
            optional($permit->reviewer)->name ?? '-',
            $permit->reviewed_at ? $permit->reviewed_at->format('Y-m-d H:i:s') : '-',
            $permit->review_note ?? '-',
            $permit->source ?? '-',
            $permit->route_raw ?? '-',
            (int) ($permit->route_segments_count ?? 0),
        ];
    }
}
```

- [ ] **Step 5: Add permit report controller**

Create `app/Http/Controllers/ReportPermitController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Exports\PermitReportExport;
use App\Http\Requests\ReportPermitRequest;
use App\Models\ParkingLocation;
use App\Services\Reports\PermitReportQuery;
use Maatwebsite\Excel\Facades\Excel;

class ReportPermitController extends Controller
{
    public function index(ReportPermitRequest $request, PermitReportQuery $reports)
    {
        $filters = $reports->filters($request->validated());

        return view('reports.permits.index', [
            'pageTitle' => 'Laporan Izin',
            'pageDescription' => 'Laporan operasional izin kendaraan, status review, dan status QR.',
            'filters' => $filters,
            'permits' => $reports->query($filters)->paginate(25)->appends($request->query()),
            'reports' => $reports,
            'statusOptions' => $reports->statusOptions(),
            'qrStatusOptions' => $reports->qrStatusOptions(),
            'reviewStatusOptions' => $reports->reviewStatusOptions(),
            'colorOptions' => $reports->colorOptions(),
            'sourceOptions' => $reports->sourceOptions(),
            'parkingLocations' => ParkingLocation::query()->orderBy('code')->get(),
            'statusSummary' => $reports->statusSummary(),
        ]);
    }

    public function export(ReportPermitRequest $request, PermitReportQuery $reports)
    {
        $filters = $reports->filters($request->validated());
        $filename = 'sirika-laporan-izin-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new PermitReportExport($reports, $filters), $filename);
    }
}
```

- [ ] **Step 6: Add permit report routes**

Modify `routes/web.php`:

```php
use App\Http\Controllers\ReportPermitController;
```

Inside the authenticated route group, add after permit/scan routes:

```php
Route::middleware('role:' . implode(',', User::rolesForRoute('reports.permits.index')))->group(function () {
    Route::get('/reports/permits', [ReportPermitController::class, 'index'])->name('reports.permits.index');
});

Route::get('/reports/permits/export', [ReportPermitController::class, 'export'])
    ->middleware('role:' . implode(',', User::rolesForRoute('reports.permits.export')))
    ->name('reports.permits.export');
```

- [ ] **Step 7: Add permit report navigation link**

Modify `resources/views/layouts/app.blade.php` inside `$visibleModules` and add `Laporan Izin` after `Izin Kendaraan`:

```php
['label' => 'Laporan Izin', 'route' => 'reports.permits.index'],
```

Do not add `Laporan Scan` yet in this task because the scan report route is created in Task 5.

- [ ] **Step 8: Add permit report Blade view**

Create directory `resources/views/reports/permits` and file `resources/views/reports/permits/index.blade.php`:

```blade
@extends('layouts.app')

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <div class="section-header">
                <div>
                    <h2 class="panel-title">Laporan Izin</h2>
                    <p class="panel-subtitle">Filter izin kendaraan, review, QR, dan rute untuk kebutuhan operasional.</p>
                </div>

                <a class="button button-primary" href="{{ route('reports.permits.export', request()->query()) }}">Export Excel</a>
            </div>

            @if ($errors->any())
                <x-alert type="danger" class="layout-gap">
                    {{ $errors->first() }}
                </x-alert>
            @endif

            <div class="status-summary layout-gap">
                @foreach ([\App\Models\VehiclePermit::STATUS_NEEDS_REVIEW => 'Perlu Review', \App\Models\VehiclePermit::STATUS_ACTIVE => 'Aktif', \App\Models\VehiclePermit::STATUS_EXPIRED => 'Kadaluwarsa', \App\Models\VehiclePermit::STATUS_REVOKED => 'Dicabut'] as $status => $label)
                    <div class="status-summary__item">
                        <span class="status-summary__label">{{ $label }}</span>
                        <strong class="status-summary__value">{{ $statusSummary[$status] ?? 0 }}</strong>
                    </div>
                @endforeach
            </div>

            <form class="filter-panel layout-gap" method="GET" action="{{ route('reports.permits.index') }}">
                <div class="form-grid">
                    <div class="form-field">
                        <label for="status">Status Izin</label>
                        <select class="form-control" id="status" name="status">
                            <option value="">Semua status</option>
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="qr_status">Status QR</label>
                        <select class="form-control" id="qr_status" name="qr_status">
                            <option value="">Semua QR</option>
                            @foreach ($qrStatusOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['qr_status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="review_status">Status Review</label>
                        <select class="form-control" id="review_status" name="review_status">
                            <option value="">Semua review</option>
                            @foreach ($reviewStatusOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['review_status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="parking_location_id">Parkir</label>
                        <select class="form-control" id="parking_location_id" name="parking_location_id">
                            <option value="">Semua parkir</option>
                            @foreach ($parkingLocations as $parkingLocation)
                                <option value="{{ $parkingLocation->id }}" {{ (string) ($filters['parking_location_id'] ?? '') === (string) $parkingLocation->id ? 'selected' : '' }}>
                                    {{ $parkingLocation->code }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="permit_color">Warna</label>
                        <select class="form-control" id="permit_color" name="permit_color">
                            <option value="">Semua warna</option>
                            @foreach ($colorOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['permit_color'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="source">Sumber</label>
                        <select class="form-control" id="source" name="source">
                            <option value="">Semua sumber</option>
                            @foreach ($sourceOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['source'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-field layout-gap">
                    <label for="search">Cari NIK, Nama, atau Plat</label>
                    <input class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Contoh: 15090187 atau DT 6899 SA">
                </div>

                <div class="form-actions layout-gap">
                    <button class="button button-primary" type="submit">Terapkan Filter</button>
                    <a class="button" href="{{ route('reports.permits.index') }}">Reset</a>
                </div>
            </form>

            <div class="table-wrap layout-gap">
                <table>
                    <thead>
                        <tr>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>Plat</th>
                            <th>Parkir</th>
                            <th>Status</th>
                            <th>Status QR</th>
                            <th>Review</th>
                            <th>Sumber</th>
                            <th>Segmen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($permits as $permit)
                            <tr>
                                <td>{{ optional($permit->employee)->nik ?? '-' }}</td>
                                <td>{{ optional($permit->employee)->name ?? '-' }}</td>
                                <td>{{ optional($permit->vehicle)->plate_number ?? '-' }}</td>
                                <td>{{ optional($permit->parkingLocation)->code ?? '-' }}</td>
                                <td><span class="status-pill">{{ $permit->status }}</span></td>
                                <td>
                                    <span class="status-pill">{{ $reports->qrStatusLabel($permit) }}</span>
                                    @if ($permit->activeToken && $permit->activeToken->expires_at)
                                        <div class="muted-text">{{ $permit->activeToken->expires_at->format('d M Y') }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $permit->reviewed_at ? 'Sudah direview' : 'Belum direview' }}
                                    @if ($permit->reviewer)
                                        <div class="muted-text">{{ $permit->reviewer->name }}</div>
                                    @endif
                                </td>
                                <td>{{ $permit->source ?? '-' }}</td>
                                <td>{{ (int) ($permit->route_segments_count ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">Tidak ada izin yang sesuai dengan filter laporan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap">
                {{ $permits->links() }}
            </div>
        </div>
    </section>
@endsection
```

- [ ] **Step 9: Run permit report tests and focused authorization tests**

Run:

```bash
php artisan test --filter=PermitReport
php artisan test --filter=ReportAuthorizationTest
```

Expected result:

```text
PASS  Tests\Feature\PermitReportQueryTest
PASS  Tests\Feature\PermitReportHttpTest
PASS  Tests\Feature\ReportAuthorizationTest
```

- [ ] **Step 10: Commit Task 3**

Run:

```bash
git add app/Exports/PermitReportExport.php app/Http/Controllers/ReportPermitController.php app/Http/Requests/ReportPermitRequest.php resources/views/layouts/app.blade.php resources/views/reports/permits/index.blade.php routes/web.php tests/Feature/PermitReportHttpTest.php
git commit -m "feat: add permit report export"
```

---

### Task 4: Scan Report Query Service

**Files:**
- Create: `tests/Feature/ScanReportQueryTest.php`
- Create: `app/Services/Reports/ScanReportQuery.php`

**Interfaces:**
- Produces: `ScanReportQuery::filters(array $input): array`
- Produces: `ScanReportQuery::query(array $filters)`
- Produces: `ScanReportQuery::assertExportRange(array $filters): void`
- Produces: `ScanReportQuery::resultOptions(): array`
- Produces: `ScanReportQuery::resultLabel(string $result): string`
- Produces: `ScanReportQuery::scannerOptions()`

- [ ] **Step 1: Write the failing scan report query tests**

Create `tests/Feature/ScanReportQueryTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\ScanLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Reports\ScanReportQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScanReportQueryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_defaults_scan_report_to_last_seven_days()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $filters = app(ScanReportQuery::class)->filters([]);

        $this->assertSame('2026-07-02', $filters['date_from']);
        $this->assertSame('2026-07-08', $filters['date_to']);
    }

    /** @test */
    public function it_filters_scans_by_date_result_scanner_and_search()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $scanner = $this->user(User::ROLE_SECURITY, 'Scanner One');
        $otherScanner = $this->user(User::ROLE_SECURITY, 'Scanner Two');
        $permit = $this->permit('SCAN MATCH USER', 'DT 9601 SM');
        $blockedPermit = $this->permit('SCAN BLOCKED USER', 'DT 9602 SB');

        $matching = $this->scan($permit, $scanner, ScanLog::RESULT_VALID, '2026-07-08 08:00:00');
        $this->scan($blockedPermit, $scanner, ScanLog::RESULT_INVALID, '2026-07-08 09:00:00');
        $this->scan($permit, $otherScanner, ScanLog::RESULT_VALID, '2026-07-08 10:00:00');
        $this->scan($permit, $scanner, ScanLog::RESULT_VALID, '2026-06-30 10:00:00');

        $reports = app(ScanReportQuery::class);
        $results = $reports->query($reports->filters([
            'date_from' => '2026-07-08',
            'date_to' => '2026-07-08',
            'result' => ScanLog::RESULT_VALID,
            'scanner_id' => $scanner->id,
            'search' => '9601',
        ]))->get();

        $this->assertCount(1, $results);
        $this->assertSame($matching->id, $results->first()->id);
    }

    /** @test */
    public function it_rejects_scan_export_ranges_longer_than_thirty_one_days()
    {
        $this->expectException(ValidationException::class);

        app(ScanReportQuery::class)->assertExportRange([
            'date_from' => '2026-07-01',
            'date_to' => '2026-08-01',
        ]);
    }

    private function user(string $role, string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function permit(string $name, string $plate): VehiclePermit
    {
        $parking = ParkingLocation::first() ?: ParkingLocation::create([
            'code' => 'P1',
            'name' => 'Parkir P1',
            'status' => 'active',
        ]);

        $employee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => $name,
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => $plate,
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parking->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'import',
            'route_raw' => 'Y1',
        ]);
    }

    private function scan(VehiclePermit $permit, User $scanner, string $result, string $scannedAt): ScanLog
    {
        return ScanLog::create([
            'permit_id' => $permit->id,
            'scanned_by' => $scanner->id,
            'scanned_at' => $scannedAt,
            'result' => $result,
            'device_info' => 'Browser Test',
            'ip_address' => '203.0.113.10',
            'notes' => 'QR ' . $result,
        ]);
    }
}
```

- [ ] **Step 2: Run the scan report query test and verify it fails**

Run:

```bash
php artisan test --filter=ScanReportQueryTest
```

Expected result:

```text
FAIL  Tests\Feature\ScanReportQueryTest
Class "App\Services\Reports\ScanReportQuery" not found
```

- [ ] **Step 3: Implement `ScanReportQuery`**

Create `app/Services/Reports/ScanReportQuery.php`:

```php
<?php

namespace App\Services\Reports;

use App\Models\ScanLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ScanReportQuery
{
    public function filters(array $input): array
    {
        return [
            'date_from' => $this->nullableString($input['date_from'] ?? null) ?: now()->subDays(6)->toDateString(),
            'date_to' => $this->nullableString($input['date_to'] ?? null) ?: now()->toDateString(),
            'result' => $this->nullableString($input['result'] ?? null),
            'scanner_id' => $input['scanner_id'] ?? null,
            'search' => $this->nullableString($input['search'] ?? null),
        ];
    }

    public function query(array $filters)
    {
        $filters = $this->filters($filters);
        $from = Carbon::parse($filters['date_from'])->startOfDay();
        $to = Carbon::parse($filters['date_to'])->endOfDay();

        $query = ScanLog::query()
            ->with([
                'permit.employee',
                'permit.vehicle',
                'permit.parkingLocation',
                'scanner',
            ])
            ->whereBetween('scanned_at', [$from, $to])
            ->orderByDesc('scanned_at')
            ->orderByDesc('id');

        if ($filters['result']) {
            $query->where('result', $filters['result']);
        }

        if ($filters['scanner_id']) {
            $query->where('scanned_by', $filters['scanner_id']);
        }

        if ($filters['search']) {
            $this->applySearchFilter($query, $filters['search']);
        }

        return $query;
    }

    public function assertExportRange(array $filters): void
    {
        $filters = $this->filters($filters);
        $from = Carbon::parse($filters['date_from'])->startOfDay();
        $to = Carbon::parse($filters['date_to'])->startOfDay();

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'date_to' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            ]);
        }

        if ($from->diffInDays($to) > 30) {
            throw ValidationException::withMessages([
                'date_range' => 'Rentang laporan scan maksimal 31 hari.',
            ]);
        }
    }

    public function resultOptions(): array
    {
        return [
            ScanLog::RESULT_VALID => 'Valid',
            ScanLog::RESULT_EXPIRED => 'Kadaluwarsa',
            ScanLog::RESULT_REVOKED => 'Dicabut',
            ScanLog::RESULT_INACTIVE => 'Tidak Aktif',
            ScanLog::RESULT_INVALID => 'Tidak Valid',
        ];
    }

    public function resultLabel(string $result): string
    {
        return $this->resultOptions()[$result] ?? $result;
    }

    public function scannerOptions()
    {
        return User::query()
            ->whereIn('id', ScanLog::query()
                ->whereNotNull('scanned_by')
                ->select('scanned_by'))
            ->orderBy('name')
            ->get();
    }

    private function applySearchFilter($query, string $search): void
    {
        $query->whereHas('permit', function ($permitQuery) use ($search) {
            $permitQuery->whereHas('employee', function ($employeeQuery) use ($search) {
                $employeeQuery->where('nik', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%');
            })->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                $vehicleQuery->where('plate_number', 'like', '%' . $search . '%');
            });
        });
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
```

- [ ] **Step 4: Run the scan report query test and verify it passes**

Run:

```bash
php artisan test --filter=ScanReportQueryTest
```

Expected result:

```text
PASS  Tests\Feature\ScanReportQueryTest
```

- [ ] **Step 5: Commit Task 4**

Run:

```bash
git add app/Services/Reports/ScanReportQuery.php tests/Feature/ScanReportQueryTest.php
git commit -m "feat: add scan report query service"
```

---

### Task 5: Scan Report Page and Excel Export

**Files:**
- Create: `tests/Feature/ScanReportHttpTest.php`
- Create: `app/Http/Requests/ReportScanRequest.php`
- Create: `app/Http/Controllers/ReportScanController.php`
- Create: `app/Exports/ScanReportExport.php`
- Create: `resources/views/reports/scans/index.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/app.blade.php`

**Interfaces:**
- Consumes: `ScanReportQuery::query(array $filters)`
- Consumes: `ScanReportQuery::assertExportRange(array $filters): void`
- Consumes: `ScanReportQuery::resultLabel(string $result): string`
- Produces route `reports.scans.index`
- Produces route `reports.scans.export`
- Produces `ScanReportExport::__construct(ScanReportQuery $reports, array $filters)`

- [ ] **Step 1: Write the failing scan report HTTP tests**

Create `tests/Feature/ScanReportHttpTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Exports\ScanReportExport;
use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\ScanLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ScanReportHttpTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_and_auditor_can_open_scan_report_but_security_cannot()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $scanner = $this->user(User::ROLE_SECURITY, 'Security Scanner');
        $permit = $this->permit('SCAN REPORT ACCESS', 'DT 9701 SA');
        $this->scan($permit, $scanner, ScanLog::RESULT_VALID, '2026-07-08 08:00:00');

        $this->actingAs($this->user(User::ROLE_ADMIN_HR, 'Admin HR'))
            ->get(route('reports.scans.index'))
            ->assertOk()
            ->assertSee('Laporan Scan')
            ->assertSee('SCAN REPORT ACCESS')
            ->assertSee('DT 9701 SA')
            ->assertSee('Valid');

        $this->actingAs($this->user(User::ROLE_AUDITOR, 'Auditor'))
            ->get(route('reports.scans.index'))
            ->assertOk()
            ->assertSee('SCAN REPORT ACCESS');

        $this->actingAs($this->user(User::ROLE_SECURITY, 'Blocked Security'))
            ->get(route('reports.scans.index'))
            ->assertForbidden();
    }

    /** @test */
    public function scan_report_uses_filters_from_query_string()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $admin = $this->user(User::ROLE_ADMIN_HR, 'Admin HR');
        $scanner = $this->user(User::ROLE_SECURITY, 'Scanner Filter');
        $matchingPermit = $this->permit('FILTERED SCAN REPORT', 'DT 9801 FS');
        $blockedPermit = $this->permit('BLOCKED SCAN REPORT', 'DT 9802 BS');

        $this->scan($matchingPermit, $scanner, ScanLog::RESULT_VALID, '2026-07-08 08:00:00');
        $this->scan($blockedPermit, $scanner, ScanLog::RESULT_INVALID, '2026-07-08 08:30:00');

        $this->actingAs($admin)
            ->get(route('reports.scans.index', [
                'date_from' => '2026-07-08',
                'date_to' => '2026-07-08',
                'result' => ScanLog::RESULT_VALID,
                'scanner_id' => $scanner->id,
                'search' => '9801',
            ]))
            ->assertOk()
            ->assertSee('FILTERED SCAN REPORT')
            ->assertSee('DT 9801 FS')
            ->assertDontSee('BLOCKED SCAN REPORT')
            ->assertDontSee('DT 9802 BS');
    }

    /** @test */
    public function scan_report_export_rejects_ranges_longer_than_thirty_one_days()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');

        $admin = $this->user(User::ROLE_ADMIN_HR, 'Admin HR');

        $this->from(route('reports.scans.index'))
            ->actingAs($admin)
            ->get(route('reports.scans.export', [
                'date_from' => '2026-07-01',
                'date_to' => '2026-08-01',
            ]))
            ->assertRedirect(route('reports.scans.index'))
            ->assertSessionHasErrors(['date_range' => 'Rentang laporan scan maksimal 31 hari.']);
    }

    /** @test */
    public function scan_report_export_uses_filters_and_does_not_expose_ip_address()
    {
        Carbon::setTestNow('2026-07-08 10:00:00');
        Excel::fake();

        $admin = $this->user(User::ROLE_ADMIN_HR, 'Admin HR');
        $scanner = $this->user(User::ROLE_SECURITY, 'Export Scanner');
        $permit = $this->permit('EXPORT SCAN REPORT', 'DT 9901 ES');
        $scan = $this->scan($permit, $scanner, ScanLog::RESULT_VALID, '2026-07-08 08:00:00', '203.0.113.44');

        $this->actingAs($admin)
            ->get(route('reports.scans.export', [
                'date_from' => '2026-07-08',
                'date_to' => '2026-07-08',
                'result' => ScanLog::RESULT_VALID,
            ]));

        Excel::assertDownloaded('sirika-laporan-scan-20260708-100000.xlsx', function (ScanReportExport $export) use ($scan) {
            $rows = $export->query()->get();
            $this->assertTrue($rows->contains('id', $scan->id));

            $mapped = $export->map($scan->fresh([
                'permit.employee',
                'permit.vehicle',
                'permit.parkingLocation',
                'scanner',
            ]));

            $this->assertContains('EXPORT SCAN REPORT', $mapped);
            $this->assertContains('DT 9901 ES', $mapped);
            $this->assertNotContains('203.0.113.44', $mapped);

            return true;
        });
    }

    private function user(string $role, string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function permit(string $name, string $plate): VehiclePermit
    {
        $parking = ParkingLocation::first() ?: ParkingLocation::create([
            'code' => 'P1',
            'name' => 'Parkir P1',
            'status' => 'active',
        ]);

        $employee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => $name,
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => $plate,
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parking->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'import',
            'route_raw' => 'Y1',
        ]);
    }

    private function scan(VehiclePermit $permit, User $scanner, string $result, string $scannedAt, string $ip = '203.0.113.10'): ScanLog
    {
        return ScanLog::create([
            'permit_id' => $permit->id,
            'scanned_by' => $scanner->id,
            'scanned_at' => $scannedAt,
            'result' => $result,
            'device_info' => 'Browser Test',
            'ip_address' => $ip,
            'notes' => 'QR ' . $result,
        ]);
    }
}
```

- [ ] **Step 2: Run the scan report HTTP test and verify it fails**

Run:

```bash
php artisan test --filter=ScanReportHttpTest
```

Expected result:

```text
FAIL  Tests\Feature\ScanReportHttpTest
Route [reports.scans.index] not defined.
```

- [ ] **Step 3: Add request validation for scan report filters**

Create `app/Http/Requests/ReportScanRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\ScanLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportScanRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'result' => [
                'nullable',
                Rule::in([
                    ScanLog::RESULT_VALID,
                    ScanLog::RESULT_EXPIRED,
                    ScanLog::RESULT_REVOKED,
                    ScanLog::RESULT_INACTIVE,
                    ScanLog::RESULT_INVALID,
                ]),
            ],
            'scanner_id' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages()
    {
        return [
            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            'result.in' => 'Hasil scan tidak valid.',
            'scanner_id.exists' => 'Scanner tidak ditemukan.',
        ];
    }
}
```

- [ ] **Step 4: Add scan report export class**

Create `app/Exports/ScanReportExport.php`:

```php
<?php

namespace App\Exports;

use App\Models\ScanLog;
use App\Services\Reports\ScanReportQuery;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ScanReportExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    private ScanReportQuery $reports;
    private array $filters;

    public function __construct(ScanReportQuery $reports, array $filters)
    {
        $this->reports = $reports;
        $this->filters = $filters;
    }

    public function query()
    {
        return $this->reports->query($this->filters);
    }

    public function headings(): array
    {
        return [
            'Waktu Scan',
            'Hasil Scan',
            'Scanner',
            'NIK',
            'Nama',
            'Plat',
            'Lokasi Parkir',
            'Warna',
            'Status Izin',
            'Sumber Izin',
            'Catatan Scan',
            'Device Info',
        ];
    }

    public function map($scanLog): array
    {
        /** @var ScanLog $scanLog */
        $permit = $scanLog->permit;

        return [
            $scanLog->scanned_at ? $scanLog->scanned_at->format('Y-m-d H:i:s') : '-',
            $this->reports->resultLabel($scanLog->result),
            optional($scanLog->scanner)->name ?? '-',
            optional(optional($permit)->employee)->nik ?? '-',
            optional(optional($permit)->employee)->name ?? '-',
            optional(optional($permit)->vehicle)->plate_number ?? '-',
            optional(optional($permit)->parkingLocation)->code ?? '-',
            optional($permit)->permit_color ?? '-',
            optional($permit)->status ?? '-',
            optional($permit)->source ?? '-',
            $scanLog->notes ?? '-',
            $scanLog->device_info ?? '-',
        ];
    }
}
```

- [ ] **Step 5: Add scan report controller**

Create `app/Http/Controllers/ReportScanController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Exports\ScanReportExport;
use App\Http\Requests\ReportScanRequest;
use App\Services\Reports\ScanReportQuery;
use Maatwebsite\Excel\Facades\Excel;

class ReportScanController extends Controller
{
    public function index(ReportScanRequest $request, ScanReportQuery $reports)
    {
        $filters = $reports->filters($request->validated());

        return view('reports.scans.index', [
            'pageTitle' => 'Laporan Scan',
            'pageDescription' => 'Laporan aktivitas scan QR kendaraan berdasarkan tanggal, hasil scan, dan scanner.',
            'filters' => $filters,
            'scanLogs' => $reports->query($filters)->paginate(25)->appends($request->query()),
            'reports' => $reports,
            'resultOptions' => $reports->resultOptions(),
            'scannerOptions' => $reports->scannerOptions(),
        ]);
    }

    public function export(ReportScanRequest $request, ScanReportQuery $reports)
    {
        $filters = $reports->filters($request->validated());
        $reports->assertExportRange($filters);

        $filename = 'sirika-laporan-scan-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new ScanReportExport($reports, $filters), $filename);
    }
}
```

- [ ] **Step 6: Add scan report routes**

Modify `routes/web.php`:

```php
use App\Http\Controllers\ReportScanController;
```

Inside the authenticated route group, add after permit report routes:

```php
Route::middleware('role:' . implode(',', User::rolesForRoute('reports.scans.index')))->group(function () {
    Route::get('/reports/scans', [ReportScanController::class, 'index'])->name('reports.scans.index');
});

Route::get('/reports/scans/export', [ReportScanController::class, 'export'])
    ->middleware('role:' . implode(',', User::rolesForRoute('reports.scans.export')))
    ->name('reports.scans.export');
```

- [ ] **Step 7: Add scan report navigation link**

Modify `resources/views/layouts/app.blade.php` inside `$visibleModules` and add `Laporan Scan` after `Laporan Izin`:

```php
['label' => 'Laporan Scan', 'route' => 'reports.scans.index'],
```

- [ ] **Step 8: Add scan report Blade view**

Create directory `resources/views/reports/scans` and file `resources/views/reports/scans/index.blade.php`:

```blade
@extends('layouts.app')

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <div class="section-header">
                <div>
                    <h2 class="panel-title">Laporan Scan</h2>
                    <p class="panel-subtitle">Pantau aktivitas scan QR berdasarkan tanggal, hasil, scanner, dan kendaraan.</p>
                </div>

                <a class="button button-primary" href="{{ route('reports.scans.export', request()->query()) }}">Export Excel</a>
            </div>

            @if ($errors->any())
                <x-alert type="danger" class="layout-gap">
                    {{ $errors->first() }}
                </x-alert>
            @endif

            <form class="filter-panel layout-gap" method="GET" action="{{ route('reports.scans.index') }}">
                <div class="form-grid">
                    <div class="form-field">
                        <label for="date_from">Tanggal Awal</label>
                        <input class="form-control" id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] }}">
                    </div>

                    <div class="form-field">
                        <label for="date_to">Tanggal Akhir</label>
                        <input class="form-control" id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] }}">
                    </div>

                    <div class="form-field">
                        <label for="result">Hasil Scan</label>
                        <select class="form-control" id="result" name="result">
                            <option value="">Semua hasil</option>
                            @foreach ($resultOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['result'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="scanner_id">Scanner</label>
                        <select class="form-control" id="scanner_id" name="scanner_id">
                            <option value="">Semua scanner</option>
                            @foreach ($scannerOptions as $scanner)
                                <option value="{{ $scanner->id }}" {{ (string) ($filters['scanner_id'] ?? '') === (string) $scanner->id ? 'selected' : '' }}>
                                    {{ $scanner->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-field layout-gap">
                    <label for="search">Cari NIK, Nama, atau Plat</label>
                    <input class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Contoh: 15090187 atau DT 6899 SA">
                </div>

                <div class="form-actions layout-gap">
                    <button class="button button-primary" type="submit">Terapkan Filter</button>
                    <a class="button" href="{{ route('reports.scans.index') }}">Reset</a>
                </div>
            </form>

            <div class="table-wrap layout-gap">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Hasil</th>
                            <th>Scanner</th>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>Plat</th>
                            <th>Parkir</th>
                            <th>Status Izin</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($scanLogs as $scanLog)
                            @php
                                $permit = $scanLog->permit;
                            @endphp
                            <tr>
                                <td>{{ optional($scanLog->scanned_at)->format('d M Y H:i') ?? '-' }}</td>
                                <td><span class="status-pill">{{ $reports->resultLabel($scanLog->result) }}</span></td>
                                <td>{{ optional($scanLog->scanner)->name ?? '-' }}</td>
                                <td>{{ optional(optional($permit)->employee)->nik ?? '-' }}</td>
                                <td>{{ optional(optional($permit)->employee)->name ?? '-' }}</td>
                                <td>{{ optional(optional($permit)->vehicle)->plate_number ?? '-' }}</td>
                                <td>{{ optional(optional($permit)->parkingLocation)->code ?? '-' }}</td>
                                <td>{{ optional($permit)->status ?? '-' }}</td>
                                <td>{{ $scanLog->notes ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">Tidak ada scan yang sesuai dengan filter laporan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap">
                {{ $scanLogs->links() }}
            </div>
        </div>
    </section>
@endsection
```

- [ ] **Step 9: Run scan report tests and focused authorization tests**

Run:

```bash
php artisan test --filter=ScanReport
php artisan test --filter=ReportAuthorizationTest
```

Expected result:

```text
PASS  Tests\Feature\ScanReportQueryTest
PASS  Tests\Feature\ScanReportHttpTest
PASS  Tests\Feature\ReportAuthorizationTest
```

- [ ] **Step 10: Commit Task 5**

Run:

```bash
git add app/Exports/ScanReportExport.php app/Http/Controllers/ReportScanController.php app/Http/Requests/ReportScanRequest.php resources/views/layouts/app.blade.php resources/views/reports/scans/index.blade.php routes/web.php tests/Feature/ScanReportHttpTest.php
git commit -m "feat: add scan report export"
```

---

### Task 6: Operational Dashboard

**Files:**
- Modify: `tests/Feature/DashboardUiTest.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/views/dashboard/index.blade.php`
- Modify: `resources/css/app.css`
- Modify: `public/css/app.css`

**Interfaces:**
- Consumes: existing dashboard route `dashboard`
- Consumes: `PermitToken::STATUS_ACTIVE`
- Consumes: `ScanLog::RESULT_INVALID`
- Produces dashboard variables:
  - `activeQrTokens`
  - `expiredQrTokens`
  - `todayInvalidScans`
  - `permitStatusSummary`
  - `scanResultSummary`
  - `activityFeed`

- [ ] **Step 1: Replace brittle dashboard tests with Phase 5B expectations**

Modify `tests/Feature/DashboardUiTest.php`.

Keep the helper method `assertDashboardLinks()` and replace the first two test methods with:

```php
/** @test */
public function super_admin_sees_operational_dashboard_metrics_and_report_links()
{
    $this->seed(RoadSegmentSeeder::class);

    $user = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'status' => User::STATUS_ACTIVE,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()
        ->assertSee('Dashboard SIRIKA')
        ->assertSee('Segmen Rute Aktif')
        ->assertSee('Izin Aktif')
        ->assertSee('Perlu Review')
        ->assertSee('QR Aktif')
        ->assertSee('QR Kadaluwarsa')
        ->assertSee('Scan Hari Ini')
        ->assertSee('Scan Invalid Hari Ini')
        ->assertSee('Ringkasan Status Izin')
        ->assertSee('Hasil Scan 7 Hari')
        ->assertSee('Aktivitas Terbaru')
        ->assertDontSee('QR code, scanner kamera, dan peta highlight rute tetap menunggu fase berikutnya.')
        ->assertSee('href="' . route('dashboard') . '"', false);

    $this->assertDashboardLinks($response, [
        route('road-segments.index'),
        route('imports.index'),
        route('permits.index'),
        route('reports.permits.index'),
        route('reports.scans.index'),
        route('scan.index'),
    ], []);
}

/** @test */
public function dashboard_links_are_filtered_by_user_role()
{
    $cases = [
        [
            'role' => User::ROLE_SECURITY,
            'allowed' => [route('scan.index')],
            'blocked' => [route('road-segments.index'), route('imports.index'), route('permits.index'), route('reports.permits.index'), route('reports.scans.index')],
        ],
        [
            'role' => User::ROLE_ADMIN_HR,
            'allowed' => [route('road-segments.index'), route('imports.index'), route('permits.index'), route('reports.permits.index'), route('reports.scans.index'), route('scan.index')],
            'blocked' => [],
        ],
        [
            'role' => User::ROLE_AUDITOR,
            'allowed' => [route('road-segments.index'), route('permits.index'), route('reports.permits.index'), route('reports.scans.index')],
            'blocked' => [route('imports.index'), route('scan.index')],
        ],
    ];

    foreach ($cases as $index => $case) {
        $user = User::factory()->create([
            'email' => "dashboard-role-{$index}@sirika.local",
            'role' => $case['role'],
            'status' => User::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $this->assertDashboardLinks($response, $case['allowed'], $case['blocked']);

        auth()->logout();
    }
}
```

- [ ] **Step 2: Run dashboard test and verify it fails**

Run:

```bash
php artisan test --filter=DashboardUiTest
```

Expected result:

```text
FAIL  Tests\Feature\DashboardUiTest
Failed asserting that ... contains "QR Aktif".
```

- [ ] **Step 3: Update dashboard controller metrics**

Replace `app/Http/Controllers/DashboardController.php` with:

```php
<?php

namespace App\Http\Controllers;

use App\Models\PermitToken;
use App\Models\RoadSegment;
use App\Models\ScanLog;
use App\Models\User;
use App\Models\VehiclePermit;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.index', [
            'pageTitle' => 'Dashboard SIRIKA',
            'pageDescription' => 'Ringkasan operasional sistem rute izin kendaraan.',
            'activeRoadSegments' => RoadSegment::where('status', 'active')->count(),
            'activeUsers' => User::where('status', User::STATUS_ACTIVE)->count(),
            'activePermits' => VehiclePermit::where('status', VehiclePermit::STATUS_ACTIVE)->count(),
            'reviewPermits' => VehiclePermit::where('status', VehiclePermit::STATUS_NEEDS_REVIEW)->count(),
            'activeQrTokens' => $this->activeQrTokens(),
            'expiredQrTokens' => $this->expiredQrTokens(),
            'todayScans' => ScanLog::whereDate('scanned_at', now()->toDateString())->count(),
            'todayInvalidScans' => ScanLog::whereDate('scanned_at', now()->toDateString())
                ->where('result', ScanLog::RESULT_INVALID)
                ->count(),
            'permitStatusSummary' => $this->permitStatusSummary(),
            'scanResultSummary' => $this->scanResultSummary(),
            'activityFeed' => $this->activityFeed(),
        ]);
    }

    private function activeQrTokens(): int
    {
        return PermitToken::query()
            ->where('status', PermitToken::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->count();
    }

    private function expiredQrTokens(): int
    {
        return PermitToken::query()
            ->where('status', PermitToken::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();
    }

    private function permitStatusSummary(): array
    {
        return VehiclePermit::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    private function scanResultSummary(): array
    {
        return ScanLog::query()
            ->select('result', DB::raw('count(*) as total'))
            ->where('scanned_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('result')
            ->pluck('total', 'result')
            ->all();
    }

    private function activityFeed()
    {
        $reviews = VehiclePermit::with(['employee', 'vehicle', 'reviewer'])
            ->whereNotNull('reviewed_at')
            ->orderByDesc('reviewed_at')
            ->limit(5)
            ->get()
            ->map(function (VehiclePermit $permit) {
                return [
                    'type' => 'Review',
                    'title' => optional($permit->employee)->name ?? optional($permit->vehicle)->plate_number ?? 'Izin kendaraan',
                    'description' => 'Direview oleh ' . (optional($permit->reviewer)->name ?? '-'),
                    'occurred_at' => $permit->reviewed_at,
                ];
            });

        $tokens = PermitToken::with(['permit.employee', 'permit.vehicle'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (PermitToken $token) {
                return [
                    'type' => 'QR',
                    'title' => optional(optional($token->permit)->vehicle)->plate_number ?? 'QR kendaraan',
                    'description' => 'Status QR: ' . $token->status,
                    'occurred_at' => $token->created_at,
                ];
            });

        $scans = ScanLog::with(['permit.employee', 'permit.vehicle', 'scanner'])
            ->orderByDesc('scanned_at')
            ->limit(5)
            ->get()
            ->map(function (ScanLog $scanLog) {
                return [
                    'type' => 'Scan',
                    'title' => optional(optional($scanLog->permit)->vehicle)->plate_number ?? 'Scan QR',
                    'description' => $scanLog->result . ' oleh ' . (optional($scanLog->scanner)->name ?? '-'),
                    'occurred_at' => $scanLog->scanned_at,
                ];
            });

        return $reviews
            ->concat($tokens)
            ->concat($scans)
            ->filter(function (array $activity) {
                return $activity['occurred_at'] !== null;
            })
            ->sortByDesc('occurred_at')
            ->take(10)
            ->values();
    }
}
```

- [ ] **Step 4: Update dashboard view**

Replace `resources/views/dashboard/index.blade.php` with:

```blade
@extends('layouts.app')

@php
    $quickActions = [
        ['label' => 'Import Excel', 'route' => 'imports.index'],
        ['label' => 'Kelola Izin', 'route' => 'permits.index'],
        ['label' => 'Laporan Izin', 'route' => 'reports.permits.index'],
        ['label' => 'Laporan Scan', 'route' => 'reports.scans.index'],
        ['label' => 'Master Rute', 'route' => 'road-segments.index'],
        ['label' => 'Scan QR', 'route' => 'scan.index'],
    ];

    $permitStatusLabels = [
        \App\Models\VehiclePermit::STATUS_NEEDS_REVIEW => 'Perlu Review',
        \App\Models\VehiclePermit::STATUS_ACTIVE => 'Aktif',
        \App\Models\VehiclePermit::STATUS_DRAFT => 'Draft',
        \App\Models\VehiclePermit::STATUS_SUSPENDED => 'Ditangguhkan',
        \App\Models\VehiclePermit::STATUS_EXPIRED => 'Kadaluwarsa',
        \App\Models\VehiclePermit::STATUS_REVOKED => 'Dicabut',
    ];

    $scanResultLabels = [
        \App\Models\ScanLog::RESULT_VALID => 'Valid',
        \App\Models\ScanLog::RESULT_EXPIRED => 'Kadaluwarsa',
        \App\Models\ScanLog::RESULT_REVOKED => 'Dicabut',
        \App\Models\ScanLog::RESULT_INACTIVE => 'Tidak Aktif',
        \App\Models\ScanLog::RESULT_INVALID => 'Tidak Valid',
    ];
@endphp

@section('content')
    <section class="page-section">
        <div class="grid stats-grid">
            <x-stat-card label="Segmen Rute Aktif" :value="$activeRoadSegments" note="Master rute resmi dari PDF VDNI" />
            <x-stat-card label="Izin Aktif" :value="$activePermits" note="Izin aktif pada tabel final" />
            <x-stat-card label="Perlu Review" :value="$reviewPermits" note="Izin yang perlu verifikasi lanjutan" />
            <x-stat-card label="QR Aktif" :value="$activeQrTokens" note="QR aktif dan belum kadaluwarsa" />
            <x-stat-card label="QR Kadaluwarsa" :value="$expiredQrTokens" note="QR aktif dengan masa berlaku lewat" />
            <x-stat-card label="Scan Hari Ini" :value="$todayScans" note="Aktivitas scan tanggal hari ini" />
            <x-stat-card label="Scan Invalid Hari Ini" :value="$todayInvalidScans" note="QR tidak dikenal pada hari ini" />
            <x-stat-card label="User Aktif" :value="$activeUsers" note="Akun yang dapat login" />
        </div>
    </section>

    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Quick Actions</h2>
            <p class="panel-subtitle">Akses cepat ke modul operasional sesuai role pengguna.</p>

            <div class="quick-actions layout-gap">
                @foreach ($quickActions as $index => $action)
                    @if (auth()->user()->canAccessRoute($action['route']))
                        <a class="button {{ $index === 0 ? 'button-primary' : '' }}" href="{{ route($action['route']) }}">
                            {{ $action['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </section>

    <section class="page-section grid dashboard-grid">
        <div class="panel">
            <div class="panel-body">
                <h2 class="panel-title">Ringkasan Status Izin</h2>
                <div class="summary-list layout-gap">
                    @foreach ($permitStatusLabels as $status => $label)
                        <div class="summary-list__item">
                            <span>{{ $label }}</span>
                            <strong>{{ $permitStatusSummary[$status] ?? 0 }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <h2 class="panel-title">Hasil Scan 7 Hari</h2>
                <div class="summary-list layout-gap">
                    @foreach ($scanResultLabels as $result => $label)
                        <div class="summary-list__item">
                            <span>{{ $label }}</span>
                            <strong>{{ $scanResultSummary[$result] ?? 0 }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Aktivitas Terbaru</h2>
            <p class="panel-subtitle">Ringkasan review izin, QR, dan scan terbaru.</p>

            <div class="activity-list layout-gap">
                @forelse ($activityFeed as $activity)
                    <div class="activity-list__item">
                        <span class="status-pill">{{ $activity['type'] }}</span>
                        <div>
                            <strong>{{ $activity['title'] }}</strong>
                            <p class="muted-text">{{ $activity['description'] }}</p>
                        </div>
                        <time class="muted-text">{{ optional($activity['occurred_at'])->format('d M Y H:i') }}</time>
                    </div>
                @empty
                    <p class="panel-subtitle">Belum ada aktivitas review, QR, atau scan.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
```

- [ ] **Step 5: Add dashboard CSS**

Append to `resources/css/app.css` before the first media query:

```css
.dashboard-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.summary-list {
    display: grid;
    gap: 10px;
}

.summary-list__item,
.activity-list__item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--sirika-border);
}

.summary-list__item:last-child,
.activity-list__item:last-child {
    border-bottom: 0;
}

.activity-list {
    display: grid;
    gap: 4px;
}
```

Inside the existing mobile media query, add:

```css
.dashboard-grid {
    grid-template-columns: 1fr;
}

.activity-list__item {
    align-items: flex-start;
    flex-direction: column;
}
```

- [ ] **Step 6: Build assets**

Run:

```bash
npm.cmd run dev
```

Expected result:

```text
webpack compiled successfully
```

This updates `public/css/app.css`.

- [ ] **Step 7: Run dashboard and report tests**

Run:

```bash
php artisan test --filter=DashboardUiTest
php artisan test --filter=Report
```

Expected result:

```text
PASS  Tests\Feature\DashboardUiTest
PASS  Tests\Feature\ReportAuthorizationTest
PASS  Tests\Feature\PermitReportQueryTest
PASS  Tests\Feature\PermitReportHttpTest
PASS  Tests\Feature\ScanReportQueryTest
PASS  Tests\Feature\ScanReportHttpTest
```

- [ ] **Step 8: Commit Task 6**

Run:

```bash
git add app/Http/Controllers/DashboardController.php resources/views/dashboard/index.blade.php resources/css/app.css public/css/app.css tests/Feature/DashboardUiTest.php
git commit -m "feat: update operational dashboard reporting"
```

---

### Task 7: Regression Verification and Production Readiness

**Files:**
- Modify only if tests expose stale assertions:
  - `tests/Feature/AuthAndRoleAccessTest.php`
  - `tests/Feature/PermitReviewHttpTest.php`
  - `tests/Feature/PermitQrHttpTest.php`
  - `tests/Feature/ScanQrHttpTest.php`
  - `tests/Feature/PermitRouteMapHttpTest.php`

**Interfaces:**
- Confirms report routes do not break existing auth, permit review, QR, scan, import, and route-map workflows.

- [ ] **Step 1: Run focused Phase 5B tests**

Run:

```bash
php artisan test --filter=Report
```

Expected result:

```text
PASS  Tests\Feature\ReportAuthorizationTest
PASS  Tests\Feature\PermitReportQueryTest
PASS  Tests\Feature\PermitReportHttpTest
PASS  Tests\Feature\ScanReportQueryTest
PASS  Tests\Feature\ScanReportHttpTest
```

- [ ] **Step 2: Run dashboard tests**

Run:

```bash
php artisan test --filter=DashboardUiTest
```

Expected result:

```text
PASS  Tests\Feature\DashboardUiTest
```

- [ ] **Step 3: Run authorization regression**

Run:

```bash
php artisan test --filter=AuthAndRoleAccessTest
```

Expected result:

```text
PASS  Tests\Feature\AuthAndRoleAccessTest
```

If this test fails because new report routes are not part of dashboard access expectations, update only brittle assertions. Do not grant report access to `security`.

- [ ] **Step 4: Run permit review and QR regressions**

Run:

```bash
php artisan test --filter=PermitReview
php artisan test --filter=PermitQr
```

Expected result:

```text
PASS  Tests\Feature\PermitReviewMetadataTest
PASS  Tests\Feature\PermitReviewServiceTest
PASS  Tests\Feature\PermitReviewHttpTest
PASS  Tests\Feature\PermitQrHttpTest
PASS  Tests\Feature\PermitQrServiceTest
```

Do not change QR eligibility rules to make report tests pass. QR must remain limited to active permits.

- [ ] **Step 5: Run scan and route-map regressions**

Run:

```bash
php artisan test --filter=ScanQr
php artisan test --filter=PermitScan
php artisan test --filter=PermitRouteMap
```

Expected result:

```text
PASS  Tests\Feature\ScanQrHttpTest
PASS  Tests\Feature\PermitScanServiceTest
PASS  Tests\Feature\PermitRouteMapHttpTest
PASS  Tests\Feature\PermitRouteMapServiceTest
```

- [ ] **Step 6: Run import/list regressions**

Run:

```bash
php artisan test --filter=Import
php artisan test --filter=PermitList
```

Expected result:

```text
PASS  Tests\Feature\ImportCommitTest
PASS  Tests\Feature\ImportExcelPreviewHttpTest
PASS  Tests\Feature\ImportExcelPreviewTest
PASS  Tests\Feature\ImportRowSchemaTest
PASS  Tests\Feature\PermitListAfterImportTest
```

- [ ] **Step 7: Run the full test suite**

Run:

```bash
php artisan test
```

Expected result:

```text
PASS
```

Record any failures with file and test method name before changing code. Fix only regressions caused by Phase 5B.

- [ ] **Step 8: Browser smoke test**

Start server if needed:

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

Manual flow:

1. Login as Admin HR.
2. Open `/dashboard`.
3. Confirm dashboard no longer shows stale copy about QR/scanner/map waiting for a future phase.
4. Open `/reports/permits`.
5. Filter status `active`.
6. Export Excel.
7. Open `/reports/scans`.
8. Filter last 7 days and result `valid`.
9. Export Excel.
10. Login as Auditor and confirm both report pages open.
11. Login as Security and confirm `/reports/permits` and `/reports/scans` return forbidden.

- [ ] **Step 9: Production rollout notes**

Before deploy:

```bash
git status --short
```

Expected result:

```text

```

Production commands after deploy:

```bash
npm run prod
php artisan route:clear
php artisan view:clear
php artisan config:clear
```

No `php artisan migrate --force` is required for Phase 5B because this phase does not add migration.

- [ ] **Step 10: Commit final regression fixes if needed**

If Task 7 changed tests or small stale assertions:

```bash
git add tests/Feature/AuthAndRoleAccessTest.php tests/Feature/PermitReviewHttpTest.php tests/Feature/PermitQrHttpTest.php tests/Feature/ScanQrHttpTest.php tests/Feature/PermitRouteMapHttpTest.php
git commit -m "test: cover report regressions"
```

If no files changed:

```bash
git status --short
```

Expected result:

```text

```

---

## Self-Review Checklist

- Spec coverage:
  - Dashboard visibility is covered by Task 6.
  - Permit report page and export are covered by Tasks 2 and 3.
  - Scan report page and export are covered by Tasks 4 and 5.
  - Authorization is covered by Task 1 and route middleware in Tasks 3 and 5.
  - Export data protection is covered by `PermitReportHttpTest` and `ScanReportHttpTest`.
  - Export scan 31-day limit is covered by `ScanReportQueryTest` and `ScanReportHttpTest`.

- Type consistency:
  - `PermitReportQuery::query(array $filters)` is used by `ReportPermitController` and `PermitReportExport`.
  - `ScanReportQuery::query(array $filters)` is used by `ReportScanController` and `ScanReportExport`.
  - Route names in `User::routeRoles()`, routes, views, and tests match.
  - Export filenames in tests match `now()->format('Ymd-His')` with `Carbon::setTestNow`.

- Production safety:
  - No migration is required.
  - No QR lifecycle behavior changes.
  - Security is not granted report access.
  - Exports omit token hash and IP address.
  - HTML pages are paginated.
  - Exports use `FromQuery`.
  - Scan export range is limited to 31 days.
