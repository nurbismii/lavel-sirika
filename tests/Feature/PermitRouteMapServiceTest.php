<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\RoadSegment;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Routes\PermitRouteMapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitRouteMapServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sirika.route_map' => [
                'key' => 'vdni-road-map-v1',
                'image_url' => '/images/maps/vdni-road-map-v1.png',
                'width' => 1600,
                'height' => 1000,
            ],
        ]);
    }

    /** @test */
    public function it_builds_route_map_data_in_permit_route_sequence()
    {
        $permit = $this->permit();
        $first = $this->segment('Y1', [
            'status' => 'complete',
            'points' => [
                ['x' => 10, 'y' => 20],
                ['x' => 30, 'y' => 40],
            ],
        ]);
        $second = $this->segment('D2', [
            'status' => 'complete',
            'points' => [
                ['x' => 50, 'y' => 60],
                ['x' => 70, 'y' => 80],
            ],
        ]);

        $permit->routeSegments()->attach($second->id, ['sequence' => 2]);
        $permit->routeSegments()->attach($first->id, ['sequence' => 1]);

        $dto = app(PermitRouteMapService::class)->forPermit($permit->fresh());

        $this->assertSame('Y1 -> D2', $dto['route_label']);
        $this->assertSame(['Y1', 'D2'], collect($dto['segments'])->pluck('code')->all());
        $this->assertSame([], $dto['missing_segments']);
        $this->assertSame([[20.0, 10.0], [40.0, 30.0]], $dto['segments'][0]['lat_lngs']);
    }

    /** @test */
    public function it_marks_segments_without_complete_coordinates_as_missing()
    {
        $permit = $this->permit();
        $complete = $this->segment('Y1', [
            'status' => 'complete',
            'points' => [
                ['x' => 10, 'y' => 20],
                ['x' => 30, 'y' => 40],
            ],
        ]);
        $missing = $this->segment('H2', null);

        $permit->routeSegments()->attach($complete->id, ['sequence' => 1]);
        $permit->routeSegments()->attach($missing->id, ['sequence' => 2]);

        $dto = app(PermitRouteMapService::class)->forPermit($permit->fresh());

        $this->assertSame(['H2'], $dto['missing_segments']);
        $this->assertCount(1, $dto['segments']);
        $this->assertFalse($dto['is_complete']);
    }

    private function permit(): VehiclePermit
    {
        $employee = Employee::create([
            'nik' => 'EMP-' . uniqid(),
            'name' => 'Test User',
            'status' => 'active',
        ]);
        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT ' . random_int(1000, 9999) . ' AA',
            'vehicle_type' => 'truck',
            'status' => 'active',
        ]);
        $parking = ParkingLocation::create([
            'code' => 'P' . random_int(1, 99),
            'name' => 'Parkir 1',
            'status' => 'active',
        ]);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parking->id,
            'permit_color' => 'Blue',
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'test',
            'route_raw' => 'Y1-D2',
        ]);
    }

    private function segment(string $code, ?array $polyline): RoadSegment
    {
        return RoadSegment::create([
            'code' => $code,
            'name' => $code,
            'start_location' => 'Start ' . $code,
            'end_location' => 'End ' . $code,
            'status' => 'active',
            'polyline_json' => $polyline ? array_merge([
                'version' => 1,
                'map_key' => 'vdni-road-map-v1',
                'updated_by' => null,
                'updated_at' => now()->toIso8601String(),
            ], $polyline) : null,
        ]);
    }
}
