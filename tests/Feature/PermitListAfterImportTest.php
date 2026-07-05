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
            'route_raw' => 'Y1-D2',
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
