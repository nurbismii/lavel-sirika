# SIRIKA Phase 5A Review Activation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement workflow review dan aktivasi izin `needs_review` agar admin dapat memperbaiki data kritis, mengaktifkan izin yang valid, lalu memakai mekanisme QR existing tanpa membuka QR untuk izin yang belum aktif.

**Architecture:** Lifecycle review dipusatkan di `PermitReviewService` dengan database transaction dan row lock. Controller hanya mengatur request, response, dan flash message; Blade hanya menampilkan state yang sudah disiapkan controller. QR service tidak diubah sehingga aturan QR hanya untuk izin `active` tetap menjadi guard utama.

**Tech Stack:** PHP 7.4, Laravel 8, Blade, Alpine.js, Leaflet.js, MySQL/PostgreSQL, Laravel Excel, BaconQrCode, html5-qrcode.

## Global Constraints

- Baseline: Phase 4 sudah merge ke `main`.
- Stack: PHP 7.4, Laravel 8, Blade, Alpine.js, Leaflet.js, MySQL/PostgreSQL, Laravel Excel, BaconQrCode, html5-qrcode.
- Izin `needs_review` tidak boleh dibuatkan QR sebelum menjadi `active`.
- Aktivasi izin tidak otomatis membuat QR.
- Lokasi parkir wajib dipilih sebelum aktivasi.
- Rute wajib diisi dan harus menghasilkan minimal 1 kode segmen resmi.
- Token rute tidak dikenal menahan aktivasi.
- Kendaraan yang masih memiliki izin `active` lain tidak boleh diaktifkan lagi.
- Phase 5A hanya mengaktifkan izin dari status `needs_review`.
- Catatan review wajib diisi saat aktivasi.
- Admin HR dan Super Admin dapat melakukan review dan aktivasi.
- Auditor hanya dapat melihat daftar dan detail izin.
- Security tidak mendapat akses ke halaman daftar, detail, review, atau aktivasi izin.
- Tidak membuat auto-generate QR saat izin diaktifkan.
- Tidak membuat bulk activation.
- Tidak membuat audit log table penuh di Phase 5A.
- Tidak mengubah mekanisme scan QR.
- Tidak mengubah aturan masa aktif QR.
- Tidak menghapus data lama dalam migration.

---

## File Structure

- Add `database/migrations/2026_07_07_000001_add_review_metadata_to_vehicle_permits_table.php`
  - Menambah `reviewed_by`, `reviewed_at`, dan `review_note` nullable.

- Modify `app/Models/VehiclePermit.php`
  - Menambah fillable dan cast review.
  - Menambah relasi `reviewer()`.

- Add `app/Services/Permits/PermitReviewService.php`
  - Menyimpan koreksi review.
  - Mengaktifkan izin `needs_review` secara transactional.
  - Parsing ulang `route_raw` dan menulis ulang `permit_route_segments`.

- Add `app/Http/Requests/UpdatePermitReviewRequest.php`
  - Validasi form review untuk save draft dan activation.

- Add `app/Http/Controllers/PermitReviewController.php`
  - Menampilkan form review.
  - Menyimpan koreksi review.
  - Mengaktifkan izin lewat service.

- Modify `app/Http/Controllers/PermitController.php`
  - Menambah filter index.
  - Menambah halaman detail `show`.

- Modify `app/Models/User.php`
  - Menambah route role `permits.show`, `permits.review.edit`, `permits.review.update`, dan `permits.review.activate`.
  - Mengizinkan auditor membuka `permits.index` dan `permits.show`.

- Modify `routes/web.php`
  - Menambah route detail dan review izin.

- Modify `resources/views/permits/index.blade.php`
  - Menambah filter, ringkasan status, dan aksi detail/review.

- Add `resources/views/permits/show.blade.php`
  - Detail izin read-only.

- Add `resources/views/permits/review/edit.blade.php`
  - Form koreksi dan aktivasi.

- Modify `resources/css/app.css`
  - Menambah style filter, status summary, detail split, dan review form jika class existing belum cukup.

- Add `tests/Feature/PermitReviewMetadataTest.php`
  - Menguji metadata review di model.

- Add `tests/Feature/PermitReviewServiceTest.php`
  - Menguji domain activation dan validasi data.

- Add `tests/Feature/PermitReviewHttpTest.php`
  - Menguji route, authorization, filter, detail, review, dan QR button setelah aktivasi.

- Modify `tests/Feature/PermitListAfterImportTest.php`
  - Menyesuaikan ekspektasi daftar izin dengan filter/action baru jika assertion lama terlalu spesifik.

---

### Task 1: Review Metadata Schema and Model

**Files:**
- Create: `tests/Feature/PermitReviewMetadataTest.php`
- Create: `database/migrations/2026_07_07_000001_add_review_metadata_to_vehicle_permits_table.php`
- Modify: `app/Models/VehiclePermit.php`

**Interfaces:**
- Produces: `VehiclePermit::reviewer()` relation returning `belongsTo(User::class, 'reviewed_by')`.
- Produces: `VehiclePermit::$casts['reviewed_at'] = 'datetime'`.
- Produces: nullable columns `vehicle_permits.reviewed_by`, `vehicle_permits.reviewed_at`, `vehicle_permits.review_note`.

- [ ] **Step 1: Write the failing metadata test**

Create `tests/Feature/PermitReviewMetadataTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitReviewMetadataTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function vehicle_permit_stores_review_metadata_and_reviewer_relation()
    {
        $reviewer = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $employee = Employee::create([
            'nik' => '15090001',
            'name' => 'REVIEW META USER',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 9001 RM',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $permit = VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_NEEDS_REVIEW,
            'source' => 'import',
        ]);

        $reviewedAt = now()->subMinute();

        $permit->update([
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => $reviewedAt,
            'review_note' => 'Rute dan parkir sudah diverifikasi.',
        ]);

        $permit->refresh();

        $this->assertSame($reviewer->id, $permit->reviewer->id);
        $this->assertSame('Rute dan parkir sudah diverifikasi.', $permit->review_note);
        $this->assertTrue($permit->reviewed_at->equalTo($reviewedAt));
    }
}
```

- [ ] **Step 2: Run the metadata test and verify it fails**

Run:

```bash
php artisan test --filter=PermitReviewMetadataTest
```

Expected result:

```text
FAIL  Tests\Feature\PermitReviewMetadataTest
SQLSTATE[HY000]: General error: 1 table vehicle_permits has no column named reviewed_by
```

SQLite may report a different SQLSTATE, but the failure must be caused by missing review metadata columns or missing `reviewer()` relation.

- [ ] **Step 3: Add the review metadata migration**

Create `database/migrations/2026_07_07_000001_add_review_metadata_to_vehicle_permits_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReviewMetadataToVehiclePermitsTable extends Migration
{
    public function up()
    {
        Schema::table('vehicle_permits', function (Blueprint $table) {
            $table->foreignId('reviewed_by')
                ->nullable()
                ->after('route_raw')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
        });
    }

    public function down()
    {
        Schema::table('vehicle_permits', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['reviewed_by', 'reviewed_at', 'review_note']);
        });
    }
}
```

- [ ] **Step 4: Update `VehiclePermit` fillable, casts, and relation**

