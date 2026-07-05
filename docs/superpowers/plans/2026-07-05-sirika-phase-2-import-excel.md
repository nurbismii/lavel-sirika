# SIRIKA Phase 2 Import Excel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Phase 2 import Excel workflow with private upload, staging rows, preview validation, conservative route parsing, and transactional commit into SIRIKA permit tables.

**Architecture:** Use Laravel 8 MVC with thin HTTP controllers and focused service classes under `app/Services/Imports`. Laravel Excel reads the first workbook sheet, `import_rows` stores row-level staging and validation results, and a commit service writes only safe data into `employees`, `vehicles`, `parking_locations`, `vehicle_permits`, and `permit_route_segments` inside a database transaction.

**Tech Stack:** PHP 7.4, Laravel 8, Blade, Alpine.js, Laravel Excel 3.1, Eloquent, PHPUnit Feature/Unit tests, MySQL/PostgreSQL-compatible migrations.

## Global Constraints

- Use PHP 7.4-compatible syntax only.
- Use Laravel 8 conventions and avoid Laravel 9+ only APIs.
- Use Blade and custom admin panel UI; do not add a third-party admin template.
- Upload and commit import are only for `admin_hr` and `super_admin`.
- Store uploaded Excel files in private storage, not `public`.
- Use staging table `import_rows` before writing import data to final tables.
- Keep route parser conservative; uncertain data becomes `needs_review`, not `active`.
- Do not overwrite old permit data in Phase 2.
- Row `invalid` must never create employee, vehicle, or permit records.
- Row `needs_review` must never become an active permit.
- QR code, camera scan, Leaflet overlay, inline row correction, error-report download, and full manual permit CRUD are out of scope.
- Migrations must stay portable for MySQL and PostgreSQL.
- Commit only files changed by the current task. Do not include unrelated user edits.

---

## Current Repository Notes

- Repository root: `C:\xampp\htdocs\lavel-sirika-vdni`
- Current branch: `main`
- Phase 1 implementation is merged and pushed.
- Phase 2 design spec is committed at `docs/superpowers/specs/2026-07-05-sirika-phase-2-import-excel-design.md`.
- `main` currently contains one local spec commit ahead of `origin/main`.
- Folder `docs` is ignored by `.gitignore`; new plan/spec files require `git add -f`.

## File Structure

- `composer.json`, `composer.lock`: add `maatwebsite/excel:^3.1`.
- `database/migrations/2026_07_05_000001_create_import_rows_table.php`: staging schema.
- `app/Models/ImportBatch.php`: status constants, `rows()` relation, helper methods.
- `app/Models/ImportRow.php`: staging model constants, casts, relationships.
- `app/Services/Imports/PermitImportHeaderMapper.php`: detects and maps bilingual Excel headers.
- `app/Services/Imports/RouteSegmentParser.php`: extracts active road segment codes from raw route strings.
- `app/Services/Imports/PermitImportRowNormalizer.php`: converts raw Excel rows into canonical normalized data plus errors/warnings.
- `app/Imports/PermitExcelArrayImport.php`: Laravel Excel import object for first-sheet array extraction.
- `app/Services/Imports/PermitExcelImportService.php`: stores file, parses sheet, creates batch rows and row counts.
- `app/Services/Imports/PermitImportCommitService.php`: transactionally writes staged rows to final tables.
- `app/Http/Requests/StoreImportRequest.php`: upload validation.
- `app/Http/Controllers/ImportController.php`: index, store, show, commit actions.
- `routes/web.php`: add POST upload, preview, and commit routes.
- `resources/views/imports/index.blade.php`: upload form and batch list.
- `resources/views/imports/show.blade.php`: batch preview and commit UI.
- `resources/views/permits/index.blade.php`: read-only permit list after import.
- `app/Http/Controllers/PermitController.php`: paginate imported permits.
- `app/Http/Controllers/DashboardController.php`: imported permit counts.
- `resources/css/app.css`: small table/status/form styles if missing.
- `tests/Feature/ImportRowSchemaTest.php`: schema/model coverage.
- `tests/Unit/PermitImportHeaderMapperTest.php`: header mapping coverage.
- `tests/Unit/RouteSegmentParserTest.php`: route parsing coverage.
- `tests/Unit/PermitImportRowNormalizerTest.php`: row classification coverage.
- `tests/Feature/ImportExcelPreviewTest.php`: upload/preview HTTP and service coverage.
- `tests/Feature/ImportCommitTest.php`: commit behavior coverage.
- `tests/Feature/PermitListAfterImportTest.php`: read-only permit list coverage.

---

### Task 1: Laravel Excel Dependency and Import Row Schema

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Create: `database/migrations/2026_07_05_000001_create_import_rows_table.php`
- Create: `app/Models/ImportRow.php`
- Modify: `app/Models/ImportBatch.php`
- Create: `tests/Feature/ImportRowSchemaTest.php`

**Interfaces:**
- Produces `App\Models\ImportRow`.
- Produces `ImportRow::STATUS_VALID`, `STATUS_NEEDS_REVIEW`, `STATUS_INVALID`, `STATUS_COMMITTED`.
- Produces `ImportBatch::STATUS_DRAFT`, `STATUS_PREVIEWED`, `STATUS_COMMITTED`, `STATUS_FAILED`.
- Produces `ImportBatch::rows(): HasMany`.
- Later tasks consume `import_rows` and these constants.

- [ ] **Step 1: Install Laravel Excel**

Run:

```bash
composer require maatwebsite/excel:^3.1
```

Expected: `composer.json` contains `maatwebsite/excel`, `composer.lock` changes, and `php artisan package:discover` runs successfully.

- [ ] **Step 2: Write the failing schema/model test**

