<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\RoadSegment;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SirikaModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function employee_vehicle_permit_and_route_segment_relationships_work()
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

        $roadSegment = RoadSegment::create([
            'code' => 'Y1',
            'name' => 'Jalan Yingbin Y1',
            'start_location' => 'Pos Gerbang Timur',
            'end_location' => 'Pos Gerbang Barat 1',
            'status' => 'active',
        ]);

        $permit->routeSegments()->attach($roadSegment->id, ['sequence' => 1]);

        $this->assertSame('DT 4423 CI', $employee->vehicles()->first()->plate_number);
        $this->assertSame('FITRIAWATI', $vehicle->employee->name);
        $this->assertSame('GA-MES1-P01', $permit->parkingLocation->code);
        $this->assertSame('Y1', $permit->routeSegments()->first()->code);
    }
}
