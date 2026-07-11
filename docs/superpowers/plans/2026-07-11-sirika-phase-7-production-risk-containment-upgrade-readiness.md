# SIRIKA Phase 7 Production Risk Containment and Upgrade Readiness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengurangi exposure keamanan yang dapat dijangkau pada PHP 7.4/Laravel 8, mendokumentasikan residual dependency risk, dan menyiapkan upgrade Laravel 12 tanpa mengubah runtime project cPanel lain.

**Architecture:** Pertahankan framework dan lock file saat ini, lalu tambahkan kontrol kompensasi pada boundary aplikasi: hapus API scaffold yang tidak dipakai, gunakan validation rule reusable untuk email, dan validasi upload Excel pada HTTP serta service boundary. QR tetap memakai random token yang disimpan sebagai SHA-256 hash; dokumentasi exposure dan regression tests membuktikan tidak ada ketergantungan pada Laravel signed URL.

**Tech Stack:** PHP 7.4.33, Laravel 8.83.29, PHPUnit, Laravel Excel 3.1, PhpSpreadsheet, MySQL/PostgreSQL, shared hosting cPanel.

## Global Constraints

- Jangan upgrade PHP, Laravel, atau dependency mayor pada Phase 7.
- Jangan mengubah versi PHP global cPanel atau project lain.
- Target upgrade berikutnya adalah PHP 8.2 dan Laravel 12 pada runtime domain SIRIKA yang terisolasi.
- `composer audit` tetap diperkirakan non-zero dan wajib dicatat sebagai residual risk.
- Jangan menghapus migration atau tabel `personal_access_tokens`.
- Jangan mengubah masa aktif QR satu tahun atau behavior scan QR kedaluwarsa.
- Jangan mengubah workflow import, review, aktivasi, route map, report, export, atau role selain kontrol keamanan yang dinyatakan di plan ini.
- Semua kode baru harus kompatibel dengan PHP 7.4; jangan memakai constructor property promotion, union types, enums, atau syntax PHP 8.
- Setiap task menggunakan TDD dan menghasilkan commit terpisah.

## File Map

- `routes/api.php`: kosongkan API scaffold yang tidak dipakai.
- `app/Http/Controllers/Api/AuthenticatedUserController.php`: hapus controller scaffold setelah route dihapus.
- `app/Models/User.php`: hapus trait `HasApiTokens`, tetapi pertahankan package dan tabel Sanctum untuk rollback compatibility.
- `app/Rules/NoControlCharacters.php`: validation rule reusable untuk menolak CR/LF dan karakter kontrol.
- `app/Http/Requests/StoreUserRequest.php`: pakai rule karakter kontrol pada email create.
- `app/Http/Requests/UpdateUserRequest.php`: pakai rule karakter kontrol pada email update.
- `app/Services/Imports/PermitImportFileValidator.php`: validasi file pada service boundary.
- `app/Services/Imports/PermitExcelImportService.php`: panggil validator, batasi row count, sanitasi parse/storage error.
- `config/sirika.php`: konfigurasi batas import dan MIME yang eksplisit.
- `tests/Feature/SecuritySurfaceContainmentTest.php`: regression test API, Sanctum surface, CORS, dan signed URL inventory.
- `tests/Feature/UserManagementHttpTest.php`: regression test email control characters.
- `tests/Feature/ImportExcelPreviewHttpTest.php`: validation test upload boundary.
- `tests/Feature/ImportExcelPreviewTest.php`: parser, row limit, transaction, dan safe error tests.
- `tests/Feature/PermitQrSecurityTest.php`: QR token hashing dan response minimization tests.
- `docs/security/SECURITY-EXPOSURE-INVENTORY.md`: bukti exposure kode aktual dan kontrol Phase 7.
- `docs/security/DEPENDENCY-RISK-REGISTER.md`: residual risk dan field acceptance owner.
- `docs/upgrade/LARAVEL-12-READINESS.md`: checklist upgrade terisolasi.
- `docs/deployment/CPANEL-PRODUCTION.md`: release gate Phase 7.
- `tests/Feature/PhaseSevenDocumentationTest.php`: memastikan dokumen release-critical tidak hilang atau melemah.

---

### Task 1: Remove Unused API and Sanctum Application Surface

**Files:**
- Modify: `routes/api.php`
- Delete: `app/Http/Controllers/Api/AuthenticatedUserController.php`
- Modify: `app/Models/User.php`
- Create: `tests/Feature/SecuritySurfaceContainmentTest.php`