Modify `app/Models/VehiclePermit.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehiclePermit extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'employee_id',
        'vehicle_id',
        'parking_location_id',
        'permit_color',
        'reason',
        'approval_status',
        'valid_from',
        'valid_until',
        'status',
        'source',
        'source_import_id',
        'route_raw',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function parkingLocation()
    {
        return $this->belongsTo(ParkingLocation::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function sourceImport()
    {
        return $this->belongsTo(ImportBatch::class, 'source_import_id');
    }

    public function permitRouteSegments()
    {
        return $this->hasMany(PermitRouteSegment::class);
    }

    public function routeSegments()
    {
        return $this->belongsToMany(
            RoadSegment::class,
            'permit_route_segments',
            'vehicle_permit_id',
            'road_segment_id'
        )
            ->withPivot('sequence')
            ->withTimestamps()
            ->orderBy('permit_route_segments.sequence');
    }

    public function tokens()
    {
        return $this->hasMany(PermitToken::class);
    }

    public function activeToken()
    {
        return $this->hasOne(PermitToken::class)
            ->where('status', PermitToken::STATUS_ACTIVE)
            ->latestOfMany();
    }

    public function latestToken()
    {
        return $this->hasOne(PermitToken::class)->latestOfMany();
    }
}
```

- [ ] **Step 5: Run the metadata test and verify it passes**

Run:

```bash
php artisan test --filter=PermitReviewMetadataTest
```

Expected result:

```text
PASS  Tests\Feature\PermitReviewMetadataTest
```

- [ ] **Step 6: Commit Task 1**

Run:

```bash
git add app/Models/VehiclePermit.php database/migrations/2026_07_07_000001_add_review_metadata_to_vehicle_permits_table.php tests/Feature/PermitReviewMetadataTest.php
git commit -m "feat: add permit review metadata"
```

---

### Task 2: Permit Review Domain Service

**Files:**
- Create: `tests/Feature/PermitReviewServiceTest.php`
- Create: `app/Services/Permits/PermitReviewService.php`

**Interfaces:**
- Consumes: `VehiclePermit::STATUS_NEEDS_REVIEW`, `VehiclePermit::STATUS_ACTIVE`.
- Consumes: `App\Services\Imports\RouteSegmentParser::parse($rawRoute, array $activeCodes): array`.
- Produces: `PermitReviewService::saveDraft(VehiclePermit $permit, array $data): VehiclePermit`.
- Produces: `PermitReviewService::activate(VehiclePermit $permit, array $data, User $reviewer): VehiclePermit`.
- Throws: `InvalidArgumentException` with user-facing Indonesian messages for domain validation failures.

- [ ] **Step 1: Write failing service tests**

Create `tests/Feature/PermitReviewServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\RoadSegment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PermitReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_saves_review_draft_without_activating_the_permit()
    {
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $parking = $this->parking('P1');

        $updated = app(PermitReviewService::class)->saveDraft($permit, [
            'parking_location_id' => $parking->id,
            'route_raw' => 'Y1 - D2',
            'review_note' => 'Menunggu cek akhir.',
        ]);

        $this->assertSame(VehiclePermit::STATUS_NEEDS_REVIEW, $updated->status);
        $this->assertSame($parking->id, $updated->parking_location_id);
        $this->assertSame('Y1 - D2', $updated->route_raw);
        $this->assertSame('Menunggu cek akhir.', $updated->review_note);
        $this->assertNull($updated->reviewed_by);
        $this->assertNull($updated->reviewed_at);
    }

    /** @test */
    public function it_activates_needs_review_permit_and_replaces_route_segments()
    {
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $parking = $this->parking('P1');
        $oldSegment = $this->segment('OLD1');
        $first = $this->segment('Y1');
        $second = $this->segment('D2');

        $permit->permitRouteSegments()->create([
            'road_segment_id' => $oldSegment->id,
            'sequence' => 1,
        ]);

        $activated = app(PermitReviewService::class)->activate($permit, [
            'parking_location_id' => $parking->id,
            'route_raw' => 'Y1 -> D2',
            'review_note' => 'Rute dan parkir sudah valid.',
        ], $reviewer);

        $this->assertSame(VehiclePermit::STATUS_ACTIVE, $activated->status);
        $this->assertSame($parking->id, $activated->parking_location_id);
        $this->assertSame('Y1 -> D2', $activated->route_raw);
        $this->assertSame($reviewer->id, $activated->reviewed_by);
        $this->assertNotNull($activated->reviewed_at);
        $this->assertSame('Rute dan parkir sudah valid.', $activated->review_note);

        $this->assertSame(
            [$first->id, $second->id],
            $activated->permitRouteSegments()->orderBy('sequence')->pluck('road_segment_id')->all()
        );

        $this->assertSame(
            [1, 2],
            array_map('intval', $activated->permitRouteSegments()->orderBy('sequence')->pluck('sequence')->all())
        );
    }

    /** @test */
    public function it_blocks_activation_when_permit_is_not_needs_review()
    {
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_ACTIVE);
        $parking = $this->parking('P1');
        $this->segment('Y1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Izin ini tidak berada dalam status needs_review.');

        app(PermitReviewService::class)->activate($permit, [
            'parking_location_id' => $parking->id,
            'route_raw' => 'Y1',
            'review_note' => 'Valid.',
        ], $reviewer);
    }

    /** @test */
    public function it_blocks_activation_when_parking_location_is_empty()
    {
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $this->segment('Y1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pilih lokasi parkir sebelum aktivasi izin.');

        app(PermitReviewService::class)->activate($permit, [
            'parking_location_id' => null,
            'route_raw' => 'Y1',
            'review_note' => 'Valid.',
        ], $reviewer);
    }

    /** @test */
    public function it_blocks_activation_when_route_is_empty()
    {
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $parking = $this->parking('P1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rute kendaraan kosong.');

        app(PermitReviewService::class)->activate($permit, [
            'parking_location_id' => $parking->id,
            'route_raw' => '',
            'review_note' => 'Valid.',
        ], $reviewer);
    }

    /** @test */
    public function it_blocks_activation_when_route_has_unknown_token()
    {
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $parking = $this->parking('P1');
        $this->segment('Y1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rute mengandung token tidak dikenal: X99');

        app(PermitReviewService::class)->activate($permit, [
            'parking_location_id' => $parking->id,
            'route_raw' => 'Y1 -> X99',
            'review_note' => 'Valid.',
        ], $reviewer);
    }

    /** @test */
    public function it_blocks_activation_when_vehicle_has_another_active_permit()
    {
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $parking = $this->parking('P1');
        $this->segment('Y1');

        VehiclePermit::create([
            'employee_id' => $permit->employee_id,
            'vehicle_id' => $permit->vehicle_id,
            'parking_location_id' => $parking->id,
            'permit_color' => 'merah',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'manual',
            'route_raw' => 'Y1',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Kendaraan ini masih memiliki izin aktif lain. Nonaktifkan izin lama sebelum aktivasi.');

        app(PermitReviewService::class)->activate($permit, [
            'parking_location_id' => $parking->id,
            'route_raw' => 'Y1',
            'review_note' => 'Valid.',
        ], $reviewer);
    }

    /** @test */
    public function it_blocks_activation_when_review_note_is_empty()
    {
        $reviewer = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $parking = $this->parking('P1');
        $this->segment('Y1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Catatan review wajib diisi sebelum aktivasi izin.');

        app(PermitReviewService::class)->activate($permit, [
            'parking_location_id' => $parking->id,
            'route_raw' => 'Y1',
            'review_note' => '   ',
        ], $reviewer);
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

    private function segment(string $code): RoadSegment
    {
        return RoadSegment::create([
            'code' => $code,
            'name' => 'Jalan ' . $code,
            'start_location' => 'Start ' . $code,
            'end_location' => 'End ' . $code,
            'status' => 'active',
        ]);
    }

    private function permit(string $status): VehiclePermit
    {
        $employee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => 'REVIEW SERVICE USER',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT ' . random_int(1000, 9999) . ' RS',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => $status,
            'source' => 'import',
            'route_raw' => null,
        ]);
    }
}
```