Create `tests/Feature/ImportRowSchemaTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportRowSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function import_rows_table_has_required_columns()
    {
        $this->assertTrue(Schema::hasColumns('import_rows', [
            'id',
            'import_batch_id',
            'row_number',
            'status',
            'raw_data',
            'normalized_data',
            'errors',
            'warnings',
            'created_employee_id',
            'created_vehicle_id',
            'created_permit_id',
            'created_at',
            'updated_at',
        ]));
    }

    /** @test */
    public function import_batch_has_rows_relationship_and_status_constants()
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $batch = ImportBatch::create([
            'filename' => 'sample.xlsx',
            'uploaded_by' => $user->id,
            'status' => ImportBatch::STATUS_PREVIEWED,
        ]);

        $row = ImportRow::create([
            'import_batch_id' => $batch->id,
            'row_number' => 5,
            'status' => ImportRow::STATUS_VALID,
            'raw_data' => ['nik' => '200115677'],
            'normalized_data' => ['nik' => '200115677'],
            'errors' => [],
            'warnings' => [],
        ]);

        $this->assertSame(ImportBatch::STATUS_PREVIEWED, $batch->status);
        $this->assertTrue($batch->rows->first()->is($row));
        $this->assertSame(['nik' => '200115677'], $row->normalized_data);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run:

```bash
php artisan test --filter=ImportRowSchemaTest
```

Expected: FAIL because `import_rows` table and `ImportRow` model do not exist.

- [ ] **Step 4: Create import row migration**

Create `database/migrations/2026_07_05_000001_create_import_rows_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateImportRowsTable extends Migration
{
    public function up()
    {
        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->onDelete('cascade');
            $table->unsignedInteger('row_number');
            $table->string('status', 32)->index();
            $table->json('raw_data')->nullable();
            $table->json('normalized_data')->nullable();
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->foreignId('created_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('created_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('created_permit_id')->nullable()->constrained('vehicle_permits')->nullOnDelete();
            $table->timestamps();

            $table->unique(['import_batch_id', 'row_number']);
            $table->index(['import_batch_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('import_rows');
    }
}
```

- [ ] **Step 5: Create ImportRow model**

Create `app/Models/ImportRow.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportRow extends Model
{
    public const STATUS_VALID = 'valid';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_COMMITTED = 'committed';

    protected $fillable = [
        'import_batch_id',
        'row_number',
        'status',
        'raw_data',
        'normalized_data',
        'errors',
        'warnings',
        'created_employee_id',
        'created_vehicle_id',
        'created_permit_id',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'normalized_data' => 'array',
        'errors' => 'array',
        'warnings' => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function createdEmployee()
    {
        return $this->belongsTo(Employee::class, 'created_employee_id');
    }

    public function createdVehicle()
    {
        return $this->belongsTo(Vehicle::class, 'created_vehicle_id');
    }

    public function createdPermit()
    {
        return $this->belongsTo(VehiclePermit::class, 'created_permit_id');
    }
}
```

- [ ] **Step 6: Update ImportBatch model**

Modify `app/Models/ImportBatch.php` to include constants and relation:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PREVIEWED = 'previewed';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'filename',
        'uploaded_by',
        'total_rows',
        'success_rows',
        'failed_rows',
        'review_rows',
        'status',
        'error_summary',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'success_rows' => 'integer',
        'failed_rows' => 'integer',
        'review_rows' => 'integer',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function rows()
    {
        return $this->hasMany(ImportRow::class, 'import_batch_id');
    }

    public function permits()
    {
        return $this->hasMany(VehiclePermit::class, 'source_import_id');
    }
}
```

- [ ] **Step 7: Run test to verify it passes**

Run:

```bash
php artisan test --filter=ImportRowSchemaTest
```

Expected: PASS.

- [ ] **Step 8: Commit**

Run:

```bash
git status --short
git add composer.json composer.lock database/migrations/2026_07_05_000001_create_import_rows_table.php app/Models/ImportRow.php app/Models/ImportBatch.php tests/Feature/ImportRowSchemaTest.php
git commit -m "feat: add sirika import row staging schema"
```

Expected: commit contains only dependency, schema, model, and test files from this task.

---

### Task 2: Header Mapping, Route Parser, and Row Normalizer

**Files:**
- Create: `app/Services/Imports/PermitImportHeaderMapper.php`
- Create: `app/Services/Imports/RouteSegmentParser.php`
- Create: `app/Services/Imports/PermitImportRowNormalizer.php`
- Create: `tests/Unit/PermitImportHeaderMapperTest.php`
- Create: `tests/Unit/RouteSegmentParserTest.php`
- Create: `tests/Unit/PermitImportRowNormalizerTest.php`

**Interfaces:**
- Produces `PermitImportHeaderMapper::findHeader(array $rows): array` returning `['row_index' => int, 'columns' => array]`.
- Produces `RouteSegmentParser::parse($rawRoute, array $activeCodes): array` returning `['codes' => array, 'warnings' => array]`.
- Produces `PermitImportRowNormalizer::normalize(array $rawRow, array $headerColumns, array $activeRouteCodes, int $rowNumber): array`.
- Later import service consumes these services to create `import_rows`.

- [ ] **Step 1: Write failing unit tests**

Create `tests/Unit/PermitImportHeaderMapperTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\Imports\PermitImportHeaderMapper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PermitImportHeaderMapperTest extends TestCase
{
    /** @test */
    public function it_finds_bilingual_header_row_and_column_indexes()
    {
        $rows = [
            ['', '', ''],
            ['VDNI Formulir', '', ''],
            ['序号 No', '摩托车牌号 Plat Motor', '姓名 Nama', '工号 Nik', '部门 Dep', '科室 Bagian', '岗位 Jabatan', '停放地点 Lokasi Parkir', '行驶路线 Rute Kendaraan', '进厂原因 Alasan Masuk', '通行证颜色 Warna kartu izin masuk', '联系方式 Nomor kontak', '审批结果 Hasil Persetujuan', 'KET', 'DIVISI'],
        ];

        $result = (new PermitImportHeaderMapper())->findHeader($rows);

        $this->assertSame(2, $result['row_index']);
        $this->assertSame(1, $result['columns']['plate_number']);
        $this->assertSame(3, $result['columns']['nik']);
        $this->assertSame(8, $result['columns']['route_raw']);
        $this->assertSame(12, $result['columns']['approval_status']);
    }

    /** @test */
    public function it_rejects_rows_without_required_headers()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Header Excel tidak valid');

        (new PermitImportHeaderMapper())->findHeader([
            ['Name', 'Plate'],
        ]);
    }
}
```

Create `tests/Unit/RouteSegmentParserTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\Imports\RouteSegmentParser;
use PHPUnit\Framework\TestCase;

class RouteSegmentParserTest extends TestCase
{
    /** @test */
    public function it_extracts_known_segment_codes_in_order()
    {
        $result = (new RouteSegmentParser())->parse('Y1→D2→Z1→D3→GA-MES1-P01', ['Y1', 'D2', 'Z1', 'D3']);

        $this->assertSame(['Y1', 'D2', 'Z1', 'D3'], $result['codes']);
        $this->assertSame([], $result['warnings']);
    }

    /** @test */
    public function it_marks_unknown_route_tokens_as_warning()
    {
        $result = (new RouteSegmentParser())->parse('Y1→X99→D2', ['Y1', 'D2']);

        $this->assertSame(['Y1', 'D2'], $result['codes']);
        $this->assertContains('Rute mengandung token tidak dikenal: X99', $result['warnings']);
    }

    /** @test */
    public function it_marks_long_instruction_text_as_warning()
    {
        $result = (new RouteSegmentParser())->parse('Y1→D2 （根据领导安排的工作区域）', ['Y1', 'D2']);

        $this->assertSame(['Y1', 'D2'], $result['codes']);
        $this->assertContains('Rute mengandung catatan teks yang perlu review', $result['warnings']);
    }
}
```

Create `tests/Unit/PermitImportRowNormalizerTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\ImportRow;
use App\Services\Imports\PermitImportRowNormalizer;
use App\Services\Imports\RouteSegmentParser;
use PHPUnit\Framework\TestCase;

class PermitImportRowNormalizerTest extends TestCase
{
    private function columns()
    {
        return [
            'plate_number' => 1,
            'employee_name' => 2,
            'nik' => 3,
            'department' => 4,
            'section' => 5,
            'position' => 6,
            'parking_location' => 7,
            'route_raw' => 8,
            'reason' => 9,
            'permit_color' => 10,
            'contact_number' => 11,
            'approval_status' => 12,
            'notes' => 13,
            'division' => 14,
        ];
    }

    /** @test */
    public function it_marks_complete_row_as_valid()
    {
        $raw = ['1', 'DT 4423 CI', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', 'Y1→D2→Z1→D3→GA-MES1-P01', 'OFFICE', 'BIRU 蓝色', '0812', '√', 'JUBIR ADMIN', 'GENERAL AFFAIR'];

        $result = (new PermitImportRowNormalizer(new RouteSegmentParser()))->normalize($raw, $this->columns(), ['Y1', 'D2', 'Z1', 'D3'], 5);

        $this->assertSame(ImportRow::STATUS_VALID, $result['status']);
        $this->assertSame('200115677', $result['normalized_data']['nik']);
        $this->assertSame('biru', $result['normalized_data']['permit_color']);
        $this->assertSame(['Y1', 'D2', 'Z1', 'D3'], $result['normalized_data']['route_segment_codes']);
        $this->assertSame([], $result['errors']);
    }

    /** @test */
    public function it_marks_blank_plate_as_invalid()
    {
        $raw = ['1', '', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', 'Y1→D2', 'OFFICE', 'BIRU 蓝色', '0812', '√', '', 'GENERAL AFFAIR'];

        $result = (new PermitImportRowNormalizer(new RouteSegmentParser()))->normalize($raw, $this->columns(), ['Y1', 'D2'], 5);

        $this->assertSame(ImportRow::STATUS_INVALID, $result['status']);
        $this->assertContains('Plat motor wajib diisi', $result['errors']);
    }

    /** @test */
    public function it_marks_blank_route_as_needs_review()
    {
        $raw = ['1', 'DT 4423 CI', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', '', 'OFFICE', 'BIRU 蓝色', '0812', '√', '', 'GENERAL AFFAIR'];

        $result = (new PermitImportRowNormalizer(new RouteSegmentParser()))->normalize($raw, $this->columns(), ['Y1', 'D2'], 5);

        $this->assertSame(ImportRow::STATUS_NEEDS_REVIEW, $result['status']);
        $this->assertContains('Rute kendaraan kosong', $result['warnings']);
    }

    /** @test */
    public function it_marks_multiple_plates_as_needs_review()
    {
        $raw = ['1', "DT 5224 AA/\nDT 2119 WA", 'MUH IRAWAN', '17011544', 'GENERAL AFFAIR', 'GA KANTOR', 'DRIVER', 'GA-MES1-P01', 'Y1→D2', 'OFFICE', 'BIRU 蓝色', '0812', '√', '', 'GENERAL AFFAIR'];

        $result = (new PermitImportRowNormalizer(new RouteSegmentParser()))->normalize($raw, $this->columns(), ['Y1', 'D2'], 5);

        $this->assertSame(ImportRow::STATUS_NEEDS_REVIEW, $result['status']);
        $this->assertContains('Plat motor berisi lebih dari satu nilai', $result['warnings']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run:

```bash
php artisan test --filter=PermitImportHeaderMapperTest
php artisan test --filter=RouteSegmentParserTest
php artisan test --filter=PermitImportRowNormalizerTest
```

Expected: FAIL because service classes do not exist.

- [ ] **Step 3: Create PermitImportHeaderMapper**

Create `app/Services/Imports/PermitImportHeaderMapper.php`:

```php
<?php

namespace App\Services\Imports;

use InvalidArgumentException;

class PermitImportHeaderMapper
{
    private const REQUIRED = [
        'plate_number' => ['plat motor'],
        'employee_name' => ['nama'],
        'nik' => ['nik'],
        'department' => ['dep'],
        'section' => ['bagian'],
        'position' => ['jabatan'],
        'parking_location' => ['lokasi parkir'],
        'route_raw' => ['rute kendaraan'],
        'reason' => ['alasan masuk'],
        'permit_color' => ['warna kartu'],
        'contact_number' => ['nomor kontak'],
        'approval_status' => ['hasil persetujuan'],
        'division' => ['divisi'],
    ];

    public function findHeader(array $rows)
    {
        foreach ($rows as $rowIndex => $row) {
            $columns = $this->mapColumns($row);

            if ($this->hasRequiredColumns($columns)) {
                return [
                    'row_index' => $rowIndex,
                    'columns' => $columns,
                ];
            }
        }

        throw new InvalidArgumentException('Header Excel tidak valid: kolom wajib tidak ditemukan.');
    }

    private function mapColumns(array $row)
    {
        $columns = [];

        foreach ($row as $index => $label) {
            $normalized = $this->normalizeLabel($label);

            foreach (self::REQUIRED as $key => $needles) {
                foreach ($needles as $needle) {
                    if ($normalized !== '' && strpos($normalized, $needle) !== false) {
                        $columns[$key] = $index;
                    }
                }
            }

            if ($normalized === 'ket') {
                $columns['notes'] = $index;
            }
        }

        return $columns;
    }

    private function hasRequiredColumns(array $columns)
    {
        foreach (array_keys(self::REQUIRED) as $required) {
            if (!array_key_exists($required, $columns)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeLabel($value)
    {
        $value = strtolower((string) $value);
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }
}
```

- [ ] **Step 4: Create RouteSegmentParser**

Create `app/Services/Imports/RouteSegmentParser.php`:

```php
<?php

namespace App\Services\Imports;

class RouteSegmentParser
{
    public function parse($rawRoute, array $activeCodes)
    {
        $rawRoute = trim((string) $rawRoute);

        if ($rawRoute === '') {
            return [
                'codes' => [],
                'warnings' => ['Rute kendaraan kosong'],
            ];
        }

        $knownCodes = array_values(array_unique(array_map('strtoupper', $activeCodes)));
        usort($knownCodes, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        $routeWithoutParkingCodes = preg_replace('/[A-Z]{2,}-[A-Z0-9]+-P\d+/i', ' ', $rawRoute);
        preg_match_all('/[A-Z]{1,3}\d{1,2}/i', $routeWithoutParkingCodes, $matches);
        $tokens = array_values(array_unique(array_map('strtoupper', $matches[0])));

        $codes = [];
        $warnings = [];

        foreach ($tokens as $token) {
            if (in_array($token, $knownCodes, true)) {
                $codes[] = $token;
            } elseif (!$this->looksLikeParkingCode($token)) {
                $warnings[] = 'Rute mengandung token tidak dikenal: ' . $token;
            }
        }

        if ($codes === []) {
            $warnings[] = 'Rute tidak mengandung kode segmen resmi';
        }

        if ($this->containsInstructionText($rawRoute)) {
            $warnings[] = 'Rute mengandung catatan teks yang perlu review';
        }

        return [
            'codes' => $codes,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function looksLikeParkingCode($token)
    {
        return preg_match('/^P\d+$/i', $token) === 1;
    }

    private function containsInstructionText($rawRoute)
    {
        return strpos($rawRoute, '（') !== false
            || strpos($rawRoute, '(') !== false
            || stripos($rawRoute, 'sesuai') !== false
            || stripos($rawRoute, '领导') !== false;
    }
}
```

- [ ] **Step 5: Create PermitImportRowNormalizer**

Create `app/Services/Imports/PermitImportRowNormalizer.php`:

```php
<?php

namespace App\Services\Imports;

use App\Models\ImportRow;

class PermitImportRowNormalizer
{
    private $routeParser;

    public function __construct(RouteSegmentParser $routeParser)
    {
        $this->routeParser = $routeParser;
    }

    public function normalize(array $rawRow, array $headerColumns, array $activeRouteCodes, int $rowNumber)
    {
        $rawData = [
            'row_number' => $rowNumber,
            'plate_number' => $this->cell($rawRow, $headerColumns, 'plate_number'),
            'employee_name' => $this->cell($rawRow, $headerColumns, 'employee_name'),
            'nik' => $this->cell($rawRow, $headerColumns, 'nik'),
            'department' => $this->cell($rawRow, $headerColumns, 'department'),
            'section' => $this->cell($rawRow, $headerColumns, 'section'),
            'position' => $this->cell($rawRow, $headerColumns, 'position'),
            'parking_location' => $this->cell($rawRow, $headerColumns, 'parking_location'),
            'route_raw' => $this->cell($rawRow, $headerColumns, 'route_raw'),
            'reason' => $this->cell($rawRow, $headerColumns, 'reason'),
            'permit_color' => $this->cell($rawRow, $headerColumns, 'permit_color'),
            'contact_number' => $this->cell($rawRow, $headerColumns, 'contact_number'),
            'approval_status' => $this->cell($rawRow, $headerColumns, 'approval_status'),
            'notes' => $this->cell($rawRow, $headerColumns, 'notes'),
            'division' => $this->cell($rawRow, $headerColumns, 'division'),
        ];

        $errors = [];
        $warnings = [];

        $plate = $this->normalizeText($rawData['plate_number']);
        $name = $this->normalizeText($rawData['employee_name']);
        $nik = $this->normalizeText($rawData['nik']);
        $parking = $this->normalizeText($rawData['parking_location']);
        $routeRaw = $this->normalizeText($rawData['route_raw']);
        $color = $this->normalizeColor($rawData['permit_color']);
        $approved = $this->isApproved($rawData['approval_status']);

        if ($nik === '') {
            $errors[] = 'NIK wajib diisi';
        }

        if ($name === '') {
            $errors[] = 'Nama wajib diisi';
        }

        if ($plate === '') {
            $errors[] = 'Plat motor wajib diisi';
        }

        if (!$approved) {
            $errors[] = 'Hasil persetujuan harus disetujui';
        }

        if ($color === null) {
            $errors[] = 'Warna kartu izin tidak valid';
        }

        if ($plate !== '' && $this->containsMultiplePlates($rawData['plate_number'])) {
            $warnings[] = 'Plat motor berisi lebih dari satu nilai';
        }

        if ($parking === '') {
            $warnings[] = 'Lokasi parkir kosong';
        }

        $route = $this->routeParser->parse($routeRaw, $activeRouteCodes);
        $warnings = array_merge($warnings, $route['warnings']);

        $status = ImportRow::STATUS_VALID;
        if ($errors !== []) {
            $status = ImportRow::STATUS_INVALID;
        } elseif ($warnings !== []) {
            $status = ImportRow::STATUS_NEEDS_REVIEW;
        }

        return [
            'row_number' => $rowNumber,
            'status' => $status,
            'raw_data' => $rawData,
            'normalized_data' => [
                'nik' => $nik,
                'employee_name' => $name,
                'department' => $this->normalizeText($rawData['department']),
                'section' => $this->normalizeText($rawData['section']),
                'position' => $this->normalizeText($rawData['position']),
                'division' => $this->normalizeText($rawData['division']),
                'contact_number' => $this->normalizeText($rawData['contact_number']),
                'plate_number' => $plate,
                'parking_location_code' => $parking,
                'route_raw' => $routeRaw,
                'route_segment_codes' => $route['codes'],
                'reason' => $this->normalizeText($rawData['reason']),
                'permit_color' => $color,
                'approval_status' => $approved ? 'approved' : 'rejected',
                'notes' => $this->normalizeText($rawData['notes']),
            ],
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function cell(array $row, array $columns, $key)
    {
        if (!array_key_exists($key, $columns)) {
            return '';
        }

        $index = $columns[$key];

        return array_key_exists($index, $row) ? $row[$index] : '';
    }

    private function normalizeText($value)
    {
        $value = trim((string) $value);
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    private function normalizeColor($value)
    {
        $value = strtolower($this->normalizeText($value));

        if (strpos($value, 'biru') !== false) {
            return 'biru';
        }

        if (strpos($value, 'kuning') !== false) {
            return 'kuning';
        }

        if (strpos($value, 'merah') !== false) {
            return 'merah';
        }

        if (strpos($value, 'hijau') !== false) {
            return 'hijau';
        }

        return null;
    }

    private function isApproved($value)
    {
        $value = strtolower($this->normalizeText($value));

        return $value === '√'
            || strpos($value, 'approved') !== false
            || strpos($value, 'disetujui') !== false
            || strpos($value, 'setuju') !== false;
    }

    private function containsMultiplePlates($value)
    {
        $value = (string) $value;

        return strpos($value, '/') !== false
            || strpos($value, ',') !== false
            || strpos($value, "\n") !== false;
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run:

```bash
php artisan test --filter=PermitImportHeaderMapperTest
php artisan test --filter=RouteSegmentParserTest
php artisan test --filter=PermitImportRowNormalizerTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

Run:

```bash
git status --short
git add app/Services/Imports/PermitImportHeaderMapper.php app/Services/Imports/RouteSegmentParser.php app/Services/Imports/PermitImportRowNormalizer.php tests/Unit/PermitImportHeaderMapperTest.php tests/Unit/RouteSegmentParserTest.php tests/Unit/PermitImportRowNormalizerTest.php
git commit -m "feat: add sirika import normalization services"
```

Expected: commit contains only service classes and unit tests from this task.

---

### Task 3: Excel Upload Preview Service

**Files:**
- Create: `app/Imports/PermitExcelArrayImport.php`
- Create: `app/Services/Imports/PermitExcelImportService.php`
- Create: `tests/Feature/ImportExcelPreviewTest.php`

**Interfaces:**
- Consumes Task 1 models and Task 2 services.
- Produces `PermitExcelImportService::preview(UploadedFile $file, User $user): ImportBatch`.
- Produces private file storage under `storage/app/imports/YYYY/MM`.
- Produces `ImportBatch` status `previewed` or `failed`.

- [ ] **Step 1: Write failing preview tests**

Create `tests/Feature/ImportExcelPreviewTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\RoadSegment;
use App\Models\User;
use App\Services\Imports\PermitExcelImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportExcelPreviewTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_preview_batch_from_excel_file()
    {
        $this->seedRoadSegments(['Y1', 'D2', 'Z1', 'D3']);

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $file = $this->excelFile([
            ['', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['VDNI Formulir', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['序号 No', '摩托车牌号 Plat Motor', '姓名 Nama', '工号 Nik', '部门 Dep', '科室 Bagian', '岗位 Jabatan', '停放地点 Lokasi Parkir', '行驶路线 Rute Kendaraan', '进厂原因 Alasan Masuk', '通行证颜色 Warna kartu izin masuk', '联系方式 Nomor kontak', '审批结果 Hasil Persetujuan', 'KET', 'DIVISI'],
            ['1', 'DT 4423 CI', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', 'Y1→D2→Z1→D3→GA-MES1-P01', 'OFFICE', 'BIRU 蓝色', '0812', '√', '', 'GENERAL AFFAIR'],
            ['2', '', 'HARLINA', '211129282', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', 'Y1→D2', 'OFFICE', 'KUNING 黄色', '0813', '√', '', 'GENERAL AFFAIR'],
            ['3', 'DT 9999 AA', 'JUMRAN', '16101080', 'GENERAL AFFAIR', 'GA KEBERSIHAN', 'ADMIN', 'GA-MES3-P01', '', 'OFFICE', 'MERAH 红色', '0814', '√', '', 'GENERAL AFFAIR'],
        ]);

        $batch = app(PermitExcelImportService::class)->preview($file, $admin);

        $this->assertSame(ImportBatch::STATUS_PREVIEWED, $batch->fresh()->status);
        $this->assertSame(3, $batch->fresh()->total_rows);
        $this->assertSame(1, $batch->fresh()->success_rows);
        $this->assertSame(1, $batch->fresh()->failed_rows);
        $this->assertSame(1, $batch->fresh()->review_rows);
        $this->assertSame(3, $batch->rows()->count());
        $this->assertDatabaseHas('import_rows', ['row_number' => 4, 'status' => ImportRow::STATUS_VALID]);
        $this->assertDatabaseHas('import_rows', ['row_number' => 5, 'status' => ImportRow::STATUS_INVALID]);
        $this->assertDatabaseHas('import_rows', ['row_number' => 6, 'status' => ImportRow::STATUS_NEEDS_REVIEW]);
    }

    /** @test */
    public function it_marks_batch_failed_when_header_is_missing()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $file = $this->excelFile([
            ['Wrong', 'Header'],
            ['1', '2'],
        ]);

        $batch = app(PermitExcelImportService::class)->preview($file, $admin);

        $this->assertSame(ImportBatch::STATUS_FAILED, $batch->fresh()->status);
        $this->assertSame(0, $batch->rows()->count());
        $this->assertStringContainsString('Header Excel tidak valid', $batch->fresh()->error_summary);
    }

    private function seedRoadSegments(array $codes)
    {
        foreach ($codes as $code) {
            RoadSegment::create([
                'code' => $code,
                'name' => 'Jalan ' . $code,
                'status' => 'active',
            ]);
        }
    }

    private function excelFile(array $rows)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValueByColumnAndRow($columnIndex + 1, $rowIndex + 1, $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'sirika-import-') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'sample.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
php artisan test --filter=ImportExcelPreviewTest
```

Expected: FAIL because `PermitExcelImportService` and `PermitExcelArrayImport` do not exist.

- [ ] **Step 3: Create Laravel Excel array import**

Create `app/Imports/PermitExcelArrayImport.php`:

```php
<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class PermitExcelArrayImport implements ToArray
{
    public function array(array $array)
    {
        return $array;
    }
}
```

- [ ] **Step 4: Create PermitExcelImportService**

Create `app/Services/Imports/PermitExcelImportService.php`:

```php
<?php

namespace App\Services\Imports;

use App\Imports\PermitExcelArrayImport;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\RoadSegment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class PermitExcelImportService
{
    private $headerMapper;
    private $normalizer;

    public function __construct(PermitImportHeaderMapper $headerMapper, PermitImportRowNormalizer $normalizer)
    {
        $this->headerMapper = $headerMapper;
        $this->normalizer = $normalizer;
    }

    public function preview(UploadedFile $file, User $user)
    {
        $filename = $file->getClientOriginalName();
        $storedPath = $this->storeFile($file);

        $batch = ImportBatch::create([
            'filename' => $filename,
            'uploaded_by' => $user->id,
            'status' => ImportBatch::STATUS_DRAFT,
            'error_summary' => $storedPath,
        ]);

        try {
            $sheets = Excel::toArray(new PermitExcelArrayImport(), $storedPath, 'local');
            $rows = isset($sheets[0]) ? $sheets[0] : [];

            if ($rows === []) {
                throw new \InvalidArgumentException('Sheet Excel kosong.');
            }

            $header = $this->headerMapper->findHeader($rows);
            $activeRouteCodes = RoadSegment::where('status', 'active')->pluck('code')->map(function ($code) {
                return strtoupper($code);
            })->all();

            $counts = [
                ImportRow::STATUS_VALID => 0,
                ImportRow::STATUS_INVALID => 0,
                ImportRow::STATUS_NEEDS_REVIEW => 0,
            ];

            DB::transaction(function () use ($batch, $rows, $header, $activeRouteCodes, &$counts) {
                foreach ($rows as $index => $row) {
                    if ($index <= $header['row_index']) {
                        continue;
                    }

                    if ($this->isEmptyRow($row)) {
                        continue;
                    }

                    $normalized = $this->normalizer->normalize($row, $header['columns'], $activeRouteCodes, $index + 1);
                    $counts[$normalized['status']]++;

                    ImportRow::create([
                        'import_batch_id' => $batch->id,
                        'row_number' => $normalized['row_number'],
                        'status' => $normalized['status'],
                        'raw_data' => $normalized['raw_data'],
                        'normalized_data' => $normalized['normalized_data'],
                        'errors' => $normalized['errors'],
                        'warnings' => $normalized['warnings'],
                    ]);
                }

                $batch->update([
                    'total_rows' => array_sum($counts),
                    'success_rows' => $counts[ImportRow::STATUS_VALID],
                    'failed_rows' => $counts[ImportRow::STATUS_INVALID],
                    'review_rows' => $counts[ImportRow::STATUS_NEEDS_REVIEW],
                    'status' => ImportBatch::STATUS_PREVIEWED,
                    'error_summary' => null,
                ]);
            });
        } catch (Throwable $exception) {
            $batch->update([
                'status' => ImportBatch::STATUS_FAILED,
                'error_summary' => $exception->getMessage(),
            ]);
        }

        return $batch->fresh();
    }

    private function storeFile(UploadedFile $file)
    {
        $extension = $file->getClientOriginalExtension() ?: 'xlsx';
        $directory = 'imports/' . date('Y/m');
        $name = (string) Str::uuid() . '.' . $extension;

        return $file->storeAs($directory, $name, 'local');
    }

    private function isEmptyRow(array $row)
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run:

```bash
php artisan test --filter=ImportExcelPreviewTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

Run:

```bash
git status --short
git add app/Imports/PermitExcelArrayImport.php app/Services/Imports/PermitExcelImportService.php tests/Feature/ImportExcelPreviewTest.php
git commit -m "feat: add sirika excel preview service"
```

Expected: commit contains only import class, preview service, and preview tests.

---

### Task 4: Import Upload and Preview UI

**Files:**
- Create: `app/Http/Requests/StoreImportRequest.php`
- Modify: `app/Http/Controllers/ImportController.php`
- Modify: `routes/web.php`
- Replace: `resources/views/imports/index.blade.php`
- Create: `resources/views/imports/show.blade.php`
- Modify: `resources/css/app.css`
- Test: `tests/Feature/ImportExcelPreviewHttpTest.php`

**Interfaces:**
- Consumes `PermitExcelImportService::preview()`.
- Produces routes `imports.store` and `imports.show`.
- Produces upload form and preview page.

- [ ] **Step 1: Write failing HTTP tests**

Create `tests/Feature/ImportExcelPreviewHttpTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportExcelPreviewHttpTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_view_import_page_with_upload_form()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)->get(route('imports.index'))
            ->assertOk()
            ->assertSee('Upload Excel')
            ->assertSee('Daftar Batch Import')
            ->assertSee('name="file"', false);
    }

    /** @test */
    public function security_cannot_upload_import_file()
    {
        Storage::fake('local');

        $security = User::factory()->create([
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($security)->post(route('imports.store'), [
            'file' => UploadedFile::fake()->create('sample.xlsx', 10),
        ])->assertForbidden();
    }

    /** @test */
    public function non_excel_upload_is_rejected()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)->from(route('imports.index'))->post(route('imports.store'), [
            'file' => UploadedFile::fake()->create('sample.txt', 10, 'text/plain'),
        ])->assertRedirect(route('imports.index'))
            ->assertSessionHasErrors('file');
    }

    /** @test */
    public function admin_can_view_import_batch_preview()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $batch = ImportBatch::create([
            'filename' => 'sample.xlsx',
            'uploaded_by' => $admin->id,
            'total_rows' => 0,
            'status' => ImportBatch::STATUS_PREVIEWED,
        ]);

        $this->actingAs($admin)->get(route('imports.show', $batch))
            ->assertOk()
            ->assertSee('Preview Import')
            ->assertSee('sample.xlsx');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run:

```bash
php artisan test --filter=ImportExcelPreviewHttpTest
```

Expected: FAIL because routes, request, controller methods, and show view do not exist.

- [ ] **Step 3: Create upload FormRequest**

Create `app/Http/Requests/StoreImportRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() !== null && $this->user()->canAccessRoute('imports.index');
    }

    public function rules()
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ];
    }

    public function messages()
    {
        return [
            'file.required' => 'File Excel wajib dipilih.',
            'file.mimes' => 'File harus berformat .xlsx atau .xls.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ];
    }
}
```

- [ ] **Step 4: Update ImportController**

Replace `app/Http/Controllers/ImportController.php` with:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Models\ImportBatch;
use App\Services\Imports\PermitExcelImportService;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function index()
    {
        return view('imports.index', [
            'batches' => ImportBatch::with('uploader')->latest()->paginate(15),
        ]);
    }

    public function store(StoreImportRequest $request, PermitExcelImportService $service)
    {
        $batch = $service->preview($request->file('file'), $request->user());

        if ($batch->status === ImportBatch::STATUS_FAILED) {
            return redirect()
                ->route('imports.show', $batch)
                ->with('status', 'File berhasil diterima, tetapi parsing gagal. Periksa detail error batch.');
        }

        return redirect()
            ->route('imports.show', $batch)
            ->with('status', 'File berhasil diproses. Periksa preview sebelum commit data.');
    }

    public function show(Request $request, ImportBatch $importBatch)
    {
        $status = $request->query('status');

        $rowsQuery = $importBatch->rows()->orderBy('row_number');
        if (in_array($status, ['valid', 'invalid', 'needs_review', 'committed'], true)) {
            $rowsQuery->where('status', $status);
        }

        return view('imports.show', [
            'batch' => $importBatch->load('uploader'),
            'rows' => $rowsQuery->paginate(50)->appends($request->query()),
            'selectedStatus' => $status,
        ]);
    }
}
```

- [ ] **Step 5: Update routes**

Modify the existing import route group in `routes/web.php`:

```php
Route::middleware('role:' . implode(',', User::rolesForRoute('imports.index')))->group(function () {
    Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
    Route::get('/imports/{importBatch}', [ImportController::class, 'show'])->name('imports.show');
});
```

- [ ] **Step 6: Replace imports index view**

Replace `resources/views/imports/index.blade.php` with:

```blade
@extends('layouts.app')

@php
    $pageTitle = 'Import Excel';
    $pageDescription = 'Upload database izin kendaraan, validasi isi file, lalu commit data yang aman.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Upload Excel</h2>
            <p class="panel-subtitle">Format yang diterima: .xlsx atau .xls, maksimal 10 MB. Data akan masuk preview terlebih dahulu.</p>

            <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="form-stack">
                @csrf
                <div class="form-field">
                    <label for="file">File Excel</label>
                    <input id="file" name="file" type="file" accept=".xlsx,.xls" required>
                    @error('file')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <button class="button button-primary" type="submit">Upload dan Preview</button>
            </form>
        </div>
    </section>

    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Daftar Batch Import</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Valid</th>
                            <th>Invalid</th>
                            <th>Review</th>
                            <th>Uploader</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($batches as $batch)
                            <tr>
                                <td>{{ $batch->filename }}</td>
                                <td><span class="status-pill">{{ $batch->status }}</span></td>
                                <td>{{ $batch->total_rows }}</td>
                                <td>{{ $batch->success_rows }}</td>
                                <td>{{ $batch->failed_rows }}</td>
                                <td>{{ $batch->review_rows }}</td>
                                <td>{{ optional($batch->uploader)->name ?? '-' }}</td>
                                <td><a class="button" href="{{ route('imports.show', $batch) }}">Preview</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">Belum ada batch import.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap">
                {{ $batches->links() }}
            </div>
        </div>
    </section>
@endsection
```

- [ ] **Step 7: Create preview view**

Create `resources/views/imports/show.blade.php`:

```blade
@extends('layouts.app')

@php
    $pageTitle = 'Preview Import';
    $pageDescription = 'Periksa hasil staging sebelum data izin ditulis permanen.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">{{ $batch->filename }}</h2>
            <p class="panel-subtitle">Status batch: <strong>{{ $batch->status }}</strong></p>

            @if ($batch->error_summary)
                <x-alert type="warning">{{ $batch->error_summary }}</x-alert>
            @endif

            <div class="stats-grid">
                <x-stat-card label="Total Row" :value="$batch->total_rows" />
                <x-stat-card label="Valid" :value="$batch->success_rows" />
                <x-stat-card label="Invalid" :value="$batch->failed_rows" />
                <x-stat-card label="Needs Review" :value="$batch->review_rows" />
            </div>
        </div>
    </section>

    <section class="page-section panel">
        <div class="panel-body">
            <div class="toolbar">
                <a class="button" href="{{ route('imports.show', $batch) }}">Semua</a>
                <a class="button" href="{{ route('imports.show', [$batch, 'status' => 'valid']) }}">Valid</a>
                <a class="button" href="{{ route('imports.show', [$batch, 'status' => 'needs_review']) }}">Needs Review</a>
                <a class="button" href="{{ route('imports.show', [$batch, 'status' => 'invalid']) }}">Invalid</a>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Row</th>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>Plat</th>
                            <th>Parkir</th>
                            <th>Rute</th>
                            <th>Warna</th>
                            <th>Status</th>
                            <th>Issue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php
                                $data = $row->normalized_data ?: [];
                                $issues = array_merge($row->errors ?: [], $row->warnings ?: []);
                            @endphp
                            <tr>
                                <td>{{ $row->row_number }}</td>
                                <td>{{ $data['nik'] ?? '-' }}</td>
                                <td>{{ $data['employee_name'] ?? '-' }}</td>
                                <td>{{ $data['plate_number'] ?? '-' }}</td>
                                <td>{{ $data['parking_location_code'] ?? '-' }}</td>
                                <td>{{ $data['route_raw'] ?? '-' }}</td>
                                <td>{{ $data['permit_color'] ?? '-' }}</td>
                                <td><span class="status-pill">{{ $row->status }}</span></td>
                                <td>{{ $issues ? implode('; ', $issues) : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">Tidak ada row untuk filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap">
                {{ $rows->links() }}
            </div>
        </div>
    </section>
@endsection
```

- [ ] **Step 8: Add small CSS utilities**

Append to `resources/css/app.css`:

```css
.form-stack {
    display: grid;
    gap: 14px;
}

.form-field {
    display: grid;
    gap: 6px;
}

.form-field label {
    font-weight: 700;
}

.form-field input[type="file"] {
    max-width: 520px;
    padding: 10px;
    border: 1px solid var(--sirika-border);
    border-radius: 8px;
    background: var(--sirika-surface);
}

.field-error {
    color: #b91c1c;
    font-size: 13px;
}

.toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 14px;
}

.pagination-wrap {
    margin-top: 14px;
}
```

Run:

```bash
npm run dev
```

Expected: Laravel Mix compiles successfully and public assets update if content changed.

- [ ] **Step 9: Run tests to verify they pass**

Run:

```bash
php artisan test --filter=ImportExcelPreviewHttpTest
```

Expected: PASS.

- [ ] **Step 10: Commit**

Run:

```bash
git status --short
git add app/Http/Requests/StoreImportRequest.php app/Http/Controllers/ImportController.php routes/web.php resources/views/imports/index.blade.php resources/views/imports/show.blade.php resources/css/app.css public/css/app.css public/js/app.js public/mix-manifest.json tests/Feature/ImportExcelPreviewHttpTest.php
git commit -m "feat: add sirika import preview UI"
```

Expected: commit contains only upload request, import controller/routes/views, CSS/assets, and HTTP tests.

---

### Task 5: Import Commit Service and Route

**Files:**
- Create: `app/Services/Imports/PermitImportCommitService.php`
- Modify: `app/Http/Controllers/ImportController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/imports/show.blade.php`
- Create: `tests/Feature/ImportCommitTest.php`

**Interfaces:**
- Produces `PermitImportCommitService::commit(ImportBatch $batch): ImportBatch`.
- Produces route `imports.commit`.
- Consumes staged `ImportRow` records.
- Writes final `employees`, `vehicles`, `parking_locations`, `vehicle_permits`, and `permit_route_segments`.

- [ ] **Step 1: Write failing commit tests**

Create `tests/Feature/ImportCommitTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\RoadSegment;
use App\Models\User;
use App\Models\VehiclePermit;
use App\Services\Imports\PermitImportCommitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportCommitTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_commits_valid_rows_to_final_tables()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_HR, 'status' => User::STATUS_ACTIVE]);
        $this->segment('Y1');
        $this->segment('D2');

        $batch = $this->batch($admin);
        $this->row($batch, ImportRow::STATUS_VALID, [
            'nik' => '200115677',
            'employee_name' => 'FITRIAWATI',
            'department' => 'GENERAL AFFAIR',
            'section' => 'GA KANTOR',
            'position' => 'ADMIN',
            'division' => 'GENERAL AFFAIR',
            'contact_number' => '0812',
            'plate_number' => 'DT 4423 CI',
            'parking_location_code' => 'GA-MES1-P01',
            'route_raw' => 'Y1→D2→GA-MES1-P01',
            'route_segment_codes' => ['Y1', 'D2'],
            'reason' => 'OFFICE',
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'notes' => '',
        ]);

        app(PermitImportCommitService::class)->commit($batch);

        $this->assertDatabaseHas('employees', ['nik' => '200115677', 'name' => 'FITRIAWATI']);
        $this->assertDatabaseHas('vehicles', ['plate_number' => 'DT 4423 CI']);
        $this->assertDatabaseHas('parking_locations', ['code' => 'GA-MES1-P01']);
        $this->assertDatabaseHas('vehicle_permits', [
            'permit_color' => 'biru',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'import',
            'source_import_id' => $batch->id,
        ]);
        $this->assertSame(2, $batch->fresh()->permits()->first()->permitRouteSegments()->count());
        $this->assertSame(ImportBatch::STATUS_COMMITTED, $batch->fresh()->status);
        $this->assertSame(ImportRow::STATUS_COMMITTED, $batch->rows()->first()->status);
    }

    /** @test */
    public function it_does_not_commit_invalid_rows()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_HR, 'status' => User::STATUS_ACTIVE]);
        $batch = $this->batch($admin);
        $this->row($batch, ImportRow::STATUS_INVALID, [
            'nik' => '200115677',
            'employee_name' => 'FITRIAWATI',
            'plate_number' => '',
        ], ['Plat motor wajib diisi']);

        app(PermitImportCommitService::class)->commit($batch);

        $this->assertDatabaseMissing('employees', ['nik' => '200115677']);
        $this->assertSame(ImportBatch::STATUS_COMMITTED, $batch->fresh()->status);
        $this->assertSame(ImportRow::STATUS_INVALID, $batch->rows()->first()->status);
    }

    /** @test */
    public function needs_review_rows_create_needs_review_permits_without_active_status()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_HR, 'status' => User::STATUS_ACTIVE]);
        $batch = $this->batch($admin);
        $this->row($batch, ImportRow::STATUS_NEEDS_REVIEW, [
            'nik' => '211129282',
            'employee_name' => 'HARLINA',
            'department' => 'GENERAL AFFAIR',
            'section' => 'GA KANTOR',
            'position' => 'ADMIN',
            'division' => 'GENERAL AFFAIR',
            'contact_number' => '0813',
            'plate_number' => 'DT 4714 BO',
            'parking_location_code' => '',
            'route_raw' => '',
            'route_segment_codes' => [],
            'reason' => 'OFFICE',
            'permit_color' => 'kuning',
            'approval_status' => 'approved',
            'notes' => '',
        ], [], ['Rute kendaraan kosong']);

        app(PermitImportCommitService::class)->commit($batch);

        $this->assertDatabaseHas('vehicle_permits', [
            'permit_color' => 'kuning',
            'status' => VehiclePermit::STATUS_NEEDS_REVIEW,
            'source_import_id' => $batch->id,
        ]);
    }

    /** @test */
    public function committed_batch_cannot_be_committed_again()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_HR, 'status' => User::STATUS_ACTIVE]);
        $batch = $this->batch($admin);
        $batch->update(['status' => ImportBatch::STATUS_COMMITTED]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Batch sudah pernah dikomit.');

        app(PermitImportCommitService::class)->commit($batch);
    }

    private function batch(User $admin)
    {
        return ImportBatch::create([
            'filename' => 'sample.xlsx',
            'uploaded_by' => $admin->id,
            'total_rows' => 1,
            'success_rows' => 1,
            'status' => ImportBatch::STATUS_PREVIEWED,
        ]);
    }

    private function row(ImportBatch $batch, $status, array $normalized, array $errors = [], array $warnings = [])
    {
        return ImportRow::create([
            'import_batch_id' => $batch->id,
            'row_number' => $batch->rows()->count() + 5,
            'status' => $status,
            'raw_data' => $normalized,
            'normalized_data' => $normalized,
            'errors' => $errors,
            'warnings' => $warnings,
        ]);
    }

    private function segment($code)
    {
        return RoadSegment::create([
            'code' => $code,
            'name' => 'Jalan ' . $code,
            'status' => 'active',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
php artisan test --filter=ImportCommitTest
```

Expected: FAIL because `PermitImportCommitService` does not exist.

- [ ] **Step 3: Create commit service**

Create `app/Services/Imports/PermitImportCommitService.php`:

```php
<?php

namespace App\Services\Imports;

use App\Models\Employee;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\ParkingLocation;
use App\Models\RoadSegment;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PermitImportCommitService
{
    public function commit(ImportBatch $batch)
    {
        return DB::transaction(function () use ($batch) {
            $lockedBatch = ImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($lockedBatch->status === ImportBatch::STATUS_COMMITTED) {
                throw new RuntimeException('Batch sudah pernah dikomit.');
            }

            if ($lockedBatch->status !== ImportBatch::STATUS_PREVIEWED) {
                throw new RuntimeException('Batch belum siap dikomit.');
            }

            $rows = $lockedBatch->rows()
                ->whereIn('status', [ImportRow::STATUS_VALID, ImportRow::STATUS_NEEDS_REVIEW])
                ->orderBy('row_number')
                ->get();

            foreach ($rows as $row) {
                $this->commitRow($lockedBatch, $row);
            }

            $lockedBatch->update(['status' => ImportBatch::STATUS_COMMITTED]);

            return $lockedBatch->fresh();
        });
    }

    private function commitRow(ImportBatch $batch, ImportRow $row)
    {
        $data = $row->normalized_data ?: [];

        if (!$this->hasMinimumData($data)) {
            return;
        }

        $employee = Employee::firstOrCreate(
            ['nik' => $data['nik']],
            [
                'name' => $data['employee_name'],
                'department' => $data['department'] ?? null,
                'section' => $data['section'] ?? null,
                'position' => $data['position'] ?? null,
                'division' => $data['division'] ?? null,
                'contact_number' => $data['contact_number'] ?? null,
                'status' => 'active',
            ]
        );

        $vehicle = Vehicle::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'plate_number' => $data['plate_number'],
            ],
            [
                'vehicle_type' => 'motorcycle',
                'status' => 'active',
            ]
        );

        $parking = null;
        if (!empty($data['parking_location_code'])) {
            $parking = ParkingLocation::firstOrCreate(
                ['code' => $data['parking_location_code']],
                ['name' => $data['parking_location_code'], 'status' => 'active']
            );
        }

        $permitStatus = $row->status === ImportRow::STATUS_VALID
            ? VehiclePermit::STATUS_ACTIVE
            : VehiclePermit::STATUS_NEEDS_REVIEW;

        if ($this->hasExistingActivePermit($vehicle->id)) {
            $permitStatus = VehiclePermit::STATUS_NEEDS_REVIEW;
            $warnings = $row->warnings ?: [];
            $warnings[] = 'Kendaraan sudah memiliki izin aktif, perlu review sebelum aktivasi.';
            $row->warnings = array_values(array_unique($warnings));
        }

        $permit = VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parking ? $parking->id : null,
            'permit_color' => $data['permit_color'] ?? null,
            'reason' => $data['reason'] ?? null,
            'approval_status' => $data['approval_status'] ?? 'approved',
            'valid_from' => null,
            'valid_until' => null,
            'status' => $permitStatus,
            'source' => 'import',
            'source_import_id' => $batch->id,
            'route_raw' => $data['route_raw'] ?? null,
        ]);

        $this->attachRouteSegments($permit, $data['route_segment_codes'] ?? []);

        $row->update([
            'status' => ImportRow::STATUS_COMMITTED,
            'warnings' => $row->warnings ?: [],
            'created_employee_id' => $employee->id,
            'created_vehicle_id' => $vehicle->id,
            'created_permit_id' => $permit->id,
        ]);
    }

    private function hasMinimumData(array $data)
    {
        return !empty($data['nik'])
            && !empty($data['employee_name'])
            && !empty($data['plate_number']);
    }

    private function hasExistingActivePermit($vehicleId)
    {
        return VehiclePermit::where('vehicle_id', $vehicleId)
            ->where('status', VehiclePermit::STATUS_ACTIVE)
            ->exists();
    }

    private function attachRouteSegments(VehiclePermit $permit, array $codes)
    {
        $segments = RoadSegment::whereIn('code', $codes)->get()->keyBy('code');

        $sequence = 1;
        foreach ($codes as $code) {
            if (!isset($segments[$code])) {
                continue;
            }

            $permit->permitRouteSegments()->create([
                'road_segment_id' => $segments[$code]->id,
                'sequence' => $sequence,
            ]);

            $sequence++;
        }
    }
}
```

- [ ] **Step 4: Add commit route and controller action**

Modify `app/Http/Controllers/ImportController.php` by importing the service:

```php
use App\Services\Imports\PermitImportCommitService;
use RuntimeException;
```

Add this method:

```php
public function commit(ImportBatch $importBatch, PermitImportCommitService $service)
{
    try {
        $service->commit($importBatch);
    } catch (RuntimeException $exception) {
        return redirect()
            ->route('imports.show', $importBatch)
            ->with('status', $exception->getMessage());
    }

    return redirect()
        ->route('imports.show', $importBatch)
        ->with('status', 'Batch import berhasil dikomit.');
}
```

Modify the import route group in `routes/web.php`:

```php
Route::middleware('role:' . implode(',', User::rolesForRoute('imports.index')))->group(function () {
    Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
    Route::get('/imports/{importBatch}', [ImportController::class, 'show'])->name('imports.show');
    Route::post('/imports/{importBatch}/commit', [ImportController::class, 'commit'])->name('imports.commit');
});
```

- [ ] **Step 5: Add commit button to preview**

In `resources/views/imports/show.blade.php`, inside the first panel after the stats grid, add:

```blade
@if ($batch->status === \App\Models\ImportBatch::STATUS_PREVIEWED && ($batch->success_rows + $batch->review_rows) > 0)
    <form method="POST" action="{{ route('imports.commit', $batch) }}" style="margin-top: 16px;">
        @csrf
        <button class="button button-primary" type="submit">Commit Data Aman</button>
    </form>
@endif
```

- [ ] **Step 6: Run tests to verify they pass**

Run:

```bash
php artisan test --filter=ImportCommitTest
php artisan test --filter=ImportExcelPreviewHttpTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

Run:

```bash
git status --short
git add app/Services/Imports/PermitImportCommitService.php app/Http/Controllers/ImportController.php routes/web.php resources/views/imports/show.blade.php tests/Feature/ImportCommitTest.php
git commit -m "feat: commit sirika import rows to permits"
```

Expected: commit contains only commit service, route/controller/view changes, and commit tests.

---

### Task 6: Imported Permit List and Dashboard Counts

**Files:**
- Modify: `app/Http/Controllers/PermitController.php`
- Replace: `resources/views/permits/index.blade.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Create: `tests/Feature/PermitListAfterImportTest.php`

**Interfaces:**
- Consumes final permit data created by Task 5.
- Produces a read-only permit list at `permits.index`.
- Produces dashboard counts from real `vehicle_permits` rows.

- [ ] **Step 1: Write failing permit list test**

Create `tests/Feature/PermitListAfterImportTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitListAfterImportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_view_imported_permits()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $employee = Employee::create([
            'nik' => '200115677',
            'name' => 'FITRIAWATI',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 4423 CI',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $parking = ParkingLocation::create([
            'code' => 'GA-MES1-P01',
            'name' => 'GA-MES1-P01',
            'status' => 'active',
        ]);

        VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parking->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'import',
            'route_raw' => 'Y1→D2',
        ]);

        $this->actingAs($admin)->get(route('permits.index'))
            ->assertOk()
            ->assertSee('Izin Kendaraan')
            ->assertSee('FITRIAWATI')
            ->assertSee('DT 4423 CI')
            ->assertSee('GA-MES1-P01')
            ->assertSee('active');
    }

    /** @test */
    public function dashboard_uses_real_permit_counts()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $employee = Employee::create(['nik' => '200115677', 'name' => 'FITRIAWATI', 'status' => 'active']);
        $vehicle = Vehicle::create(['employee_id' => $employee->id, 'plate_number' => 'DT 4423 CI', 'vehicle_type' => 'motorcycle', 'status' => 'active']);

        VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'import',
        ]);

        VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'kuning',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_NEEDS_REVIEW,
            'source' => 'import',
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Izin Aktif')
            ->assertSee('1')
            ->assertSee('Perlu Review')
            ->assertSee('1');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