**Interfaces:**
- Consumes: Laravel `RouteServiceProvider` tetap memuat `routes/api.php` dengan prefix `/api`.
- Produces: `/api/user` mengembalikan 404; `User` tidak lagi mengekspos personal access token API melalui trait.

- [ ] **Step 1: Write failing containment tests**

Create `tests/Feature/SecuritySurfaceContainmentTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\HasApiTokens;
use Tests\TestCase;

class SecuritySurfaceContainmentTest extends TestCase
{
    /** @test */
    public function unused_authenticated_user_api_is_not_exposed()
    {
        $this->getJson('/api/user')->assertNotFound();
    }

    /** @test */
    public function user_model_does_not_expose_unused_sanctum_token_api()
    {
        $this->assertNotContains(HasApiTokens::class, class_uses_recursive(User::class));
    }

    /** @test */
    public function cors_remains_closed_when_no_cross_origin_path_is_configured()
    {
        $this->assertSame([], config('cors.paths'));
        $this->assertFalse(config('cors.supports_credentials'));
    }
}
```

- [ ] **Step 2: Run tests and verify the scaffold is still exposed**

Run:

```bash
php artisan test --filter=SecuritySurfaceContainmentTest
```

Expected: FAIL because `/api/user` is registered and `User` uses `HasApiTokens`.

- [ ] **Step 3: Remove only the unused application surface**

Replace `routes/api.php` with:

```php
<?php

use Illuminate\Support\Facades\Route;

/*
| SIRIKA is a session-based web application. No public or token-authenticated
| API routes are currently exposed.
*/
```

Delete `app/Http/Controllers/Api/AuthenticatedUserController.php`.

In `app/Models/User.php`, remove:

```php
use Laravel\Sanctum\HasApiTokens;
```

and change the trait declaration to:

```php
use HasFactory, Notifiable;
```

Do not remove `laravel/sanctum` from `composer.json`, `composer.lock`, `config/sanctum.php`, or the token migration in this phase. Package removal belongs to the Laravel 12 upgrade because dependency churn cannot remediate the current framework advisory on PHP 7.4.

- [ ] **Step 4: Verify containment and route cache**

Run:

```bash
php artisan test --filter=SecuritySurfaceContainmentTest
php artisan route:cache
php artisan route:clear
```

Expected: tests PASS; route cache commands exit 0.

- [ ] **Step 5: Commit**

```bash
git add routes/api.php app/Models/User.php app/Http/Controllers/Api/AuthenticatedUserController.php tests/Feature/SecuritySurfaceContainmentTest.php
git commit -m "security: remove unused api token surface"
```

---

### Task 2: Reject Control Characters in Managed User Emails

**Files:**
- Create: `app/Rules/NoControlCharacters.php`
- Modify: `app/Http/Requests/StoreUserRequest.php`
- Modify: `app/Http/Requests/UpdateUserRequest.php`
- Create: `tests/Unit/NoControlCharactersTest.php`
- Modify: `tests/Feature/UserManagementHttpTest.php`

**Interfaces:**
- Produces: `NoControlCharacters` implements Laravel 8 `Illuminate\Contracts\Validation\Rule`; `passes(string $attribute, mixed $value): bool` is expressed without PHP 8 type syntax.
- Consumes: Store/update requests insert the rule before Laravel's `email` rule.

- [ ] **Step 1: Add a failing unit test for the missing rule**

Create `tests/Unit/NoControlCharactersTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Rules\NoControlCharacters;
use PHPUnit\Framework\TestCase;

class NoControlCharactersTest extends TestCase
{
    /** @test */
    public function it_rejects_ascii_control_characters_without_rewriting_the_value()
    {
        $rule = new NoControlCharacters();

        $this->assertTrue($rule->passes('email', 'valid@example.com'));
        $this->assertFalse($rule->passes('email', "unsafe@example.com\r\nBcc:test@example.com"));
        $this->assertFalse($rule->passes('email', "unsafe@example.com\x00"));
    }
}
```

- [ ] **Step 2: Run the unit test and verify the rule is absent**

Run:

```bash
php artisan test tests/Unit/NoControlCharactersTest.php
```

Expected: FAIL because `App\Rules\NoControlCharacters` does not exist.

- [ ] **Step 3: Add HTTP regression tests**

Append to `tests/Feature/UserManagementHttpTest.php`:

