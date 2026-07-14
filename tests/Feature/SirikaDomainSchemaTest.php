<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class SirikaDomainSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function sirika_core_tables_exist_with_required_columns()
    {
        $this->assertTrue(Schema::hasColumns('employees', [
            'id', 'nik', 'name', 'department', 'section', 'position', 'division', 'contact_number', 'status',
        ]));

        $this->assertTrue(Schema::hasColumns('vehicles', [
            'id', 'employee_id', 'plate_number', 'vehicle_type', 'status',
        ]));

        $this->assertTrue(Schema::hasColumns('parking_locations', [
            'id', 'code', 'name', 'status',
        ]));

        $this->assertTrue(Schema::hasColumns('road_segments', [
            'id', 'code', 'name', 'start_location', 'end_location', 'polyline_json', 'status',
        ]));

        $this->assertTrue(Schema::hasColumns('import_batches', [
            'id', 'filename', 'uploaded_by', 'total_rows', 'success_rows', 'failed_rows', 'review_rows', 'status', 'error_summary',
        ]));

        $this->assertTrue(Schema::hasColumns('vehicle_permits', [
            'id', 'employee_id', 'vehicle_id', 'parking_location_id', 'permit_color', 'reason', 'approval_status', 'valid_from', 'valid_until', 'status', 'source', 'source_import_id', 'route_raw',
        ]));

        $this->assertTrue(Schema::hasColumns('permit_route_segments', [
            'id', 'vehicle_permit_id', 'road_segment_id', 'sequence',
        ]));

        $this->assertTrue(Schema::hasColumns('permit_tokens', [
            'id', 'vehicle_permit_id', 'token_hash', 'status', 'expires_at', 'revoked_at',
        ]));

        $this->assertTrue(Schema::hasColumns('scan_logs', [
            'id', 'permit_id', 'scanned_by', 'scanned_at', 'result', 'device_info', 'ip_address', 'notes',
        ]));
    }

    /** @test */
    public function permit_route_segment_sequence_must_be_unique_per_permit_even_for_different_road_segments()
    {
        $employeeId = DB::table('employees')->insertGetId([
            'nik' => 'EMP-001',
            'name' => 'Test Employee',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $vehicleId = DB::table('vehicles')->insertGetId([
            'employee_id' => $employeeId,
            'plate_number' => 'DD 1234 XX',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permitId = DB::table('vehicle_permits')->insertGetId([
            'employee_id' => $employeeId,
            'vehicle_id' => $vehicleId,
            'approval_status' => 'approved',
            'status' => 'draft',
            'source' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $firstRoadSegmentId = DB::table('road_segments')->insertGetId([
            'code' => 'RS-001',
            'name' => 'Main Gate',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondRoadSegmentId = DB::table('road_segments')->insertGetId([
            'code' => 'RS-002',
            'name' => 'West Gate',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('permit_route_segments')->insert([
            'vehicle_permit_id' => $permitId,
            'road_segment_id' => $firstRoadSegmentId,
            'sequence' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('permit_route_segments')->insert([
            'vehicle_permit_id' => $permitId,
            'road_segment_id' => $secondRoadSegmentId,
            'sequence' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function vehicle_permit_unique_migration_reports_duplicate_existing_permit_rows()
    {
        $migration = new \AddUniqueEmployeeVehicleToVehiclePermitsTable();
        $migration->down();

        $employeeId = DB::table('employees')->insertGetId([
            'nik' => 'EMP-UNIQUE-001',
            'name' => 'Test Employee',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $vehicleId = DB::table('vehicles')->insertGetId([
            'employee_id' => $employeeId,
            'plate_number' => 'DD 9999 XX',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['draft', 'needs_review'] as $status) {
            DB::table('vehicle_permits')->insert([
                'employee_id' => $employeeId,
                'vehicle_id' => $vehicleId,
                'approval_status' => 'approved',
                'status' => $status,
                'source' => 'manual',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot add vehicle_permits_employee_vehicle_unique because duplicate permit rows exist');

        $migration->up();
    }
}