php artisan test --filter=PermitListAfterImportTest
```

Expected: FAIL because `permits.index` still shows Phase 1 read-only module copy and dashboard note still says no permit data.

- [ ] **Step 3: Update PermitController**

Replace `app/Http/Controllers/PermitController.php` with:

```php
<?php

namespace App\Http\Controllers;

use App\Models\VehiclePermit;

class PermitController extends Controller
{
    public function index()
    {
        return view('permits.index', [
            'permits' => VehiclePermit::with(['employee', 'vehicle', 'parkingLocation'])
                ->latest()
                ->paginate(25),
        ]);
    }
}
```

- [ ] **Step 4: Replace permit list view**

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
            <h2 class="panel-title">Daftar Izin</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>Plat</th>
                            <th>Parkir</th>
                            <th>Warna</th>
                            <th>Status</th>
                            <th>Sumber</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($permits as $permit)
                            <tr>
                                <td>{{ optional($permit->employee)->nik ?? '-' }}</td>
                                <td>{{ optional($permit->employee)->name ?? '-' }}</td>
                                <td>{{ optional($permit->vehicle)->plate_number ?? '-' }}</td>
                                <td>{{ optional($permit->parkingLocation)->code ?? '-' }}</td>
                                <td>{{ $permit->permit_color ?? '-' }}</td>
                                <td><span class="status-pill">{{ $permit->status }}</span></td>
                                <td>{{ $permit->source }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">Belum ada data izin kendaraan. Gunakan modul Import Excel untuk membuat data awal.</td>
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

- [ ] **Step 5: Update dashboard counts**

Modify `app/Http/Controllers/DashboardController.php` so active and review permit counts are real:

```php
<?php