```php
/** @test */
public function user_email_rejects_control_characters_on_create_and_update()
{
    $admin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'status' => User::STATUS_ACTIVE,
    ]);

    $payload = [
        'name' => 'Unsafe Email',
        'email' => "unsafe@example.com\r\nBcc:test@example.com",
        'role' => User::ROLE_AUDITOR,
        'status' => User::STATUS_ACTIVE,
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    $this->actingAs($admin)
        ->from('/users/create')
        ->post('/users', $payload)
        ->assertRedirect('/users/create')
        ->assertSessionHasErrors('email');

    $managedUser = User::factory()->create();

    $this->actingAs($admin)
        ->from('/users/' . $managedUser->id . '/edit')
        ->put('/users/' . $managedUser->id, [
            'name' => $managedUser->name,
            'email' => "unsafe@example.com\nInjected: value",
            'role' => User::ROLE_AUDITOR,
            'status' => User::STATUS_ACTIVE,
        ])
        ->assertRedirect('/users/' . $managedUser->id . '/edit')
        ->assertSessionHasErrors('email');

    $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
    $this->assertNotSame("unsafe@example.com\nInjected: value", $managedUser->fresh()->email);
}
```

- [ ] **Step 4: Run the focused HTTP test**

Run:

```bash
php artisan test --filter=user_email_rejects_control_characters
```

Expected: the request is rejected. This test locks HTTP behavior; the unit test above is the required red test proving the new rule is not implemented yet.

- [ ] **Step 5: Add the reusable Laravel 8 rule**

Create `app/Rules/NoControlCharacters.php`:

```php
<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class NoControlCharacters implements Rule
{
    public function passes($attribute, $value)
    {
        return is_string($value) && preg_match('/[\x00-\x1F\x7F]/', $value) !== 1;
    }

    public function message()
    {
        return 'Kolom :attribute mengandung karakter yang tidak diizinkan.';
    }
}
```

Add this import to both request files:

```php
use App\Rules\NoControlCharacters;
```

Use this email rule sequence in `StoreUserRequest`:

```php
'email' => ['required', new NoControlCharacters(), 'email', 'max:255', 'unique:users,email'],
```

Use this sequence in `UpdateUserRequest`:

```php
'email' => [
    'required',
    new NoControlCharacters(),
    'email',
    'max:255',
    Rule::unique('users', 'email')->ignore($user ? $user->id : null),
],
```

- [ ] **Step 6: Verify the rule and user management behavior**

Run:

```bash
php artisan test tests/Feature/UserManagementHttpTest.php
php artisan test tests/Unit/NoControlCharactersTest.php
```

Expected: all tests PASS, including existing valid create/update flow.

- [ ] **Step 7: Commit**

```bash
git add app/Rules/NoControlCharacters.php app/Http/Requests/StoreUserRequest.php app/Http/Requests/UpdateUserRequest.php tests/Unit/NoControlCharactersTest.php tests/Feature/UserManagementHttpTest.php
git commit -m "security: reject control characters in user emails"
```

---

### Task 3: Add Defense-in-Depth Excel File Validation

**Files:**
- Modify: `config/sirika.php`
- Create: `app/Services/Imports/PermitImportFileValidator.php`
- Modify: `app/Services/Imports/PermitExcelImportService.php`
- Modify: `app/Http/Requests/StoreImportRequest.php`
- Modify: `tests/Feature/ImportExcelPreviewHttpTest.php`
- Modify: `tests/Feature/ImportExcelPreviewTest.php`

**Interfaces:**
- Produces: `PermitImportFileValidator::validate(UploadedFile $file): void`; throws `InvalidArgumentException` with safe user-facing messages.
- Consumes: `PermitExcelImportService` invokes the validator before creating an `ImportBatch`.
- Produces: config keys `sirika.import.max_file_kilobytes`, `max_rows`, `extensions`, and `mime_types`.

- [ ] **Step 1: Add failing boundary tests**

Add to `ImportExcelPreviewHttpTest`:

```php
/** @test */
public function oversized_excel_upload_is_rejected_before_preview()
{
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN_HR,
        'status' => User::STATUS_ACTIVE,
    ]);

    $this->actingAs($admin)->from(route('imports.index'))->post(route('imports.store'), [
        'file' => UploadedFile::fake()->create(
            'oversized.xlsx',
            10241,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ),
    ])->assertRedirect(route('imports.index'))
        ->assertSessionHasErrors('file');

    $this->assertSame(0, ImportBatch::count());
}
```

Add to `ImportExcelPreviewTest`:

```php
/** @test */
public function service_rejects_a_disguised_non_excel_file_before_creating_a_batch()
{
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN_HR,
        'status' => User::STATUS_ACTIVE,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'sirika-invalid-');
    file_put_contents($path, 'not an excel workbook');
    $file = new UploadedFile($path, 'disguised.xlsx', 'text/plain', null, true);

    try {
        app(PermitExcelImportService::class)->preview($file, $admin);
        $this->fail('Expected invalid file validation exception.');
    } catch (\InvalidArgumentException $exception) {
        $this->assertStringContainsString('tipe file', $exception->getMessage());
    }

    $this->assertSame(0, ImportBatch::count());
}

/** @test */
public function preview_rejects_workbooks_over_the_configured_row_limit_without_partial_rows()
{
    config(['sirika.import.max_rows' => 2]);
    $admin = User::factory()->create([
        'role' => User::ROLE_ADMIN_HR,
        'status' => User::STATUS_ACTIVE,
    ]);

    $file = $this->excelFile([
        ['NIK', 'Nama', 'Plat Motor', 'Lokasi Parkir', 'Rute Kendaraan'],
        ['1', 'A', 'DT 1 AA', 'P1', 'Y1'],
        ['2', 'B', 'DT 2 AA', 'P1', 'Y1'],
        ['3', 'C', 'DT 3 AA', 'P1', 'Y1'],
    ]);

    $batch = app(PermitExcelImportService::class)->preview($file, $admin);

    $this->assertSame(ImportBatch::STATUS_FAILED, $batch->status);
    $this->assertStringContainsString('maksimal 2 baris', $batch->error_summary);
    $this->assertSame(0, $batch->rows()->count());
}
```

- [ ] **Step 2: Run focused tests and confirm failure**

Run:

```bash
php artisan test tests/Feature/ImportExcelPreviewHttpTest.php tests/Feature/ImportExcelPreviewTest.php
```

Expected: the new service-boundary and row-limit tests FAIL.

- [ ] **Step 3: Add import security configuration**

Append inside the returned array in `config/sirika.php`:

```php
'import' => [
    'max_file_kilobytes' => 10240,
    'max_rows' => 5000,
    'extensions' => ['xlsx', 'xls'],
    'mime_types' => [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/zip',
        'application/x-ole-storage',
        'application/octet-stream',
    ],
],
```

`application/octet-stream` remains allowed only as a MIME signal for shared-hosting variability; extension checks and successful PhpSpreadsheet parsing remain mandatory additional controls.

- [ ] **Step 4: Implement service-boundary validation**

Create `app/Services/Imports/PermitImportFileValidator.php`:

```php
<?php

namespace App\Services\Imports;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class PermitImportFileValidator
{
    public function validate(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('Upload file Excel tidak valid.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, config('sirika.import.extensions', []), true)) {
            throw new InvalidArgumentException('Ekstensi file Excel tidak didukung.');
        }

        $kilobytes = (int) ceil(((int) $file->getSize()) / 1024);
        if ($kilobytes > (int) config('sirika.import.max_file_kilobytes', 10240)) {
            throw new InvalidArgumentException('Ukuran file Excel maksimal 10 MB.');
        }

        $mimeType = (string) $file->getMimeType();
        if (! in_array($mimeType, config('sirika.import.mime_types', []), true)) {
            throw new InvalidArgumentException('Tipe file Excel tidak didukung.');
        }
    }
}
```

Inject `PermitImportFileValidator` into `PermitExcelImportService`:

```php
private $fileValidator;

public function __construct(
    PermitImportHeaderMapper $headerMapper,
    PermitImportRowNormalizer $normalizer,
    PermitImportFileValidator $fileValidator
) {
    $this->headerMapper = $headerMapper;
    $this->normalizer = $normalizer;
    $this->fileValidator = $fileValidator;
}
```

Call it after authorization and before `ImportBatch::create()`:

```php
$this->authorizePreview($user);
$this->fileValidator->validate($file);
```

Any existing test anonymous subclass must pass `app(PermitImportFileValidator::class)` as the third constructor argument.

- [ ] **Step 5: Use the same upload limit at the HTTP boundary**

In `StoreImportRequest::rules()` use:

```php
$maxKilobytes = (int) config('sirika.import.max_file_kilobytes', 10240);

return [
    'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:' . $maxKilobytes],
];
```

Keep the existing Indonesian validation messages.

- [ ] **Step 6: Enforce row limit before the row transaction**

After extracting `$rows` and checking an empty sheet in `PermitExcelImportService::preview()`, add:

```php
$maxRows = (int) config('sirika.import.max_rows', 5000);
if (count($rows) > $maxRows) {
    throw new \InvalidArgumentException(
        'Sheet Excel maksimal ' . $maxRows . ' baris termasuk header.'
    );
}
```

Keep `DB::transaction()` around all `ImportRow` writes. A parse, header, or row-limit failure may leave one `ImportBatch` in `failed` state for operator visibility, but must leave zero `ImportRow` records.

- [ ] **Step 7: Verify focused import suite**

Run:

```bash
php artisan test tests/Feature/ImportExcelPreviewHttpTest.php tests/Feature/ImportExcelPreviewTest.php tests/Feature/ImportCommitTest.php
```

Expected: all tests PASS; invalid files create no batch when rejected before parsing; parse/header failures create a failed batch with no rows.

- [ ] **Step 8: Commit**

```bash
git add config/sirika.php app/Services/Imports/PermitImportFileValidator.php app/Services/Imports/PermitExcelImportService.php app/Http/Requests/StoreImportRequest.php tests/Feature/ImportExcelPreviewHttpTest.php tests/Feature/ImportExcelPreviewTest.php
git commit -m "security: harden excel import boundaries"
```

---

### Task 4: Sanitize Import Parser Failures

**Files:**
- Modify: `app/Services/Imports/PermitExcelImportService.php`
- Modify: `tests/Feature/ImportExcelPreviewTest.php`

**Interfaces:**
- Produces: expected validation failures retain safe Indonesian messages; unexpected parser/storage exceptions return a generic summary and are logged without file content or server path.

- [ ] **Step 1: Replace the storage failure assertion with a safe-error test**

In `it_marks_batch_failed_when_file_storage_fails`, pass the validator dependency to the anonymous service constructor and replace the final assertion with:

```php
$this->assertSame('File Excel gagal diproses. Periksa format file lalu coba kembali.', $batch->error_summary);
```

Add `use Illuminate\Support\Facades\Log;` and call `Log::spy()` before invoking the service. Then assert:

```php
Log::shouldHaveReceived('warning')->once()->withArgs(function ($message, $context) use ($batch) {
    return $message === 'Permit Excel import failed.'
        && $context['import_batch_id'] === $batch->id
        && $context['exception'] === RuntimeException::class
        && ! array_key_exists('path', $context)
        && ! array_key_exists('contents', $context);
});
```

- [ ] **Step 2: Run the failure test**

Run:

```bash
php artisan test --filter=it_marks_batch_failed_when_file_storage_fails
```

Expected: FAIL because raw exception text is currently stored.

- [ ] **Step 3: Add safe exception mapping and logging**

Add imports:

```php
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
```

Replace the catch body with:

```php
} catch (Throwable $exception) {
    Log::warning('Permit Excel import failed.', [
        'import_batch_id' => $batch->id,
        'exception' => get_class($exception),
    ]);

    $batch->update([
        'status' => ImportBatch::STATUS_FAILED,
        'error_summary' => $this->safeErrorSummary($exception),
    ]);
}
```

Add:

```php
private function safeErrorSummary(Throwable $exception): string
{
    if ($exception instanceof InvalidArgumentException) {
        return $exception->getMessage();
    }

    return 'File Excel gagal diproses. Periksa format file lalu coba kembali.';
}
```

- [ ] **Step 4: Verify parser and storage failures**

Run:

```bash
php artisan test tests/Feature/ImportExcelPreviewTest.php
```

