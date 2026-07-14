# Export Izin Perlu Review dengan Validasi Rute Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyediakan export XLSX khusus izin perlu review yang menandai kode rute tidak tersedia pada Master Rute aktif.

**Architecture:** Endpoint baru mengunci filter status menjadi `needs_review`. `PermitNeedsReviewExport` memuat kode `RoadSegment` aktif sekali, menggunakan `RouteSegmentParser` untuk membaca rute, menghasilkan kolom diagnostik, dan memberi styling berbasis baris. Database tidak diubah.

**Tech Stack:** Laravel 8, PHP 7.3+, PHPUnit 9, Maatwebsite Excel 3.1, PhpSpreadsheet.

## Global Constraints

- Acuan rute tersedia wajib `road_segments.status = active`.
- Endpoint memakai middleware peran `reports.permits.export` yang sudah berlaku.
- Export wajib selalu membatasi `VehiclePermit::STATUS_NEEDS_REVIEW`, apa pun query `status` dari pengguna.
- Tidak boleh mengekspor `permit_tokens.token_hash` atau data QR sensitif.
- Tidak ada migration atau perubahan data produksi.

---

### Task 1: Uji kontrak endpoint dan export

**Files:**
- Modify/Test: `tests/Feature/PermitReportHttpTest.php`

**Interfaces:**
- Consumes: route `reports.permits.needs-review.export`.
- Produces: kontrak untuk `PermitNeedsReviewExport`.

- [ ] **Step 1: Tulis test gagal**

Tambahkan import `PermitNeedsReviewExport` dan `RoadSegment`, lalu test:

```php
/** @test */
public function needs_review_export_only_includes_needs_review_permits_and_flags_unavailable_routes()
{
    Carbon::setTestNow('2026-07-14 10:00:00');
    Excel::fake();

    $admin = $this->user(User::ROLE_ADMIN_HR);
    RoadSegment::create(['code' => 'Y1', 'name' => 'Y1', 'status' => RoadSegment::STATUS_ACTIVE]);
    RoadSegment::create(['code' => 'D2', 'name' => 'D2', 'status' => RoadSegment::STATUS_INACTIVE]);
    $review = $this->permit(['name' => 'PERLU REVIEW', 'status' => VehiclePermit::STATUS_NEEDS_REVIEW, 'route_raw' => 'Y1 -> D2 -> X99']);
    $active = $this->permit(['name' => 'AKTIF', 'status' => VehiclePermit::STATUS_ACTIVE, 'route_raw' => 'Y1']);

    $this->actingAs($admin)->get(route('reports.permits.needs-review.export', ['status' => VehiclePermit::STATUS_ACTIVE]));

    Excel::assertDownloaded('sirika-izin-perlu-review-20260714-100000.xlsx', function (PermitNeedsReviewExport $export) use ($review, $active) {
        $rows = $export->query()->get();
        $this->assertTrue($rows->contains('id', $review->id));
        $this->assertFalse($rows->contains('id', $active->id));

        $mapped = $export->map($review->fresh(['employee', 'vehicle', 'parkingLocation', 'reviewer']));
        $this->assertContains('D2, X99', $mapped);
        $this->assertContains('Perlu perbaikan rute', $mapped);

        return true;
    });
}
```

- [ ] **Step 2: Pastikan test merah**

Run: `php artisan test tests/Feature/PermitReportHttpTest.php --filter=needs_review_export_only_includes`

Expected: FAIL karena route belum ada.

- [ ] **Step 3: Commit test**

```powershell
git add tests/Feature/PermitReportHttpTest.php
git commit -m "test: define needs review route export"
```

### Task 2: Endpoint dan export validator

**Files:**
- Create: `app/Exports/PermitNeedsReviewExport.php`
- Modify: `app/Http/Controllers/ReportPermitController.php`
- Modify: `routes/web.php`
- Modify/Test: `tests/Feature/PermitReportHttpTest.php`

**Interfaces:**
- Consumes: `PermitReportQuery::query(array $filters)`, `RouteSegmentParser::parse($rawRoute, array $activeCodes)`.
- Produces: `PermitNeedsReviewExport` (`FromQuery`, `WithHeadings`, `WithMapping`, `WithEvents`, `ShouldAutoSize`) dan route GET bernama `reports.permits.needs-review.export`.

- [ ] **Step 1: Implementasi minimum route dan controller**

Tambahkan import `PermitNeedsReviewExport` ke controller serta method berikut:

```php
public function exportNeedsReview(ReportPermitRequest $request, PermitReportQuery $reports)
{
    $filters = $reports->filters($request->validated());
    $filters['status'] = VehiclePermit::STATUS_NEEDS_REVIEW;

    return Excel::download(
        new PermitNeedsReviewExport($reports, $filters),
        'sirika-izin-perlu-review-' . now()->format('Ymd-His') . '.xlsx'
    );
}
```

Tambahkan route setelah route export laporan izin:

```php
Route::get('/reports/permits/export-needs-review', [ReportPermitController::class, 'exportNeedsReview'])
    ->middleware('role:' . implode(',', User::rolesForRoute('reports.permits.export')))
    ->name('reports.permits.needs-review.export');
```

- [ ] **Step 2: Implementasi export minimum**

Di constructor, isi `$activeRouteCodes` dari `RoadSegment::query()->where('status', RoadSegment::STATUS_ACTIVE)->pluck('code')->map('strtoupper')->all()`. Untuk setiap permit, gunakan parser lalu token regex `/[A-Z]{1,3}\d{1,2}/i` setelah menghapus pola parkir `/[A-Z]{2,}-[A-Z0-9]+-P\d+/i`. Token yang tidak ada pada daftar aktif menjadi `Rute Tidak Tersedia`. `map()` wajib menyertakan kolom `Rute Mentah`, `Rute Tidak Tersedia`, dan `Status Validasi Rute`; status adalah `Perlu perbaikan rute` bila ada token tak tersedia atau warning parser, jika tidak `Rute tersedia`.

- [ ] **Step 3: Pastikan test hijau**

Run: `php artisan test tests/Feature/PermitReportHttpTest.php --filter=needs_review_export_only_includes`

Expected: PASS.

- [ ] **Step 4: Tambahkan test rute aktif**

Buat permit `needs_review` dengan `route_raw` `Y1`; assert hasil `map()` memuat `Rute tersedia` dan `-`, serta tidak memuat `Perlu perbaikan rute`.

- [ ] **Step 5: Jalankan test export terkait**

Run: `php artisan test tests/Feature/PermitReportHttpTest.php --filter="needs_review_export|permit_report_export"`

Expected: PASS.

- [ ] **Step 6: Commit**

```powershell
git add app/Exports/PermitNeedsReviewExport.php app/Http/Controllers/ReportPermitController.php routes/web.php tests/Feature/PermitReportHttpTest.php
git commit -m "feat: export permits requiring route review"
```

### Task 3: Highlight XLSX dan tombol laporan

**Files:**
- Modify: `app/Exports/PermitNeedsReviewExport.php`
- Modify: `resources/views/reports/permits/index.blade.php`
- Modify/Test: `tests/Feature/PermitReportHttpTest.php`

**Interfaces:**
- Consumes: `PermitNeedsReviewExport::registerEvents(): array` dan route export baru.
- Produces: highlight kuning pada rute bermasalah, merah muda pada status, dan tombol yang mempertahankan query filter.

- [ ] **Step 1: Tulis test gagal tombol dan event**

Tambahkan assertion:

```php
$this->actingAs($this->user(User::ROLE_ADMIN_HR))
    ->get(route('reports.permits.index'))
    ->assertOk()
    ->assertSee(route('reports.permits.needs-review.export'))
    ->assertSee('Export Perlu Review');
```

Dalam test export, panggil `$events = $export->registerEvents();` dan assert key `\Maatwebsite\Excel\Events\AfterSheet::class` tersedia.

- [ ] **Step 2: Pastikan test merah**

Run: `php artisan test tests/Feature/PermitReportHttpTest.php --filter=needs_review_export`

Expected: FAIL karena view atau event belum tersedia.

- [ ] **Step 3: Implementasi styling dan tombol**

Dengan event `AfterSheet`, iterasi baris mulai 2. Jika kolom status validasi bernilai `Perlu perbaikan rute`, beri fill solid `FFFDE68A` pada sel `Rute Mentah` dan `FFFBCFE8` pada sel status menggunakan `PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID`. Simpan indeks kolom sebagai konstanta private. Tambahkan anchor kedua di header halaman:

```blade
<a class="button button-primary" href="{{ route('reports.permits.needs-review.export', request()->query()) }}">Export Perlu Review</a>
```

- [ ] **Step 4: Jalankan test hijau**

Run: `php artisan test tests/Feature/PermitReportHttpTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```powershell
git add app/Exports/PermitNeedsReviewExport.php resources/views/reports/permits/index.blade.php tests/Feature/PermitReportHttpTest.php
git commit -m "feat: highlight unavailable routes in review export"
```

### Task 4: Verifikasi regresi

**Files:**
- Verify only: `tests/Feature/PermitReportHttpTest.php`, `tests/Unit/RouteSegmentParserTest.php`

- [ ] **Step 1: Jalankan test terkait**

Run: `php artisan test tests/Feature/PermitReportHttpTest.php tests/Unit/RouteSegmentParserTest.php`

Expected: PASS tanpa failure atau warning.

- [ ] **Step 2: Jalankan suite penuh**

Run: `php artisan test`

Expected: PASS seluruh suite.

- [ ] **Step 3: Periksa kualitas perubahan**

Run: `git diff --check; git status --short`

Expected: tidak ada whitespace error dan tidak ada perubahan tak disengaja.