namespace App\Http\Controllers;

use App\Models\RoadSegment;
use App\Models\ScanLog;
use App\Models\User;
use App\Models\VehiclePermit;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.index', [
            'activeRoadSegments' => RoadSegment::where('status', 'active')->count(),
            'activeUsers' => User::where('status', User::STATUS_ACTIVE)->count(),
            'activePermits' => VehiclePermit::where('status', VehiclePermit::STATUS_ACTIVE)->count(),
            'reviewPermits' => VehiclePermit::where('status', VehiclePermit::STATUS_NEEDS_REVIEW)->count(),
            'todayScans' => ScanLog::whereDate('scanned_at', now()->toDateString())->count(),
        ]);
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run:

```bash
php artisan test --filter=PermitListAfterImportTest
php artisan test --filter=DashboardUiTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

Run:

```bash
git status --short
git add app/Http/Controllers/PermitController.php resources/views/permits/index.blade.php app/Http/Controllers/DashboardController.php tests/Feature/PermitListAfterImportTest.php
git commit -m "feat: show imported sirika permits"
```

Expected: commit contains only permit list, dashboard count, and tests.

---

### Task 7: Full Phase 2 Verification

**Files:**
- Modify only if a verification failure identifies a concrete bug in files changed by Tasks 1-6.

**Interfaces:**
- Consumes all previous Phase 2 tasks.
- Produces a verified Phase 2 implementation ready to push or merge.

- [ ] **Step 1: Run full PHP test suite**

Run:

```bash
php artisan test
```

Expected: PASS for all Unit and Feature tests.

- [ ] **Step 2: Verify migration and seed from empty database**

Run:

```bash
php artisan migrate:fresh --seed
```

Expected: all migrations complete, four starter users exist, and 26 road segments are seeded.

- [ ] **Step 3: Verify route registration**

Run:

```bash
php artisan route:list --columns=method,uri,name,action,middleware
```

Expected output includes:

```text
GET|HEAD imports imports.index
POST imports imports.store
GET|HEAD imports/{importBatch} imports.show
POST imports/{importBatch}/commit imports.commit
GET|HEAD permits permits.index
```

- [ ] **Step 4: Verify frontend build**

Run:

```bash
npm run dev
```

Expected: Laravel Mix compiles successfully.

- [ ] **Step 5: Manual smoke test with sample Excel**

Start or reuse the local server:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Open:

```text
http://127.0.0.1:8000/login
```

Login:

```text
Email: superadmin@sirika.local
Password: password
```

Upload this sample file:

```text
C:\Users\New Owner\Documents\SURAT IZIN MASUK KENDARAAN\DATABASE IZIN MASUK KENDARAAN.xlsx
```

Expected:

- `/imports` shows upload form.
- Upload redirects to `/imports/{id}`.
- Preview shows a batch with approximately 477 row records.
- Preview includes `valid`, `invalid`, and `needs_review` rows.
- Commit button appears for a previewed batch.
- Commit creates final permit records without processing invalid rows.
- `/permits` shows imported permit data.
- Dashboard permit counts are no longer hardcoded zeros after commit.

- [ ] **Step 6: Check production-sensitive dependency audit**

Run:

```bash
npm audit --omit=dev
```

Expected: `found 0 vulnerabilities`.

Run:

```bash
composer audit
```

Expected: no direct production package advisories. If `composer audit` is unsupported by the local Composer version, record the exact command output in the final report.

- [ ] **Step 7: Review git status**

Run:

```bash
git status --short --branch
```

Expected: clean working tree on the Phase 2 branch or `main`, depending on execution strategy.

- [ ] **Step 8: Stop on verification failure**

If any verification command fails, identify the owner task from the file list above, apply the smallest safe fix in that task's files, rerun the task-specific test, then rerun Task 7 from Step 1. Do not claim Phase 2 is complete until all verification commands have fresh passing output.