Expected: all tests PASS; missing header remains actionable; unexpected storage error is generic.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Imports/PermitExcelImportService.php tests/Feature/ImportExcelPreviewTest.php
git commit -m "security: sanitize import processing failures"
```

---

### Task 5: Lock QR Security Behavior with Regression Tests

**Files:**
- Create: `tests/Feature/PermitQrSecurityTest.php`

**Interfaces:**
- Consumes: `PermitTokenService::generateForPermit(VehiclePermit): array` and `PermitScanService::scan(string, ?User, array): array`.
- Produces: tests proving random plaintext QR values are never persisted, signed URLs are not part of the QR flow, and invalid scans reveal no permit data.

- [ ] **Step 1: Add QR security tests using existing model factories/helpers**

Create `tests/Feature/PermitQrSecurityTest.php` by reusing the active-permit setup pattern from `tests/Feature/PermitQrServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Permits\PermitScanService;
use App\Services\Permits\PermitTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitQrSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function generated_qr_plaintext_is_only_persisted_as_a_sha256_hash()
    {
        $permit = $this->activePermit();
        $result = app(PermitTokenService::class)->generateForPermit($permit);
        $token = $result['permit_token']->fresh();

        $this->assertSame(64, strlen($result['plain_token']));
        $this->assertSame(hash('sha256', $result['plain_token']), $token->token_hash);
        $this->assertNotSame($result['plain_token'], $token->token_hash);
        $this->assertStringNotContainsString('/scan', $result['plain_token']);
        $this->assertStringNotContainsString('signature=', $result['plain_token']);
    }

    /** @test */
    public function invalid_scan_does_not_reveal_permit_data()
    {
        $scanner = User::factory()->create([
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);

        $result = app(PermitScanService::class)->scan(str_repeat('x', 64), $scanner);

        $this->assertSame('invalid', $result['result']);
        $this->assertNull($result['permit']);
        $this->assertSame('QR tidak dikenal.', $result['message']);
    }

    private function activePermit()
    {
        $employee = Employee::create(['nik' => 'SEC-001', 'name' => 'Security Test']);
        $vehicle = Vehicle::create(['plate_number' => 'DT 7001 SEC']);
        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'manual',
        ]);
    }
}
```

- [ ] **Step 2: Run QR security and existing QR/scan suites**

Run:

```bash
php artisan test tests/Feature/PermitQrSecurityTest.php tests/Feature/PermitQrServiceTest.php tests/Feature/PermitScanServiceTest.php tests/Feature/ScanQrHttpTest.php
```

Expected: all tests PASS without application code changes. If a new test fails due to actual plaintext persistence or data leakage, stop this task and fix the smallest responsible service before committing.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/PermitQrSecurityTest.php
git commit -m "test: lock qr token security behavior"
```

---

### Task 6: Add Security Inventory, Risk Register, and Upgrade Runbook

**Files:**
- Create: `docs/security/SECURITY-EXPOSURE-INVENTORY.md`
- Create: `docs/security/DEPENDENCY-RISK-REGISTER.md`
- Create: `docs/upgrade/LARAVEL-12-READINESS.md`
- Modify: `docs/deployment/CPANEL-PRODUCTION.md`
- Create: `tests/Feature/PhaseSevenDocumentationTest.php`

**Interfaces:**
- Produces: operator-readable release gate documents and test-enforced required sections.

- [ ] **Step 1: Write failing documentation contract tests**

Create `tests/Feature/PhaseSevenDocumentationTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class PhaseSevenDocumentationTest extends TestCase
{
    /** @test */
    public function phase_seven_security_documents_define_residual_risk_and_upgrade_exit_condition()
    {
        $inventory = file_get_contents(base_path('docs/security/SECURITY-EXPOSURE-INVENTORY.md'));
        $riskRegister = file_get_contents(base_path('docs/security/DEPENDENCY-RISK-REGISTER.md'));
        $readiness = file_get_contents(base_path('docs/upgrade/LARAVEL-12-READINESS.md'));

        $this->assertStringContainsString('Signed URL', $inventory);
        $this->assertStringContainsString('Tidak ditemukan penggunaan', $inventory);
        $this->assertStringContainsString('GHSA-crmm-hgp2-wgrp', $riskRegister);
        $this->assertStringContainsString('GHSA-5vg9-5847-vvmq', $riskRegister);
        $this->assertStringContainsString('GHSA-78fx-h6xr-vch4', $riskRegister);
        $this->assertStringContainsString('Pemilik penerimaan risiko:', $riskRegister);
        $this->assertStringContainsString('Tanggal review ulang:', $riskRegister);
        $this->assertStringContainsString('PHP 8.2', $readiness);
        $this->assertStringContainsString('Laravel 12', $readiness);
        $this->assertStringContainsString('sirika.vdnisite.com', $readiness);
    }

    /** @test */
    public function production_runbook_requires_explicit_dependency_risk_decision()
    {
        $runbook = file_get_contents(base_path('docs/deployment/CPANEL-PRODUCTION.md'));

        $this->assertStringContainsString('composer audit', $runbook);
        $this->assertStringContainsString('penerimaan risiko', $runbook);
        $this->assertStringContainsString('advisory baru', $runbook);
    }
}
```

- [ ] **Step 2: Run test and verify missing documents fail**

Run:

```bash
php artisan test --filter=PhaseSevenDocumentationTest
```

Expected: FAIL because the three Phase 7 documents do not exist.

- [ ] **Step 3: Write the exposure inventory from verified code findings**

