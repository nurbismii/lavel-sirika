<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\RoadSegment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitRouteMapHttpTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_hr_can_open_permit_route_map_preview_from_permit_list()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $permit = $this->permitWithRoute();

        $response = $this->actingAs($admin)->get(route('permits.index'));

        $response->assertOk();
        $response->assertSee('Rute');
        $response->assertSee('Lihat Rute');
        $response->assertSee(route('permits.route-map.show', $permit), false);
    }

    /** @test */
    public function admin_hr_can_open_preview_page_and_only_sees_route_map_fields()
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN_HR);
        $permit = $this->permitWithRoute();

        $this->actingAs($admin)
            ->get(route('permits.route-map.show', $permit))
            ->assertOk()
            ->assertSee('Peta Rute Izin')
            ->assertSee('ROUTE USER')
            ->assertSee('DT 5001 RM')
            ->assertSee('P1')
            ->assertSee('Y1 -&gt; D2', false)
            ->assertSee('Segmen belum dikurasi: D2')
            ->assertDontSee('EMP-ROUTE-001')
            ->assertDontSee('manual');
    }

    /** @test */
    public function security_cannot_open_admin_permit_route_map_preview()
    {
        $security = $this->userWithRole(User::ROLE_SECURITY);
        $permit = $this->permitWithRoute();

        $this->actingAs($security)
            ->get(route('permits.route-map.show', $permit))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function permitWithRoute(): VehiclePermit
    {
        $employee = Employee::create([
            'nik' => 'EMP-ROUTE-001',
            'name' => 'ROUTE USER',
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 5001 RM',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $parking = ParkingLocation::create([
            'code' => 'P1',
            'name' => 'Parkir 1',
            'status' => 'active',
        ]);

        $permit = VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parking->id,
            'permit_color' => 'biru',
            'approval_status' => 'approved',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'manual',
            'route_raw' => 'Y1 - D2',
        ]);

        $complete = RoadSegment::create([
            'code' => 'Y1',
            'name' => 'Y1',
            'start_location' => 'Start Y1',
            'end_location' => 'End Y1',
            'status' => 'active',
            'polyline_json' => [
                'version' => 1,
                'map_key' => 'vdni-road-map-v1',
                'status' => 'complete',
                'points' => [
                    ['x' => 10, 'y' => 20],
                    ['x' => 30, 'y' => 40],
                ],
                'updated_by' => null,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);

        $missing = RoadSegment::create([
            'code' => 'D2',
            'name' => 'D2',
            'start_location' => 'Start D2',
            'end_location' => 'End D2',
            'status' => 'active',
            'polyline_json' => null,
        ]);

        $permit->routeSegments()->attach($complete->id, ['sequence' => 1]);
        $permit->routeSegments()->attach($missing->id, ['sequence' => 2]);

        return $permit;
    }
}
