# SIRIKA Phase 3 QR dan Scan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build QR digital, small printable permit cards, QR renewal, bulk token generation, camera scan, manual token scan, and scan logging for SIRIKA Phase 3.

**Architecture:** Token, scan, and renewal logic live in focused service classes. Controllers only handle HTTP flow, authorization, redirects, and view rendering. QR payload contains only the raw random token; the database stores only SHA-256 token hashes.

**Tech Stack:** PHP 7.4, Laravel 8, Blade, Alpine.js, BaconQrCode, html5-qrcode, MySQL/PostgreSQL.

## Global Constraints

- Baseline branch is `main` after Phase 2.
- Use an isolated worktree branch named `sirika-phase-3-qr-scan` before implementation.
- Follow TDD: write the failing test, verify it fails, implement minimal code, verify it passes.
- QR code must not contain NIK, name, contact number, plate number, or route data.
- Raw QR token must not be stored in database, session, log, or public storage.
- `permit_tokens.token_hash` stores `hash('sha256', $plainToken)`.
- QR lifetime is 1 year from generate, regenerate, or renew.
- Expired token remains scannable and returns result `expired`.
- Expired scan detail for security is limited to status, plate, name, and parking location.
- Renewal is not automatic during scan.
- Renewal is only allowed for `admin_hr` and `super_admin`.
- Security can scan QR and submit manual token fallback.
- All scan attempts create `scan_logs`, including invalid tokens with `permit_id = null`.
- Phase 3 does not implement map highlight, route overlay, coordinate editor, batch A4 card print, public scan, manual permit CRUD, or report export.

---

## File Structure

- Create `app/Services/Permits/PermitTokenService.php`
  - Generates, hashes, revokes, renews, bulk-generates tokens, and renders QR SVG.
- Create `app/Services/Permits/PermitScanService.php`
  - Validates scanned token and creates scan logs for every result.
- Create `app/Http/Controllers/PermitQrController.php`
  - Admin QR actions: manual generate, bulk generate, show QR, print card, renew.
- Create `app/Http/Requests/VerifyScanRequest.php`
  - Validates scan token and optional device information.
- Modify `app/Http/Controllers/PermitController.php`
  - Eager-load permit token data for QR status in the list.
- Modify `app/Http/Controllers/ScanController.php`
  - Serve scanner UI and verify scan token.
- Modify `app/Models/PermitToken.php`
  - Add constants, token status helpers, and active/expired helpers.
- Modify `app/Models/ScanLog.php`
  - Add result constants.
- Modify `app/Models/VehiclePermit.php`
  - Add `activeToken()` and `latestToken()` relationships.
- Modify `app/Models/User.php`
  - Add route role mappings for QR admin routes and `scan.verify`.
- Modify `routes/web.php`
  - Add admin QR routes and POST scan verify route with throttle.
- Create `resources/views/permits/qr/show.blade.php`
  - Digital QR view.
- Create `resources/views/permits/qr/print.blade.php`
  - Small printable card view rendered immediately after generate or renew because raw tokens are not persisted.
- Modify `resources/views/permits/index.blade.php`
  - Add QR status and action controls.
- Modify `resources/views/scan/index.blade.php`
  - Add camera scanner, manual token input, loading state, and result panel.
- Modify `resources/js/app.js`
  - Register an Alpine scanner component using `html5-qrcode`.
- Modify `resources/css/app.css`
  - Add styles for QR actions, scanner, scan result panels, and printable card.
- Modify `composer.json` and `composer.lock`
  - Add `bacon/bacon-qr-code:^2.0`.
- Modify `package.json` and `package-lock.json`
  - Add `html5-qrcode:^2.3.8`.
- Create `tests/Feature/PermitQrServiceTest.php`
  - Service-level token generation, renew, and bulk behavior.
- Create `tests/Feature/PermitScanServiceTest.php`
  - Service-level scan validation and logging.
- Create `tests/Feature/PermitQrHttpTest.php`
  - Admin QR routes and authorization.
- Create `tests/Feature/ScanQrHttpTest.php`
  - Security scan routes, JSON responses, and authorization.
- Modify `tests/Feature/SirikaModuleAccessTest.php`
  - Update scan page assertions from temporary copy to active scanner assertions.
- Modify `tests/Feature/PermitListAfterImportTest.php`
  - Assert QR columns and actions appear for admin permit list.

---

### Task 1: Dependency and Model Foundation

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `package.json`
- Modify: `package-lock.json`
- Modify: `app/Models/PermitToken.php`
- Modify: `app/Models/ScanLog.php`
- Modify: `app/Models/VehiclePermit.php`
- Test: `tests/Feature/PermitQrServiceTest.php`

**Interfaces:**
- Produces `PermitToken::STATUS_ACTIVE`, `PermitToken::STATUS_REVOKED`.
- Produces `ScanLog::RESULT_VALID`, `RESULT_EXPIRED`, `RESULT_REVOKED`, `RESULT_INACTIVE`, `RESULT_INVALID`.
- Produces `VehiclePermit::activeToken()` and `VehiclePermit::latestToken()`.
- Later tasks consume these constants and relationships.

- [ ] **Step 1: Install PHP QR dependency**

Run:

```bash
composer require bacon/bacon-qr-code:^2.0 --no-interaction
```

Expected: `composer.json` and `composer.lock` include `bacon/bacon-qr-code`.

- [ ] **Step 2: Install scanner frontend dependency**

Run:

```bash
npm install html5-qrcode@^2.3.8 --save
```

Expected: `package.json` and `package-lock.json` include `html5-qrcode`.

- [ ] **Step 3: Write failing model foundation test**

Create `tests/Feature/PermitQrServiceTest.php` with the initial test:

```php
<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PermitToken;
use App\Models\ScanLog;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitQrServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function permit_token_and_scan_log_constants_and_relationships_are_available()
    {
        $employee = Employee::create([
            'nik' => 'EMP-001',
            'name' => 'TEST USER',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 1001 AA',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $permit = VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'manual',
        ]);

        PermitToken::create([
            'vehicle_permit_id' => $permit->id,
            'token_hash' => hash('sha256', 'old-token'),
            'status' => PermitToken::STATUS_REVOKED,
            'expires_at' => now()->addDay(),
            'revoked_at' => now(),
        ]);

        $activeToken = PermitToken::create([
            'vehicle_permit_id' => $permit->id,
            'token_hash' => hash('sha256', 'active-token'),
            'status' => PermitToken::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        $this->assertSame('active', PermitToken::STATUS_ACTIVE);
        $this->assertSame('revoked', PermitToken::STATUS_REVOKED);
        $this->assertSame('valid', ScanLog::RESULT_VALID);
        $this->assertSame('expired', ScanLog::RESULT_EXPIRED);
        $this->assertSame($activeToken->id, $permit->fresh()->activeToken->id);
        $this->assertSame($activeToken->id, $permit->fresh()->latestToken->id);
    }
}
```

- [ ] **Step 4: Run test to verify RED**

Run:

```bash
php artisan test --filter=PermitQrServiceTest::permit_token_and_scan_log_constants_and_relationships_are_available
```

Expected: FAIL because `PermitToken::STATUS_ACTIVE` or `VehiclePermit::activeToken()` is not defined.

- [ ] **Step 5: Implement model constants and relationships**