Create `docs/security/SECURITY-EXPOSURE-INVENTORY.md` with these definitive findings:

```markdown
# SIRIKA Security Exposure Inventory

Baseline: PHP 7.4.33 / Laravel 8.83.29

## Signed URL

Tidak ditemukan penggunaan `URL::signedRoute`, `URL::temporarySignedRoute`, `hasValidSignature`, atau middleware `signed` pada route SIRIKA. QR menggunakan random token 64 karakter; database hanya menyimpan SHA-256 hash. Route alias `signed` bawaan masih terdaftar di Kernel tetapi tidak dipakai route aplikasi.

## Email Validation

Email yang dapat diubah user hanya berada pada CRUD user Super Admin. `NoControlCharacters` menolak CR, LF, dan karakter kontrol sebelum rule `email` Laravel.

## File Import

Import hanya menerima XLSX/XLS sampai 10 MB, memeriksa extension dan MIME pada service boundary, wajib lolos parsing PhpSpreadsheet, wajib memiliki header SIRIKA, dibatasi 5000 baris termasuk header, dan menulis preview rows dalam transaction.

## API, Sanctum, and CORS

SIRIKA tidak memiliki API publik. Route scaffold `/api/user` dan trait `HasApiTokens` sudah dihapus. Package dan migration Sanctum dipertahankan sampai upgrade Laravel 12 agar Phase 7 tidak melakukan dependency atau schema churn. CORS default tidak memiliki path aktif dan credentials lintas origin nonaktif.

## Mail

Tidak ditemukan pengiriman email pada workflow aplikasi. SwiftMailer hadir secara transitive melalui Laravel 8 dan dihapus melalui upgrade Laravel 12, bukan lewat perubahan parsial pada PHP 7.4.
```

- [ ] **Step 4: Write the dependency risk register**

Create `docs/security/DEPENDENCY-RISK-REGISTER.md`:

```markdown
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
```

- [ ] **Step 5: Write the Laravel 12 readiness checklist**

Create `docs/upgrade/LARAVEL-12-READINESS.md`:

```markdown
# SIRIKA Laravel 12 Upgrade Readiness

Target: PHP 8.2 atau lebih baru dan Laravel 12 versi security-supported.

Constraint: runtime hanya boleh diubah untuk `sirika.vdnisite.com`. PHP global cPanel dan project Laravel 8 lain tidak boleh berubah.

## Hosting Prerequisites

- [ ] MultiPHP Manager atau mekanisme hosting setara menyediakan PHP 8.2+ khusus `sirika.vdnisite.com`.
- [ ] CLI PHP pada folder source SIRIKA menunjuk ke versi yang sama dengan runtime domain.
- [ ] Extension `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `session`, `tokenizer`, `xml`, `zip`, dan driver database tersedia.
- [ ] Composer yang kompatibel berjalan dengan PHP target.

## Staging and Backup

- [ ] Buat staging terisolasi dengan salinan database yang sudah disanitasi.
- [ ] Backup database production dan verifikasi file backup dapat dibaca.
- [ ] Backup source, `.env`, folder public, storage yang dibutuhkan, dan release aktif.
- [ ] Catat path source dan document root production dari Phase 6.
- [ ] Buat branch upgrade terpisah; jangan upgrade langsung pada `main` atau production.

## Dependency Work

- [ ] Ikuti Laravel upgrade guide secara bertahap hingga Laravel 12.
- [ ] Targetkan Laravel 12 terbaru yang masih menerima security fixes.
- [ ] Putuskan apakah Sanctum dibutuhkan; hapus package dan config bila tetap tidak digunakan, atau upgrade ke versi Laravel 12-compatible.
- [ ] Hapus `fruitcake/laravel-cors` dan gunakan mekanisme CORS framework target.
- [ ] Ganti dependency Ignition, Collision, dan Sail dengan versi Laravel 12-compatible.
- [ ] Verifikasi SwiftMailer sudah digantikan Symfony Mailer.
- [ ] Verifikasi Laravel Excel, PhpSpreadsheet, dan BaconQrCode kompatibel dengan PHP/framework target.
- [ ] Jalankan `composer validate` dan `composer audit` tanpa advisory yang belum dinilai.

## Application Verification

- [ ] Jalankan `php artisan test` dan catat jumlah test lulus.
- [ ] Jalankan `php artisan config:cache`, `route:cache`, dan `view:cache`.
- [ ] Uji login dan seluruh role.
- [ ] Uji import preview/commit untuk workbook valid dan invalid.
- [ ] Uji review, aktivasi, QR generate/bulk/show/print/renew, dan scan semua status.
- [ ] Uji route map, report, dan export.
- [ ] Pastikan `/api/user` tetap tidak tersedia kecuali ada desain API baru yang disetujui.

