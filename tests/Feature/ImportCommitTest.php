<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\ParkingLocation;
use App\Models\RoadSegment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Imports\PermitImportCommitService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ImportCommitTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_commits_valid_and_needs_review_rows_without_touching_invalid_rows()
    {
        $admin = $this->admin();
        $this->seedRoadSegments(['Y1', 'D2']);

        $batch = $this->batch($admin, [
            'success_rows' => 1,
            'failed_rows' => 1,
            'review_rows' => 1,
            'total_rows' => 3,
        ]);

        $validRow = $this->row($batch, 5, ImportRow::STATUS_VALID, [
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

        $reviewRow = $this->row($batch, 6, ImportRow::STATUS_NEEDS_REVIEW, [
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

        $invalidRow = $this->row($batch, 7, ImportRow::STATUS_INVALID, [
            'nik' => '99887766',
            'employee_name' => 'INVALID ROW',
            'plate_number' => '',
        ], ['Plat motor wajib diisi']);

        $committedBatch = app(PermitImportCommitService::class)->commit($batch);

        $this->assertSame(ImportBatch::STATUS_COMMITTED, $committedBatch->status);

        $this->assertDatabaseHas('employees', ['nik' => '200115677', 'name' => 'FITRIAWATI']);
        $this->assertDatabaseHas('employees', ['nik' => '211129282', 'name' => 'HARLINA']);
        $this->assertDatabaseMissing('employees', ['nik' => '99887766']);

        $this->assertDatabaseHas('vehicles', ['plate_number' => 'DT 4423 CI']);
        $this->assertDatabaseHas('vehicles', ['plate_number' => 'DT 4714 BO']);
        $this->assertDatabaseMissing('vehicles', ['plate_number' => '']);

        $this->assertDatabaseHas('parking_locations', ['code' => 'GA-MES1-P01']);

        $activePermit = VehiclePermit::where('source_import_id', $batch->id)
            ->where('permit_color', 'biru')
            ->first();
        $this->assertNotNull($activePermit);
        $this->assertSame(VehiclePermit::STATUS_ACTIVE, $activePermit->status);
        $this->assertSame('import', $activePermit->source);
        $this->assertSame(2, $activePermit->permitRouteSegments()->count());
        $this->assertSame(
            [1, 2],
            array_map('intval', $activePermit->permitRouteSegments()->orderBy('sequence')->pluck('sequence')->all())
        );

        $reviewPermit = VehiclePermit::where('source_import_id', $batch->id)
            ->where('permit_color', 'kuning')
            ->first();
        $this->assertNotNull($reviewPermit);
        $this->assertSame(VehiclePermit::STATUS_NEEDS_REVIEW, $reviewPermit->status);
        $this->assertSame(0, $reviewPermit->permitRouteSegments()->count());

        $validRow->refresh();
        $reviewRow->refresh();
        $invalidRow->refresh();

        $this->assertSame(ImportRow::STATUS_COMMITTED, $validRow->status);
        $this->assertNotNull($validRow->created_employee_id);
        $this->assertNotNull($validRow->created_vehicle_id);
        $this->assertNotNull($validRow->created_permit_id);

        $this->assertSame(ImportRow::STATUS_COMMITTED, $reviewRow->status);
        $this->assertNotNull($reviewRow->created_employee_id);
        $this->assertNotNull($reviewRow->created_vehicle_id);
        $this->assertNotNull($reviewRow->created_permit_id);

        $this->assertSame(ImportRow::STATUS_INVALID, $invalidRow->status);
        $this->assertNull($invalidRow->created_employee_id);
        $this->assertNull($invalidRow->created_vehicle_id);
        $this->assertNull($invalidRow->created_permit_id);
    }

    /** @test */
    public function it_rejects_duplicate_permit_when_committing_an_existing_employee_vehicle_pair()
    {
        $admin = $this->admin();
        $batch = $this->batch($admin, [
            'success_rows' => 1,
            'total_rows' => 1,
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

        VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'merah',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'manual',
        ]);

        $row = $this->row($batch, 5, ImportRow::STATUS_VALID, [
            'nik' => '200115677',
            'employee_name' => 'FITRIAWATI',
            'department' => 'GENERAL AFFAIR',
            'section' => 'GA KANTOR',
            'position' => 'ADMIN',
            'division' => 'GENERAL AFFAIR',
            'contact_number' => '0812',
            'plate_number' => 'DT 4423 CI',
            'parking_location_code' => '',
            'route_raw' => '',
            'route_segment_codes' => [],
            'reason' => 'OFFICE',
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'notes' => '',
        ]);

        try {
            app(PermitImportCommitService::class)->commit($batch);
            $this->fail('Expected duplicate permit to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Izin kendaraan untuk NIK dan plat ini sudah terdaftar.', $exception->getMessage());
        }

        $this->assertSame(ImportBatch::STATUS_PREVIEWED, $batch->fresh()->status);
        $this->assertSame(1, VehiclePermit::count());

        $row->refresh();
        $this->assertSame(ImportRow::STATUS_VALID, $row->status);
    }

    /** @test */
    public function needs_review_rows_do_not_persist_partial_route_segments()
    {
        $admin = $this->admin();
        $this->seedRoadSegments(['Y1', 'D2']);

        $batch = $this->batch($admin, [
            'review_rows' => 1,
            'total_rows' => 1,
        ]);

        $this->row($batch, 5, ImportRow::STATUS_NEEDS_REVIEW, [
            'nik' => '200115677',
            'employee_name' => 'FITRIAWATI',
            'department' => 'GENERAL AFFAIR',
            'section' => 'GA KANTOR',
            'position' => 'ADMIN',
            'division' => 'GENERAL AFFAIR',
            'contact_number' => '0812',
            'plate_number' => 'DT 4423 CI',
            'parking_location_code' => '',
            'route_raw' => 'Y1 -> jalan tidak dikenal -> D2',
            'route_segment_codes' => ['Y1', 'D2'],
            'reason' => 'OFFICE',
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'notes' => '',
        ], [], ['Rute mengandung teks bebas yang perlu review']);

        app(PermitImportCommitService::class)->commit($batch);

        $permit = VehiclePermit::where('source_import_id', $batch->id)->first();

        $this->assertNotNull($permit);
        $this->assertSame(VehiclePermit::STATUS_NEEDS_REVIEW, $permit->status);
        $this->assertSame(0, $permit->permitRouteSegments()->count());
    }

    /** @test */
    public function it_downgrades_rows_with_unsafe_parking_code_without_creating_parking_location()
    {
        $admin = $this->admin();
        $batch = $this->batch($admin, [
            'success_rows' => 1,
            'total_rows' => 1,
        ]);

        $longParkingCode = str_repeat('PLTU-OF-P01 ', 7);

        $row = $this->row($batch, 5, ImportRow::STATUS_VALID, [
            'nik' => '200115677',
            'employee_name' => 'FITRIAWATI',
            'department' => 'GENERAL AFFAIR',
            'section' => 'GA KANTOR',
            'position' => 'ADMIN',
            'division' => 'GENERAL AFFAIR',
            'contact_number' => '0812',
            'plate_number' => 'DT 4423 CI',
            'parking_location_code' => $longParkingCode,
            'route_raw' => 'Y1',
            'route_segment_codes' => [],
            'reason' => 'OFFICE',
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'notes' => '',
        ]);

        app(PermitImportCommitService::class)->commit($batch);

        $permit = VehiclePermit::where('source_import_id', $batch->id)->first();

        $this->assertSame(0, ParkingLocation::count());
        $this->assertNotNull($permit);
        $this->assertNull($permit->parking_location_id);
        $this->assertSame(VehiclePermit::STATUS_NEEDS_REVIEW, $permit->status);

        $row->refresh();
        $this->assertSame(ImportRow::STATUS_COMMITTED, $row->status);
        $this->assertContains(
            'Lokasi parkir terlalu panjang untuk master data, perlu review manual.',
            $row->warnings
        );
    }

    /** @test */
    public function vehicle_identity_is_unique_per_employee_and_plate_number()
    {
        $employee = Employee::create([
            'nik' => '200115677',
            'name' => 'FITRIAWATI',
            'status' => 'active',
        ]);

        Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 4423 CI',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $this->expectException(QueryException::class);

        Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 4423 CI',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);
    }

    /** @test */
    public function it_rejects_commit_for_already_committed_or_not_ready_batches()
    {
        $admin = $this->admin();

        $committedBatch = $this->batch($admin, [
            'status' => ImportBatch::STATUS_COMMITTED,
        ]);

        try {
            app(PermitImportCommitService::class)->commit($committedBatch);
            $this->fail('Expected committed batch to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Batch sudah pernah dikomit.', $exception->getMessage());
        }

        $draftBatch = $this->batch($admin, [
            'status' => ImportBatch::STATUS_DRAFT,
        ]);

        try {
            app(PermitImportCommitService::class)->commit($draftBatch);
            $this->fail('Expected non-previewed batch to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Batch belum siap dikomit.', $exception->getMessage());
        }
    }

    /** @test */
    public function it_rejects_commit_when_batch_has_no_committable_rows()
    {
        $admin = $this->admin();
        $batch = $this->batch($admin, [
            'failed_rows' => 2,
            'total_rows' => 2,
        ]);

        $this->row($batch, 5, ImportRow::STATUS_INVALID, [
            'nik' => '99887766',
            'employee_name' => 'INVALID ROW 1',
            'plate_number' => '',
        ], ['Plat motor wajib diisi']);
        $this->row($batch, 6, ImportRow::STATUS_INVALID, [
            'nik' => '99887767',
            'employee_name' => 'INVALID ROW 2',
            'plate_number' => '',
        ], ['Plat motor wajib diisi']);

        try {
            app(PermitImportCommitService::class)->commit($batch);
            $this->fail('Expected invalid-only batch to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Batch tidak memiliki baris valid untuk dikomit.', $exception->getMessage());
        }

        $this->assertSame(ImportBatch::STATUS_PREVIEWED, $batch->fresh()->status);
        $this->assertSame(0, Employee::count());
        $this->assertSame(0, Vehicle::count());
        $this->assertSame(0, VehiclePermit::count());
    }

    /** @test */
    public function it_rejects_commit_when_committable_row_lacks_minimum_data()
    {
        $admin = $this->admin();
        $batch = $this->batch($admin, [
            'success_rows' => 1,
            'total_rows' => 1,
        ]);

        $row = $this->row($batch, 5, ImportRow::STATUS_VALID, [
            'nik' => '200115677',
            'employee_name' => 'FITRIAWATI',
            'department' => 'GENERAL AFFAIR',
            'section' => 'GA KANTOR',
            'position' => 'ADMIN',
            'division' => 'GENERAL AFFAIR',
            'contact_number' => '0812',
            'plate_number' => '',
            'parking_location_code' => '',
            'route_raw' => '',
            'route_segment_codes' => [],
            'reason' => 'OFFICE',
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'notes' => '',
        ]);

        try {
            app(PermitImportCommitService::class)->commit($batch);
            $this->fail('Expected malformed committable row to block commit.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Batch tidak memiliki baris valid untuk dikomit.', $exception->getMessage());
        }

        $row->refresh();
        $this->assertSame(ImportRow::STATUS_VALID, $row->status);
        $this->assertSame(ImportBatch::STATUS_PREVIEWED, $batch->fresh()->status);
        $this->assertSame(0, Employee::count());
        $this->assertSame(0, Vehicle::count());
        $this->assertSame(0, VehiclePermit::count());
    }

    /** @test */
    public function commit_route_is_explicitly_limited_to_admin_hr_and_super_admin_override()
    {
        $this->assertSame([User::ROLE_ADMIN_HR], User::rolesForRoute('imports.commit'));

        $admin = $this->admin();
        $security = User::factory()->create([
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->assertTrue($admin->canAccessRoute('imports.commit'));
        $this->assertFalse($security->canAccessRoute('imports.commit'));
        $this->assertTrue($superAdmin->canAccessRoute('imports.commit'));
    }

    /** @test */
    public function admin_can_commit_batch_via_http_but_security_cannot()
    {
        $admin = $this->admin();
        $security = User::factory()->create([
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->seedRoadSegments(['Y1']);

        $batch = $this->batch($admin, [
            'success_rows' => 1,
            'total_rows' => 1,
        ]);

        $this->row($batch, 5, ImportRow::STATUS_VALID, [
            'nik' => '200115677',
            'employee_name' => 'FITRIAWATI',
            'department' => 'GENERAL AFFAIR',
            'section' => 'GA KANTOR',
            'position' => 'ADMIN',
            'division' => 'GENERAL AFFAIR',
            'contact_number' => '0812',
            'plate_number' => 'DT 4423 CI',
            'parking_location_code' => '',
            'route_raw' => 'Y1',
            'route_segment_codes' => ['Y1'],
            'reason' => 'OFFICE',
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'notes' => '',
        ]);

        $this->actingAs($security)
            ->post(route('imports.commit', $batch))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('imports.commit', $batch))
            ->assertRedirect(route('imports.show', $batch))
            ->assertSessionHas('status', 'Batch import berhasil dikomit.');

        $this->assertSame(ImportBatch::STATUS_COMMITTED, $batch->fresh()->status);
    }

    /** @test */
    public function admin_http_commit_rejects_batch_without_committable_rows()
    {
        $admin = $this->admin();
        $batch = $this->batch($admin, [
            'failed_rows' => 1,
            'total_rows' => 1,
        ]);

        $this->row($batch, 5, ImportRow::STATUS_INVALID, [
            'nik' => '99887766',
            'employee_name' => 'INVALID ROW',
            'plate_number' => '',
        ], ['Plat motor wajib diisi']);

        $this->actingAs($admin)
            ->post(route('imports.commit', $batch))
            ->assertRedirect(route('imports.show', $batch))
            ->assertSessionHas('status', 'Batch tidak memiliki baris valid untuk dikomit.');

        $this->assertSame(ImportBatch::STATUS_PREVIEWED, $batch->fresh()->status);
        $this->assertSame(0, VehiclePermit::count());
    }

    /** @test */
    public function preview_shows_commit_button_only_for_previewed_batches_with_valid_or_review_rows()
    {
        $admin = $this->admin();

        $readyBatch = $this->batch($admin, [
            'success_rows' => 1,
            'total_rows' => 1,
        ]);
        $emptyBatch = $this->batch($admin, [
            'filename' => 'empty.xlsx',
            'total_rows' => 0,
        ]);
        $invalidOnlyBatch = $this->batch($admin, [
            'filename' => 'invalid-only.xlsx',
            'failed_rows' => 2,
            'total_rows' => 2,
        ]);
        $committedBatch = $this->batch($admin, [
            'filename' => 'committed.xlsx',
            'success_rows' => 1,
            'total_rows' => 1,
            'status' => ImportBatch::STATUS_COMMITTED,
        ]);

        $this->actingAs($admin)
            ->get(route('imports.show', $readyBatch))
            ->assertOk()
            ->assertSee('Commit Data Aman')
            ->assertSee(route('imports.commit', $readyBatch), false);

        $this->actingAs($admin)
            ->get(route('imports.show', $emptyBatch))
            ->assertOk()
            ->assertDontSee('Commit Data Aman');

        $this->actingAs($admin)
            ->get(route('imports.show', $invalidOnlyBatch))
            ->assertOk()
            ->assertDontSee('Commit Data Aman');

        $this->actingAs($admin)
            ->get(route('imports.show', $committedBatch))
            ->assertOk()
            ->assertDontSee('Commit Data Aman');
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function batch(User $admin, array $overrides = []): ImportBatch
    {
        return ImportBatch::create(array_merge([
            'filename' => 'sample.xlsx',
            'uploaded_by' => $admin->id,
            'total_rows' => 1,
            'success_rows' => 0,
            'failed_rows' => 0,
            'review_rows' => 0,
            'status' => ImportBatch::STATUS_PREVIEWED,
        ], $overrides));
    }

    private function row(
        ImportBatch $batch,
        int $rowNumber,
        string $status,
        array $normalized,
        array $errors = [],
        array $warnings = []
    ): ImportRow {
        return ImportRow::create([
            'import_batch_id' => $batch->id,
            'row_number' => $rowNumber,
            'status' => $status,
            'raw_data' => $normalized,
            'normalized_data' => $normalized,
            'errors' => $errors,
            'warnings' => $warnings,
        ]);
    }

    private function seedRoadSegments(array $codes): void
    {
        foreach ($codes as $code) {
            RoadSegment::create([
                'code' => $code,
                'name' => 'Jalan ' . $code,
                'status' => 'active',
            ]);
        }
    }
}