- [ ] **Step 2: Run service tests and verify they fail because the service does not exist**

Run:

```bash
php artisan test --filter=PermitReviewServiceTest
```

Expected result:

```text
Target class [App\Services\Permits\PermitReviewService] does not exist
```

- [ ] **Step 3: Implement `PermitReviewService`**

Create `app/Services/Permits/PermitReviewService.php`:

```php
<?php

namespace App\Services\Permits;

use App\Models\ParkingLocation;
use App\Models\RoadSegment;
use App\Models\User;
use App\Models\VehiclePermit;
use App\Services\Imports\RouteSegmentParser;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PermitReviewService
{
    private RouteSegmentParser $routeParser;

    public function __construct(RouteSegmentParser $routeParser)
    {
        $this->routeParser = $routeParser;
    }

    public function saveDraft(VehiclePermit $permit, array $data): VehiclePermit
    {
        return DB::transaction(function () use ($permit, $data) {
            $lockedPermit = $this->lockPermit($permit);
            $this->ensureNeedsReview($lockedPermit);

            $lockedPermit->update([
                'parking_location_id' => $data['parking_location_id'] ?? null,
                'route_raw' => $this->cleanText($data['route_raw'] ?? null),
                'review_note' => $this->cleanText($data['review_note'] ?? null),
            ]);

            return $lockedPermit->fresh(['employee', 'vehicle', 'parkingLocation', 'routeSegments', 'reviewer']);
        });
    }

    public function activate(VehiclePermit $permit, array $data, User $reviewer): VehiclePermit
    {
        return DB::transaction(function () use ($permit, $data, $reviewer) {
            $lockedPermit = $this->lockPermit($permit);
            $this->ensureNeedsReview($lockedPermit);
            $this->ensurePermitHasCoreRelations($lockedPermit);

            $parking = $this->resolveParkingLocation($data['parking_location_id'] ?? null);
            $routeRaw = $this->cleanText($data['route_raw'] ?? null);
            $reviewNote = $this->cleanText($data['review_note'] ?? null);

            if ($reviewNote === null) {
                throw new InvalidArgumentException('Catatan review wajib diisi sebelum aktivasi izin.');
            }

            $codes = $this->resolveRouteCodes($routeRaw);
            $segments = $this->loadRouteSegments($codes);

            $this->ensureNoOtherActivePermit($lockedPermit);

            $lockedPermit->update([
                'parking_location_id' => $parking->id,
                'route_raw' => $routeRaw,
                'review_note' => $reviewNote,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'status' => VehiclePermit::STATUS_ACTIVE,
            ]);

            $lockedPermit->permitRouteSegments()->delete();

            $sequence = 1;
            foreach ($codes as $code) {
                $lockedPermit->permitRouteSegments()->create([
                    'road_segment_id' => $segments[$code]->id,
                    'sequence' => $sequence,
                ]);
                $sequence++;
            }

            return $lockedPermit->fresh(['employee', 'vehicle', 'parkingLocation', 'permitRouteSegments', 'routeSegments', 'reviewer']);
        });
    }

    private function lockPermit(VehiclePermit $permit): VehiclePermit
    {
        return VehiclePermit::query()
            ->whereKey($permit->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureNeedsReview(VehiclePermit $permit): void
    {
        if ($permit->status !== VehiclePermit::STATUS_NEEDS_REVIEW) {
            throw new InvalidArgumentException('Izin ini tidak berada dalam status needs_review.');
        }
    }

    private function ensurePermitHasCoreRelations(VehiclePermit $permit): void
    {
        if (! $permit->employee_id) {
            throw new InvalidArgumentException('Data karyawan izin tidak valid.');
        }

        if (! $permit->vehicle_id) {
            throw new InvalidArgumentException('Data kendaraan izin tidak valid.');
        }
    }

    private function resolveParkingLocation($parkingLocationId): ParkingLocation
    {
        if (! $parkingLocationId) {
            throw new InvalidArgumentException('Pilih lokasi parkir sebelum aktivasi izin.');
        }

        $parking = ParkingLocation::query()
            ->whereKey($parkingLocationId)
            ->where('status', 'active')
            ->first();

        if (! $parking) {
            throw new InvalidArgumentException('Pilih lokasi parkir sebelum aktivasi izin.');
        }

        return $parking;
    }

    private function resolveRouteCodes(?string $routeRaw): array
    {
        if ($routeRaw === null) {
            throw new InvalidArgumentException('Rute kendaraan kosong.');
        }

        $activeCodes = RoadSegment::query()
            ->where('status', 'active')
            ->orderBy('code')
            ->pluck('code')
            ->all();

        $parsed = $this->routeParser->parse($routeRaw, $activeCodes);

        if (($parsed['codes'] ?? []) === []) {
            $warnings = $parsed['warnings'] ?? [];
            if (in_array('Rute kendaraan kosong', $warnings, true)) {
                throw new InvalidArgumentException('Rute kendaraan kosong.');
            }

            throw new InvalidArgumentException('Rute tidak mengandung kode segmen resmi.');
        }

        $warnings = $parsed['warnings'] ?? [];
        if ($warnings !== []) {
            throw new InvalidArgumentException($warnings[0]);
        }

        return array_values($parsed['codes']);
    }

    private function loadRouteSegments(array $codes): array
    {
        $segments = RoadSegment::query()
            ->where('status', 'active')
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        foreach ($codes as $code) {
            if (! $segments->has($code)) {
                throw new InvalidArgumentException('Kode segmen rute tidak ditemukan di master aktif: ' . $code . '.');
            }
        }

        return $segments->all();
    }

    private function ensureNoOtherActivePermit(VehiclePermit $permit): void
    {
        $exists = VehiclePermit::query()
            ->where('vehicle_id', $permit->vehicle_id)
            ->where('status', VehiclePermit::STATUS_ACTIVE)
            ->where('id', '!=', $permit->id)
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('Kendaraan ini masih memiliki izin aktif lain. Nonaktifkan izin lama sebelum aktivasi.');
        }
    }

    private function cleanText($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
```

- [ ] **Step 4: Run service tests and verify they pass**

Run:

```bash
php artisan test --filter=PermitReviewServiceTest
```

Expected result:

```text
PASS  Tests\Feature\PermitReviewServiceTest
```

- [ ] **Step 5: Run metadata test to catch migration/model regressions**

Run:

```bash
php artisan test --filter=PermitReviewMetadataTest
```

Expected result:

```text
PASS  Tests\Feature\PermitReviewMetadataTest
```

- [ ] **Step 6: Commit Task 2**

Run:

```bash
git add app/Services/Permits/PermitReviewService.php tests/Feature/PermitReviewServiceTest.php
git commit -m "feat: add permit review activation service"
```

---

### Task 3: Permit Review HTTP Routes, Request, and Controllers

