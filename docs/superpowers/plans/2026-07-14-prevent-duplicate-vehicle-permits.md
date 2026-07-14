# Pencegahan Izin Kendaraan Duplikat Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mencegah import Excel membuat izin kendaraan ganda untuk pasangan NIK dan plat yang sama, tanpa membatasi NIK dengan plat yang berbeda.

**Architecture:** Preview menormalisasi seluruh baris, membuat identity key NIK + plat, lalu menandai duplikat di file atau yang sudah memiliki izin sebagai invalid sebelum data preview disimpan. Commit mengecek ulang pasangan employee + vehicle di transaction, dan indeks unik database menjaga integritas saat ada proses paralel.

**Tech Stack:** Laravel 8, PHP 7.3+, PHPUnit 9, Eloquent, migrations.

## Global Constraints

- NIK sama dengan plat berbeda tetap diperbolehkan.
- NIK sama dengan plat sama tidak boleh membuat izin kedua.
- Plat yang sama tidak boleh dimiliki NIK berbeda.
- Baris duplikat berstatus `invalid` dan tidak dapat dikomit.
- Migration tidak menghapus atau mengubah data lama; migration gagal dengan pesan aman bila data lama duplikat.
- Tidak ada perubahan terhadap izin lama yang sudah valid.

---

### Task 1: Validasi duplikat saat preview import

**Files:**
- Modify: `app/Services/Imports/PermitExcelImportService.php`
- Modify: `tests/Feature/ImportExcelPreviewTest.php`

**Interfaces:**
- Consumes: hasil `PermitImportRowNormalizer::normalize()` dengan `normalized_data.nik` dan `normalized_data.plate_number`.
- Produces: `ImportRow::STATUS_INVALID` dan error duplikat yang dapat terlihat pada preview.

- [ ] **Step 1: Tulis test gagal duplikat dalam file dan izin lama**

Tambahkan test Excel preview dengan tiga data valid: dua baris NIK `200115677` + plat `DT 4423 CI`, serta satu baris NIK sama + plat `DT 4714 BO`. Assert baris kedua invalid dengan error `NIK dan plat kendaraan duplikat pada baris 5.`, sedangkan plat berbeda valid. Tambahkan fixture employee, vehicle, dan `VehiclePermit` yang sudah tersimpan untuk NIK `200115678` + `DT 4715 BO`; assert baris import yang sama menjadi invalid dengan error `Izin kendaraan untuk NIK dan plat ini sudah terdaftar.`.

- [ ] **Step 2: Pastikan test merah**

Run: `php artisan test tests/Feature/ImportExcelPreviewTest.php --filter=duplicate`

Expected: FAIL karena preview saat ini menyimpan baris duplikat sebagai valid/needs_review.

- [ ] **Step 3: Implementasi validasi preview minimum**

Ubah `PermitExcelImportService::preview()` menjadi dua tahap di dalam transaction:

```php
$normalizedRows = $this->normalizedRows($rows, $header, $activeRouteCodes);
$existingPermitIdentities = $this->existingPermitIdentities($normalizedRows);
$firstRowsByIdentity = [];
foreach ($normalizedRows as $normalized) {
    $identity = $this->permitIdentity($normalized['normalized_data'] ?? []);
    if ($identity !== null && isset($firstRowsByIdentity[$identity])) {
        $normalized['errors'][] = 'NIK dan plat kendaraan duplikat pada baris ' . $firstRowsByIdentity[$identity] . '.';
    } elseif ($identity !== null && isset($existingPermitIdentities[$identity])) {
        $normalized['errors'][] = 'Izin kendaraan untuk NIK dan plat ini sudah terdaftar.';
    }
    if ($identity !== null) {
        $firstRowsByIdentity[$identity] = $firstRowsByIdentity[$identity] ?? $normalized['row_number'];
    }
    if ($normalized['errors'] !== []) {
        $normalized['status'] = ImportRow::STATUS_INVALID;
    }
    // update counts and create ImportRow
}
```

`permitIdentity()` mengembalikan `strtoupper(trim($nik)) . '|' . strtoupper(trim($plate))` hanya bila keduanya tidak kosong. `existingPermitIdentities()` melakukan satu query join antara `vehicle_permits`, `employees`, dan `vehicles`, dibatasi ke NIK dan plat yang muncul pada import; hasilnya dimap ke key yang sama. Jangan query satu kali untuk setiap baris.

- [ ] **Step 4: Jalankan test hijau**

Run: `php artisan test tests/Feature/ImportExcelPreviewTest.php --filter=duplicate`

Expected: PASS.

- [ ] **Step 5: Jalankan seluruh test preview**