Modify `app/Models/PermitToken.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermitToken extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'vehicle_permit_id',
        'token_hash',
        'status',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function permit()
    {
        return $this->belongsTo(VehiclePermit::class, 'vehicle_permit_id');
    }

    public function isRevoked()
    {
        return $this->status === self::STATUS_REVOKED;
    }

    public function isExpired()
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable()
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }
}
```

Modify `app/Models/ScanLog.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanLog extends Model
{
    public const RESULT_VALID = 'valid';
    public const RESULT_EXPIRED = 'expired';
    public const RESULT_REVOKED = 'revoked';
    public const RESULT_INACTIVE = 'inactive';
    public const RESULT_INVALID = 'invalid';

    protected $fillable = [
        'permit_id',
        'scanned_by',
        'scanned_at',
        'result',
        'device_info',
        'ip_address',
        'notes',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function permit()
    {
        return $this->belongsTo(VehiclePermit::class, 'permit_id');
    }

    public function scanner()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
```

Add to `app/Models/VehiclePermit.php`:

```php
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
```

- [ ] **Step 6: Run test to verify GREEN**

Run:

```bash
php artisan test --filter=PermitQrServiceTest::permit_token_and_scan_log_constants_and_relationships_are_available
```

Expected: PASS.

- [ ] **Step 7: Commit task**

Run:

```bash
git add composer.json composer.lock package.json package-lock.json app/Models/PermitToken.php app/Models/ScanLog.php app/Models/VehiclePermit.php tests/Feature/PermitQrServiceTest.php
git commit -m "feat: add sirika qr token foundation"
```

Expected: commit contains only dependency files, token/log model changes, permit token relationships, and the foundation test.

---

### Task 2: Permit Token Service

**Files:**
- Create: `app/Services/Permits/PermitTokenService.php`
- Modify: `tests/Feature/PermitQrServiceTest.php`

**Interfaces:**
- Consumes `PermitToken` constants and `VehiclePermit::activeToken()`.
- Produces `PermitTokenService::generateForPermit(VehiclePermit $permit): array`.
- Produces `PermitTokenService::renewForPermit(VehiclePermit $permit): array`.
- Produces `PermitTokenService::bulkGenerateForActivePermits(): array`.
- Produces `PermitTokenService::renderSvg(string $plainToken): string`.
- Return array for generate and renew:
  - `plain_token`: raw token used only for immediate QR rendering.
  - `permit_token`: created `PermitToken` model.
  - `qr_svg`: SVG markup string.
- Return array for bulk:
  - `created`: integer.
  - `skipped`: integer.

- [ ] **Step 1: Add failing token generation tests**

Append to `tests/Feature/PermitQrServiceTest.php`:

```php
use App\Services\Permits\PermitTokenService;
```

Add helper methods inside the class:

```php
    private function createPermit($status = VehiclePermit::STATUS_ACTIVE)
    {
        $employee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => 'TEST USER',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT ' . random_int(1000, 9999) . ' AA',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => $status,
            'source' => 'manual',
        ]);
    }
```

Add tests:

```php
    /** @test */
    public function token_service_generates_hash_only_token_and_qr_svg_for_active_permit()
    {
        $permit = $this->createPermit();
        $service = app(PermitTokenService::class);

        $result = $service->generateForPermit($permit);

        $this->assertArrayHasKey('plain_token', $result);
        $this->assertArrayHasKey('permit_token', $result);
        $this->assertArrayHasKey('qr_svg', $result);
        $this->assertSame(hash('sha256', $result['plain_token']), $result['permit_token']->token_hash);
        $this->assertDatabaseMissing('permit_tokens', ['token_hash' => $result['plain_token']]);
        $this->assertStringContainsString('<svg', $result['qr_svg']);
        $this->assertTrue($result['permit_token']->expires_at->isSameDay(now()->addYear()));
    }

    /** @test */
    public function token_service_refuses_non_active_permit()
    {
        $permit = $this->createPermit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $service = app(PermitTokenService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('QR hanya dapat dibuat untuk izin aktif.');

        $service->generateForPermit($permit);
    }

    /** @test */
    public function token_service_does_not_create_duplicate_active_token()
    {
        $permit = $this->createPermit();
        $service = app(PermitTokenService::class);

        $first = $service->generateForPermit($permit);
        $second = $service->generateForPermit($permit->fresh());

        $this->assertSame($first['permit_token']->id, $second['permit_token']->id);
        $this->assertSame(1, PermitToken::where('vehicle_permit_id', $permit->id)->count());
    }

    /** @test */
    public function token_service_renew_revokes_old_token_and_creates_new_one_year_token()
    {
        $permit = $this->createPermit();
        $service = app(PermitTokenService::class);

        $old = $service->generateForPermit($permit);
        $new = $service->renewForPermit($permit->fresh());

        $this->assertNotSame($old['permit_token']->id, $new['permit_token']->id);
        $this->assertSame(PermitToken::STATUS_REVOKED, $old['permit_token']->fresh()->status);
        $this->assertNotNull($old['permit_token']->fresh()->revoked_at);
        $this->assertSame(PermitToken::STATUS_ACTIVE, $new['permit_token']->status);
        $this->assertTrue($new['permit_token']->expires_at->isSameDay(now()->addYear()));
    }

    /** @test */
    public function token_service_bulk_generates_only_active_permits_without_active_token()
    {
        $firstActive = $this->createPermit();
        $secondActive = $this->createPermit();
        $needsReview = $this->createPermit(VehiclePermit::STATUS_NEEDS_REVIEW);
        $service = app(PermitTokenService::class);

        $service->generateForPermit($secondActive);
        $summary = $service->bulkGenerateForActivePermits();

        $this->assertSame(1, $summary['created']);
        $this->assertSame(2, $summary['skipped']);
        $this->assertNotNull($firstActive->fresh()->activeToken);
        $this->assertNotNull($secondActive->fresh()->activeToken);
        $this->assertNull($needsReview->fresh()->activeToken);
    }
```

- [ ] **Step 2: Run tests to verify RED**

Run:

```bash
php artisan test --filter=PermitQrServiceTest
```

Expected: FAIL because `App\Services\Permits\PermitTokenService` does not exist.

- [ ] **Step 3: Implement token service**

Create `app/Services/Permits/PermitTokenService.php`:

```php
<?php

namespace App\Services\Permits;

use App\Models\PermitToken;
use App\Models\VehiclePermit;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PermitTokenService
{
    public function generateForPermit(VehiclePermit $permit)
    {
        $this->ensurePermitCanHaveQr($permit);

        $existing = $permit->fresh()->activeToken;

        if ($existing) {
            return [
                'plain_token' => null,
                'permit_token' => $existing,
                'qr_svg' => null,
            ];
        }

        return $this->createTokenForPermit($permit);
    }

    public function renewForPermit(VehiclePermit $permit)
    {
        $this->ensurePermitCanHaveQr($permit);

        return DB::transaction(function () use ($permit) {
            PermitToken::where('vehicle_permit_id', $permit->id)
                ->where('status', PermitToken::STATUS_ACTIVE)
                ->update([
                    'status' => PermitToken::STATUS_REVOKED,
                    'revoked_at' => now(),
                ]);

            return $this->createTokenForPermit($permit);
        });
    }

    public function bulkGenerateForActivePermits()
    {
        $created = 0;
        $skipped = 0;

        VehiclePermit::with('activeToken')
            ->where('status', VehiclePermit::STATUS_ACTIVE)
            ->orderBy('id')
            ->chunkById(100, function ($permits) use (&$created, &$skipped) {
                foreach ($permits as $permit) {
                    if ($permit->activeToken) {
                        $skipped++;
                        continue;
                    }

                    $this->createTokenForPermit($permit);
                    $created++;
                }
            });

        $skipped += VehiclePermit::where('status', '!=', VehiclePermit::STATUS_ACTIVE)->count();

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    public function renderSvg($plainToken)
    {
        $renderer = new ImageRenderer(
            new RendererStyle(280),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($plainToken);
    }

    private function createTokenForPermit(VehiclePermit $permit)
    {
        $plainToken = Str::random(64);

        $token = PermitToken::create([
            'vehicle_permit_id' => $permit->id,
            'token_hash' => hash('sha256', $plainToken),
            'status' => PermitToken::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        return [
            'plain_token' => $plainToken,
            'permit_token' => $token,
            'qr_svg' => $this->renderSvg($plainToken),
        ];
    }

    private function ensurePermitCanHaveQr(VehiclePermit $permit)
    {
        if ($permit->status !== VehiclePermit::STATUS_ACTIVE) {
            throw new InvalidArgumentException('QR hanya dapat dibuat untuk izin aktif.');
        }
    }
}
```

- [ ] **Step 4: Run tests to verify GREEN**

Run:

```bash
php artisan test --filter=PermitQrServiceTest
```

Expected: PASS.

- [ ] **Step 5: Commit task**

Run:

```bash
git add app/Services/Permits/PermitTokenService.php tests/Feature/PermitQrServiceTest.php
git commit -m "feat: add permit token service"
```

Expected: commit includes only `PermitTokenService` and its tests.

---

### Task 3: Permit Scan Service

**Files:**
- Create: `app/Services/Permits/PermitScanService.php`
- Create: `tests/Feature/PermitScanServiceTest.php`

**Interfaces:**
- Consumes `PermitTokenService` to create test tokens.
- Produces `PermitScanService::scan(string $plainToken, ?User $scanner, array $context = []): array`.
- Return array:
  - `result`: one of `valid`, `expired`, `revoked`, `inactive`, `invalid`.
  - `message`: user-facing Indonesian message.
  - `permit`: safe associative array or null.
  - `scan_log`: created `ScanLog`.

- [ ] **Step 1: Write failing scan service tests**

Create `tests/Feature/PermitScanServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\PermitToken;
use App\Models\ScanLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitScanService;
use App\Services\Permits\PermitTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitScanServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createPermit($status = VehiclePermit::STATUS_ACTIVE)
    {
        $employee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => 'SECURITY TEST USER',
            'department' => 'GA',
            'position' => 'STAFF',
            'contact_number' => '08123456789',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT ' . random_int(1000, 9999) . ' XY',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $parking = ParkingLocation::create([
            'code' => 'GA-MES1-P01',
            'name' => 'GA-MES1-P01',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parking->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => $status,
            'source' => 'manual',
            'route_raw' => 'Y1-D2',
        ]);
    }

    private function securityUser()
    {
        return User::factory()->create([
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    /** @test */
    public function scan_service_accepts_valid_token_and_logs_valid_result()
    {
        $permit = $this->createPermit();
        $tokenResult = app(PermitTokenService::class)->generateForPermit($permit);

        $result = app(PermitScanService::class)->scan($tokenResult['plain_token'], $this->securityUser(), [
            'ip_address' => '127.0.0.1',
            'device_info' => 'Feature test',
        ]);

        $this->assertSame(ScanLog::RESULT_VALID, $result['result']);
        $this->assertSame($permit->id, $result['scan_log']->permit_id);
        $this->assertSame('SECURITY TEST USER', $result['permit']['employee_name']);
        $this->assertArrayNotHasKey('contact_number', $result['permit']);
        $this->assertDatabaseHas('scan_logs', [
            'permit_id' => $permit->id,
            'result' => ScanLog::RESULT_VALID,
        ]);
    }

    /** @test */
    public function scan_service_returns_expired_with_limited_detail_and_logs_it()
    {
        $permit = $this->createPermit();
        $tokenResult = app(PermitTokenService::class)->generateForPermit($permit);
        $tokenResult['permit_token']->update(['expires_at' => now()->subMinute()]);

        $result = app(PermitScanService::class)->scan($tokenResult['plain_token'], $this->securityUser());

        $this->assertSame(ScanLog::RESULT_EXPIRED, $result['result']);
        $this->assertSame('SECURITY TEST USER', $result['permit']['employee_name']);
        $this->assertArrayHasKey('plate_number', $result['permit']);
        $this->assertArrayHasKey('parking_code', $result['permit']);
        $this->assertArrayNotHasKey('nik', $result['permit']);
        $this->assertArrayNotHasKey('route_raw', $result['permit']);
        $this->assertDatabaseHas('scan_logs', [
            'permit_id' => $permit->id,
            'result' => ScanLog::RESULT_EXPIRED,
        ]);
    }

    /** @test */
    public function scan_service_returns_revoked_for_revoked_token()
    {
        $permit = $this->createPermit();
        $tokenResult = app(PermitTokenService::class)->generateForPermit($permit);
        $tokenResult['permit_token']->update([
            'status' => PermitToken::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);

        $result = app(PermitScanService::class)->scan($tokenResult['plain_token'], $this->securityUser());

        $this->assertSame(ScanLog::RESULT_REVOKED, $result['result']);
        $this->assertDatabaseHas('scan_logs', [
            'permit_id' => $permit->id,
            'result' => ScanLog::RESULT_REVOKED,
        ]);
    }

    /** @test */
    public function scan_service_returns_inactive_when_permit_is_not_active()
    {
        $permit = $this->createPermit();
        $tokenResult = app(PermitTokenService::class)->generateForPermit($permit);
        $permit->update(['status' => VehiclePermit::STATUS_SUSPENDED]);

        $result = app(PermitScanService::class)->scan($tokenResult['plain_token'], $this->securityUser());

        $this->assertSame(ScanLog::RESULT_INACTIVE, $result['result']);
        $this->assertDatabaseHas('scan_logs', [
            'permit_id' => $permit->id,
            'result' => ScanLog::RESULT_INACTIVE,
        ]);
    }

    /** @test */
    public function scan_service_logs_invalid_token_without_permit_id()
    {
        $result = app(PermitScanService::class)->scan('not-a-known-token', $this->securityUser());

        $this->assertSame(ScanLog::RESULT_INVALID, $result['result']);
        $this->assertNull($result['permit']);
        $this->assertDatabaseHas('scan_logs', [
            'permit_id' => null,
            'result' => ScanLog::RESULT_INVALID,
        ]);
    }
}
```

- [ ] **Step 2: Run tests to verify RED**

Run:

```bash
php artisan test --filter=PermitScanServiceTest
```

Expected: FAIL because `PermitScanService` does not exist.

- [ ] **Step 3: Implement scan service**

Create `app/Services/Permits/PermitScanService.php`:

```php
<?php

namespace App\Services\Permits;

use App\Models\PermitToken;
use App\Models\ScanLog;
use App\Models\User;
use App\Models\VehiclePermit;

class PermitScanService
{
    public function scan($plainToken, ?User $scanner, array $context = [])
    {
        $plainToken = trim((string) $plainToken);

        if (strlen($plainToken) < 16) {
            return $this->logAndReturn(ScanLog::RESULT_INVALID, null, $scanner, $context, 'QR tidak dikenal.', null);
        }

        $token = PermitToken::with(['permit.employee', 'permit.vehicle', 'permit.parkingLocation'])
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $token) {
            return $this->logAndReturn(ScanLog::RESULT_INVALID, null, $scanner, $context, 'QR tidak dikenal.', null);
        }

        $permit = $token->permit;

        if ($token->status === PermitToken::STATUS_REVOKED) {
            return $this->logAndReturn(ScanLog::RESULT_REVOKED, $permit, $scanner, $context, 'QR telah dicabut.', null);
        }

        if ($token->isExpired() || $this->permitDateExpired($permit)) {
            return $this->logAndReturn(ScanLog::RESULT_EXPIRED, $permit, $scanner, $context, 'QR kadaluwarsa.', $this->limitedPermitData($permit));
        }

        if (! $permit || $permit->status !== VehiclePermit::STATUS_ACTIVE) {
            return $this->logAndReturn(ScanLog::RESULT_INACTIVE, $permit, $scanner, $context, 'Izin tidak aktif.', null);
        }

        return $this->logAndReturn(ScanLog::RESULT_VALID, $permit, $scanner, $context, 'QR valid.', $this->fullPermitData($permit));
    }

    private function logAndReturn($result, ?VehiclePermit $permit, ?User $scanner, array $context, $message, ?array $permitData)
    {
        $log = ScanLog::create([
            'permit_id' => $permit ? $permit->id : null,
            'scanned_by' => $scanner ? $scanner->id : null,
            'scanned_at' => now(),
            'result' => $result,
            'device_info' => $context['device_info'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
            'notes' => $message,
        ]);

        return [
            'result' => $result,
            'message' => $message,
            'permit' => $permitData,
            'scan_log' => $log,
        ];
    }

    private function permitDateExpired(?VehiclePermit $permit)
    {
        return $permit && $permit->valid_until && $permit->valid_until->isPast();
    }

    private function limitedPermitData(?VehiclePermit $permit)
    {
        if (! $permit) {
            return null;
        }

        return [
            'employee_name' => optional($permit->employee)->name,
            'plate_number' => optional($permit->vehicle)->plate_number,
            'parking_code' => optional($permit->parkingLocation)->code,
        ];
    }

    private function fullPermitData(VehiclePermit $permit)
    {
        return [
            'employee_name' => optional($permit->employee)->name,
            'plate_number' => optional($permit->vehicle)->plate_number,
            'parking_code' => optional($permit->parkingLocation)->code,
            'permit_color' => $permit->permit_color,
            'status' => $permit->status,
            'route_raw' => $permit->route_raw,
        ];
    }
}
```

- [ ] **Step 4: Run tests to verify GREEN**

Run:

```bash
php artisan test --filter=PermitScanServiceTest
```

Expected: PASS.

- [ ] **Step 5: Commit task**

Run:

```bash
git add app/Services/Permits/PermitScanService.php tests/Feature/PermitScanServiceTest.php
git commit -m "feat: add permit scan service"
```

Expected: commit includes only `PermitScanService` and its tests.

---

### Task 4: Admin QR HTTP Flow

**Files:**
- Create: `app/Http/Controllers/PermitQrController.php`
- Create: `resources/views/permits/qr/show.blade.php`
- Create: `resources/views/permits/qr/print.blade.php`
- Modify: `app/Models/User.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/PermitQrHttpTest.php`

**Interfaces:**
- Consumes `PermitTokenService`.
- Produces route names:
  - `permits.qr.generate`
  - `permits.qr.bulk-generate`
  - `permits.qr.show`
  - `permits.qr.print`
  - `permits.qr.renew`
- `permits.qr.print` is a POST action that generates a fresh printable QR by renewing the token. This is deliberate because the old raw token cannot be reconstructed from `token_hash`.

- [ ] **Step 1: Write failing HTTP tests**

Create `tests/Feature/PermitQrHttpTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\PermitToken;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitQrHttpTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole($role)
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function permit($status = VehiclePermit::STATUS_ACTIVE)
    {
        $employee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => 'QR HTTP USER',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 7001 QR',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $parking = ParkingLocation::create([
            'code' => 'GA-MES1-P01',
            'name' => 'GA-MES1-P01',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parking->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => $status,
            'source' => 'manual',
        ]);
    }

    /** @test */
    public function admin_can_generate_show_print_and_renew_qr_for_active_permit()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $permit = $this->permit();

        $this->actingAs($admin)->post(route('permits.qr.generate', $permit))
            ->assertOk()
            ->assertSee('QR Digital')
            ->assertSee('<svg', false)
            ->assertSee('QR HTTP USER')
            ->assertSee('DT 7001 QR');

        $this->assertSame(1, PermitToken::where('vehicle_permit_id', $permit->id)->count());

        $this->actingAs($admin)->get(route('permits.qr.show', $permit))
            ->assertOk()
            ->assertSee('QR Digital')
            ->assertSee('QR HTTP USER')
            ->assertSee('DT 7001 QR');

        $this->actingAs($admin)->post(route('permits.qr.print', $permit))
            ->assertOk()
            ->assertSee('SIRIKA VDNI')
            ->assertSee('DT 7001 QR')
            ->assertSee('<svg', false);

        $oldTokenId = $permit->fresh()->activeToken->id;

        $this->actingAs($admin)->post(route('permits.qr.renew', $permit))
            ->assertOk()
            ->assertSee('QR Digital')
            ->assertSee('<svg', false);

        $this->assertSame(PermitToken::STATUS_REVOKED, PermitToken::find($oldTokenId)->status);
        $this->assertNotSame($oldTokenId, $permit->fresh()->activeToken->id);
    }

    /** @test */
    public function security_cannot_access_admin_qr_routes()
    {
        $security = $this->userWithRole(User::ROLE_SECURITY);
        $permit = $this->permit();

        $this->actingAs($security)->post(route('permits.qr.generate', $permit))->assertForbidden();
        $this->actingAs($security)->get(route('permits.qr.show', $permit))->assertForbidden();
        $this->actingAs($security)->post(route('permits.qr.print', $permit))->assertForbidden();
        $this->actingAs($security)->post(route('permits.qr.renew', $permit))->assertForbidden();
    }

    /** @test */
    public function bulk_generate_creates_tokens_for_active_permits_without_existing_active_token()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $first = $this->permit();
        $second = $this->permit();
        $review = $this->permit(VehiclePermit::STATUS_NEEDS_REVIEW);

        app(PermitTokenService::class)->generateForPermit($second);

        $this->actingAs($admin)->post(route('permits.qr.bulk-generate'))
            ->assertRedirect(route('permits.index'))
            ->assertSessionHas('status');

        $this->assertNotNull($first->fresh()->activeToken);
        $this->assertNotNull($second->fresh()->activeToken);
        $this->assertNull($review->fresh()->activeToken);
    }
}
```

- [ ] **Step 2: Run tests to verify RED**

Run:

```bash
php artisan test --filter=PermitQrHttpTest
```

