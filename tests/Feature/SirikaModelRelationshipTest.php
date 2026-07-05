<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ImportBatch;
use App\Models\ParkingLocation;
use App\Models\RoadSegment;
use App\Models\ScanLog;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SirikaModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function employee_vehicle_vehicle_permit_and_token_relationships_work()
    {
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

        $parkingLocation = ParkingLocation::create([
            'code' => 'GA-MES1-P01',
            'name' => 'GA MES 1 P01',
            'status' => 'active',
        ]);

        $permit = VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parkingLocation->id,
            'permit_color' => 'BIRU',
            'approval_status' => 'approved',
            'status' => 'draft',
            'source' => 'manual',
            'route_raw' => 'Y1->D2->Z1->D3',
        ]);

        $token = $permit->tokens()->create([
            'token_hash' => 'token-hash-001',
            'status' => 'active',
        ]);

        $this->assertSame(1, $employee->vehicles()->count());
        $this->assertSame('DT 4423 CI', $employee->vehicles()->first()->plate_number);
        $this->assertSame(1, $employee->permits()->count());
        $this->assertTrue($employee->permits->first()->is($permit));
        $this->assertTrue($vehicle->employee->is($employee));
        $this->assertSame(1, $vehicle->permits()->count());
        $this->assertTrue($vehicle->permits->first()->is($permit));
        $this->assertTrue($permit->employee->is($employee));
        $this->assertTrue($permit->vehicle->is($vehicle));
        $this->assertSame('GA-MES1-P01', $permit->parkingLocation->code);
        $this->assertSame(1, $permit->tokens()->count());
        $this->assertTrue($permit->tokens->first()->is($token));
        $this->assertTrue($token->permit->is($permit));
    }

    /** @test */
    public function road_segment_import_batch_and_scan_log_relationships_work()
    {
        $uploader = User::factory()->create();
        $scanner = User::factory()->create();

        $employee = Employee::create([
            'nik' => '200115678',
            'name' => 'NURHIDAYAT',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 7788 ZZ',
            'vehicle_type' => 'car',
            'status' => 'active',
        ]);

        $permit = VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'HITAM',
            'approval_status' => 'approved',
            'status' => 'active',
            'source' => 'import',
        ]);

        $roadSegment = RoadSegment::create([
            'code' => 'Y1',
            'name' => 'Jalan Yingbin Y1',
            'start_location' => 'Pos Gerbang Timur',
            'end_location' => 'Pos Gerbang Barat 1',
            'status' => 'active',
        ]);

        $permit->routeSegments()->attach($roadSegment->id, ['sequence' => 1]);

        $batch = ImportBatch::create([
            'filename' => 'permit-import.csv',
            'uploaded_by' => $uploader->id,
            'status' => 'processed',
        ]);

        $scanLog = ScanLog::create([
            'permit_id' => $permit->id,
            'scanned_by' => $scanner->id,
            'scanned_at' => now(),
            'result' => 'valid',
        ]);

        $this->assertSame($uploader->id, $batch->uploader->id);
        $this->assertSame(1, $roadSegment->permitRoutes()->count());
        $this->assertTrue($roadSegment->permitRoutes->first()->permit->is($permit));
        $this->assertSame('Y1', $permit->routeSegments->first()->code);
        $this->assertSame($permit->id, $scanLog->permit->id);
        $this->assertSame($scanner->id, $scanLog->scanner->id);
    }
}