## Cutover and Rollback

- [ ] Aktifkan maintenance mode dan verifikasi HTTP 503 sebelum cutover.
- [ ] Deploy source, vendor, dan public assets tanpa menimpa entrypoint cPanel yang sudah dipatch.
- [ ] Jalankan migration hanya setelah review dan backup database.
- [ ] Bangun ulang cache dengan PHP target.
- [ ] Jalankan smoke test sebelum membuka traffic.
- [ ] Simpan release lama sampai periode observasi selesai.
- [ ] Jika smoke test gagal, pulihkan source/vendor/public release lama dan database hanya dari backup yang telah diverifikasi.
```

- [ ] **Step 6: Tighten the production audit gate**

Update `docs/deployment/CPANEL-PRODUCTION.md` under `Audit Dependency`:

```markdown
Sebelum setiap release, jalankan `composer audit` dan cocokkan hasilnya dengan `docs/security/DEPENDENCY-RISK-REGISTER.md`. Advisory baru yang belum dinilai menghentikan release. Selama baseline PHP 7.4/Laravel 8 masih digunakan, deployment hanya dapat dilanjutkan setelah field penerimaan risiko di risk register diisi oleh pemilik sistem; developer tidak dapat menyetujui risiko tersebut atas nama pemilik sistem.
```

- [ ] **Step 7: Verify documentation contracts**

Run:

```bash
php artisan test --filter=PhaseSevenDocumentationTest
```

Expected: all tests PASS.

- [ ] **Step 8: Commit ignored docs explicitly**

```bash
git add tests/Feature/PhaseSevenDocumentationTest.php
git add -f docs/security/SECURITY-EXPOSURE-INVENTORY.md docs/security/DEPENDENCY-RISK-REGISTER.md docs/upgrade/LARAVEL-12-READINESS.md docs/deployment/CPANEL-PRODUCTION.md
git commit -m "docs: add phase 7 security release controls"
```

---

### Task 7: Full Regression and Production Readiness Verification

**Files:**
- Modify only files required to fix regressions introduced by Tasks 1-6; do not broaden scope.

**Interfaces:**
- Consumes: all Phase 7 controls and existing SIRIKA features.
- Produces: evidence that the release candidate passes tests/cache checks while residual Composer advisories remain explicitly documented.

- [ ] **Step 1: Run the complete automated suite**

Run:

```bash
php artisan test
```

Expected: all tests PASS. Record the exact pass count in the completion report.

- [ ] **Step 2: Verify production caches**

Run:

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

Expected: every command exits 0; final clear restores local development behavior.

- [ ] **Step 3: Run dependency audits**

Run:

```bash
composer audit --format=plain
npm audit --omit=dev
```

Expected: npm audit exits clean. Composer audit may remain non-zero only for advisories/package abandonment already represented in `DEPENDENCY-RISK-REGISTER.md`; any new finding blocks completion.

- [ ] **Step 4: Inspect final route and diff**

Run:

```bash
php artisan route:list
git diff --check
git status --short
```

Expected: no `/api/user`; existing web routes remain; no whitespace errors; only intentional Phase 7 files are changed.

- [ ] **Step 5: Perform manual browser smoke test**

Using the local server and in-app browser, verify:

1. Login Super Admin.
2. User create/update accepts a normal email and rejects an email with a pasted line break.
3. Import valid Excel reaches preview.
4. Invalid/disguised file is rejected with a safe message.
5. Permit list, review, QR generate/show/print/renew, scan, route map, reports, and exports open without regression.
6. Security role cannot access user management or reports.

- [ ] **Step 6: Resolve verification failures at their owning task**

If verification fails, return to the task that owns the failing behavior, add a focused regression test there, make the minimum fix, rerun that task's commands, and commit with that task's exact file list. Do not create an empty verification commit or broaden Phase 7 scope.

## Completion Gate

Implementation is complete only when:

- Tasks 1-7 are reviewed and committed.
- Full PHPUnit suite and cache commands pass.
- Browser smoke test passes.
- npm production audit is clean.
- Composer findings exactly match the risk register; new findings block release.
- The final report explicitly states that PHP 7.4/Laravel 8 residual risk remains until isolated PHP 8.2/Laravel 12 upgrade.