Expected: FAIL because route `permits.qr.generate` is not defined.

- [ ] **Step 3: Implement controller**

Create `app/Http/Controllers/PermitQrController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\VehiclePermit;
use App\Services\Permits\PermitTokenService;

class PermitQrController extends Controller
{
    private $tokens;

    public function __construct(PermitTokenService $tokens)
    {
        $this->tokens = $tokens;
    }

    public function generate(VehiclePermit $permit)
    {
        $result = $this->tokens->generateForPermit($permit);
        $permit->load(['employee', 'vehicle', 'parkingLocation', 'activeToken']);

        return view('permits.qr.show', [
            'permit' => $permit,
            'token' => $result['permit_token'],
            'qrSvg' => $result['qr_svg'],
        ]);
    }

    public function bulkGenerate()
    {
        $summary = $this->tokens->bulkGenerateForActivePermits();

        return redirect()
            ->route('permits.index')
            ->with('status', "Bulk generate selesai. Dibuat: {$summary['created']}. Dilewati: {$summary['skipped']}.");
    }

    public function show(VehiclePermit $permit)
    {
        $permit->load(['employee', 'vehicle', 'parkingLocation', 'activeToken']);
        $token = $permit->activeToken;

        abort_unless($token, 404);

        return view('permits.qr.show', [
            'permit' => $permit,
            'token' => $token,
            'qrSvg' => null,
        ]);
    }

    public function print(VehiclePermit $permit)
    {
        $result = $this->tokens->renewForPermit($permit);
        $permit->load(['employee', 'vehicle', 'parkingLocation']);

        return view('permits.qr.print', [
            'permit' => $permit,
            'token' => $result['permit_token'],
            'qrSvg' => $result['qr_svg'],
        ]);
    }

    public function renew(VehiclePermit $permit)
    {
        $result = $this->tokens->renewForPermit($permit);
        $permit->load(['employee', 'vehicle', 'parkingLocation']);

        return view('permits.qr.show', [
            'permit' => $permit,
            'token' => $result['permit_token'],
            'qrSvg' => $result['qr_svg'],
        ]);
    }
}
```

Because raw tokens are not persisted, `show` cannot reconstruct old QR SVG. It shows token status and expiry. `generate`, `renew`, and `print` render QR SVG immediately in the same response. Reprinting later intentionally renews the token and revokes the old one.

- [ ] **Step 4: Add routes and role mapping**

Modify `app/Models/User.php` inside `routeRoles()`:

```php
            'permits.qr.generate' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.qr.bulk-generate' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.qr.show' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.qr.print' => [
                self::ROLE_ADMIN_HR,
            ],
            'permits.qr.renew' => [
                self::ROLE_ADMIN_HR,
            ],
            'scan.verify' => [
                self::ROLE_ADMIN_HR,
                self::ROLE_SECURITY,
            ],
```

`super_admin` remains allowed by `canAccessRoute()` bypass.

Modify `routes/web.php` imports:

```php
use App\Http\Controllers\PermitQrController;
```

Add inside the `auth` group:

```php
    Route::post('/permits/qr/bulk-generate', [PermitQrController::class, 'bulkGenerate'])
        ->middleware('role:' . implode(',', User::rolesForRoute('permits.qr.bulk-generate')))
        ->name('permits.qr.bulk-generate');

    Route::post('/permits/{permit}/qr/generate', [PermitQrController::class, 'generate'])
        ->middleware('role:' . implode(',', User::rolesForRoute('permits.qr.generate')))
        ->name('permits.qr.generate');

    Route::get('/permits/{permit}/qr', [PermitQrController::class, 'show'])
        ->middleware('role:' . implode(',', User::rolesForRoute('permits.qr.show')))
        ->name('permits.qr.show');

    Route::post('/permits/{permit}/qr/print', [PermitQrController::class, 'print'])
        ->middleware('role:' . implode(',', User::rolesForRoute('permits.qr.print')))
        ->name('permits.qr.print');

    Route::post('/permits/{permit}/qr/renew', [PermitQrController::class, 'renew'])
        ->middleware('role:' . implode(',', User::rolesForRoute('permits.qr.renew')))
        ->name('permits.qr.renew');
```

- [ ] **Step 5: Add minimal QR views**

Create `resources/views/permits/qr/show.blade.php`:

```blade
@extends('layouts.app')

@php
    $pageTitle = 'QR Digital';
    $pageDescription = 'Status QR izin kendaraan.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">QR Digital</h2>
            <p class="panel-subtitle">Token mentah tidak disimpan. QR tampil saat generate atau renew; cetak ulang akan membuat token baru.</p>

            @if ($qrSvg)
                <div class="qr-display layout-gap">{!! $qrSvg !!}</div>
            @else
                <x-alert type="info" class="layout-gap">
                    QR lama tidak bisa ditampilkan ulang karena token mentah tidak disimpan. Gunakan renew untuk membuat QR baru.
                </x-alert>
            @endif

            <dl class="detail-grid layout-gap">
                <div><dt>Nama</dt><dd>{{ optional($permit->employee)->name ?? '-' }}</dd></div>
                <div><dt>Plat</dt><dd>{{ optional($permit->vehicle)->plate_number ?? '-' }}</dd></div>
                <div><dt>Lokasi Parkir</dt><dd>{{ optional($permit->parkingLocation)->code ?? '-' }}</dd></div>
                <div><dt>Status Token</dt><dd>{{ $token->status }}</dd></div>
                <div><dt>Berlaku Sampai</dt><dd>{{ optional($token->expires_at)->format('d M Y H:i') ?? '-' }}</dd></div>
            </dl>

            <div class="quick-actions layout-gap">
                <a class="button" href="{{ route('permits.index') }}">Kembali</a>
                <form method="POST" action="{{ route('permits.qr.renew', $permit) }}">
                    @csrf
                    <button class="button button-primary" type="submit">Renew QR 1 Tahun</button>
                </form>
                <form method="POST" action="{{ route('permits.qr.print', $permit) }}">
                    @csrf
                    <button class="button" type="submit">Renew & Print Kartu</button>
                </form>
            </div>
        </div>
    </section>
@endsection
```

Create `resources/views/permits/qr/print.blade.php`:

```blade
@extends('layouts.app')

@php
    $pageTitle = 'Print Kartu QR';
    $pageDescription = 'Kartu kecil per izin kendaraan.';
@endphp

@section('content')
    <section class="permit-card-print">
        <div class="permit-card">
            <div>
                <p class="permit-card__brand">SIRIKA VDNI</p>
                <p class="permit-card__label">Plat</p>
                <p class="permit-card__value">{{ optional($permit->vehicle)->plate_number ?? '-' }}</p>
                <p class="permit-card__label">Nama</p>
                <p class="permit-card__value">{{ optional($permit->employee)->name ?? '-' }}</p>
                <p class="permit-card__label">Parkir</p>
                <p class="permit-card__value">{{ optional($permit->parkingLocation)->code ?? '-' }}</p>
                <p class="permit-card__label">Berlaku sampai</p>
                <p class="permit-card__value">{{ optional($token->expires_at)->format('d M Y') ?? '-' }}</p>
            </div>
            <div class="permit-card__qr">
                {!! $qrSvg !!}
            </div>
        </div>

        <div class="quick-actions layout-gap no-print">
            <button class="button button-primary" type="button" onclick="window.print()">Print</button>
            <a class="button" href="{{ route('permits.qr.show', $permit) }}">Kembali</a>
        </div>
    </section>
@endsection
```