**Files:**
- Create: `tests/Feature/PermitReviewHttpTest.php`
- Create: `app/Http/Requests/UpdatePermitReviewRequest.php`
- Create: `app/Http/Controllers/PermitReviewController.php`
- Modify: `app/Http/Controllers/PermitController.php`
- Modify: `app/Models/User.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `PermitReviewService::saveDraft(VehiclePermit $permit, array $data): VehiclePermit`.
- Consumes: `PermitReviewService::activate(VehiclePermit $permit, array $data, User $reviewer): VehiclePermit`.
- Produces route: `GET /permits` named `permits.index`.
- Produces route: `GET /permits/{permit}` named `permits.show`.
- Produces route: `GET /permits/{permit}/review` named `permits.review.edit`.
- Produces route: `POST /permits/{permit}/review` named `permits.review.update`.
- Produces route: `POST /permits/{permit}/review/activate` named `permits.review.activate`.

- [ ] **Step 1: Write failing HTTP tests**

Create `tests/Feature/PermitReviewHttpTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\RoadSegment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitReviewHttpTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_filter_needs_review_permits_from_list()
    {
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $reviewPermit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW, 'REVIEW FILTER USER', 'DT 5101 RF');
        $activePermit = $this->permit(VehiclePermit::STATUS_ACTIVE, 'ACTIVE FILTER USER', 'DT 5102 AF');

        $this->actingAs($admin)
            ->get(route('permits.index', ['status' => VehiclePermit::STATUS_NEEDS_REVIEW]))
            ->assertOk()
            ->assertSee('REVIEW FILTER USER')
            ->assertSee('DT 5101 RF')
            ->assertDontSee('ACTIVE FILTER USER')
            ->assertDontSee('DT 5102 AF')
            ->assertSee(route('permits.review.edit', $reviewPermit), false);
    }

    /** @test */
    public function auditor_can_view_list_and_detail_but_cannot_open_review_form()
    {
        $auditor = $this->user(User::ROLE_AUDITOR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW, 'AUDITOR DETAIL USER', 'DT 5201 AD');

        $this->actingAs($auditor)
            ->get(route('permits.index'))
            ->assertOk()
            ->assertSee('AUDITOR DETAIL USER');

        $this->actingAs($auditor)
            ->get(route('permits.show', $permit))
            ->assertOk()
            ->assertSee('Detail Izin')
            ->assertSee('AUDITOR DETAIL USER')
            ->assertDontSee('Simpan Review')
            ->assertDontSee('Aktifkan Izin');

        $this->actingAs($auditor)
            ->get(route('permits.review.edit', $permit))
            ->assertForbidden();
    }

    /** @test */
    public function security_cannot_view_permit_admin_pages()
    {
        $security = $this->user(User::ROLE_SECURITY);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW, 'SECURITY BLOCKED USER', 'DT 5301 SB');

        $this->actingAs($security)->get(route('permits.index'))->assertForbidden();
        $this->actingAs($security)->get(route('permits.show', $permit))->assertForbidden();
        $this->actingAs($security)->get(route('permits.review.edit', $permit))->assertForbidden();
    }

    /** @test */
    public function admin_is_redirected_when_opening_review_form_for_non_review_permit()
    {
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_ACTIVE, 'ACTIVE DIRECT REVIEW USER', 'DT 5351 AR');

        $this->actingAs($admin)
            ->get(route('permits.review.edit', $permit))
            ->assertRedirect(route('permits.show', $permit))
            ->assertSessionHas('error', 'Izin ini tidak berada dalam status needs_review.');
    }

    /** @test */
    public function admin_can_save_review_draft()
    {
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW, 'DRAFT REVIEW USER', 'DT 5401 DR');
        $parking = $this->parking('P1');

        $this->actingAs($admin)
            ->post(route('permits.review.update', $permit), [
                'parking_location_id' => $parking->id,
                'route_raw' => 'Y1 - D2',
                'review_note' => 'Menunggu aktivasi.',
            ])
            ->assertRedirect(route('permits.review.edit', $permit))
            ->assertSessionHas('status', 'Review izin berhasil disimpan.');

        $permit->refresh();

        $this->assertSame(VehiclePermit::STATUS_NEEDS_REVIEW, $permit->status);
        $this->assertSame($parking->id, $permit->parking_location_id);
        $this->assertSame('Y1 - D2', $permit->route_raw);
        $this->assertSame('Menunggu aktivasi.', $permit->review_note);
    }

    /** @test */
    public function admin_can_activate_reviewed_permit_and_then_generate_qr_action_is_visible()
    {
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW, 'ACTIVATE HTTP USER', 'DT 5501 AH');
        $parking = $this->parking('P1');
        $this->segment('Y1');
        $this->segment('D2');

        $this->actingAs($admin)
            ->post(route('permits.review.activate', $permit), [
                'parking_location_id' => $parking->id,
                'route_raw' => 'Y1 -> D2',
                'review_note' => 'Rute dan parkir sudah valid.',
            ])
            ->assertRedirect(route('permits.show', $permit))
            ->assertSessionHas('status', 'Izin berhasil diaktifkan.');

        $permit->refresh();

        $this->assertSame(VehiclePermit::STATUS_ACTIVE, $permit->status);
        $this->assertSame($admin->id, $permit->reviewed_by);
        $this->assertSame(2, $permit->permitRouteSegments()->count());

        $this->actingAs($admin)
            ->get(route('permits.index', ['status' => VehiclePermit::STATUS_ACTIVE]))
            ->assertOk()
            ->assertSee('ACTIVATE HTTP USER')
            ->assertSee('Generate QR')
            ->assertSee(route('permits.qr.generate', $permit), false);
    }

    /** @test */
    public function activation_redirects_back_with_validation_error_when_domain_rule_fails()
    {
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $permit = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW, 'INVALID ROUTE USER', 'DT 5601 IR');
        $parking = $this->parking('P1');
        $this->segment('Y1');

        $this->from(route('permits.review.edit', $permit))
            ->actingAs($admin)
            ->post(route('permits.review.activate', $permit), [
                'parking_location_id' => $parking->id,
                'route_raw' => 'Y1 -> X99',
                'review_note' => 'Dicoba aktivasi.',
            ])
            ->assertRedirect(route('permits.review.edit', $permit))
            ->assertSessionHasErrors(['activation' => 'Rute mengandung token tidak dikenal: X99']);

        $this->assertSame(VehiclePermit::STATUS_NEEDS_REVIEW, $permit->fresh()->status);
        $this->assertSame(0, $permit->fresh()->permitRouteSegments()->count());
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

    private function segment(string $code): RoadSegment
    {
        return RoadSegment::create([
            'code' => $code,
            'name' => 'Jalan ' . $code,
            'start_location' => 'Start ' . $code,
            'end_location' => 'End ' . $code,
            'status' => 'active',
        ]);
    }

    private function permit(string $status, string $name, string $plateNumber): VehiclePermit
    {
        $employee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => $name,
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => $plateNumber,
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => $status,
            'source' => 'import',
            'route_raw' => 'Y1',
        ]);
    }
}
```

- [ ] **Step 2: Run HTTP tests and verify route failures**

Run:

```bash
php artisan test --filter=PermitReviewHttpTest
```

Expected result:

```text
Route [permits.show] not defined
```

The exact first missing route can be `permits.review.edit` depending on test order.

- [ ] **Step 3: Add request validation**

Create `app/Http/Requests/UpdatePermitReviewRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermitReviewRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $activating = $this->routeIs('permits.review.activate');

        return [
            'parking_location_id' => [
                $activating ? 'required' : 'nullable',
                'integer',
                'exists:parking_locations,id',
            ],
            'route_raw' => [
                $activating ? 'required' : 'nullable',
                'string',
                'max:5000',
            ],
            'review_note' => [
                $activating ? 'required' : 'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages()
    {
        return [
            'parking_location_id.required' => 'Pilih lokasi parkir sebelum aktivasi izin.',
            'parking_location_id.exists' => 'Pilih lokasi parkir yang valid.',
            'route_raw.required' => 'Rute kendaraan kosong.',
            'route_raw.max' => 'Rute kendaraan maksimal 5000 karakter.',
            'review_note.required' => 'Catatan review wajib diisi sebelum aktivasi izin.',
            'review_note.max' => 'Catatan review maksimal 2000 karakter.',
        ];
    }
}
```

- [ ] **Step 4: Implement `PermitReviewController`**

Create `app/Http/Controllers/PermitReviewController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePermitReviewRequest;
use App\Models\ParkingLocation;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitReviewService;
use InvalidArgumentException;

class PermitReviewController extends Controller
{
    private PermitReviewService $reviews;

    public function __construct(PermitReviewService $reviews)
    {
        $this->reviews = $reviews;
    }

    public function edit(VehiclePermit $permit)
    {
        if ($permit->status !== VehiclePermit::STATUS_NEEDS_REVIEW) {
            return redirect()
                ->route('permits.show', $permit)
                ->with('error', 'Izin ini tidak berada dalam status needs_review.');
        }

        $permit->loadMissing(['employee', 'vehicle', 'parkingLocation', 'routeSegments', 'reviewer']);

        return view('permits.review.edit', [
            'permit' => $permit,
            'parkingLocations' => ParkingLocation::query()
                ->where('status', 'active')
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function update(UpdatePermitReviewRequest $request, VehiclePermit $permit)
    {
        try {
            $this->reviews->saveDraft($permit, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('permits.review.edit', $permit)
                ->withErrors(['review' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('permits.review.edit', $permit)
            ->with('status', 'Review izin berhasil disimpan.');
    }

    public function activate(UpdatePermitReviewRequest $request, VehiclePermit $permit)
    {
        try {
            $this->reviews->activate($permit, $request->validated(), $request->user());
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('permits.review.edit', $permit)
                ->withErrors(['activation' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('permits.show', $permit)
            ->with('status', 'Izin berhasil diaktifkan.');
    }
}
```

- [ ] **Step 5: Expand `PermitController` for filters and detail**

Replace `app/Http/Controllers/PermitController.php` with:

```php
<?php

namespace App\Http\Controllers;

use App\Models\ParkingLocation;
use App\Models\PermitToken;
use App\Models\VehiclePermit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermitController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'status' => $request->query('status'),
            'qr_status' => $request->query('qr_status'),
            'permit_color' => $request->query('permit_color'),
            'parking_location_id' => $request->query('parking_location_id'),
            'search' => $request->query('search'),
        ];

        $query = VehiclePermit::query()
            ->with(['employee', 'vehicle', 'parkingLocation', 'activeToken', 'latestToken', 'routeSegments'])
            ->latest();

        $this->applyFilters($query, $filters);

        return view('permits.index', [
            'permits' => $query->paginate(25)->appends($request->query()),
            'filters' => $filters,
            'statusOptions' => $this->statusOptions(),
            'qrStatusOptions' => $this->qrStatusOptions(),
            'colorOptions' => $this->colorOptions(),
            'parkingLocations' => ParkingLocation::query()->orderBy('code')->get(),
            'statusSummary' => $this->statusSummary(),
        ]);
    }

    public function show(VehiclePermit $permit)
    {
        $permit->loadMissing([
            'employee',
            'vehicle',
            'parkingLocation',
            'activeToken',
            'latestToken',
            'routeSegments',
            'reviewer',
        ]);

        return view('permits.show', [
            'permit' => $permit,
        ]);
    }

    private function applyFilters($query, array $filters): void
    {
        if ($filters['status'] && array_key_exists($filters['status'], $this->statusOptions())) {
            $query->where('status', $filters['status']);
        }

        if ($filters['permit_color']) {
            $query->where('permit_color', $filters['permit_color']);
        }

        if ($filters['parking_location_id']) {
            $query->where('parking_location_id', $filters['parking_location_id']);
        }

        if ($filters['search']) {
            $search = trim((string) $filters['search']);
            $query->where(function ($subQuery) use ($search) {
                $subQuery->whereHas('employee', function ($employeeQuery) use ($search) {
                    $employeeQuery->where('nik', 'like', '%' . $search . '%')
                        ->orWhere('name', 'like', '%' . $search . '%');
                })->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                    $vehicleQuery->where('plate_number', 'like', '%' . $search . '%');
                });
            });
        }

        if ($filters['qr_status']) {
            $this->applyQrStatusFilter($query, $filters['qr_status']);
        }
    }

    private function applyQrStatusFilter($query, string $qrStatus): void
    {
        if ($qrStatus === 'active') {
            $query->whereHas('activeToken', function ($tokenQuery) {
                $tokenQuery->where(function ($dateQuery) {
                    $dateQuery->whereNull('expires_at')
                        ->orWhere('expires_at', '>=', now());
                });
            });
        }

        if ($qrStatus === 'expired') {
            $query->whereHas('activeToken', function ($tokenQuery) {
                $tokenQuery->whereNotNull('expires_at')
                    ->where('expires_at', '<', now());
            });
        }

        if ($qrStatus === 'missing') {
            $query->whereDoesntHave('activeToken');
        }

        if ($qrStatus === 'revoked') {
            $query->whereDoesntHave('activeToken')
                ->whereHas('tokens', function ($tokenQuery) {
                    $tokenQuery->where('status', PermitToken::STATUS_REVOKED);
                });
        }
    }

    private function statusOptions(): array
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

    private function qrStatusOptions(): array
    {
        return [
            'missing' => 'Belum dibuat',
            'active' => 'QR Aktif',
            'expired' => 'QR Kadaluwarsa',
            'revoked' => 'QR Dicabut',
        ];
    }

    private function colorOptions(): array
    {
        return VehiclePermit::query()
            ->whereNotNull('permit_color')
            ->where('permit_color', '!=', '')
            ->orderBy('permit_color')
            ->distinct()
            ->pluck('permit_color', 'permit_color')
            ->all();
    }

    private function statusSummary(): array
    {
        return VehiclePermit::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }
}
```

- [ ] **Step 6: Update route role map**

Modify the permit entries in `app/Models/User.php` inside `routeRoles()`:

```php
'permits.index' => [
    self::ROLE_ADMIN_HR,
    self::ROLE_AUDITOR,
],
'permits.show' => [
    self::ROLE_ADMIN_HR,
    self::ROLE_AUDITOR,
],
'permits.review.edit' => [
    self::ROLE_ADMIN_HR,
],
'permits.review.update' => [
    self::ROLE_ADMIN_HR,
],
'permits.review.activate' => [
    self::ROLE_ADMIN_HR,
],
'permits.route-map.show' => [
    self::ROLE_ADMIN_HR,
    self::ROLE_AUDITOR,
],
```

Keep all existing QR route entries limited to `self::ROLE_ADMIN_HR`. `super_admin` remains allowed through `EnsureUserHasRole` override and does not need to be repeated in every route list.

- [ ] **Step 7: Add web routes**

Modify `routes/web.php`.

Add import:

```php
use App\Http\Controllers\PermitReviewController;
```

Replace the current `/permits` group with:

```php
Route::middleware('role:' . implode(',', User::rolesForRoute('permits.index')))->group(function () {
    Route::get('/permits', [PermitController::class, 'index'])->name('permits.index');
});

Route::get('/permits/{permit}', [PermitController::class, 'show'])
    ->middleware('role:' . implode(',', User::rolesForRoute('permits.show')))
    ->name('permits.show');

Route::get('/permits/{permit}/review', [PermitReviewController::class, 'edit'])
    ->middleware('role:' . implode(',', User::rolesForRoute('permits.review.edit')))
    ->name('permits.review.edit');

Route::post('/permits/{permit}/review', [PermitReviewController::class, 'update'])
    ->middleware('role:' . implode(',', User::rolesForRoute('permits.review.update')))
    ->name('permits.review.update');

Route::post('/permits/{permit}/review/activate', [PermitReviewController::class, 'activate'])
    ->middleware('role:' . implode(',', User::rolesForRoute('permits.review.activate')))
    ->name('permits.review.activate');
```

Keep the existing `/permits/{permit}/route-map` and QR routes below or above this block. There is no conflict because `GET /permits/{permit}` only matches one URI segment.

- [ ] **Step 8: Run HTTP tests and note expected view failures**

Run:

```bash
php artisan test --filter=PermitReviewHttpTest
```

Expected result:

```text
InvalidArgumentException: View [permits.show] not found
```

The controller and route layer is now wired. Missing Blade views are implemented in Task 4.

- [ ] **Step 9: Run service tests to catch integration regressions**

Run:

```bash
php artisan test --filter=PermitReviewServiceTest
```

Expected result:

```text
PASS  Tests\Feature\PermitReviewServiceTest
```

- [ ] **Step 10: Commit Task 3**

Run:

```bash
git add app/Http/Controllers/PermitController.php app/Http/Controllers/PermitReviewController.php app/Http/Requests/UpdatePermitReviewRequest.php app/Models/User.php routes/web.php tests/Feature/PermitReviewHttpTest.php
git commit -m "feat: add permit review routes"
```

---

### Task 4: Permit Review UI

**Files:**
- Modify: `resources/views/permits/index.blade.php`
- Create: `resources/views/permits/show.blade.php`
- Create: `resources/views/permits/review/edit.blade.php`
- Modify: `resources/css/app.css`
- Modify: `tests/Feature/PermitListAfterImportTest.php`

**Interfaces:**
- Consumes: controller variables `filters`, `statusOptions`, `qrStatusOptions`, `colorOptions`, `parkingLocations`, `statusSummary`, and `permits`.
- Consumes route names from Task 3.
- Produces visible actions: `Detail`, `Review`, `Lihat Rute`, `Generate QR`, `Lihat QR`, `Renew & Print`, `Renew`.

- [ ] **Step 1: Run current HTTP test to confirm missing view failure**

Run:

```bash
php artisan test --filter=PermitReviewHttpTest
```

Expected result:

```text
View [permits.show] not found
```

- [ ] **Step 2: Replace permit index view with filter and actions**

Replace `resources/views/permits/index.blade.php` with:

```blade
@extends('layouts.app')

@php
    $pageTitle = 'Izin Kendaraan';
    $pageDescription = 'Daftar izin kendaraan hasil import dan status review.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <div class="section-header">
                <div>
                    <h2 class="panel-title">Daftar Izin</h2>
                    <p class="panel-subtitle">Kelola izin kendaraan, status review, rute, dan QR.</p>
                </div>

                @if (auth()->user()->canAccessRoute('permits.qr.bulk-generate'))
                    <form method="POST" action="{{ route('permits.qr.bulk-generate') }}">
                        @csrf
                        <button class="button button-primary" type="submit">Bulk Generate QR Aktif</button>
                    </form>
                @endif
            </div>

            <div class="status-summary layout-gap">
                @foreach ([\App\Models\VehiclePermit::STATUS_NEEDS_REVIEW => 'Perlu Review', \App\Models\VehiclePermit::STATUS_ACTIVE => 'Aktif', \App\Models\VehiclePermit::STATUS_EXPIRED => 'Kadaluwarsa', \App\Models\VehiclePermit::STATUS_REVOKED => 'Dicabut'] as $status => $label)
                    <div class="status-summary__item">
                        <span class="status-summary__label">{{ $label }}</span>
                        <strong class="status-summary__value">{{ $statusSummary[$status] ?? 0 }}</strong>
                    </div>
                @endforeach
            </div>

            <form class="filter-panel layout-gap" method="GET" action="{{ route('permits.index') }}">
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
                        <label for="permit_color">Warna</label>
                        <select class="form-control" id="permit_color" name="permit_color">
                            <option value="">Semua warna</option>
                            @foreach ($colorOptions as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['permit_color'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
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
                </div>

                <div class="form-field layout-gap">
                    <label for="search">Cari NIK, Nama, atau Plat</label>
                    <input class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Contoh: 15090187 atau DT 6899 SA">
                </div>

                <div class="form-actions layout-gap">
                    <button class="button button-primary" type="submit">Filter</button>
                    <a class="button" href="{{ route('permits.index') }}">Reset</a>
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
                            <th>Warna</th>
                            <th>Status</th>
                            <th>Status QR</th>
                            <th>Sumber</th>
                            <th>Rute</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($permits as $permit)
                            @php
                                $activeToken = $permit->activeToken;
                                $latestToken = $permit->latestToken;
                                $qrLabel = 'Belum dibuat';

                                if ($activeToken && $activeToken->expires_at && $activeToken->expires_at->isPast()) {
                                    $qrLabel = 'QR Kadaluwarsa';
                                } elseif ($activeToken) {
                                    $qrLabel = 'QR Aktif';
                                } elseif ($latestToken && $latestToken->status === \App\Models\PermitToken::STATUS_REVOKED) {
                                    $qrLabel = 'QR Dicabut';
                                }
                            @endphp
                            <tr>
                                <td>{{ optional($permit->employee)->nik ?? '-' }}</td>
                                <td>{{ optional($permit->employee)->name ?? '-' }}</td>
                                <td>{{ optional($permit->vehicle)->plate_number ?? '-' }}</td>
                                <td>{{ optional($permit->parkingLocation)->code ?? '-' }}</td>
                                <td>{{ $permit->permit_color ?? '-' }}</td>
                                <td><span class="status-pill">{{ $permit->status ?? '-' }}</span></td>
                                <td>
                                    <span class="status-pill">{{ $qrLabel }}</span>
                                    @if ($activeToken)
                                        <div class="muted-text">{{ optional($activeToken->expires_at)->format('d M Y') }}</div>
                                    @endif
                                </td>
                                <td>{{ $permit->source ?? '-' }}</td>
                                <td>
                                    <div>{{ $permit->route_raw ?? '-' }}</div>
                                    <div class="muted-text">{{ $permit->routeSegments->count() }} segmen</div>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a class="button" href="{{ route('permits.show', $permit) }}">Detail</a>

                                        @if (auth()->user()->canAccessRoute('permits.review.edit') && $permit->status === \App\Models\VehiclePermit::STATUS_NEEDS_REVIEW)
                                            <a class="button button-primary" href="{{ route('permits.review.edit', $permit) }}">Review</a>
                                        @endif

                                        <a class="button" href="{{ route('permits.route-map.show', $permit) }}">Lihat Rute</a>

                                        @if (auth()->user()->canAccessRoute('permits.qr.generate') && ! $activeToken && $permit->status === \App\Models\VehiclePermit::STATUS_ACTIVE)
                                            <form method="POST" action="{{ route('permits.qr.generate', $permit) }}">
                                                @csrf
                                                <button class="button" type="submit">Generate QR</button>
                                            </form>
                                        @endif

                                        @if (auth()->user()->canAccessRoute('permits.qr.show') && $activeToken)
                                            <a class="button" href="{{ route('permits.qr.show', $permit) }}">Lihat QR</a>
                                        @endif

                                        @if (auth()->user()->canAccessRoute('permits.qr.print') && $activeToken)
                                            <form method="POST" action="{{ route('permits.qr.print', $permit) }}">
                                                @csrf
                                                <button class="button" type="submit">Renew &amp; Print</button>
                                            </form>
                                        @endif

                                        @if (auth()->user()->canAccessRoute('permits.qr.renew') && $activeToken)
                                            <form method="POST" action="{{ route('permits.qr.renew', $permit) }}">
                                                @csrf
                                                <button class="button" type="submit">Renew</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">Belum ada data izin kendaraan. Gunakan modul Import Excel untuk membuat data awal.</td>
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

- [ ] **Step 3: Add read-only permit detail view**

Create `resources/views/permits/show.blade.php`:

```blade
@extends('layouts.app')

@php
    $pageTitle = 'Detail Izin';
    $pageDescription = 'Detail izin kendaraan dan status review.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <div class="section-header">
                <div>
                    <h2 class="panel-title">Detail Izin</h2>
                    <p class="panel-subtitle">{{ optional($permit->employee)->name ?? '-' }} - {{ optional($permit->vehicle)->plate_number ?? '-' }}</p>
                </div>

                <div class="quick-actions">
                    <a class="button" href="{{ route('permits.index') }}">Kembali</a>
                    <a class="button" href="{{ route('permits.route-map.show', $permit) }}">Lihat Rute</a>
                    @if (auth()->user()->canAccessRoute('permits.review.edit') && $permit->status === \App\Models\VehiclePermit::STATUS_NEEDS_REVIEW)
                        <a class="button button-primary" href="{{ route('permits.review.edit', $permit) }}">Review</a>
                    @endif
                </div>
            </div>

            <dl class="detail-grid layout-gap">
                <div>
                    <dt>NIK</dt>
                    <dd>{{ optional($permit->employee)->nik ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Nama</dt>
                    <dd>{{ optional($permit->employee)->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Departemen</dt>
                    <dd>{{ optional($permit->employee)->department ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Plat</dt>
                    <dd>{{ optional($permit->vehicle)->plate_number ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Parkir</dt>
                    <dd>{{ optional($permit->parkingLocation)->code ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Warna</dt>
                    <dd>{{ $permit->permit_color ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Status Izin</dt>
                    <dd><span class="status-pill">{{ $permit->status }}</span></dd>
                </div>
                <div>
                    <dt>Sumber</dt>
                    <dd>{{ $permit->source ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Reviewer</dt>
                    <dd>{{ optional($permit->reviewer)->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Waktu Review</dt>
                    <dd>{{ optional($permit->reviewed_at)->format('d M Y H:i') ?? '-' }}</dd>
                </div>
            </dl>

            <div class="detail-section layout-gap">
                <h3 class="panel-title">Rute</h3>
                <p class="panel-subtitle">{{ $permit->route_raw ?? '-' }}</p>
                <p class="muted-text">{{ $permit->routeSegments->count() }} segmen tersimpan</p>
            </div>

            <div class="detail-section layout-gap">
                <h3 class="panel-title">Segmen Rute</h3>
                @if ($permit->routeSegments->isNotEmpty())
                    <div class="route-segment-list">
                        @foreach ($permit->routeSegments as $segment)
                            <span class="status-pill">{{ $segment->code }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="panel-subtitle">Belum ada segmen rute tersimpan.</p>
                @endif
            </div>

            <div class="detail-section layout-gap">
                <h3 class="panel-title">Catatan Review</h3>
                <p class="panel-subtitle">{{ $permit->review_note ?? '-' }}</p>
            </div>
        </div>
    </section>
@endsection
```

- [ ] **Step 4: Add review edit view**

Create directory `resources/views/permits/review` and file `resources/views/permits/review/edit.blade.php`:

```blade
@extends('layouts.app')

@php
    $pageTitle = 'Review Izin';
    $pageDescription = 'Koreksi data izin sebelum aktivasi dan generate QR.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <div class="section-header">
                <div>
                    <h2 class="panel-title">Review Izin</h2>
                    <p class="panel-subtitle">{{ optional($permit->employee)->name ?? '-' }} - {{ optional($permit->vehicle)->plate_number ?? '-' }}</p>
                </div>

                <div class="quick-actions">
                    <a class="button" href="{{ route('permits.show', $permit) }}">Detail</a>
                    <a class="button" href="{{ route('permits.index') }}">Daftar Izin</a>
                </div>
            </div>

            @if ($errors->has('review'))
                <x-alert type="danger" class="layout-gap">{{ $errors->first('review') }}</x-alert>
            @endif

            @if ($errors->has('activation'))
                <x-alert type="danger" class="layout-gap">{{ $errors->first('activation') }}</x-alert>
            @endif

            <dl class="detail-grid layout-gap">
                <div>
                    <dt>NIK</dt>
                    <dd>{{ optional($permit->employee)->nik ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Nama</dt>
                    <dd>{{ optional($permit->employee)->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Plat</dt>
                    <dd>{{ optional($permit->vehicle)->plate_number ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd><span class="status-pill">{{ $permit->status }}</span></dd>
                </div>
            </dl>

            <form class="form-stack layout-gap" method="POST" action="{{ route('permits.review.update', $permit) }}">
                @csrf

                <div class="form-grid">
                    <div class="form-field">
                        <label for="parking_location_id">Lokasi Parkir</label>
                        <select class="form-control" id="parking_location_id" name="parking_location_id">
                            <option value="">Pilih lokasi parkir</option>
                            @foreach ($parkingLocations as $parkingLocation)
                                <option value="{{ $parkingLocation->id }}" {{ (string) old('parking_location_id', $permit->parking_location_id) === (string) $parkingLocation->id ? 'selected' : '' }}>
                                    {{ $parkingLocation->code }}
                                </option>
                            @endforeach
                        </select>
                        @error('parking_location_id')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="form-field">
                    <label for="route_raw">Rute</label>
                    <textarea class="form-control" id="route_raw" name="route_raw" rows="4">{{ old('route_raw', $permit->route_raw) }}</textarea>
                    @error('route_raw')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="review_note">Catatan Review</label>
                    <textarea class="form-control" id="review_note" name="review_note" rows="4">{{ old('review_note', $permit->review_note) }}</textarea>
                    @error('review_note')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-actions">
                    <button class="button" type="submit">Simpan Review</button>
                    <button
                        class="button button-primary"
                        type="submit"
                        formaction="{{ route('permits.review.activate', $permit) }}"
                        formmethod="POST"
                    >
                        Aktifkan Izin
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection
```

- [ ] **Step 5: Add UI styles**

Append to `resources/css/app.css` before the first media query:

```css
.filter-panel {
    padding: 14px;
    border: 1px solid var(--sirika-border);
    border-radius: 8px;
    background: var(--sirika-panel);
}

.status-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.status-summary__item {
    min-height: 82px;
    display: grid;
    align-content: center;
    gap: 4px;
    padding: 12px;
    border: 1px solid var(--sirika-border);
    border-radius: 8px;
    background: var(--sirika-surface);
}

.status-summary__label {
    color: var(--sirika-muted);
    font-size: 13px;
    font-weight: 700;
}

.status-summary__value {
    font-size: 26px;
    line-height: 1;
}

.detail-section {
    padding-top: 16px;
    border-top: 1px solid var(--sirika-border);
}

.route-segment-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}
```

Inside the existing mobile media query that already handles `.form-grid`, add:

```css
.status-summary {
    grid-template-columns: 1fr;
}
```

- [ ] **Step 6: Update list test expectations if needed**

Run:

```bash
php artisan test --filter=PermitListAfterImportTest
```

If the list test fails because the index text changed from read-only copy to operational copy, update only the brittle text assertions. Keep assertions for actual behavior.

Expected retained assertions in `tests/Feature/PermitListAfterImportTest.php`:

```php
$this->actingAs($admin)->get(route('permits.index'))
    ->assertOk()
    ->assertSee('Izin Kendaraan')
    ->assertSee('FITRIAWATI')
    ->assertSee('DT 4423 CI')
    ->assertSee('GA-MES1-P01')
    ->assertSee('active')
    ->assertSee('Status QR')
    ->assertSee('Belum dibuat')
    ->assertSee('Generate QR')
    ->assertSee('Rute')
    ->assertSee('Y1-D2')
    ->assertSee('Lihat Rute')
    ->assertSee('Detail')
    ->assertSee('Bulk Generate QR Aktif');
```

- [ ] **Step 7: Run UI and HTTP tests**

Run:

```bash
php artisan test --filter=PermitReviewHttpTest
php artisan test --filter=PermitListAfterImportTest
```

Expected result:

```text
PASS  Tests\Feature\PermitReviewHttpTest
PASS  Tests\Feature\PermitListAfterImportTest
```

- [ ] **Step 8: Commit Task 4**

Run:

```bash
git add resources/views/permits/index.blade.php resources/views/permits/show.blade.php resources/views/permits/review/edit.blade.php resources/css/app.css tests/Feature/PermitListAfterImportTest.php
git commit -m "feat: add permit review interface"
```

---

### Task 5: Regression Verification and Production Readiness

**Files:**
- Modify only if tests expose stale assertions:
  - `tests/Feature/PermitQrHttpTest.php`
  - `tests/Feature/PermitRouteMapHttpTest.php`
  - `tests/Feature/ScanQrHttpTest.php`

**Interfaces:**
- Confirms `PermitTokenService` continues to reject non-active permits.
- Confirms route map preview still works.
- Confirms scan QR behavior is unchanged.

- [ ] **Step 1: Run focused Phase 5A tests**

Run:

```bash
php artisan test --filter=PermitReview
```

Expected result:

```text
PASS  Tests\Feature\PermitReviewMetadataTest
PASS  Tests\Feature\PermitReviewServiceTest
PASS  Tests\Feature\PermitReviewHttpTest
```

- [ ] **Step 2: Run QR regression tests**

Run:

```bash
php artisan test --filter=PermitQr
```

Expected result:

```text
PASS  Tests\Feature\PermitQrHttpTest
PASS  Tests\Feature\PermitQrServiceTest
```

If `PermitQrHttpTest` fails because an assertion expects generic `Print`, update it to the current UI label:

```php
->assertSee('Renew &amp; Print', false)
```

Do not loosen QR tests that assert non-active permits cannot generate QR.

- [ ] **Step 3: Run permit list and route map regressions**

Run:

```bash
php artisan test --filter=PermitList
php artisan test --filter=PermitRouteMap
```

Expected result:

```text
PASS  Tests\Feature\PermitListAfterImportTest
PASS  Tests\Feature\PermitRouteMapHttpTest
PASS  Tests\Feature\PermitRouteMapServiceTest
```

- [ ] **Step 4: Run scan regression tests**

Run:

```bash
php artisan test --filter=ScanQr
php artisan test --filter=PermitScan
```

Expected result:

```text
PASS  Tests\Feature\ScanQrHttpTest
PASS  Tests\Feature\PermitScanServiceTest
```

- [ ] **Step 5: Run the full test suite**

Run:

```bash
php artisan test
```

Expected result:

```text
PASS
```

Record any failures with file and test method name before changing code. Do not change QR eligibility rules to make tests pass.

- [ ] **Step 6: Manual browser smoke test**

Start the server if it is not running:

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

Manual flow:

1. Login as Admin HR test account.
2. Open `/permits?status=needs_review`.
3. Click `Review` on a `needs_review` permit.
4. Select parking location.
5. Enter route using known road segment codes, for example `Y1 -> D2`.
6. Fill review note.
7. Click `Simpan Review`.
8. Confirm status remains `needs_review`.
9. Click `Aktifkan Izin`.
10. Confirm redirect to detail page and status becomes `active`.
11. Return to `/permits?status=active`.
12. Confirm `Generate QR` appears for the activated permit.
13. Generate QR.
14. Open `Lihat Rute`.
15. Confirm route preview still loads.

- [ ] **Step 7: Production rollout notes**

Before deploy:

```bash
git status --short
```

Expected result:

```text

```

Production commands after deploy:

```bash
php artisan migrate --force
php artisan route:clear
php artisan view:clear
php artisan config:clear
```

Rollback behavior:

- Reverting code commit hides the review UI and routes.
- The nullable review metadata columns can remain in database without breaking old code.
- Do not drop review metadata columns during an emergency rollback unless the rollback is planned and backup exists.

- [ ] **Step 8: Commit final regression fixes**

If Task 5 changed tests or small UI assertions:

```bash
git add tests/Feature/PermitQrHttpTest.php tests/Feature/PermitRouteMapHttpTest.php tests/Feature/ScanQrHttpTest.php
git commit -m "test: cover permit review regressions"
```

If no files changed in Task 5:

```bash
git status --short
```

Expected result:

```text

```

---

## Self-Review Checklist

- Spec coverage:
  - Metadata review is covered by Task 1.
  - Domain validation and transactional activation are covered by Task 2.
  - Routes, request validation, authorization, filters, and detail page are covered by Task 3.
  - Blade UI and user-facing actions are covered by Task 4.
  - QR, route map, scan, and production rollout regression are covered by Task 5.

- Type consistency:
  - `PermitReviewService::saveDraft(VehiclePermit $permit, array $data): VehiclePermit` is used by `PermitReviewController::update`.
  - `PermitReviewService::activate(VehiclePermit $permit, array $data, User $reviewer): VehiclePermit` is used by `PermitReviewController::activate`.
  - Route names in tests, views, `User::routeRoles()`, and `routes/web.php` match.
  - Review metadata names match migration and `VehiclePermit` fillable/casts.

- Production safety:
  - Migration only adds nullable columns.
  - QR service remains unchanged.
  - Activation requires transaction, row lock, active parking, parseable route, and no active duplicate permit.
  - Auditor has read-only access.
  - Security has no permit admin access.