Run: `php artisan test tests/Feature/ImportExcelPreviewTest.php tests/Unit/PermitImportRowNormalizerTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```powershell
git add app/Services/Imports/PermitExcelImportService.php tests/Feature/ImportExcelPreviewTest.php
git commit -m "feat: reject duplicate permits during preview"
```

### Task 2: Proteksi commit dan indeks database

**Files:**
- Create: `database/migrations/2026_07_14_000003_add_unique_employee_vehicle_to_vehicle_permits_table.php`
- Modify: `app/Services/Imports/PermitImportCommitService.php`
- Modify: `tests/Feature/ImportCommitTest.php`
- Modify: `tests/Feature/SirikaDomainSchemaTest.php`

**Interfaces:**
- Consumes: pasangan `employee_id` dan `vehicle_id` setelah resolver kendaraan menjalankan lock.
- Produces: indeks unik `vehicle_permits_employee_vehicle_unique` dan penolakan commit aman bila izin telah ada.

- [ ] **Step 1: Tulis test gagal untuk proteksi commit dan migration**

Tambahkan test commit: buat employee/vehicle/permit yang sudah ada, lalu buat `ImportRow::STATUS_VALID` dengan pasangan NIK+plat sama. Saat `PermitImportCommitService::commit()` dipanggil, assert `RuntimeException` berisi `Izin kendaraan untuk NIK dan plat ini sudah terdaftar.`, batch tetap `previewed`, dan jumlah permit tidak bertambah. Tambahkan test migration yang menyisipkan dua `vehicle_permits` dengan pasangan employee_id+vehicle_id sama dan assert migration melempar pesan `Cannot add vehicle_permits_employee_vehicle_unique because duplicate permit rows exist`.

- [ ] **Step 2: Pastikan test merah**

Run: `php artisan test tests/Feature/ImportCommitTest.php tests/Feature/SirikaDomainSchemaTest.php --filter="duplicate|unique"`

Expected: FAIL karena commit masih membuat permit kedua dan indeks belum ada.

- [ ] **Step 3: Implementasi proteksi commit**

Sebelum `VehiclePermit::create()` di `commitRow()`, tambahkan:

```php
if ($this->findExistingPermit($employee->id, $vehicle->id) !== null) {
    throw new RuntimeException('Izin kendaraan untuk NIK dan plat ini sudah terdaftar.');
}
```

Implementasi `findExistingPermit(int $employeeId, int $vehicleId): ?VehiclePermit` harus memakai `where('employee_id', $employeeId)->where('vehicle_id', $vehicleId)->lockForUpdate()->first()`.

- [ ] **Step 4: Implementasi migration aman**

Buat migration yang menjalankan query group-by `employee_id, vehicle_id` pada `vehicle_permits`; jika ada duplikat, lempar `RuntimeException` berisi id pasangan yang konflik. Bila bersih, tambah:

```php
$table->unique(['employee_id', 'vehicle_id'], 'vehicle_permits_employee_vehicle_unique');
```

`down()` hanya menghapus indeks dengan nama tersebut.

- [ ] **Step 5: Jalankan test hijau**

Run: `php artisan test tests/Feature/ImportCommitTest.php tests/Feature/SirikaDomainSchemaTest.php --filter="duplicate|unique"`

Expected: PASS.

- [ ] **Step 6: Jalankan suite import terkait**

Run: `php artisan test tests/Feature/ImportCommitTest.php tests/Feature/ImportExcelPreviewTest.php tests/Feature/VehicleUniqueMigrationTest.php`

Expected: PASS.

- [ ] **Step 7: Commit**

```powershell
git add app/Services/Imports/PermitImportCommitService.php database/migrations/2026_07_14_000003_add_unique_employee_vehicle_to_vehicle_permits_table.php tests/Feature/ImportCommitTest.php tests/Feature/SirikaDomainSchemaTest.php
git commit -m "feat: enforce unique vehicle permits"
```

### Task 3: Verifikasi regresi

**Files:**
- Verify only: `tests/Feature/ImportCommitTest.php`, `tests/Feature/ImportExcelPreviewTest.php`, `tests/Feature/PermitReviewServiceTest.php`

- [ ] **Step 1: Jalankan test import dan review**

Run: `php artisan test tests/Feature/ImportCommitTest.php tests/Feature/ImportExcelPreviewTest.php tests/Feature/PermitReviewServiceTest.php`

Expected: PASS.

- [ ] **Step 2: Jalankan suite penuh**

Run: `$env:CORS_ALLOWED_ORIGINS='https://sirika.vdnisite.com'; php artisan test`

Expected: PASS seluruh suite.

- [ ] **Step 3: Periksa kualitas diff**

Run: `git diff --check; git status --short`

Expected: tidak ada whitespace error dan tidak ada perubahan tidak disengaja.

