<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
}