- [ ] **Step 6: Run tests to verify GREEN**

Run:

```bash
php artisan test --filter=PermitQrHttpTest
```

Expected: PASS.

- [ ] **Step 7: Commit task**

Run:

```bash
git add app/Http/Controllers/PermitQrController.php app/Models/User.php routes/web.php resources/views/permits/qr/show.blade.php resources/views/permits/qr/print.blade.php tests/Feature/PermitQrHttpTest.php
git commit -m "feat: add permit qr admin routes"
```

Expected: commit includes QR controller, routes, views, role mapping, and HTTP tests.

---

### Task 5: Permit List QR Actions and Reprint-Safe UX

**Files:**
- Modify: `app/Http/Controllers/PermitController.php`
- Modify: `app/Http/Controllers/PermitQrController.php`
- Modify: `resources/views/permits/index.blade.php`
- Modify: `resources/views/permits/qr/show.blade.php`
- Modify: `resources/views/permits/qr/print.blade.php`
- Modify: `resources/css/app.css`
- Modify: `tests/Feature/PermitListAfterImportTest.php`
- Modify: `tests/Feature/PermitQrHttpTest.php`

**Interfaces:**
- Consumes QR routes from Task 4.
- Produces visible QR status and actions in `/permits`.
- Uses renew flow for reprint when raw token is no longer available.

- [ ] **Step 1: Add failing permit list QR assertions**

Modify `tests/Feature/PermitListAfterImportTest.php` in `admin_can_view_imported_permits()` after permit creation:

```php
        $this->actingAs($admin)->get(route('permits.index'))
            ->assertOk()
            ->assertSee('Status QR')
            ->assertSee('Belum dibuat')
            ->assertSee('Generate QR')
            ->assertSee('Bulk Generate QR Aktif');
```

Add a second test:

```php
    /** @test */
    public function admin_sees_qr_active_status_and_actions_when_permit_has_token()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $employee = Employee::create([
            'nik' => '200115678',
            'name' => 'QR READY USER',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 8899 QA',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $permit = VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'hijau',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'import',
        ]);

        \App\Models\PermitToken::create([
            'vehicle_permit_id' => $permit->id,
            'token_hash' => hash('sha256', 'ready-token'),
            'status' => \App\Models\PermitToken::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        $this->actingAs($admin)->get(route('permits.index'))
            ->assertOk()
            ->assertSee('QR Aktif')
            ->assertSee('Lihat QR')
            ->assertSee('Print')
            ->assertSee('Renew');
    }
```

- [ ] **Step 2: Run tests to verify RED**

Run:

```bash
php artisan test --filter=PermitListAfterImportTest
```

Expected: FAIL because permit list does not show QR status/actions.

- [ ] **Step 3: Eager-load token relationships**

Modify `app/Http/Controllers/PermitController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\VehiclePermit;

class PermitController extends Controller
{
    public function index()
    {
        return view('permits.index', [
            'permits' => VehiclePermit::with(['employee', 'vehicle', 'parkingLocation', 'activeToken', 'latestToken'])
                ->latest()
                ->paginate(25),
        ]);
    }
}
```

- [ ] **Step 4: Update permit list view**

Replace the table header in `resources/views/permits/index.blade.php` with:

```blade
<tr>
    <th>NIK</th>
    <th>Nama</th>
    <th>Plat</th>
    <th>Parkir</th>
    <th>Warna</th>
    <th>Status</th>
    <th>Status QR</th>
    <th>Sumber</th>
    <th>Aksi QR</th>
</tr>
```

Add a bulk form above the table:

```blade
<div class="quick-actions layout-gap">
    <form method="POST" action="{{ route('permits.qr.bulk-generate') }}">
        @csrf
        <button class="button button-primary" type="submit">Bulk Generate QR Aktif</button>
    </form>
</div>
```

Replace each row body with:

```blade
@php
    $activeToken = $permit->activeToken;
    $latestToken = $permit->latestToken;
    $qrLabel = 'Belum dibuat';

    if ($activeToken && $activeToken->isExpired()) {
        $qrLabel = 'QR Kadaluwarsa';
    } elseif ($activeToken) {
        $qrLabel = 'QR Aktif';
    } elseif ($latestToken && $latestToken->isRevoked()) {
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
        <div class="table-actions">
            @if (! $activeToken && $permit->status === \App\Models\VehiclePermit::STATUS_ACTIVE)
                <form method="POST" action="{{ route('permits.qr.generate', $permit) }}">
                    @csrf
                    <button class="button" type="submit">Generate QR</button>
                </form>
            @endif

            @if ($activeToken)
                <a class="button" href="{{ route('permits.qr.show', $permit) }}">Lihat QR</a>
                <form method="POST" action="{{ route('permits.qr.print', $permit) }}">
                    @csrf
                    <button class="button" type="submit">Renew & Print</button>
                </form>
                <form method="POST" action="{{ route('permits.qr.renew', $permit) }}">
                    @csrf
                    <button class="button" type="submit">Renew</button>
                </form>
            @endif
        </div>
    </td>
</tr>
```

Update empty state colspan from `7` to `9`.

- [ ] **Step 5: Add CSS for actions and print card**

Append to `resources/css/app.css`:

```css
.table-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.muted-text {
    margin-top: 4px;
    color: var(--sirika-muted);
    font-size: 12px;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.detail-grid dt {
    color: var(--sirika-muted);
    font-size: 13px;
    font-weight: 700;
}

.detail-grid dd {
    margin: 4px 0 0;
    font-weight: 700;
}

.permit-card-print {
    max-width: 760px;
}

.permit-card {
    width: 520px;
    min-height: 320px;
    display: grid;
    grid-template-columns: 1fr 180px;
    gap: 18px;
    padding: 20px;
    border: 1px solid var(--sirika-border);
    border-radius: 8px;
    background: #ffffff;
    color: var(--sirika-text);
}

.permit-card__brand {
    margin: 0 0 16px;
    font-size: 20px;
    font-weight: 700;
}

.permit-card__label {
    margin: 10px 0 0;
    color: var(--sirika-muted);
    font-size: 12px;
    font-weight: 700;
}

.permit-card__value {
    margin: 2px 0 0;
    font-size: 16px;
    font-weight: 700;
}

.permit-card__qr {
    min-height: 180px;
    display: grid;
    place-items: center;
    border: 1px dashed var(--sirika-border);
    border-radius: 8px;
    text-align: center;
    color: var(--sirika-muted);
    font-size: 13px;
}

@media print {
    .sidebar,
    .topbar,
    .page-title,
    .page-description,
    .no-print {
        display: none !important;
    }

    .content {
        padding: 0;
    }

    .panel,
    .permit-card {
        box-shadow: none;
    }
}
```

- [ ] **Step 6: Run tests to verify GREEN**

Run:

```bash
php artisan test --filter=PermitListAfterImportTest
```

Expected: PASS.

- [ ] **Step 7: Commit task**

Run:

```bash
git add app/Http/Controllers/PermitController.php resources/views/permits/index.blade.php resources/views/permits/qr/show.blade.php resources/views/permits/qr/print.blade.php resources/css/app.css tests/Feature/PermitListAfterImportTest.php
git commit -m "feat: show permit qr actions"
```

Expected: commit includes permit list/controller/view/css and related tests.

---

### Task 6: Security Scan HTTP Endpoint

**Files:**
- Create: `app/Http/Requests/VerifyScanRequest.php`
- Modify: `app/Http/Controllers/ScanController.php`
- Modify: `routes/web.php`
- Modify: `app/Models/User.php`
- Create: `tests/Feature/ScanQrHttpTest.php`

**Interfaces:**
- Consumes `PermitScanService`.
- Produces route `scan.verify`.
- Produces JSON response with `result`, `message`, and `permit`.

- [ ] **Step 1: Write failing scan HTTP tests**

Create `tests/Feature/ScanQrHttpTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ScanLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanQrHttpTest extends TestCase
{
    use RefreshDatabase;

    private function security()
    {
        return User::factory()->create([
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function permit()
    {
        $employee = Employee::create([
            'nik' => 'EMP-SCAN',
            'name' => 'SCAN HTTP USER',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 9001 SC',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'merah',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'manual',
        ]);
    }

    /** @test */
    public function security_can_verify_valid_token_via_http_and_scan_is_logged()
    {
        $permit = $this->permit();
        $token = app(PermitTokenService::class)->generateForPermit($permit);

        $this->actingAs($this->security())
            ->postJson(route('scan.verify'), [
                'token' => $token['plain_token'],
                'device_info' => 'Browser test',
            ])
            ->assertOk()
            ->assertJsonPath('result', ScanLog::RESULT_VALID)
            ->assertJsonPath('permit.employee_name', 'SCAN HTTP USER')
            ->assertJsonMissingPath('permit.contact_number');

        $this->assertDatabaseHas('scan_logs', [
            'permit_id' => $permit->id,
            'result' => ScanLog::RESULT_VALID,
            'device_info' => 'Browser test',
        ]);
    }

    /** @test */
    public function security_can_verify_invalid_token_and_it_is_logged()
    {
        $this->actingAs($this->security())
            ->postJson(route('scan.verify'), [
                'token' => 'not-a-valid-token-for-sirika',
            ])
            ->assertOk()
            ->assertJsonPath('result', ScanLog::RESULT_INVALID)
            ->assertJsonPath('permit', null);

        $this->assertDatabaseHas('scan_logs', [
            'permit_id' => null,
            'result' => ScanLog::RESULT_INVALID,
        ]);
    }

    /** @test */
    public function auditor_cannot_verify_scan_token()
    {
        $auditor = User::factory()->create([
            'role' => User::ROLE_AUDITOR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($auditor)
            ->postJson(route('scan.verify'), ['token' => 'anything'])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run tests to verify RED**

Run:

```bash
php artisan test --filter=ScanQrHttpTest
```

Expected: FAIL because route `scan.verify` is not defined.

- [ ] **Step 3: Create scan request**

Create `app/Http/Requests/VerifyScanRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyScanRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'token' => ['required', 'string', 'min:1', 'max:255'],
            'device_info' => ['nullable', 'string', 'max:500'],
        ];
    }
}
```

- [ ] **Step 4: Implement controller and route**

Modify `app/Http/Controllers/ScanController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\VerifyScanRequest;
use App\Services\Permits\PermitScanService;

class ScanController extends Controller
{
    public function index()
    {
        return view('scan.index');
    }

    public function verify(VerifyScanRequest $request, PermitScanService $scanner)
    {
        $result = $scanner->scan($request->input('token'), $request->user(), [
            'device_info' => $request->input('device_info'),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'result' => $result['result'],
            'message' => $result['message'],
            'permit' => $result['permit'],
        ]);
    }
}
```

Add to `routes/web.php` near scan route:

```php
    Route::post('/scan/verify', [ScanController::class, 'verify'])
        ->middleware([
            'role:' . implode(',', User::rolesForRoute('scan.verify')),
            'throttle:60,1',
        ])
        ->name('scan.verify');
```

If not already added in Task 4, add `scan.verify` role mapping to `User::routeRoles()`:

```php
            'scan.verify' => [
                self::ROLE_ADMIN_HR,
                self::ROLE_SECURITY,
            ],
```

- [ ] **Step 5: Run tests to verify GREEN**

Run:

```bash
php artisan test --filter=ScanQrHttpTest
```

Expected: PASS.

- [ ] **Step 6: Commit task**

Run:

```bash
git add app/Http/Requests/VerifyScanRequest.php app/Http/Controllers/ScanController.php app/Models/User.php routes/web.php tests/Feature/ScanQrHttpTest.php
git commit -m "feat: add qr scan verification endpoint"
```

Expected: commit includes scan request, controller, route, role mapping, and HTTP tests.

---

### Task 7: Scanner UI and Frontend Bundle

**Files:**
- Modify: `resources/views/scan/index.blade.php`
- Modify: `resources/js/app.js`
- Modify: `resources/css/app.css`
- Modify: `tests/Feature/SirikaModuleAccessTest.php`

**Interfaces:**
- Consumes route `scan.verify`.
- Produces Alpine component `sirikaScan`.
- Produces camera scan and manual token fallback UI.

- [ ] **Step 1: Update failing module access test assertions**

Modify `tests/Feature/SirikaModuleAccessTest.php` scan assertions:

```php
        $this->actingAs($admin)->get('/scan')
            ->assertOk()
            ->assertSee('Scan QR')
            ->assertSee('Mulai Kamera')
            ->assertSee('Input Token Manual');
```

And:

```php
        $this->actingAs($security)->get('/scan')
            ->assertOk()
            ->assertSee('Scan QR')
            ->assertSee('Mulai Kamera')
            ->assertSee('Input Token Manual');
```

- [ ] **Step 2: Run test to verify RED**

Run:

```bash
php artisan test --filter=SirikaModuleAccessTest
```

Expected: FAIL because scan page still contains the old temporary module copy.

- [ ] **Step 3: Replace scan view**

Replace `resources/views/scan/index.blade.php` with:

```blade
@extends('layouts.app')

@php
    $pageTitle = 'Scan QR';
    $pageDescription = 'Validasi izin kendaraan melalui kamera atau input token manual.';
@endphp

@section('content')
    <section
        class="page-section panel"
        x-data="sirikaScan({
            verifyUrl: '{{ route('scan.verify') }}',
            csrfToken: '{{ csrf_token() }}'
        })"
    >
        <div class="panel-body">
            <div class="scan-layout">
                <div>
                    <h2 class="panel-title">Scanner Kamera</h2>
                    <p class="panel-subtitle">Gunakan kamera perangkat untuk membaca QR izin kendaraan.</p>

                    <div id="sirika-qr-reader" class="qr-reader layout-gap"></div>

                    <div class="quick-actions layout-gap">
                        <button class="button button-primary" type="button" x-on:click="startCamera" x-bind:disabled="cameraRunning">
                            Mulai Kamera
                        </button>
                        <button class="button" type="button" x-on:click="stopCamera" x-bind:disabled="! cameraRunning">
                            Stop Kamera
                        </button>
                    </div>
                </div>

                <div>
                    <h2 class="panel-title">Input Token Manual</h2>
                    <p class="panel-subtitle">Gunakan fallback ini jika kamera gagal membaca QR.</p>

                    <form class="form-stack layout-gap" x-on:submit.prevent="submitManual">
                        <div class="form-field">
                            <label for="manual-token">Token QR</label>
                            <input id="manual-token" type="text" x-model="manualToken" autocomplete="off">
                        </div>
                        <button class="button button-primary" type="submit" x-bind:disabled="loading">
                            Validasi Token
                        </button>
                    </form>

                    <div class="scan-result layout-gap" x-bind:class="'scan-result--' + (result ? result.result : 'empty')">
                        <template x-if="loading">
                            <p>Memvalidasi QR...</p>
                        </template>

                        <template x-if="! loading && ! result">
                            <p>Hasil scan akan tampil di sini.</p>
                        </template>

                        <template x-if="! loading && result">
                            <div>
                                <h3 x-text="result.message"></h3>
                                <template x-if="result.permit">
                                    <dl class="scan-result__details">
                                        <div x-show="result.permit.employee_name">
                                            <dt>Nama</dt>
                                            <dd x-text="result.permit.employee_name"></dd>
                                        </div>
                                        <div x-show="result.permit.plate_number">
                                            <dt>Plat</dt>
                                            <dd x-text="result.permit.plate_number"></dd>
                                        </div>
                                        <div x-show="result.permit.parking_code">
                                            <dt>Parkir</dt>
                                            <dd x-text="result.permit.parking_code"></dd>
                                        </div>
                                        <div x-show="result.permit.permit_color">
                                            <dt>Warna</dt>
                                            <dd x-text="result.permit.permit_color"></dd>
                                        </div>
                                        <div x-show="result.permit.route_raw">
                                            <dt>Rute</dt>
                                            <dd x-text="result.permit.route_raw"></dd>
                                        </div>
                                    </dl>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
```

- [ ] **Step 4: Add scanner JavaScript**

Modify `resources/js/app.js`:

```js
require('./bootstrap');

import Alpine from 'alpinejs';
import { Html5Qrcode } from 'html5-qrcode';

window.sirikaScan = function ({ verifyUrl, csrfToken }) {
    return {
        qrReader: null,
        cameraRunning: false,
        loading: false,
        manualToken: '',
        result: null,

        async startCamera() {
            if (this.cameraRunning) {
                return;
            }

            this.qrReader = this.qrReader || new Html5Qrcode('sirika-qr-reader');
            const cameras = await Html5Qrcode.getCameras();

            if (!cameras.length) {
                this.result = { result: 'invalid', message: 'Kamera tidak ditemukan.', permit: null };
                return;
            }

            await this.qrReader.start(
                cameras[0].id,
                { fps: 10, qrbox: { width: 240, height: 240 } },
                (decodedText) => this.submitToken(decodedText),
                () => {}
            );

            this.cameraRunning = true;
        },

        async stopCamera() {
            if (!this.qrReader || !this.cameraRunning) {
                return;
            }

            await this.qrReader.stop();
            this.cameraRunning = false;
        },

        submitManual() {
            this.submitToken(this.manualToken);
        },

        async submitToken(token) {
            if (!token || this.loading) {
                return;
            }

            this.loading = true;

            try {
                const response = await fetch(verifyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        token,
                        device_info: window.navigator.userAgent,
                    }),
                });

                this.result = await response.json();
            } catch (error) {
                this.result = {
                    result: 'invalid',
                    message: 'Scan gagal diproses.',
                    permit: null,
                };
            } finally {
                this.loading = false;
            }
        },
    };
};

window.Alpine = Alpine;
Alpine.start();
```

- [ ] **Step 5: Add scanner CSS**

Append to `resources/css/app.css`:

```css
.scan-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(320px, 420px);
    gap: 18px;
}

.qr-reader {
    min-height: 320px;
    border: 1px solid var(--sirika-border);
    border-radius: 8px;
    background: #f8fafc;
    overflow: hidden;
}

.scan-result {
    min-height: 160px;
    padding: 16px;
    border: 1px solid var(--sirika-border);
    border-radius: 8px;
    background: #f8fafc;
}

.scan-result--valid {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: var(--sirika-success);
}

.scan-result--expired {
    background: #fffbeb;
    border-color: #fcd34d;
    color: var(--sirika-warning);
}

.scan-result--revoked,
.scan-result--inactive,
.scan-result--invalid {
    background: #fef2f2;
    border-color: #fecaca;
    color: var(--sirika-danger);
}

.scan-result h3 {
    margin: 0;
}

.scan-result__details {
    display: grid;
    gap: 10px;
    margin: 14px 0 0;
}

.scan-result__details dt {
    font-size: 12px;
    font-weight: 700;
}

.scan-result__details dd {
    margin: 2px 0 0;
    color: var(--sirika-text);
    font-weight: 700;
}

@media (max-width: 960px) {
    .scan-layout {
        grid-template-columns: 1fr;
    }
}
```

- [ ] **Step 6: Run tests and build**

Run:

```bash
php artisan test --filter=SirikaModuleAccessTest
npm run dev
```

Expected: test PASS and Laravel Mix compiles `js/app.js` and `css/app.css`.

- [ ] **Step 7: Commit task**

Run:

```bash
git add resources/views/scan/index.blade.php resources/js/app.js resources/css/app.css public/js/app.js public/css/app.css public/mix-manifest.json tests/Feature/SirikaModuleAccessTest.php
git commit -m "feat: add qr scanner ui"
```

Expected: commit includes scanner UI, JS bundle source, compiled assets, and updated module access test.

---

### Task 8: Final Verification

**Files:**
- No new production files.
- Update docs only if verification discovers a mismatch with the approved spec.

**Interfaces:**
- Verifies all previous tasks together.

- [ ] **Step 1: Run full PHP test suite**

Run:

```bash
php artisan test
```

Expected: all tests pass. Expected count is current baseline plus new Phase 3 tests.

- [ ] **Step 2: Run frontend build**

Run:

```bash
npm run dev
```

Expected: Laravel Mix compiles successfully.

- [ ] **Step 3: Verify route list**

Run:

```bash
php artisan route:list
```

Expected output includes:

```text
permits.qr.generate
permits.qr.bulk-generate
permits.qr.show
permits.qr.print
permits.qr.renew
scan.verify
```

- [ ] **Step 4: Run dependency audit commands**

Run:

```bash
npm audit --omit=dev
composer audit
```

Expected: `npm audit --omit=dev` should have no production vulnerabilities. `composer audit` may still report Laravel 8 baseline advisories; if present, document them in final output and do not claim dependency audit clean.

- [ ] **Step 5: Manual browser smoke**

Use the local server already running or start a separate server on an unused port:

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

Manual checks:

- Login as admin HR.
- Open `/permits`.
- Generate QR for one active permit.
- Confirm QR status changes.
- Open QR detail page.
- Open print card page.
- Open `/scan` as security.
- Submit invalid token manually and confirm invalid result and new `scan_logs` row.
- Submit a known generated raw token only if available from the immediate generate/renew response during manual testing.

- [ ] **Step 6: Commit verification note only if files changed**

If no files changed, do not create a commit.

If generated assets changed during final build, commit them:

```bash
git add public/js/app.js public/css/app.css public/mix-manifest.json
git commit -m "build: compile sirika phase 3 assets"
```

Expected: final working tree is clean.
